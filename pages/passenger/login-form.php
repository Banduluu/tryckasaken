<?php
session_start();
require_once '../../config/Database.php';

// Check if user is logged in as passenger
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'passenger') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Function to convert coordinates to location name using reverse geocoding
function getLocationFromCoordinates($lat, $lng) {
    try {
        // Use Nominatim (OpenStreetMap) for reverse geocoding
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'TrycKaSaken/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return "Lat: {$lat}, Lng: {$lng}";
        }
        
        $data = json_decode($response, true);
        if ($data && isset($data['address'])) {
            $address = $data['address'];
            
            // Build location string from address components
            $locationParts = [];
            
            if (isset($address['road'])) {
                $locationParts[] = $address['road'];
            }
            if (isset($address['village'])) {
                $locationParts[] = $address['village'];
            }
            if (isset($address['suburb'])) {
                $locationParts[] = $address['suburb'];
            }
            if (isset($address['city'])) {
                $locationParts[] = $address['city'];
            }
            
            if (!empty($locationParts)) {
                return implode(', ', $locationParts);
            }
        }
        
        return "Lat: {$lat}, Lng: {$lng}";
    } catch (Exception $e) {
        return "Lat: {$lat}, Lng: {$lng}";
    }
}

// Get current active booking with driver info (pending or accepted, not completed/cancelled/declined)
$booking_query = "SELECT b.*, 
                  u.name as driver_name, 
                  u.phone as driver_phone,
                  d.tricycle_info as vehicle_info
                  FROM tricycle_bookings b
                  LEFT JOIN rfid_drivers d ON b.driver_id = d.driver_id
                  LEFT JOIN users u ON d.user_id = u.user_id
                  WHERE b.user_id = ? 
                  AND LOWER(b.status) NOT IN ('completed', 'cancelled', 'declined')
                  ORDER BY b.booking_time DESC 
                  LIMIT 1";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Don't close connection yet, we need it for rendering
// $conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Passenger Dashboard - TrycKaSaken</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/passenger-login-form.css">
</head>
<body>

<nav class="navbar">
  <div class="container">
    <a class="navbar-brand" href="#">
      <i class="bi bi-truck me-2"></i>
      TrycKaSaken
    </a>
    <button class="navbar-toggler" onclick="toggleMenu()">
      <i class="bi bi-list"></i>
    </button>
    <ul class="navbar-nav" id="navMenu">
      <li class="nav-item">
        <a class="nav-link" href="../../pages/passenger/login-form.php">
          <i class="bi bi-house"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a href="../../pages/passenger/dashboard-lipa.php" class="btn-request">
          <i class="bi bi-plus-circle"></i> Book Ride
        </a>
      </li>
      <li class="nav-item">
        <a href="../../pages/passenger/trips-history.php" class="nav-link">
          <i class="bi bi-clock-history"></i> Trip History
        </a>
      </li>
      <li class="nav-item">
        <a href="../../pages/auth/logout-handler.php" class="btn btn-danger btn-sm">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </li>
    </ul>
  </div>
</nav>

