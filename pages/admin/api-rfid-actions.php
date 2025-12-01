<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/Database.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$action = $data['action'];

// Handle different actions
switch ($action) {
    case 'assign':
    case 'update':
        handleAssignUpdate($conn, $data);
        break;
    
    case 'remove':
        handleRemove($conn, $data);
        break;
    
    case 'test':
        handleTest($conn, $data);
        break;
    
    case 'register':
        handleRegister($conn, $data);
        break;
    
    case 'block':
        handleBlock($conn, $data);
        break;
    
    case 'unblock':
        handleUnblock($conn, $data);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$db->closeConnection();

// Function to assign or update RFID
function handleAssignUpdate($conn, $data) {
    if (!isset($data['user_id']) || !isset($data['rfid_uid'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $userId = intval($data['user_id']);
    $rfidUid = strtoupper(trim($data['rfid_uid']));
    
    // Validate UID format (hex characters only)
    if (!preg_match('/^[A-F0-9]+$/', $rfidUid)) {
        echo json_encode(['success' => false, 'message' => 'Invalid UID format. Use hexadecimal characters only.']);
        return;
    }
    
    // Check if UID is already assigned to another driver
    $checkStmt = $conn->prepare("SELECT u.user_id, u.name FROM rfid_drivers d 
                                  JOIN users u ON d.user_id = u.user_id 
                                  WHERE d.rfid_uid = ? AND d.user_id != ?");
    $checkStmt->bind_param("si", $rfidUid, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $existingDriver = $checkResult->fetch_assoc();
        $checkStmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'This RFID card is already assigned to ' . htmlspecialchars($existingDriver['name'])
        ]);
        return;
    }
    $checkStmt->close();
    
    // Check if user is a verified driver
    $driverStmt = $conn->prepare("SELECT d.driver_id, d.verification_status, u.name 
                                   FROM rfid_drivers d 
                                   JOIN users u ON d.user_id = u.user_id 
                                   WHERE d.user_id = ?");
    $driverStmt->bind_param("i", $userId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    
    if ($driverResult->num_rows === 0) {
        $driverStmt->close();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        return;
    }
    
    $driver = $driverResult->fetch_assoc();
    $driverStmt->close();
    
    if ($driver['verification_status'] !== 'verified') {
        echo json_encode(['success' => false, 'message' => 'Driver must be verified before assigning RFID card']);
        return;
    }
    
    // Update RFID UID
    $updateStmt = $conn->prepare("UPDATE rfid_drivers SET rfid_uid = ? WHERE user_id = ?");
    $updateStmt->bind_param("si", $rfidUid, $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log to admin_action_logs
        $adminId = $_SESSION['user_id'];
        $actionType = $data['action'] === 'update' ? 'rfid_update' : 'rfid_assign';
        $actionDetails = "RFID card {$rfidUid} " . ($data['action'] === 'update' ? 'updated' : 'assigned') . " to driver: {$driver['name']} (ID: {$userId})";
        
        $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isis", $adminId, $actionType, $userId, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        $_SESSION['success_message'] = 'RFID card ' . $rfidUid . ' successfully ' . 
                                       ($data['action'] === 'update' ? 'updated' : 'assigned') . 
                                       ' to ' . htmlspecialchars($driver['name']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'RFID card ' . ($data['action'] === 'update' ? 'updated' : 'assigned') . ' successfully'
        ]);
    } else {
        $updateStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

// Function to remove RFID
function handleRemove($conn, $data) {
    if (!isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    $userId = intval($data['user_id']);
    
    // Get driver name and old UID for logging
    $nameStmt = $conn->prepare("SELECT u.name, d.rfid_uid FROM rfid_drivers d JOIN users u ON d.user_id = u.user_id WHERE d.user_id = ?");
    $nameStmt->bind_param("i", $userId);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $driverData = $nameResult->num_rows > 0 ? $nameResult->fetch_assoc() : ['name' => 'Unknown', 'rfid_uid' => 'N/A'];
    $nameStmt->close();
    
    // Remove RFID UID
    $updateStmt = $conn->prepare("UPDATE rfid_drivers SET rfid_uid = NULL WHERE user_id = ?");
    $updateStmt->bind_param("i", $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log to admin_action_logs
        $adminId = $_SESSION['user_id'];
        $actionType = 'rfid_remove';
        $actionDetails = "Removed RFID card {$driverData['rfid_uid']} from driver: {$driverData['name']} (ID: {$userId})";
        
        $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, ?, ?)");
        $logStmt->bind_param("isis", $adminId, $actionType, $userId, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        $_SESSION['success_message'] = 'RFID card removed from ' . htmlspecialchars($driverData['name']);
        echo json_encode(['success' => true, 'message' => 'RFID card removed successfully']);
    } else {
        $updateStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

// Function to test RFID card
function handleTest($conn, $data) {
    if (!isset($data['rfid_uid'])) {
        echo json_encode(['success' => false, 'message' => 'Missing RFID UID']);
        return;
    }
    
    $rfidUid = strtoupper(trim($data['rfid_uid']));
    
    // Find driver with this RFID
    $stmt = $conn->prepare("SELECT u.user_id, u.name, u.email, u.phone, 
                                   d.driver_id, d.license_number, d.tricycle_info, 
                                   d.verification_status, d.is_online, d.last_attendance
                            FROM rfid_drivers d
                            JOIN users u ON d.user_id = u.user_id
                            WHERE d.rfid_uid = ?");
    $stmt->bind_param("s", $rfidUid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $driver = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Card found',
            'driver' => [
                'user_id' => $driver['user_id'],
                'driver_id' => $driver['driver_id'],
                'name' => $driver['name'],
                'email' => $driver['email'],
                'phone' => $driver['phone'],
                'license_number' => $driver['license_number'],
                'tricycle_info' => $driver['tricycle_info'],
                'verification_status' => $driver['verification_status'],
                'is_online' => $driver['is_online'],
                'last_attendance' => $driver['last_attendance']
            ]
        ]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Card not found in system']);
    }
}

// Function to register unknown RFID (from learning mode)
function handleRegister($conn, $data) {
    if (!isset($data['rfid_uid']) || !isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $rfidUid = strtoupper(trim($data['rfid_uid']));
    $userId = intval($data['user_id']);
    
    // Validate UID format
    if (!preg_match('/^[A-F0-9]+$/', $rfidUid)) {
        echo json_encode(['success' => false, 'message' => 'Invalid UID format']);
        return;
    }
    
    // Check if UID already exists
    $checkStmt = $conn->prepare("SELECT u.name FROM rfid_drivers d 
                                  JOIN users u ON d.user_id = u.user_id 
                                  WHERE d.rfid_uid = ?");
    $checkStmt->bind_param("s", $rfidUid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        echo json_encode([
            'success' => false, 
            'message' => 'This card is already registered to ' . htmlspecialchars($existing['name'])
        ]);
        return;
    }
    $checkStmt->close();
    
    // Verify driver exists and is verified
    $driverStmt = $conn->prepare("SELECT d.driver_id, d.verification_status, u.name 
                                   FROM rfid_drivers d 
                                   JOIN users u ON d.user_id = u.user_id 
                                   WHERE d.user_id = ?");
    $driverStmt->bind_param("i", $userId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    
    if ($driverResult->num_rows === 0) {
        $driverStmt->close();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        return;
    }
    
    $driver = $driverResult->fetch_assoc();
    $driverStmt->close();
    
    if ($driver['verification_status'] !== 'verified') {
        echo json_encode(['success' => false, 'message' => 'Driver must be verified']);
        return;
    }
    
    // Assign the RFID
    $updateStmt = $conn->prepare("UPDATE rfid_drivers SET rfid_uid = ? WHERE user_id = ?");
    $updateStmt->bind_param("si", $rfidUid, $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'RFID card registered successfully',
            'driver_name' => $driver['name']
        ]);
    } else {
        $updateStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

// Function to block a card
function handleBlock($conn, $data) {
    if (!isset($data['user_id']) || !isset($data['reason']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $userId = intval($data['user_id']);
    $reason = trim($data['reason']);
    $status = $data['status']; // 'blocked', 'lost', or 'stolen'
    
    // Validate status
    if (!in_array($status, ['blocked', 'lost', 'stolen'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid card status']);
        return;
    }
    
    // Get driver info
    $driverStmt = $conn->prepare("SELECT u.name, rd.rfid_uid FROM users u 
                                   JOIN rfid_drivers rd ON u.user_id = rd.user_id 
                                   WHERE u.user_id = ?");
    $driverStmt->bind_param("i", $userId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    
    if ($driverResult->num_rows === 0) {
        $driverStmt->close();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        return;
    }
    
    $driver = $driverResult->fetch_assoc();
    $driverStmt->close();
    
    if (empty($driver['rfid_uid'])) {
        echo json_encode(['success' => false, 'message' => 'Driver has no RFID card assigned']);
        return;
    }
    
    // Block the card
    $updateStmt = $conn->prepare("UPDATE rfid_drivers SET card_status = ?, 
                                   card_blocked_at = NOW(), card_blocked_reason = ? 
                                   WHERE user_id = ?");
    $updateStmt->bind_param("ssi", $status, $reason, $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log the action for record tracking
        $actionText = ucfirst($status);
        $logStmt = $conn->prepare("INSERT INTO admin_action_logs 
                                   (admin_id, action_type, target_user_id, action_details, created_at) 
                                   VALUES (?, 'rfid_block', ?, ?, NOW())");
        $adminId = $_SESSION['user_id'];
        $actionDetails = "Marked card as $actionText for " . $driver['name'] . " - Reason: $reason";
        $logStmt->bind_param("iis", $adminId, $userId, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode(['success' => true, 'message' => "Card marked as $actionText successfully"]);
    } else {
        $updateStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

// Function to unblock a card
function handleUnblock($conn, $data) {
    if (!isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        return;
    }
    
    $userId = intval($data['user_id']);
    
    // Get driver info
    $driverStmt = $conn->prepare("SELECT u.name, rd.card_status FROM users u 
                                   JOIN rfid_drivers rd ON u.user_id = rd.user_id 
                                   WHERE u.user_id = ?");
    $driverStmt->bind_param("i", $userId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    
    if ($driverResult->num_rows === 0) {
        $driverStmt->close();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        return;
    }
    
    $driver = $driverResult->fetch_assoc();
    $driverStmt->close();
    
    // Unblock the card
    $updateStmt = $conn->prepare("UPDATE rfid_drivers SET card_status = 'active', 
                                   card_blocked_at = NULL, card_blocked_reason = NULL 
                                   WHERE user_id = ?");
    $updateStmt->bind_param("i", $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log the action for record tracking
        $logStmt = $conn->prepare("INSERT INTO admin_action_logs 
                                   (admin_id, action_type, target_user_id, action_details, created_at) 
                                   VALUES (?, 'rfid_unblock', ?, ?, NOW())");
        $adminId = $_SESSION['user_id'];
        $actionDetails = "Unblocked card for " . $driver['name'];
        $logStmt->bind_param("iis", $adminId, $userId, $actionDetails);
        $logStmt->execute();
        $logStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Card unblocked successfully']);
    } else {
        $updateStmt->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}
?>

