<?php
session_start();
header('Content-Type: application/json');
ob_start();

try {
    // Simple direct mysqli connection
    $conn = new mysqli("localhost", "root", "", "tric_db");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create table if needed
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `driver_locations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `driver_id` int(11) NOT NULL,
      `latitude` decimal(10,8) NOT NULL,
      `longitude` decimal(11,8) NOT NULL,
      `accuracy` float DEFAULT NULL,
      `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_driver_id` (`driver_id`),
      KEY `idx_timestamp` (`timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table_sql);

    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
        throw new Exception("Unauthorized");
    }

    $user_id = intval($_SESSION['user_id']);
    
    // Get driver_id
    $result = $conn->query("SELECT driver_id FROM rfid_drivers WHERE user_id = $user_id LIMIT 1");
    
    if (!$result || $result->num_rows == 0) {
        throw new Exception("Driver not found");
    }
    
    $row = $result->fetch_assoc();
    $driver_id = intval($row['driver_id']);

    // Get input
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    
    if (!$input || !isset($input['latitude']) || !isset($input['longitude'])) {
        throw new Exception("Missing latitude or longitude");
    }

    $lat = floatval($input['latitude']);
    $lng = floatval($input['longitude']);
    $acc = floatval($input['accuracy'] ?? 0);

    // Insert location
    $sql = "INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy) VALUES ($driver_id, $lat, $lng, $acc)";
    
    if (!$conn->query($sql)) {
        throw new Exception("Insert failed: " . $conn->error);
    }

    $location_id = $conn->insert_id;
    $conn->close();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Location saved successfully',
        'location_id' => $location_id
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
