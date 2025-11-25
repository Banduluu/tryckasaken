<?php
session_start();
require_once '../../config/Database.php';

header('Content-Type: application/json');

// Check if user is logged in as driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Check driver verification status
$verification_query = "SELECT verification_status FROM rfid_drivers WHERE user_id = ?";
$stmt = $conn->prepare($verification_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$verification_result = $stmt->get_result()->fetch_assoc();
$verification_status = $verification_result ? $verification_result['verification_status'] : 'pending';
$stmt->close();

// Prevent status change for non-verified drivers
if ($verification_status !== 'verified') {
    $message = $verification_status === 'rejected' 
        ? 'Cannot change status - Your account verification was rejected' 
        : 'Cannot change status - Please wait for account verification';
    echo json_encode([
        'success' => false, 
        'message' => $message
    ]);
    exit();
}

// Check if driver has active trip
$active_trip_query = "SELECT COUNT(*) as has_active FROM tricycle_bookings WHERE driver_id = ? AND LOWER(status) = 'accepted'";
$stmt = $conn->prepare($active_trip_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_result = $stmt->get_result()->fetch_assoc();
$has_active_trip = $active_result['has_active'] > 0;
$stmt->close();

// Prevent going offline if driver has active trip
if ($has_active_trip) {
    echo json_encode([
        'success' => false, 
        'message' => 'Cannot change status while on an active trip'
    ]);
    exit();
}

// Get current status
$status_query = "SELECT is_online FROM rfid_drivers WHERE user_id = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$current_status = $result['is_online'];
$stmt->close();

// Toggle status
$new_status = $current_status ? 0 : 1;

// Update status
$update_query = "UPDATE rfid_drivers SET is_online = ? WHERE user_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("ii", $new_status, $user_id);

if ($stmt->execute()) {
    $status_text = $new_status ? 'online' : 'offline';
    echo json_encode([
        'success' => true, 
        'status' => $status_text,
        'is_online' => $new_status,
        'message' => 'You are now ' . $status_text
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update status'
    ]);
}

$stmt->close();
$conn->close();
?>
