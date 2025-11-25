<?php
// RFID Learning Mode Handler - Receives unknown card UIDs from ESP32
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

// Handle POST for enable/disable learning mode (check this FIRST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $learningStatusFile = __DIR__ . '/../../temp/learning_mode_status.json';
    $learningDir = dirname($learningStatusFile);
    
    if (!file_exists($learningDir)) {
        mkdir($learningDir, 0777, true);
    }
    
    if ($action === 'enable') {
        // Enable learning mode
        $statusData = [
            'enabled' => true,
            'enabled_at' => date('Y-m-d H:i:s'),
            'enabled_by' => 'admin'
        ];
        file_put_contents($learningStatusFile, json_encode($statusData));
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Learning mode enabled']);
        $conn->close();
        exit();
    } elseif ($action === 'disable') {
        // Disable learning mode
        $statusData = [
            'enabled' => false,
            'disabled_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($learningStatusFile, json_encode($statusData));
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Learning mode disabled']);
        $conn->close();
        exit();
    }
}

// Handle POST - ESP32 sends unknown card
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['uid'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing UID']);
        $conn->close();
        exit();
    }
    
    $uid = strtoupper(trim($data['uid']));
    
    // Check if this card already exists
    $checkStmt = $conn->prepare("SELECT d.rfid_uid, u.name FROM rfid_drivers d 
                                  JOIN users u ON d.user_id = u.user_id 
                                  WHERE d.rfid_uid = ?");
    $checkStmt->bind_param("s", $uid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Card already registered',
            'assigned_to' => $existing['name']
        ]);
        $conn->close();
        exit();
    }
    $checkStmt->close();
    
    // Store the unknown card in a temporary table or file for learning mode
    // For now, we'll create a simple file-based storage
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
    
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'Unknown card detected',
        'uid' => $uid,
        'action' => 'register_new'
    ]);
    $conn->close();
    exit();
}

// Handle GET - Admin polls for new cards OR enables/disables learning mode
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'poll';
    
    if ($action === 'status') {
        // Return learning mode status
        $learningStatusFile = __DIR__ . '/../../temp/learning_mode_status.json';
        $enabled = false;
        
        if (file_exists($learningStatusFile)) {
            $statusData = json_decode(file_get_contents($learningStatusFile), true);
            $enabled = $statusData['enabled'] ?? false;
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'enabled' => $enabled]);
        $conn->close();
        exit();
    }
    
    // Default: poll for new cards
    $learningFile = __DIR__ . '/../../temp/rfid_learning_latest.json';
    
    if (file_exists($learningFile)) {
        $data = json_decode(file_get_contents($learningFile), true);
        
        // Check if card was detected in last 10 seconds
        $detectedTime = strtotime($data['timestamp']);
        $now = time();
        
        if (($now - $detectedTime) <= 10) {
            // Clear the file after reading
            unlink($learningFile);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'new_card' => true,
                'uid' => $data['uid'],
                'timestamp' => $data['timestamp']
            ]);
            $conn->close();
            exit();
        }
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'new_card' => false
    ]);
    $conn->close();
    exit();
}

ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
$conn->close();
?>
