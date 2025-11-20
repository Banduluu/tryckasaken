<?php
session_start();
require_once '../../config/Database.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php?error=missing_user_id');
    exit;
}

$userId = intval($_GET['id']);

$db = new Database();
$conn = $db->getConnection();

// Get user info first
$stmt = $conn->prepare("SELECT name, email, user_type FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: dashboard.php?error=user_not_found');
    exit;
}

// Delete user (CASCADE will handle related records)
$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $stmt->close();
    $db->closeConnection();
    
    // Log the deletion
    error_log("User deleted - ID: {$userId}, Name: {$user['name']}, Email: {$user['email']}");
    
    header("Location: dashboard.php?success=User '{$user['name']}' has been permanently deleted&type=warning");
    exit;
} else {
    $error = $conn->error;
    $stmt->close();
    $db->closeConnection();
    
    header("Location: dashboard.php?error=Failed to delete user: " . urlencode($error));
    exit;
}
?>
