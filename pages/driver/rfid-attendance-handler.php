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

require_once '../../config/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error']);
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
    
    $query = "SELECT u.user_id, u.name, u.phone, d.driver_id, d.verification_status, d.picture_path, d.tricycle_info
              FROM users u 
              JOIN rfid_drivers d ON u.user_id = d.user_id
              WHERE d.rfid_uid = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unknown card']);
        $stmt->close();
        $conn->close();
        exit();
    }
    
    $driver = $result->fetch_assoc();
    $stmt->close();
    
    if ($driver['verification_status'] !== 'verified') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Driver not verified']);
        $conn->close();
        exit();
    }
    
    $insert_query = "INSERT INTO driver_attendance (driver_id, user_id, action, timestamp, rfid_uid) 
                     VALUES (?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iisss", 
        $driver['driver_id'],
        $driver['user_id'], 
        $action, 
        $timestamp, 
        $uid
    );
    
    if ($insert_stmt->execute()) {
        $is_online = ($action === 'online') ? 1 : 0;
        
        // Update rfid_drivers table
        $update_query = "UPDATE rfid_drivers SET is_online = ?, last_attendance = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("isi", $is_online, $timestamp, $driver['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        ob_end_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Success',
            'driver' => [
                'name' => $driver['name'],
                'phone' => $driver['phone'],
                'tricycle_info' => $driver['tricycle_info'],
                'action' => $action
            ]
        ]);
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