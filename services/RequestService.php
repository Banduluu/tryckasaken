<?php
class RequestService {
    private $conn;
    private $driver_id;

    public function __construct($conn, $driver_id) {
        $this->conn = $conn;
        $this->driver_id = $driver_id;
    }

    /**
     * Get all pending requests and driver's active trips
     */
    public function getRequests() {
        $sql = "SELECT * FROM tricycle_bookings 
                WHERE (LOWER(TRIM(status)) = 'pending') 
                   OR (LOWER(TRIM(status)) = 'accepted' AND driver_id = ?)
                ORDER BY booking_time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'requests' => $requests,
            'count' => count($requests)
        ];
    }

    /**
     * Accept a ride request
     */
    public function acceptRide($booking_id) {
        // Validate booking ID
        if ($booking_id <= 0) {
            return ['success' => false, 'message' => 'Invalid booking ID'];
        }

        // Check driver verification status
        $driver_info = $this->getDriverInfo();
        
        if (!$driver_info) {
            return ['success' => false, 'message' => 'Driver information not found'];
        }

        if ($driver_info['verification_status'] !== 'verified') {
            return ['success' => false, 'message' => 'You must be verified before accepting rides'];
        }

        if (!$driver_info['is_online']) {
            return ['success' => false, 'message' => 'You must be online to accept rides'];
        }

        // Check if driver already has an active trip
        if ($this->hasActiveTrip()) {
            return ['success' => false, 'message' => 'You already have an active trip. Complete it first.'];
        }

        // Accept the ride
        $update_sql = "UPDATE tricycle_bookings 
                       SET driver_id = ?, status = 'accepted' 
                       WHERE id = ? AND LOWER(status) = 'pending'";
        $stmt = $this->conn->prepare($update_sql);
        $stmt->bind_param("ii", $this->driver_id, $booking_id);
        
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success 
            ? ['success' => true, 'message' => 'Ride accepted successfully! You are now on a trip.']
            : ['success' => false, 'message' => 'Failed to accept ride or ride already taken'];
    }

    /**
     * Complete a ride
     */
    public function completeRide($booking_id) {
        if ($booking_id <= 0) {
            return ['success' => false, 'message' => 'Invalid booking ID'];
        }

        $complete_sql = "UPDATE tricycle_bookings 
                        SET status = 'completed' 
                        WHERE id = ? AND driver_id = ? AND LOWER(status) = 'accepted'";
        $stmt = $this->conn->prepare($complete_sql);
        $stmt->bind_param("ii", $booking_id, $this->driver_id);
        
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success
            ? ['success' => true, 'message' => 'Ride completed successfully!']
            : ['success' => false, 'message' => 'Failed to complete ride'];
    }

    /**
     * Check driver's current status
     */
    public function checkStatus() {
        $status_query = "SELECT 
            (SELECT COUNT(*) FROM tricycle_bookings WHERE driver_id = ? AND LOWER(status) = 'accepted') as active_trips,
            (SELECT COUNT(*) FROM tricycle_bookings WHERE LOWER(status) = 'pending') as pending_requests
        ";
        $stmt = $this->conn->prepare($status_query);
        $stmt->bind_param("i", $this->driver_id);
        $stmt->execute();
        $status = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return [
            'success' => true,
            'active_trips' => $status['active_trips'],
            'pending_requests' => $status['pending_requests']
        ];
    }

    /**
     * Get driver information
     */
    private function getDriverInfo() {
        $query = "SELECT verification_status, is_online FROM drivers WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->driver_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Check if driver has an active trip
     */
    private function hasActiveTrip() {
        $check = "SELECT COUNT(*) as active_count FROM tricycle_bookings 
                  WHERE driver_id = ? AND LOWER(status) = 'accepted'";
        $stmt = $this->conn->prepare($check);
        $stmt->bind_param("i", $this->driver_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['active_count'] > 0;
    }
}
