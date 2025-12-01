<?php
session_start();
require_once '../../config/Database.php';

// Check if driver ID and action are provided
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: drivers-verification.php?error=missing_params');
    exit;
}

$driverId = intval($_GET['id']);
$action = $_GET['action'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    header('Location: drivers-verification.php?error=invalid_action');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get driver info
$stmt = $conn->prepare("SELECT d.*, u.name, u.email FROM drivers d INNER JOIN users u ON d.user_id = u.user_id WHERE d.driver_id = ?");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();
$stmt->close();

if (!$driver) {
    header('Location: drivers-verification.php?error=driver_not_found');
    exit;
}

// Update verification status
if ($action === 'approve') {
    $newStatus = 'verified';
    $message = 'Driver has been successfully verified and approved!';
    $messageType = 'success';
} else {
    $newStatus = 'rejected';
    $message = 'Driver application has been rejected.';
    $messageType = 'warning';
}

$updateStmt = $conn->prepare("UPDATE drivers SET verification_status = ? WHERE driver_id = ?");
$updateStmt->bind_param("si", $newStatus, $driverId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    
    // Log to admin_action_logs
    $adminId = $_SESSION['user_id'];
    $actionType = $action === 'approve' ? 'driver_verify' : 'driver_reject';
    $actionDetails = "Driver verification {$newStatus}: {$driver['name']} (ID: {$driverId}, Email: {$driver['email']})";
    
    $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, ?, ?)");
    $logStmt->bind_param("isis", $adminId, $actionType, $driver['user_id'], $actionDetails);
    $logStmt->execute();
    $logStmt->close();
    
    header("Location: drivers-verification.php?success=" . urlencode($message) . "&type=" . $messageType);
} else {
    header('Location: drivers-verification.php?error=update_failed');
}

$updateStmt->close();
$db->closeConnection();
exit;
?>