<div class="page-content">
  <!-- Enhanced Welcome Section -->
  <div class="welcome-section">
    <h2>Hello <?= htmlspecialchars($user_name); ?>!</h2>
    <p>Book a tricycle and get to your destination safely and quickly.</p>
  </div>

  <!-- Current Booking Status Section -->
  <div class="current-booking-section" id="currentBookingSection">
    <h3>
      <i class="bi bi-card-checklist"></i>
      Current Booking Status
    </h3>
    
    <div id="bookingContent">
    <?php if ($current_booking): ?>
      <div class="booking-info-grid">
        <div class="booking-info-item">
          <i class="bi bi-hash" style="color: #86efac;"></i>
          <div>
            <div class="booking-info-label">Booking ID</div>
            <div class="booking-info-value">#<?= htmlspecialchars($current_booking['id']); ?></div>
          </div>
        </div>

        <div class="booking-info-item">
          <i class="bi bi-geo-alt-fill" style="color: #86efac;"></i>
          <div>
            <div class="booking-info-label">Pickup Location</div>
            <div class="booking-info-value"><?= htmlspecialchars(
              (!empty($current_booking['pickup_lat']) && !empty($current_booking['pickup_lng'])) 
                ? getLocationFromCoordinates($current_booking['pickup_lat'], $current_booking['pickup_lng'])
                : $current_booking['location']
            ); ?></div>
          </div>
        </div>

        <div class="booking-info-item">
          <i class="bi bi-flag-fill" style="color: #86efac;"></i>
          <div>
            <div class="booking-info-label">Destination</div>
            <div class="booking-info-value"><?= htmlspecialchars(
              (!empty($current_booking['dest_lat']) && !empty($current_booking['dest_lng'])) 
                ? getLocationFromCoordinates($current_booking['dest_lat'], $current_booking['dest_lng'])
                : $current_booking['destination']
            ); ?></div>
          </div>
        </div>

        <div class="booking-info-item">
          <i class="bi bi-calendar-event" style="color: #86efac;"></i>
          <div>
            <div class="booking-info-label">Booking Time</div>
            <div class="booking-info-value"><?= date('M d, Y h:i A', strtotime($current_booking['booking_time'])); ?></div>
          </div>
        </div>
      </div>

      <?php if (!empty($current_booking['driver_id'])): ?>
        <!-- Driver Information Section -->
        <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid rgba(134, 239, 172, 0.2);">
          <h4 style="color: #86efac; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <i class="bi bi-person-badge"></i>
            Driver Information
          </h4>
          <div class="booking-info-grid">
            <div class="booking-info-item">
              <i class="bi bi-person-circle" style="color: #86efac;"></i>
              <div>
                <div class="booking-info-label">Driver Name</div>
                <div class="booking-info-value"><?= htmlspecialchars($current_booking['driver_name'] ?? 'N/A'); ?></div>
              </div>
            </div>

            <div class="booking-info-item">
              <i class="bi bi-telephone-fill" style="color: #86efac;"></i>
              <div>
                <div class="booking-info-label">Phone Number</div>
                <div class="booking-info-value">
                  <?php if (!empty($current_booking['driver_phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($current_booking['driver_phone']); ?>" style="color: #86efac; text-decoration: none; font-weight: 600;">
                      <?= htmlspecialchars($current_booking['driver_phone']); ?>
                    </a>
                  <?php else: ?>
                    N/A
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <?php if (!empty($current_booking['vehicle_info'])): ?>
            <div class="booking-info-item">
              <i class="bi bi-truck" style="color: #86efac;"></i>
              <div>
                <div class="booking-info-label">Vehicle Info</div>
                <div class="booking-info-value"><?= htmlspecialchars($current_booking['vehicle_info']); ?></div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div>
        <?php 
        $status = strtolower($current_booking['status']);
        $status_class = 'status-pending';
        $status_icon = 'bi-hourglass-split';
        $status_text = htmlspecialchars(ucfirst($status));
        
        if ($status === 'accepted') {
          $status_class = 'status-accepted';
          $status_icon = 'bi-check-circle-fill';
          $status_text = 'On the way to the location';
        }
        ?>
        <span class="booking-status-badge <?= $status_class; ?>">
          <i class="bi <?= $status_icon; ?>"></i>
          <?= $status_text; ?>
        </span>
      </div>

      <div class="booking-actions">
        <a href="../../pages/passenger/trips-history.php" class="btn-view-details">
          <i class="bi bi-eye"></i>
          View
        </a>
      </div>

    <?php else: ?>
      <div class="no-booking-message">
        <i class="bi bi-inbox"></i>
        <h4>No Active Booking</h4>
        <p>You don't have any active bookings at the moment.</p>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <!-- Enhanced Services Grid -->
  <div class="services-grid">
    <div class="service-card <?= $current_booking ? 'disabled' : ''; ?>">
      <div class="service-icon">
        <i class="bi bi-calendar-check"></i>
      </div>
      <h3>Book a Ride</h3>
      <?php if ($current_booking): ?>
        <p>You have an active booking. Please wait for it to be completed before creating a new one.</p>
        <span class="service-btn disabled">
          <i class="bi bi-x-circle me-2"></i>
          Booking In Progress
        </span>
      <?php else: ?>
        <p>Find a tricycle near you and book your ride instantly. Safe, fast, and convenient transportation.</p>
        <a href="../../pages/passenger/dashboard-lipa.php" class="service-btn">
          <i class="bi bi-plus-circle me-2"></i>
          Book Now
        </a>
      <?php endif; ?>
    </div>

    <div class="service-card">
      <div class="service-icon">
        <i class="bi bi-clock-history"></i>
      </div>
      <h3>Trip History</h3>
      <p>View your complete booking history, track your rides, and manage your transportation records.</p>
      <a href="../../pages/passenger/trips-history.php" class="service-btn">
        <i class="bi bi-eye me-2"></i>
        View History
      </a>
    </div>

    <div class="service-card">
      <div class="service-icon">
        <i class="bi bi-flag"></i>
      </div>
      <h3>Report an Issue</h3>
      <p>Report any issues or concerns about your rides, drivers, or our service.</p>
      <a href="../../pages/passenger/passenger-report.php" class="service-btn">
        <i class="bi bi-exclamation-circle me-2"></i>
        Submit Report
      </a>
    </div>
  </div>
</div>

<!-- Enhanced JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// AJAX: Check booking status periodically
let previousStatus = null;
let previousDriverId = null;

function checkBookingStatus() {
  fetch('api-booking-actions.php?action=get_booking_status')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.booking) {
        const booking = data.booking;
        const currentStatus = booking.status;
        const currentDriverId = booking.driver_id;
        
        // Check if status changed or driver was assigned
        if (previousStatus !== null) {
          if (previousStatus !== currentStatus) {
            // Status changed - show notification and reload
            showStatusNotification('Booking status updated to: ' + currentStatus.toUpperCase());
            setTimeout(() => window.location.reload(), 2000);
            return;
          }
          
          if (previousDriverId === null && currentDriverId !== null) {
            // Driver was assigned - show notification and reload
            showStatusNotification('A driver has been assigned to your booking!');
            setTimeout(() => window.location.reload(), 2000);
            return;
          }
        }
        
        previousStatus = currentStatus;
        previousDriverId = currentDriverId;
        
        // Update the booking display
        updateBookingDisplay(booking);
      } else if (!data.booking && previousStatus !== null) {
        // Booking was completed or cancelled - reload page
        showStatusNotification('Your booking has been updated!');
        setTimeout(() => window.location.reload(), 2000);
      }
    })
    .catch(error => {
      console.error('Status check error:', error);
    });
}

