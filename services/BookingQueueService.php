<?php
class BookingQueueService {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Release driver from booking when they go offline or complete trip
     */
    public function releaseDriverBooking($driver_id, $release_reason = 'manual') {
        // Get active booking for this driver
        $query = "SELECT id FROM tricycle_bookings 
                  WHERE driver_id = ? 
                  AND (status = 'accepted' OR status = 'in-transit')
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            $stmt->close();
            
            // Only cancel if no passenger has been picked up yet (accepted status)
            if ($this->isBookingInAcceptedStatus($booking['id'])) {
                $updateQuery = "UPDATE tricycle_bookings 
                                SET queue_assigned = 0, driver_id = NULL, status = 'pending'
                                WHERE id = ?";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $booking['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                return true;
            }
        } else {
            $stmt->close();
        }
        
        return false;
    }

    /**
     * Check if booking is in accepted status (not yet in transit)
     */
    private function isBookingInAcceptedStatus($booking_id) {
        $query = "SELECT status FROM tricycle_bookings WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        return $booking && $booking['status'] === 'accepted';
    }

    /**
     * Complete booking and release driver for next booking
     */
    public function completeBooking($booking_id) {
        $updateQuery = "UPDATE tricycle_bookings SET status = 'completed' WHERE id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param("i", $booking_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }

    /**
     * Get next pending booking in queue
     */
    public function getNextPendingBooking() {
        $query = "SELECT b.id, b.user_id, b.pickup_location, b.dropoff_location, b.booking_time 
                  FROM tricycle_bookings b
                  WHERE b.status = 'pending' 
                  AND b.driver_id IS NULL
                  AND b.queue_assigned = 0
                  ORDER BY b.booking_time ASC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();
        
        return $booking;
    }

    /**
     * Assign driver to booking
     */
    public function assignDriverToBooking($driver_id, $booking_id) {
        $query = "UPDATE tricycle_bookings 
                  SET driver_id = ?, 
                      status = 'accepted', 
                      queue_assigned = 1, 
                      assigned_driver_id = ?
                  WHERE id = ? AND queue_assigned = 0";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $driver_id, $driver_id, $booking_id);
        
        $success = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        
        return $success;
    }

    /**
     * Check if driver has an active booking
     */
    public function driverHasActiveBooking($driver_id) {
        $query = "SELECT COUNT(*) as count FROM tricycle_bookings 
                  WHERE driver_id = ? 
                  AND (status = 'accepted' OR status = 'in-transit')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data['count'] > 0;
    }
}
?>
