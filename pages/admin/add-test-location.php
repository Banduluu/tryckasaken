<?php
session_start();
require_once '../../config/Database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    echo 'Not authorized';
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Get all online drivers
$drivers = $conn->query("SELECT d.driver_id, d.user_id, u.name, d.is_online, d.verification_status 
                         FROM rfid_drivers d 
                         INNER JOIN users u ON d.user_id = u.user_id 
                         ORDER BY u.name");

echo "<h2>Manual Location Entry for Testing</h2>";
echo "<p>Use this tool to manually add test locations to verify the map works correctly.</p>";

// Handle manual location entry
if ($_POST) {
    $driver_id = intval($_POST['driver_id']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $accuracy = floatval($_POST['accuracy']) ?? 10;

    $stmt = $conn->prepare("INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iddf", $driver_id, $latitude, $longitude, $accuracy);

    if ($stmt->execute()) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>✓ Location saved successfully!</strong>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>✗ Error: " . $conn->error . "</strong>";
        echo "</div>";
    }
    $stmt->close();
}

echo "<form method='POST' style='border: 1px solid #ddd; padding: 15px; border-radius: 5px;'>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Select Driver:</strong></label><br>";
echo "<select name='driver_id' required>";
echo "<option value=''>-- Choose a driver --</option>";
while ($driver = $drivers->fetch_assoc()) {
    $status = $driver['is_online'] ? '🟢' : '🔴';
    $verified = $driver['verification_status'] === 'verified' ? '✓' : '✗';
    echo "<option value='{$driver['driver_id']}'>{$status} {$verified} {$driver['name']} (ID: {$driver['driver_id']})</option>";
}
echo "</select>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Latitude:</strong></label><br>";
echo "<input type='number' name='latitude' step='0.000001' value='13.941876' placeholder='13.941876' required>";
echo "<small>Example: 13.941876 (Lipa City center)</small>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Longitude:</strong></label><br>";
echo "<input type='number' name='longitude' step='0.000001' value='121.164421' placeholder='121.164421' required>";
echo "<small>Example: 121.164421 (Lipa City center)</small>";
echo "</div>";

echo "<div style='margin-bottom: 15px;'>";
echo "<label><strong>Accuracy (meters):</strong></label><br>";
echo "<input type='number' name='accuracy' step='0.1' value='10' placeholder='10'>";
echo "<small>Accuracy in meters, default: 10m</small>";
echo "</div>";

echo "<button type='submit' style='background: #28a745; border: none; color: white; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>Add Test Location</button>";
echo "</form>";

// Show latest locations
echo "<h3 style='margin-top: 30px;'>Latest 10 Locations in Database</h3>";

$latest = $conn->query("SELECT dl.id, dl.driver_id, u.name, dl.latitude, dl.longitude, dl.accuracy, dl.timestamp,
                        TIMESTAMPDIFF(SECOND, dl.timestamp, NOW()) as age_seconds
                        FROM driver_locations dl
                        LEFT JOIN rfid_drivers d ON dl.driver_id = d.driver_id
                        LEFT JOIN users u ON d.user_id = u.user_id
                        ORDER BY dl.timestamp DESC LIMIT 10");

if ($latest->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Driver Name</th><th>Latitude</th><th>Longitude</th><th>Accuracy</th><th>Timestamp</th><th>Age</th></tr>";
    while ($row = $latest->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['name'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format($row['latitude'], 6) . "</td>";
        echo "<td>" . number_format($row['longitude'], 6) . "</td>";
        echo "<td>" . $row['accuracy'] . "m</td>";
        echo "<td>" . $row['timestamp'] . "</td>";
        echo "<td>" . $row['age_seconds'] . "s ago</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>No location data found in database.</strong></p>";
}

// Show status of all drivers
echo "<h3 style='margin-top: 30px;'>All Drivers Status</h3>";

$all_drivers = $conn->query("SELECT d.driver_id, d.user_id, u.name, d.is_online, d.verification_status,
                            (SELECT COUNT(*) FROM driver_locations WHERE driver_id = d.driver_id) as location_count,
                            (SELECT MAX(timestamp) FROM driver_locations WHERE driver_id = d.driver_id) as last_location
                            FROM rfid_drivers d
                            INNER JOIN users u ON d.user_id = u.user_id
                            ORDER BY d.is_online DESC, u.name");

echo "<table border='1' cellpadding='10' style='width: 100%;'>";
echo "<tr><th>Driver Name</th><th>Online</th><th>Verified</th><th>Locations Saved</th><th>Last Location</th></tr>";
while ($row = $all_drivers->fetch_assoc()) {
    $online_status = $row['is_online'] ? '<span style="color: green;">✓ Online</span>' : '<span style="color: red;">✗ Offline</span>';
    $verified_status = $row['verification_status'] === 'verified' ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: orange;">⚠ ' . $row['verification_status'] . '</span>';
    $last_loc = $row['last_location'] ? date('Y-m-d H:i:s', strtotime($row['last_location'])) : 'Never';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . $online_status . "</td>";
    echo "<td>" . $verified_status . "</td>";
    echo "<td>" . $row['location_count'] . "</td>";
    echo "<td>" . $last_loc . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
