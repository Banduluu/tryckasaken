<?php
/**
 * Booking Service - Handles passenger booking operations
 * Separates business logic from API endpoints
 */

class BookingService {
    private $conn;
    private $user_id;

    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    /**
     * Get current booking status with driver info
     */
    public function getBookingStatus() {
        $booking_query = "SELECT b.*, 
                          u.name as driver_name, 
                          u.phone as driver_phone,
                          u.tricycle_info as vehicle_info
                          FROM tricycle_bookings b
                          LEFT JOIN users u ON b.driver_id = u.user_id
                          WHERE b.user_id = ? 
                          AND LOWER(b.status) NOT IN ('completed', 'cancelled', 'declined')
                          ORDER BY b.booking_time DESC 
                          LIMIT 1";
        $stmt = $this->conn->prepare($booking_query);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return [
            'success' => true,
            'has_booking' => $booking ? true : false,
            'booking' => $booking
        ];
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking($booking_id) {
        if ($booking_id <= 0) {
            return ['success' => false, 'message' => 'Invalid booking ID'];
        }

        // Check if booking exists and belongs to user
        $booking = $this->getBookingById($booking_id);
        
        if (!$booking) {
            return ['success' => false, 'message' => 'Booking not found'];
        }

        // Check if booking is cancellable
        $status = strtolower($booking['status']);
        if ($status !== 'pending' && !($status === 'accepted' && !$booking['driver_id'])) {
            return ['success' => false, 'message' => 'Cannot cancel this booking'];
        }

        // Cancel the booking
        $cancel_stmt = $this->conn->prepare("UPDATE tricycle_bookings SET status = 'cancelled' WHERE id = ?");
        $cancel_stmt->bind_param("i", $booking_id);
        $success = $cancel_stmt->execute();
        $cancel_stmt->close();
        
        return $success
            ? ['success' => true, 'message' => 'Booking cancelled successfully!']
            : ['success' => false, 'message' => 'Failed to cancel booking'];
    }

    /**
     * Create a new booking
     */
    public function createBooking($data) {
        // Validate required fields
        $required = ['pickup_location', 'dropoff_location', 'booking_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Missing required field: $field"];
            }
        }

        // Check for existing active booking
        if ($this->hasActiveBooking()) {
            return ['success' => false, 'message' => 'You already have an active booking'];
        }

        // Insert booking
        $sql = "INSERT INTO tricycle_bookings (user_id, pickup_location, dropoff_location, booking_time, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isss", 
            $this->user_id, 
            $data['pickup_location'], 
            $data['dropoff_location'], 
            $data['booking_time']
        );
        
        $success = $stmt->execute();
        $booking_id = $stmt->insert_id;
        $stmt->close();
        
        return $success
            ? ['success' => true, 'message' => 'Booking created successfully!', 'booking_id' => $booking_id]
            : ['success' => false, 'message' => 'Failed to create booking'];
    }

    /**
     * Get booking by ID (must belong to user)
     */
    private function getBookingById($booking_id) {
        $check_stmt = $this->conn->prepare("SELECT * FROM tricycle_bookings WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $booking_id, $this->user_id);
        $check_stmt->execute();
        $booking = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        return $booking;
    }

    /**
     * Check if user has an active booking
     */
    private function hasActiveBooking() {
        $check = "SELECT COUNT(*) as count FROM tricycle_bookings 
                  WHERE user_id = ? AND LOWER(status) NOT IN ('completed', 'cancelled', 'declined')";
        $stmt = $this->conn->prepare($check);
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] > 0;
    }

    /**
     * Get booking history
     */
    public function getBookingHistory($limit = 10) {
        $sql = "SELECT b.*, 
                u.name as driver_name
                FROM tricycle_bookings b
                LEFT JOIN users u ON b.driver_id = u.user_id
                WHERE b.user_id = ?
                ORDER BY b.booking_time DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $this->user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return [
            'success' => true,
            'bookings' => $bookings,
            'count' => count($bookings)
        ];
    }
}
