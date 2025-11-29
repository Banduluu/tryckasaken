<?php
session_start();
require_once '../../config/Database.php';

if (!isset($_GET['id'])) {
    header('Location: bookings-list.php?error=missing_booking_id');
    exit;
}

$bookingId = intval($_GET['id']);

$db = new Database();
$conn = $db->getConnection();

// Get booking info
$stmt = $conn->prepare("SELECT id, status, location, destination FROM tricycle_bookings WHERE id = ?");
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    header('Location: bookings-list.php?error=booking_not_found');
    exit;
}

// Check if already cancelled or completed
if ($booking['status'] === 'cancelled') {
    header("Location: booking-details.php?id=$bookingId&error=Booking is already cancelled");
    exit;
}

if ($booking['status'] === 'completed') {
    header("Location: booking-details.php?id=$bookingId&error=Cannot cancel a completed booking");
    exit;
}

// Update booking status to cancelled
$stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'cancelled' WHERE id = ?");
$stmt->bind_param("i", $bookingId);

if ($stmt->execute()) {
    $stmt->close();
    
    // Log to admin_action_logs
    $adminId = $_SESSION['user_id'];
    $actionType = 'booking_cancel';
    $actionDetails = "Cancelled booking #{$bookingId}. Route: {$booking['location']} → {$booking['destination']}. Previous status: {$booking['status']}";
    
    $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, NULL, ?)");
    $logStmt->bind_param("iss", $adminId, $actionType, $actionDetails);
    $logStmt->execute();
    $logStmt->close();
    
    $db->closeConnection();
    
    header("Location: bookings-list.php?success=Booking #{$bookingId} has been cancelled successfully");
    exit;
} else {
    $error = $conn->error;
    $stmt->close();
    $db->closeConnection();
    
    header("Location: booking-details.php?id=$bookingId&error=Failed to cancel booking: " . urlencode($error));
    exit;
}
?>
