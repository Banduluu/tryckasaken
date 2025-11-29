<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit();
}

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'PHP Error: ' . $errstr,
        'debug' => [
            'file' => $errfile,
            'line' => $errline
        ]
    ]);
    exit();
});

require_once '../../config/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Connection Error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['uid']) || !isset($data['action'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing fields']);
        $conn->close();
        exit();
    }
    
    $uid = $conn->real_escape_string($data['uid']);
    $action = $conn->real_escape_string($data['action']);
    $timestamp = date('Y-m-d H:i:s');
    
    if (!in_array($action, ['online', 'offline'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        $conn->close();
        exit();
    }
    
    $query = "SELECT u.user_id, u.name, u.phone, d.driver_id, d.verification_status, d.picture_path, d.tricycle_info, d.is_online
              FROM users u 
              JOIN rfid_drivers d ON u.user_id = d.user_id
              WHERE d.rfid_uid = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare Error: ' . $conn->error]);
        $conn->close();
        exit();
    }
    
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Check if learning mode is active
        $learningStatusFile = __DIR__ . '/../../temp/learning_mode_status.json';
        $learningEnabled = false;
        
        if (file_exists($learningStatusFile)) {
            $statusData = json_decode(file_get_contents($learningStatusFile), true);
            if ($statusData && isset($statusData['enabled']) && $statusData['enabled'] === true) {
                $learningEnabled = true;
            }
        }
        
        // Only log unknown card if learning mode is active
        if ($learningEnabled) {
            $learningFile = __DIR__ . '/../../temp/rfid_learning_latest.json';
            $learningDir = dirname($learningFile);
            
            if (!file_exists($learningDir)) {
                mkdir($learningDir, 0777, true);
            }
            
            $learningData = [
                'uid' => $uid,
                'timestamp' => date('Y-m-d H:i:s'),
                'detected' => true
            ];
            
            file_put_contents($learningFile, json_encode($learningData));
        }
        
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unknown card']);
        $stmt->close();
        $conn->close();
        exit();
    }
    
    $driver = $result->fetch_assoc();
    $stmt->close();
    
    // Check if card_status column exists and check if card is blocked
    $checkCardStatusQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rfid_drivers' AND COLUMN_NAME='card_status'";
    $cardStatusExists = $conn->query($checkCardStatusQuery)->num_rows > 0;
    
    if ($cardStatusExists) {
        $cardCheckQuery = "SELECT card_status FROM rfid_drivers WHERE driver_id = ?";
        $cardStmt = $conn->prepare($cardCheckQuery);
        $cardStmt->bind_param("i", $driver['driver_id']);
        $cardStmt->execute();
        $cardResult = $cardStmt->get_result();
        $cardData = $cardResult->fetch_assoc();
        $cardStmt->close();
        
        if ($cardData && isset($cardData['card_status']) && $cardData['card_status'] !== 'active') {
            ob_end_clean();
            $statusMessage = $cardData['card_status'] === 'stolen' ? 'Card reported stolen' : 
                            ($cardData['card_status'] === 'lost' ? 'Card reported lost' : 'Card blocked');
            echo json_encode(['success' => false, 'message' => $statusMessage]);
            $conn->close();
            exit();
        }
    }
    
    if ($driver['verification_status'] !== 'verified') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Driver not verified']);
        $conn->close();
        exit();
    }
    
    // Auto-determine action based on current status
    // If currently offline (0), make them online
    // If currently online (1), make them offline
    $actualAction = ($driver['is_online'] == 0) ? 'online' : 'offline';
    
    // If driver is coming online, try to assign them to the first pending booking
    $bookingAssigned = false;
    $assignedBooking = null;
    
    if ($actualAction === 'online') {
        // Get the first pending booking
        $bookingQuery = "SELECT b.id, b.user_id, b.location as pickup_location, b.destination as dropoff_location, b.booking_time 
                         FROM tricycle_bookings b
                         WHERE b.status = 'pending' 
                         AND b.driver_id IS NULL
                         ORDER BY b.booking_time ASC 
                         LIMIT 1";
        $bookingStmt = $conn->prepare($bookingQuery);
        $bookingStmt->execute();
        $bookingResult = $bookingStmt->get_result();
        
        if ($bookingResult->num_rows > 0) {
            $booking = $bookingResult->fetch_assoc();
            $bookingStmt->close();
            
            // Assign driver to this booking
            $assignQuery = "UPDATE tricycle_bookings 
                            SET driver_id = ?, 
                                status = 'accepted'
                            WHERE id = ?";
            
            $assignStmt = $conn->prepare($assignQuery);
            $assignStmt->bind_param("ii", $driver['driver_id'], $booking['id']);
            
            if ($assignStmt->execute() && $assignStmt->affected_rows > 0) {
                $bookingAssigned = true;
                $assignedBooking = $booking;
            }
            $assignStmt->close();
        } else {
            $bookingStmt->close();
        }
    }
    
    $insert_query = "INSERT INTO driver_attendance (driver_id, user_id, action, timestamp, rfid_uid) 
                     VALUES (?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iisss", 
        $driver['driver_id'],
        $driver['user_id'], 
        $actualAction, 
        $timestamp, 
        $uid
    );
    
    if ($insert_stmt->execute()) {
        $is_online = ($actualAction === 'online') ? 1 : 0;
        
        // Update rfid_drivers table
        $update_query = "UPDATE rfid_drivers SET is_online = ?, last_attendance = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("isi", $is_online, $timestamp, $driver['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Prepare response with booking information if assigned
        $response = [
            'success' => true, 
            'message' => 'Success',
            'action' => $actualAction,
            'driver' => [
                'name' => $driver['name'],
                'phone' => $driver['phone'],
                'tricycle_info' => $driver['tricycle_info'],
                'action' => $actualAction
            ]
        ];
        
        if ($bookingAssigned && $assignedBooking) {
            $response['booking_assigned'] = true;
            $response['booking'] = [
                'id' => $assignedBooking['id'],
                'user_id' => $assignedBooking['user_id'],
                'pickup_location' => $assignedBooking['pickup_location'],
                'dropoff_location' => $assignedBooking['dropoff_location'],
                'booking_time' => $assignedBooking['booking_time']
            ];
            $response['message'] = 'You have been assigned to a booking!';
        } else {
            $response['booking_assigned'] = false;
        }
        
        ob_end_clean();
        echo json_encode($response);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error']);
    }
    
    $insert_stmt->close();
    $conn->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : null;
    
    if ($driver_id) {
        $query = "SELECT da.*, u.name as driver_name 
                  FROM driver_attendance da
                  JOIN users u ON da.user_id = u.user_id
                  WHERE da.user_id = ?
                  ORDER BY da.timestamp DESC LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $driver_id, $limit);
    } else {
        $query = "SELECT da.*, u.name as driver_name 
                  FROM driver_attendance da
                  JOIN users u ON da.user_id = u.user_id
                  ORDER BY da.timestamp DESC LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    ob_end_clean();
    echo json_encode(['success' => true, 'records' => $records, 'count' => count($records)]);
    
    $stmt->close();
    $conn->close();
    exit();
}
?>