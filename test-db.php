<?php
// Comprehensive RFID Attendance System Debugging
$host = "localhost";
$username = "root";
$password = "";
$database = "tric_db";
?>
<!DOCTYPE html>
<html>
<head>
    <title>RFID Debug Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #667eea;
            font-size: 2em;
            margin-bottom: 5px;
        }
        .section {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            font-size: 1.4em;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover { background: #f9f9f9; }
        .success { color: #27ae60; font-weight: 600; }
        .error { color: #e74c3c; font-weight: 600; }
        .warning { color: #f39c12; font-weight: 600; }
        .info { color: #3498db; }
        p { 
            margin: 10px 0;
            line-height: 1.6;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .verified { background: #d4edda; color: #155724; }
        .pending { background: #fff3cd; color: #856404; }
        .rejected { background: #f8d7da; color: #721c24; }
        .online { background: #d4edda; color: #155724; }
        .offline { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔌 RFID Attendance System - Debug Test</h1>
        <p style="color: #666; margin-top: 10px;">Comprehensive database and connection diagnostics</p>
    </div>
<?php
echo "";
// Test connection
$conn = @mysqli_connect($host, $username, $password);

if (!$conn) {
    echo '<div class="section">';
    echo '<h2>MySQL Connection Status</h2>';
    echo '<p class="error">❌ ERROR: Could not connect to MySQL!</p>';
    echo '<p><strong>Error:</strong> ' . mysqli_connect_error() . '</p>';
    echo '<p><strong>Please make sure:</strong></p>';
    echo '<ul style="margin-left: 20px;"><li>MySQL service is running</li><li>Host is correct: ' . $host . '</li><li>Username is correct: ' . $username . '</li><li>Password is empty</li></ul>';
    echo '</div></div></body></html>';
    exit;
} else {
    echo '<div class="section">';
    echo '<h2>MySQL Connection Status</h2>';
    echo '<p class="success">✓ SUCCESS: Connected to MySQL!</p>';
    echo '<table><tr><th>Setting</th><th>Value</th></tr>';
    echo '<tr><td>Host</td><td>' . $host . '</td></tr>';
    echo '<tr><td>Username</td><td>' . $username . '</td></tr>';
    echo '<tr><td>MySQL Version</td><td>' . mysqli_get_server_info($conn) . '</td></tr>';
    echo '</table>';
    echo '</div>';
}

// Test connection
$conn = @mysqli_connect($host, $username, $password);

if (!$conn) {
    echo "❌ ERROR: Could not connect to MySQL!\n";
    echo "Error: " . mysqli_connect_error() . "\n";
    echo "\nPlease make sure:\n";
    echo "1. MySQL service is running\n";
    echo "2. Host is correct: $host\n";
    echo "3. Username is correct: $username\n";
    echo "4. Password is empty\n";
    exit;
} else {
    echo "✓ SUCCESS: Connected to MySQL!\n";
    echo "  - Host: $host\n";
    echo "  - User: $username\n";
    echo "  - MySQL Version: " . mysqli_get_server_info($conn) . "\n\n";
}


// Select database
if (!mysqli_select_db($conn, $database)) {
    echo '<div class="section">';
    echo '<h2>Database Selection</h2>';
    echo '<p class="error">❌ ERROR: Could not select database "' . $database . '"</p>';
    echo '<p><strong>Error:</strong> ' . mysqli_error($conn) . '</p>';
    echo '</div></div></body></html>';
    mysqli_close($conn);
    exit;
} else {
    echo '<div class="section">';
    echo '<h2>Database Selection</h2>';
    echo '<p class="success">✓ SUCCESS: Selected database "' . $database . '"</p>';
    echo '</div>';
}

// Check RFID Drivers Table
echo '<div class="section">';
echo '<h2>RFID Drivers Table</h2>';

$query = "SELECT driver_id, user_id, rfid_uid, verification_status, is_online FROM rfid_drivers";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo '<p class="error">❌ ERROR: Failed to query rfid_drivers table</p>';
    echo '<p><strong>Error:</strong> ' . mysqli_error($conn) . '</p>';
} else {
    $count = mysqli_num_rows($result);
    echo '<p class="info">✓ Found <strong>' . $count . '</strong> driver(s)</p>';
    
    if ($count == 0) {
        echo '<p class="warning">⚠️ WARNING: No drivers found! The table is empty.</p>';
        echo '<p>You need to add drivers to rfid_drivers before testing.</p>';
    } else {
        echo '<table>';
        echo '<tr><th>Driver ID</th><th>User ID</th><th>RFID UID</th><th>Status</th><th>Online</th></tr>';
        
        while ($row = mysqli_fetch_assoc($result)) {
            $rfid = $row['rfid_uid'] ? $row['rfid_uid'] : '<span class="warning">NOT SET</span>';
            $status_class = $row['verification_status'];
            $online_class = $row['is_online'] ? 'online' : 'offline';
            $online_text = $row['is_online'] ? 'Yes' : 'No';
            
            echo '<tr>';
            echo '<td><strong>' . $row['driver_id'] . '</strong></td>';
            echo '<td>' . $row['user_id'] . '</td>';
            echo '<td><code>' . $rfid . '</code></td>';
            echo '<td><span class="status-badge ' . $status_class . '">' . ucfirst($row['verification_status']) . '</span></td>';
            echo '<td><span class="status-badge ' . $online_class . '">' . $online_text . '</span></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
echo '</div>';

// Check Users Table
echo '<div class="section">';
echo '<h2>Users Table (Drivers)</h2>';

$query = "SELECT user_id, name, user_type, email FROM users WHERE user_type = 'driver'";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo '<p class="error">❌ ERROR: Failed to query users table</p>';
    echo '<p><strong>Error:</strong> ' . mysqli_error($conn) . '</p>';
} else {
    $count = mysqli_num_rows($result);
    echo '<p class="info">✓ Found <strong>' . $count . '</strong> driver user(s)</p>';
    
    if ($count == 0) {
        echo '<p class="warning">⚠️ WARNING: No driver users found!</p>';
    } else {
        echo '<table>';
        echo '<tr><th>User ID</th><th>Name</th><th>Email</th></tr>';
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<tr>';
            echo '<td><strong>' . $row['user_id'] . '</strong></td>';
            echo '<td>' . $row['name'] . '</td>';
            echo '<td>' . $row['email'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}
echo '</div>';

// Check Driver Attendance Table
echo '<div class="section">';
echo '<h2>Driver Attendance Table</h2>';

$query = "SELECT COUNT(*) as total FROM driver_attendance";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$total = $row['total'];

echo '<p class="success">✓ Total attendance records: <strong>' . $total . '</strong></p>';
echo '</div>';

// Test Card UID Query (simulation)
echo '<div class="section">';
echo '<h2>Card UID Lookup Test</h2>';
echo '<p>Testing if UID <code>E317A32A</code> exists...</p>';

$test_uid = "E317A32A";
$query = "SELECT u.user_id, u.name, d.driver_id, d.verification_status, d.is_online
          FROM users u 
          JOIN rfid_drivers d ON u.user_id = d.user_id
          WHERE d.rfid_uid = ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo '<p class="error">❌ ERROR: Failed to prepare statement</p>';
    echo '<p><strong>Error:</strong> ' . mysqli_error($conn) . '</p>';
} else {
    mysqli_stmt_bind_param($stmt, "s", $test_uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo '<p class="error">❌ Card UID "' . $test_uid . '" NOT FOUND in database</p>';
        echo '<p><strong>This is why you see "Unknown Card" error!</strong></p>';
        echo '<p style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 10px;">';
        echo '<strong>Solution:</strong> Add this UID to the rfid_drivers table for one of your drivers.';
        echo '</p>';
    } else {
        $row = mysqli_fetch_assoc($result);
        echo '<p class="success">✓ Card UID "' . $test_uid . '" FOUND!</p>';
        echo '<table>';
        echo '<tr><th>Property</th><th>Value</th></tr>';
        echo '<tr><td>Driver Name</td><td><strong>' . $row['name'] . '</strong></td></tr>';
        echo '<tr><td>User ID</td><td>' . $row['user_id'] . '</td></tr>';
        echo '<tr><td>Driver ID</td><td>' . $row['driver_id'] . '</td></tr>';
        echo '<tr><td>Verification Status</td><td><span class="status-badge ' . $row['verification_status'] . '">' . ucfirst($row['verification_status']) . '</span></td></tr>';
        echo '<tr><td>Online Status</td><td><span class="status-badge ' . ($row['is_online'] ? 'online' : 'offline') . '">' . ($row['is_online'] ? 'Yes' : 'No') . '</span></td></tr>';
        echo '</table>';
    }
    mysqli_stmt_close($stmt);
}
echo '</div>';

echo '<div class="section" style="border-left-color: #27ae60;">';
echo '<h2 style="color: #27ae60;">✓ Debug Test Complete</h2>';
echo '<p>Check the information above to diagnose your RFID system issues.</p>';
echo '</div>';

echo '</div>';
echo '</body></html>';

mysqli_close($conn);
?>
