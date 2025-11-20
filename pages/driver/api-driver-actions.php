<?php
session_start();
require_once '../../config/Database.php';
require_once '../../services/RequestService.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize database and service
$database = new Database();
$conn = $database->getConnection();
$service = new RequestService($conn, $_SESSION['user_id']);

// Get action type
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle actions using service layer
switch ($action) {
    case 'get_requests':
        echo json_encode($service->getRequests());
        break;

    case 'accept_ride':
        $booking_id = intval($_POST['booking_id'] ?? 0);
        echo json_encode($service->acceptRide($booking_id));
        break;

    case 'complete_ride':
        $booking_id = intval($_POST['booking_id'] ?? 0);
        echo json_encode($service->completeRide($booking_id));
        break;

    case 'check_status':
        echo json_encode($service->checkStatus());
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
