<?php
session_start();
require_once '../../config/Database.php';

header('Content-Type: application/json');

// Check if user is logged in as passenger
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'passenger') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get action type
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_booking_status':
        // Get current active booking with driver info (include completed for real-time update)
        $booking_query = "SELECT b.*, 
                          u.name as driver_name, 
                          u.phone as driver_phone,
                          d.tricycle_info as vehicle_info
                          FROM tricycle_bookings b
                          LEFT JOIN rfid_drivers d ON b.driver_id = d.driver_id
                          LEFT JOIN users u ON d.user_id = u.user_id
                          WHERE b.user_id = ? 
                          AND LOWER(b.status) NOT IN ('cancelled', 'declined')
                          ORDER BY b.booking_time DESC 
                          LIMIT 1";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'has_booking' => $booking ? true : false,
            'booking' => $booking
        ]);
        break;

    case 'cancel_booking':
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit();
        }

        // Check if booking is cancellable
        $check_stmt = $conn->prepare("SELECT status, driver_id FROM tricycle_bookings WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $booking_id, $user_id);
        $check_stmt->execute();
        $booking = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit();
        }

        $status = strtolower($booking['status']);
        if ($status === 'pending' || ($status === 'accepted' && !$booking['driver_id'])) {
            $cancel_stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'cancelled' WHERE id = ?");
            $cancel_stmt->bind_param("i", $booking_id);
            
            if ($cancel_stmt->execute()) {
                $cancel_stmt->close();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Booking cancelled successfully!'
                ]);
            } else {
                $cancel_stmt->close();
                echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking cannot be cancelled at this stage']);
        }
        break;

    case 'create_booking':
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $destination = trim($_POST['destination'] ?? '');

        if (empty($name) || empty($location) || empty($destination)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit();
        }

        // Check if user already has an active booking
        $check_query = "SELECT COUNT(*) as count FROM tricycle_bookings 
                       WHERE user_id = ? 
                       AND LOWER(status) NOT IN ('completed', 'cancelled', 'declined')";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have an active booking']);
            exit();
        }

        // Insert new booking
        $stmt = $conn->prepare("INSERT INTO tricycle_bookings (user_id, name, location, destination, status) 
                               VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("isss", $user_id, $name, $location, $destination);

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true, 
                'message' => 'Booking created successfully!'
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