function updateBookingDisplay(booking) {
  const bookingContent = document.getElementById('bookingContent');
  if (!bookingContent) return;
  
  const status = booking.status.toLowerCase();
  const statusClass = status === 'accepted' ? 'status-accepted' : 'status-pending';
  const statusIcon = status === 'accepted' ? 'bi-check-circle-fill' : 'bi-hourglass-split';
  const statusText = status === 'accepted' ? 'On the way to the location' : status.charAt(0).toUpperCase() + status.slice(1);
  
  // Format booking time
  const bookingDate = new Date(booking.booking_time);
  const formattedDate = bookingDate.toLocaleString('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  });
  
  let driverHtml = '';
  if (booking.driver_id) {
    driverHtml = `
      <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid rgba(134, 239, 172, 0.2);">
        <h4 style="color: #86efac; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
          <i class="bi bi-person-badge"></i>
          Driver Information
        </h4>
        <div class="booking-info-grid">
          <div class="booking-info-item">
            <i class="bi bi-person-circle" style="color: #86efac;"></i>
            <div>
              <div class="booking-info-label">Driver Name</div>
              <div class="booking-info-value">${booking.driver_name || 'N/A'}</div>
            </div>
          </div>
          <div class="booking-info-item">
            <i class="bi bi-telephone-fill" style="color: #86efac;"></i>
            <div>
              <div class="booking-info-label">Phone Number</div>
              <div class="booking-info-value">
                ${booking.driver_phone ? `<a href="tel:${booking.driver_phone}" style="color: #86efac; text-decoration: none; font-weight: 600;">${booking.driver_phone}</a>` : 'N/A'}
              </div>
            </div>
          </div>
          ${booking.vehicle_info ? `
          <div class="booking-info-item">
            <i class="bi bi-truck" style="color: #86efac;"></i>
            <div>
              <div class="booking-info-label">Vehicle Info</div>
              <div class="booking-info-value">${booking.vehicle_info}</div>
            </div>
          </div>
          ` : ''}
        </div>
      </div>
    `;
  }
  
  bookingContent.innerHTML = `
    <div class="booking-info-grid">
      <div class="booking-info-item">
        <i class="bi bi-hash" style="color: #86efac;"></i>
        <div>
          <div class="booking-info-label">Booking ID</div>
          <div class="booking-info-value">#${booking.id}</div>
        </div>
      </div>
      <div class="booking-info-item">
        <i class="bi bi-geo-alt-fill" style="color: #86efac;"></i>
        <div>
          <div class="booking-info-label">Pickup Location</div>
          <div class="booking-info-value">${booking.location}</div>
        </div>
      </div>
      <div class="booking-info-item">
        <i class="bi bi-flag-fill" style="color: #86efac;"></i>
        <div>
          <div class="booking-info-label">Destination</div>
          <div class="booking-info-value">${booking.destination}</div>
        </div>
      </div>
      <div class="booking-info-item">
        <i class="bi bi-calendar-event" style="color: #86efac;"></i>
        <div>
          <div class="booking-info-label">Booking Time</div>
          <div class="booking-info-value">${formattedDate}</div>
        </div>
      </div>
    </div>
    ${driverHtml}
    <div>
      <span class="booking-status-badge ${statusClass}">
        <i class="bi ${statusIcon}"></i>
        ${statusText}
      </span>
    </div>
    <div class="booking-actions">
      <a href="../../pages/passenger/dashboard-lipa.php" class="btn-view-details">
        <i class="bi bi-eye"></i>
        View Full Details
      </a>
    </div>
  `;
}

function showStatusNotification(message) {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
    background: rgba(16, 185, 129, 0.95);
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease-out;
  `;
  notification.innerHTML = `
    <i class="bi bi-check-circle-fill" style="font-size: 1.2rem;"></i>
    <span>${message}</span>
  `;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Start checking booking status every 15 seconds
<?php if ($current_booking): ?>
setInterval(checkBookingStatus, 15000);
// Set initial status
previousStatus = '<?= strtolower($current_booking['status']); ?>';
previousDriverId = <?= !empty($current_booking['driver_id']) ? $current_booking['driver_id'] : 'null'; ?>;
<?php endif; ?>

function toggleMenu() {
  const menu = document.getElementById('navMenu');
  menu.classList.toggle('show');
}

// Initialize animations on page load
document.addEventListener('DOMContentLoaded', function() {
  // Animation observer for cards
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, observerOptions);
  
  // Observe all animated elements
  document.querySelectorAll('.stat-card, .service-card').forEach(el => {
    observer.observe(el);
  });

  // Add loading states to buttons
  document.querySelectorAll('.service-btn').forEach(button => {
    button.addEventListener('click', function(e) {
      if (!this.classList.contains('disabled')) {
        this.style.opacity = '0.8';
        this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Loading...';
      }
    });
  });

  // Add ripple effect to buttons
  document.querySelectorAll('.service-btn, .btn-request').forEach(button => {
    button.addEventListener('click', function(e) {
      if (this.classList.contains('disabled')) return;
      
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
      `;
      
      this.style.position = 'relative';
      this.style.overflow = 'hidden';
      this.appendChild(ripple);
      
      setTimeout(() => {
        if (ripple.parentNode) {
          ripple.remove();
        }
      }, 600);
    });
  });
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
  @keyframes ripple {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }
  @keyframes slideIn {
    from {
      transform: translateX(400px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(400px);
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);
</script>

</body>
</html>

<?php 
// Close database connection at the end
$database->closeConnection(); 
?>