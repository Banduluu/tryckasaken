<?php
session_start();
require_once '../../config/Database.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php?error=missing_user_id');
    exit;
}

$userId = intval($_GET['id']);
$action = isset($_GET['action']) ? $_GET['action'] : 'suspend';

$db = new Database();
$conn = $db->getConnection();

// Get user info
$stmt = $conn->prepare("SELECT name, status FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: dashboard.php?error=user_not_found');
    exit;
}

// Determine new status
if ($action === 'activate') {
    $newStatus = 'active';
    $message = "User '{$user['name']}' has been activated";
    $messageType = 'success';
} else {
    $newStatus = 'suspended';
    $message = "User '{$user['name']}' has been suspended";
    $messageType = 'warning';
}

// Update status
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
$stmt->bind_param("si", $newStatus, $userId);

if ($stmt->execute()) {
    $stmt->close();
    
    // Log to admin_action_logs
    $adminId = $_SESSION['user_id'];
    $actionType = $action === 'activate' ? 'user_activate' : 'user_suspend';
    $actionDetails = "Changed status to {$newStatus} for user: {$user['name']} (ID: {$userId})";
    
    $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, ?, ?)");
    $logStmt->bind_param("isis", $adminId, $actionType, $userId, $actionDetails);
    $logStmt->execute();
    $logStmt->close();
    
    $db->closeConnection();
    
    header("Location: user-details.php?id={$userId}&success=" . urlencode($message) . "&type=" . $messageType);
    exit;
} else {
    $error = $conn->error;
    $stmt->close();
    $db->closeConnection();
    
    header("Location: user-details.php?id={$userId}&error=Failed to update status: " . urlencode($error));
    exit;
}
?>
