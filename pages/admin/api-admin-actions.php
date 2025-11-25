<?php
session_start();
require_once '../../config/Database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'get_stats':
        getStats($conn);
        break;
    
    case 'get_recent_activity':
        getRecentActivity($conn);
        break;
    
    case 'verify_driver':
        verifyDriver($conn);
        break;
    
    case 'reject_driver':
        rejectDriver($conn);
        break;
    
    case 'get_pending_drivers':
        getPendingDrivers($conn);
        break;
    
    case 'assign_driver':
        assignDriver($conn);
        break;
    
    case 'cancel_booking':
        cancelBooking($conn);
        break;
    
    case 'suspend_user':
        suspendUser($conn);
        break;
    
    case 'activate_user':
        activateUser($conn);
        break;
    
    case 'delete_user':
        deleteUser($conn);
        break;
    
    case 'get_bookings':
        getBookings($conn);
        break;
    
    case 'get_available_drivers':
        getAvailableDrivers($conn);
        break;
    
    case 'update_booking_status':
        updateBookingStatus($conn);
        break;
    
    case 'get_driver_attendance':
        getDriverAttendance($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$database->closeConnection();

// Function to get dashboard statistics
function getStats($conn) {
    try {
        $stats = [];
        
        // User counts
        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'passenger'");
        $stats['passengers'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT COUNT(*) as total FROM rfid_drivers");
        $stats['drivers'] = $result ? $result->fetch_assoc()['total'] : 0;

        // Booking counts
        $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings");
        $stats['total_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings WHERE status = 'pending'");
        $stats['pending_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

        $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings WHERE status = 'completed'");
        $stats['completed_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

        // Pending driver verifications
        $result = $conn->query("
            SELECT COUNT(*) as total 
            FROM drivers d
            INNER JOIN users u ON d.user_id = u.user_id 
            WHERE u.user_type = 'driver' 
            AND d.verification_status = 'pending'
        ");
        $stats['pending_verifications'] = $result ? $result->fetch_assoc()['total'] : 0;

        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch statistics: ' . $e->getMessage()]);
    }
}

// Function to get recent activity
function getRecentActivity($conn) {
    try {
        $data = [];
        
        // Recent bookings
        $result = $conn->query("SELECT b.*, p.name as passenger_name 
                               FROM tricycle_bookings b 
                               LEFT JOIN users p ON b.user_id = p.user_id 
                               ORDER BY b.booking_time DESC LIMIT 5");
        $data['bookings'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        // Recent driver applications
        $result = $conn->query("SELECT u.user_id, u.name, u.email, u.created_at 
                              FROM rfid_drivers d
                              INNER JOIN users u ON d.user_id = u.user_id
                              WHERE d.verification_status = 'pending'
                              ORDER BY u.created_at DESC LIMIT 5");
        $data['drivers'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch activity: ' . $e->getMessage()]);
    }
}

// Function to verify a driver
function verifyDriver($conn) {
    if (!isset($_POST['driver_id'])) {
        echo json_encode(['success' => false, 'message' => 'Driver ID is required']);
        return;
    }

    $driverId = intval($_POST['driver_id']);

    // Check if driver exists in rfid_drivers table
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM rfid_drivers WHERE driver_id = ?");
    $checkStmt->bind_param("i", $driverId);
    $checkStmt->execute();
    $driverExists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
    $checkStmt->close();

    if (!$driverExists) {
        echo json_encode(['success' => false, 'message' => 'Driver record not found']);
        return;
    }

    // Update rfid_drivers table
    $conn->begin_transaction();

    try {
        // Update rfid_drivers table
        $stmt = $conn->prepare("UPDATE rfid_drivers SET verification_status = 'verified' WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        $result = $stmt->execute();

        if ($result) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Driver verified successfully!']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to verify driver']);
        }

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to reject a driver
function rejectDriver($conn) {
    if (!isset($_POST['driver_id'])) {
        echo json_encode(['success' => false, 'message' => 'Driver ID is required']);
        return;
    }

    $driverId = intval($_POST['driver_id']);

    // Check if driver exists in rfid_drivers table
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM rfid_drivers WHERE driver_id = ?");
    $checkStmt->bind_param("i", $driverId);
    $checkStmt->execute();
    $driverExists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
    $checkStmt->close();

    if (!$driverExists) {
        echo json_encode(['success' => false, 'message' => 'Driver record not found']);
        return;
    }

    // Update rfid_drivers table
    $conn->begin_transaction();

    try {
        // Update rfid_drivers table
        $stmt = $conn->prepare("UPDATE rfid_drivers SET verification_status = 'rejected' WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        $result = $stmt->execute();

        if ($result) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Driver application rejected successfully!']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to reject driver']);
        }

        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to get pending drivers with details
function getPendingDrivers($conn) {
    try {
        $query = "SELECT u.user_id, u.name, u.email, u.phone, d.license_number, d.tricycle_info, u.created_at,
                         d.verification_status, d.or_cr_path, d.license_path, d.picture_path, d.driver_id
                  FROM rfid_drivers d
                  INNER JOIN users u ON d.user_id = u.user_id
                  WHERE d.verification_status = 'pending'
                  ORDER BY u.created_at DESC";
        
        $result = $conn->query($query);
        $drivers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        echo json_encode(['success' => true, 'data' => $drivers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch drivers: ' . $e->getMessage()]);
    }
}

// Function to assign driver to booking
function assignDriver($conn) {
    if (!isset($_POST['booking_id']) || !isset($_POST['driver_id'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and Driver ID are required']);
        return;
    }

    $bookingId = intval($_POST['booking_id']);
    $driverId = intval($_POST['driver_id']);

    // Check if driver is verified (using rfid_drivers table)
    $checkStmt = $conn->prepare("SELECT verification_status FROM rfid_drivers WHERE driver_id = ?");
    $checkStmt->bind_param("i", $driverId);
    $checkStmt->execute();
    $result = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$result || $result['verification_status'] !== 'verified') {
        echo json_encode(['success' => false, 'message' => 'Driver is not verified']);
        return;
    }

    // Assign driver to booking
    $stmt = $conn->prepare("UPDATE tricycle_bookings SET driver_id = ?, status = 'accepted' WHERE id = ?");
    $stmt->bind_param("ii", $driverId, $bookingId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Driver assigned successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign driver or booking not found']);
    }

    $stmt->close();
}

// Function to cancel booking
function cancelBooking($conn) {
    if (!isset($_POST['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        return;
    }

    $bookingId = intval($_POST['booking_id']);

    $stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $bookingId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking or booking not found']);
    }

    $stmt->close();
}

// Function to suspend user
function suspendUser($conn) {
    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    $userId = intval($_POST['user_id']);

    $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
    $stmt->bind_param("i", $userId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'User suspended successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to suspend user or user not found']);
    }

    $stmt->close();
}

// Function to activate user
function activateUser($conn) {
    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    $userId = intval($_POST['user_id']);

    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
    $stmt->bind_param("i", $userId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'User activated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate user or user not found']);
    }

    $stmt->close();
}

// Function to delete user
function deleteUser($conn) {
    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }

    $userId = intval($_POST['user_id']);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // If user is a driver, delete from rfid_drivers table first
        $stmt1 = $conn->prepare("DELETE FROM rfid_drivers WHERE user_id = ?");
        $stmt1->bind_param("i", $userId);
        $stmt1->execute();
        $stmt1->close();

        // Delete user
        $stmt2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt2->bind_param("i", $userId);
        
        if ($stmt2->execute() && $stmt2->affected_rows > 0) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'User not found or already deleted']);
        }

        $stmt2->close();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to get bookings with filters
function getBookings($conn) {
    try {
        $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
        $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

        $whereConditions = [];
        $params = [];
        $types = '';

        if ($statusFilter !== 'all') {
            $whereConditions[] = "b.status = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }

        if ($searchQuery) {
            $whereConditions[] = "(b.id = ? OR p.name LIKE ? OR p.email LIKE ?)";
            $searchId = is_numeric($searchQuery) ? intval($searchQuery) : 0;
            $searchLike = "%$searchQuery%";
            $params = array_merge($params, [$searchId, $searchLike, $searchLike]);
            $types .= 'iss';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT b.*, p.name as passenger_name, p.email as passenger_email, 
                         d.name as driver_name
                  FROM tricycle_bookings b 
                  LEFT JOIN users p ON b.user_id = p.user_id 
                  LEFT JOIN users d ON b.driver_id = d.user_id
                  $whereClause
                  ORDER BY b.booking_time DESC";

        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $bookings = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'data' => $bookings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch bookings: ' . $e->getMessage()]);
    }
}

// Function to get available drivers
function getAvailableDrivers($conn) {
    try {
        $query = "SELECT u.user_id, u.name, u.phone, d.tricycle_info, d.is_online, d.driver_id
                  FROM rfid_drivers d
                  INNER JOIN users u ON d.user_id = u.user_id
                  WHERE d.verification_status = 'verified'
                  AND u.status = 'active'
                  ORDER BY d.is_online DESC, u.name ASC";
        
        $result = $conn->query($query);
        $drivers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        echo json_encode(['success' => true, 'data' => $drivers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch drivers: ' . $e->getMessage()]);
    }
}

// Function to update booking status
function updateBookingStatus($conn) {
    if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and status are required']);
        return;
    }

    $bookingId = intval($_POST['booking_id']);
    $status = $_POST['status'];

    // Validate status
    $validStatuses = ['pending', 'accepted', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }

    $stmt = $conn->prepare("UPDATE tricycle_bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $bookingId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Booking status updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update booking or booking not found']);
    }

    $stmt->close();
}

// Function to get recent driver attendance records
function getDriverAttendance($conn) {
    try {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        $query = "SELECT da.id, da.timestamp, da.action, u.name, u.phone, d.picture_path, d.tricycle_info
                  FROM driver_attendance da
                  JOIN users u ON da.user_id = u.user_id
                  JOIN rfid_drivers d ON da.driver_id = d.driver_id
                  ORDER BY da.timestamp DESC
                  LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $records]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch attendance: ' . $e->getMessage()]);
    }
}
?>
