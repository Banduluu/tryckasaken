<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$bookingId) {
    header("Location: bookings-list.php");
    exit();
}

// Get booking details with user info
$query = "SELECT b.*, p.name as passenger_name, p.email as passenger_email, p.phone as passenger_phone,
                 d.name as driver_name, d.email as driver_email, d.phone as driver_phone,
                 d.license_number, d.tricycle_info
          FROM tricycle_bookings b 
          LEFT JOIN users p ON b.user_id = p.user_id 
          LEFT JOIN users d ON b.driver_id = d.user_id
          WHERE b.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    header("Location: bookings-list.php");
    exit();
}

renderAdminHeader("Booking Details #" . $bookingId, "bookings");
?>
<link rel="stylesheet" href="../../public/css/booking-details.css">

<!-- Booking Header -->
<div class="content-card">
    <div class="booking-header">
        <div class="booking-title">
            <h3><i class="bi bi-calendar-check"></i> Booking #<?= $booking['id'] ?></h3>
            <span class="status-badge status-<?= $booking['status'] ?>">
                <?= ucfirst($booking['status']) ?>
            </span>
        </div>
        <div class="booking-actions">
            <a href="bookings-list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Bookings
            </a>
            <?php if ($booking['status'] === 'pending' && !$booking['driver_id']): ?>
                <a href="booking-assign-driver.php?id=<?= $booking['id'] ?>" class="btn btn-info">
                    <i class="bi bi-person-plus"></i> Assign Driver
                </a>
            <?php endif; ?>
            <?php if (in_array($booking['status'], ['pending', 'accepted'])): ?>
                <a href="booking-cancel-handler.php?id=<?= $booking['id'] ?>" class="btn btn-danger" 
                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                    <i class="bi bi-x-circle"></i> Cancel Booking
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Booking Details -->
<div class="row g-4">
    <!-- Trip Information -->
    <div class="col-md-6">
        <div class="content-card">
            <h5><i class="bi bi-geo-alt"></i> Trip Information</h5>
            <div class="detail-group">
                <div class="detail-item">
                    <label>Pickup Location:</label>
                    <div class="detail-value">
                        <i class="bi bi-geo-alt text-success"></i>
                        <?= htmlspecialchars($booking['location']) ?>
                    </div>
                </div>
                <div class="detail-item">
                    <label>Destination:</label>
                    <div class="detail-value">
                        <i class="bi bi-flag text-primary"></i>
                        <?= htmlspecialchars($booking['destination']) ?>
                    </div>
                </div>
                <div class="detail-item">
                    <label>Booking Date & Time:</label>
                    <div class="detail-value">
                        <i class="bi bi-calendar"></i>
                        <?= date('F d, Y \a\t g:i A', strtotime($booking['booking_time'])) ?>
                    </div>
                </div>
                <?php if ($booking['notes']): ?>
                <div class="detail-item">
                    <label>Special Notes:</label>
                    <div class="detail-value">
                        <i class="bi bi-chat-text"></i>
                        <?= htmlspecialchars($booking['notes']) ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($booking['fare']): ?>
                <div class="detail-item">
                    <label>Fare:</label>
                    <div class="detail-value">
                        <i class="bi bi-currency-dollar"></i>
                        ₱<?= number_format($booking['fare'], 2) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Timeline -->
    <div class="col-md-6">
        <div class="content-card">
            <h5><i class="bi bi-clock-history"></i> Status Timeline</h5>
            <div class="timeline">
                <div class="timeline-item <?= in_array($booking['status'], ['pending', 'accepted', 'completed', 'cancelled']) ? 'completed' : '' ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <h6>Booking Created</h6>
                        <small><?= date('M d, Y g:i A', strtotime($booking['booking_time'])) ?></small>
                    </div>
                </div>
                
                <div class="timeline-item <?= in_array($booking['status'], ['accepted', 'completed']) ? 'completed' : ($booking['status'] === 'pending' ? 'current' : '') ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <h6><?= $booking['driver_id'] ? 'Driver Assigned' : 'Awaiting Driver' ?></h6>
                        <small><?= $booking['status'] === 'pending' ? 'In progress...' : 'Completed' ?></small>
                    </div>
                </div>
                
                <div class="timeline-item <?= $booking['status'] === 'completed' ? 'completed' : ($booking['status'] === 'accepted' ? 'current' : '') ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <h6>Trip <?= $booking['status'] === 'completed' ? 'Completed' : ($booking['status'] === 'accepted' ? 'In Progress' : 'Pending') ?></h6>
                        <small><?= $booking['status'] === 'completed' ? 'Trip finished successfully' : ($booking['status'] === 'accepted' ? 'Driver en route' : 'Waiting for acceptance') ?></small>
                    </div>
                </div>
                
                <?php if ($booking['status'] === 'cancelled'): ?>
                <div class="timeline-item cancelled">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <h6>Booking Cancelled</h6>
                        <small>Trip was cancelled</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Passenger & Driver Info -->
<div class="row g-4 mt-2">
    <!-- Passenger Information -->
    <div class="col-md-6">
        <div class="content-card">
            <h5><i class="bi bi-person"></i> Passenger Information</h5>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <h6><?= htmlspecialchars($booking['passenger_name']) ?></h6>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="bi bi-envelope"></i>
                            <a href="mailto:<?= htmlspecialchars($booking['passenger_email']) ?>">
                                <?= htmlspecialchars($booking['passenger_email']) ?>
                            </a>
                        </div>
                        <div class="contact-item">
                            <i class="bi bi-phone"></i>
                            <a href="tel:<?= htmlspecialchars($booking['passenger_phone']) ?>">
                                <?= htmlspecialchars($booking['passenger_phone']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="user-actions">
                        <a href="user-details.php?id=<?= $booking['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i> View Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Driver Information -->
    <div class="col-md-6">
        <div class="content-card">
            <h5><i class="bi bi-car-front"></i> Driver Information</h5>
            <?php if ($booking['driver_id']): ?>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="user-details">
                        <h6><?= htmlspecialchars($booking['driver_name']) ?></h6>
                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="bi bi-envelope"></i>
                                <a href="mailto:<?= htmlspecialchars($booking['driver_email']) ?>">
                                    <?= htmlspecialchars($booking['driver_email']) ?>
                                </a>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-phone"></i>
                                <a href="tel:<?= htmlspecialchars($booking['driver_phone']) ?>">
                                    <?= htmlspecialchars($booking['driver_phone']) ?>
                                </a>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-card-text"></i>
                                License: <?= htmlspecialchars($booking['license_number']) ?>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-truck"></i>
                                <?= htmlspecialchars($booking['tricycle_info']) ?>
                            </div>
                        </div>
                        <div class="user-actions">
                            <a href="user-details.php?id=<?= $booking['driver_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> View Profile
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-person-dash"></i>
                    <h6>No Driver Assigned</h6>
                    <p>This booking is still waiting for a driver assignment.</p>
                    <?php if ($booking['status'] === 'pending'): ?>
                        <a href="booking-assign-driver.php?id=<?= $booking['id'] ?>" class="btn btn-custom">
                            <i class="bi bi-person-plus"></i> Assign Driver
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>