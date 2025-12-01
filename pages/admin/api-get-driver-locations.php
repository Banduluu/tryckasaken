<?php
session_start();
header('Content-Type: application/json');
ob_start();

try {
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
        throw new Exception("Unauthorized access");
    }

    // Direct mysqli connection
    $conn = new mysqli("localhost", "root", "", "tric_db");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get all online verified drivers with latest location
    $query = "SELECT 
        d.driver_id,
        u.user_id,
        u.name,
        u.email,
        u.phone,
        d.tricycle_info,
        d.license_number,
        d.is_online,
        d.verification_status,
        (SELECT COUNT(*) FROM tricycle_bookings WHERE driver_id = d.driver_id AND status IN ('accepted', 'in-transit')) as active_trips,
        dl.latitude,
        dl.longitude,
        dl.accuracy,
        dl.timestamp as location_timestamp
    FROM rfid_drivers d
    INNER JOIN users u ON d.user_id = u.user_id
    LEFT JOIN (
        SELECT driver_id, latitude, longitude, accuracy, timestamp
        FROM driver_locations
        WHERE id IN (
            SELECT MAX(id)
            FROM driver_locations
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY driver_id
        )
    ) dl ON d.driver_id = dl.driver_id
    WHERE d.verification_status = 'verified' AND u.status = 'active' AND d.is_online = 1
    ORDER BY dl.timestamp DESC, u.name ASC";

    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query error: " . $conn->error);
    }

    $drivers = [];
    $count = 0;

    while ($row = $result->fetch_assoc()) {
        // Calculate location age
        $location_age_seconds = null;
        if ($row['location_timestamp']) {
            $location_age_seconds = (int)(time() - strtotime($row['location_timestamp']));
        }
        
        // Check if location data is valid
        $has_location = 0;
        if ($row['latitude'] !== null && $row['longitude'] !== null && 
            $row['latitude'] != 0 && $row['longitude'] != 0) {
            $has_location = 1;
            $count++;
        }
        
        $drivers[] = [
            'driver_id' => (int)$row['driver_id'],
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'tricycle_info' => $row['tricycle_info'],
            'license_number' => $row['license_number'],
            'is_online' => (int)$row['is_online'],
            'verification_status' => $row['verification_status'],
            'active_trips' => (int)$row['active_trips'],
            'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
            'accuracy' => $row['accuracy'] ? (float)$row['accuracy'] : 0,
            'location_timestamp' => $row['location_timestamp'],
            'has_location' => $has_location,
            'location_age_seconds' => $location_age_seconds
        ];
    }

    $conn->close();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'count' => $count,
        'drivers' => $drivers
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
