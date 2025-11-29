<?php
session_start();
require_once '../../config/Database.php';

// Check if user is logged in as driver
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Check driver verification status and online status
$verification_query = "SELECT verification_status, is_online FROM rfid_drivers WHERE user_id = ?";
$stmt = $conn->prepare($verification_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$verification_result = $stmt->get_result()->fetch_assoc();
$verification_status = $verification_result ? $verification_result['verification_status'] : 'pending';
$is_online = $verification_result ? $verification_result['is_online'] : 1;
$stmt->close();

$show_verified_notification = false;
if ($verification_status === 'verified') {
    if (!isset($_SESSION['verification_notified'])) {
        $show_verified_notification = true;
        $_SESSION['verification_notified'] = true;
    }
}

// Get driver profile information
$driver_profile_query = "SELECT u.name, u.email, u.phone, d.picture_path, d.verification_status 
                         FROM users u 
                         LEFT JOIN rfid_drivers d ON u.user_id = d.user_id 
                         WHERE u.user_id = ?";
$stmt = $conn->prepare($driver_profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$driver_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if driver has an active trip
$active_trip_query = "SELECT COUNT(*) as has_active FROM tricycle_bookings WHERE driver_id = ? AND LOWER(status) = 'accepted'";
$stmt = $conn->prepare($active_trip_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_result = $stmt->get_result()->fetch_assoc();
$has_active_trip = $active_result['has_active'] > 0;
$stmt->close();

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Driver Dashboard - TrycKaSaken</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Glass Design CSS -->
  <link rel="stylesheet" href="../../public/css/glass.css">
  <link rel="stylesheet" href="../../public/css/drivers-login-form.css">
</head>
<body>

<nav class="navbar">
  <div class="container">
    <!-- Profile Card -->
    <div class="profile-card" href="#">
      <div class="profile-avatar">
        <?php if ($driver_profile && $driver_profile['picture_path'] && file_exists('../../' . $driver_profile['picture_path'])): ?>
          <img src="../../<?= htmlspecialchars($driver_profile['picture_path']) ?>" alt="Profile" class="profile-avatar">
        <?php else: ?>
          <?= strtoupper(substr($user_name, 0, 1)) ?>
        <?php endif; ?>
        <div class="verification-badge <?= $verification_status ?>">
          <?php if ($verification_status === 'verified'): ?>
            <i class="bi bi-check"></i>
          <?php elseif ($verification_status === 'pending'): ?>
            <i class="bi bi-clock"></i>
          <?php else: ?>
            <i class="bi bi-x"></i>
          <?php endif; ?>
        </div>
      </div>
      <div class="profile-info">
        <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
        <div class="profile-status">
          <?= ucfirst($verification_status) ?> Driver
        </div>
      </div>
    </div>
    
    <!-- Online/Offline Toggle -->
    <?php if ($verification_status === 'pending'): ?>
      <div class="offline-toggle pending-verification" style="cursor: default;">
        <i class="bi bi-clock-fill"></i>
        <span>Pending Verification</span>
      </div>
    <?php elseif ($verification_status === 'rejected'): ?>
      <div class="offline-toggle account-rejected" style="cursor: default;">
        <i class="bi bi-x-circle-fill"></i>
        <span>Account Rejected</span>
      </div>
    <?php elseif ($has_active_trip): ?>
      <div class="offline-toggle on-trip" style="cursor: default;">
        <i class="bi bi-circle-fill"></i>
        <span>On Trip</span>
      </div>
    <?php else: ?>
      <div class="offline-toggle <?= $is_online ? 'online' : 'offline' ?>" style="cursor: default;">
        <i class="bi bi-circle-fill"></i>
        <span><?= $is_online ? 'Online' : 'Offline' ?></span>
      </div>
    <?php endif; ?>
    
    <button class="navbar-toggler" onclick="toggleMenu()">
      <i class="bi bi-list"></i>
    </button>
    <ul class="navbar-nav" id="navMenu">
      <li class="nav-item">
        <a class="nav-link" href="../../pages/driver/login-form.php">
          <i class="bi bi-house"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <?php if ($verification_status === 'verified' && $is_online): ?>
          <a href="../../pages/driver/trydashboard.php" class="btn-request">
            <i class="bi bi-<?= $has_active_trip ? 'check-circle' : 'card-list' ?>"></i> 
            <?= $has_active_trip ? 'Complete Trip' : 'View Requests' ?>
          </a>
        <?php else: ?>
          <a href="#" class="btn-request" style="opacity: 0.5; cursor: not-allowed;" onclick="alert('<?= !$is_online ? 'You must be online to view requests. Please toggle your status to Online first.' : 'View Requests is locked. Please wait for verification approval.' ?>'); return false;">
            <i class="bi bi-lock"></i> View Requests
          </a>
        <?php endif; ?>
      </li>
      <li class="nav-item">
        <?php if ($verification_status !== 'verified'): ?>
          <a href="#" class="nav-link" style="opacity: 0.5; cursor: not-allowed;" onclick="alert('Trip History is locked. Please wait for verification approval.'); return false;">
            <i class="bi bi-lock"></i> Trip History
          </a>
        <?php else: ?>
          <a href="trips-history.php" class="nav-link">
            <i class="bi bi-clock-history"></i> Trip History
          </a>
        <?php endif; ?>
      </li>
      <li class="nav-item">
        <a href="report.php" class="nav-link">
          <i class="bi bi-flag"></i> Report
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
  <!-- Success/Error Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle"></i> <?= $_SESSION['success_message']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle"></i> <?= $_SESSION['error_message']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <!-- Enhanced Verification Status -->
  <?php if ($show_verified_notification && $verification_status === 'verified'): ?>
    <div class="verification-status verified" id="verificationNotif">
      <div class="d-flex align-items-center">
        <i class="bi bi-check-circle" style="font-size: 2rem; color: #10b981; margin-right: 16px;"></i>
        <div>
          <h5 class="mb-1" style="color: #10b981;">Account Verified! 🎉</h5>
          <p class="mb-0">Your driver account has been verified. You can now accept ride requests and start earning!</p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Enhanced Welcome Section -->
  <div class="welcome-section">
    <h2>Welcome <?= htmlspecialchars($user_name); ?>!</h2>
    <p>
      <?php if ($verification_status === 'verified'): ?>
        Start accepting ride requests. Your passengers are waiting!
      <?php elseif ($verification_status === 'pending'): ?>
        Your account is currently under review. Once verified, you can start accepting rides.
      <?php else: ?>
        Your application was rejected. Please contact support for further assistance.
      <?php endif; ?>
    </p>
    <?php if ($has_active_trip): ?>
      <div class="mt-3 p-3" style="background: rgba(52, 152, 219, 0.1); border-radius: 12px; border-left: 4px solid #3498db;">
        <i class="bi bi-info-circle-fill me-2" style="color: #3498db;"></i>
        <strong>Active Trip:</strong> You have an active trip. Please complete it before accepting new requests.
      </div>
    <?php endif; ?>
    
    <?php if ($verification_status === 'verified' && !$is_online && !$has_active_trip): ?>
      <div class="mt-3 p-3" style="background: rgba(156, 163, 175, 0.1); border-radius: 12px; border-left: 4px solid #6b7280;">
        <i class="bi bi-wifi-off me-2" style="color: #6b7280;"></i>
        <strong>You're Offline:</strong> Tap the card to make your status "Online" to start viewing and accepting ride requests.
      </div>
    <?php endif; ?>
    
    <?php if ($verification_status === 'pending'): ?>
      <div class="mt-3 p-3" style="background: rgba(251, 191, 36, 0.1); border-radius: 12px; border-left: 4px solid #f59e0b;">
        <i class="bi bi-clock-fill me-2" style="color: #f59e0b;"></i>
        <strong>Status:</strong> Online status toggle is disabled until your account is verified.
      </div>
    <?php elseif ($verification_status === 'rejected'): ?>
      <div class="mt-3 p-3" style="background: rgba(239, 68, 68, 0.1); border-radius: 12px; border-left: 4px solid #ef4444;">
        <i class="bi bi-exclamation-triangle-fill me-2" style="color: #ef4444;"></i>
        <strong>Account Rejected:</strong> Your verification was rejected. All features are restricted. Please contact support.
      </div>
    <?php endif; ?>
  </div>

  <!-- Enhanced Services Grid -->
  <div class="services-grid">
    <div class="service-card <?= ($verification_status !== 'verified' || (!$is_online && !$has_active_trip)) ? 'opacity-50' : '' ?>">
      <div class="service-icon <?= ($has_active_trip ? 'bg-info' : ((!$is_online && $verification_status === 'verified') ? 'bg-secondary' : '')) ?>">
        <i class="bi bi-<?= $has_active_trip ? 'check-circle' : 'card-list' ?>"></i>
      </div>
      <h3><?= $has_active_trip ? 'Complete Trip' : 'View Requests' ?></h3>
      <p>
        <?php if ($has_active_trip): ?>
          Complete your current active trip
        <?php elseif ($verification_status !== 'verified'): ?>
          Verification required to view requests
        <?php elseif (!$is_online): ?>
          You must be online to view and accept requests
        <?php else: ?>
          Check available booking requests from passengers
        <?php endif; ?>
      </p>
      <?php if ($verification_status === 'verified' && ($is_online || $has_active_trip)): ?>
        <a href="../../pages/driver/trydashboard.php" class="service-btn">
          <?= $has_active_trip ? 'Complete Trip' : 'View Requests' ?>
        </a>
      <?php elseif ($verification_status === 'verified' && !$is_online): ?>
        <button class="service-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
          <i class="bi bi-wifi-off"></i> Go Online to View Requests
        </button>
      <?php else: ?>
        <button class="service-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
          <i class="bi bi-lock"></i> Locked - Verification Required
        </button>
      <?php endif; ?>
    </div>

    <div class="service-card <?= $verification_status !== 'verified' ? 'opacity-50' : '' ?>">
      <div class="service-icon <?= $verification_status !== 'verified' ? 'bg-secondary' : '' ?>">
        <i class="bi bi-clock-history"></i>
      </div>
      <h3>Trip History</h3>
      <p>
        <?php if ($verification_status === 'rejected'): ?>
          Access restricted - verification rejected
        <?php elseif ($verification_status === 'pending'): ?>
          Verification required to access trip history
        <?php else: ?>
          View your completed trips and performance summary
        <?php endif; ?>
      </p>
      <?php if ($verification_status === 'verified'): ?>
        <a href="trips-history.php" class="service-btn">View History</a>
      <?php else: ?>
        <button class="service-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
          <i class="bi bi-lock"></i> Locked - Verification Required
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Enhanced JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMenu() {
  const menu = document.getElementById('navMenu');
  menu.classList.toggle('show');
}

// Initialize animations and interactions
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
  document.querySelectorAll('.service-card, .verification-status').forEach(el => {
    observer.observe(el);
  });

  // Add loading states to buttons
  document.querySelectorAll('.service-btn:not(.disabled)').forEach(button => {
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

  // Auto-dismiss verification status after some time (only for success)
  <?php if ($show_verified_notification && $verification_status === 'verified'): ?>
  setTimeout(() => {
    const notif = document.getElementById('verificationNotif');
    if (notif) {
      notif.style.transition = 'all 0.5s ease';
      notif.style.opacity = '0';
      notif.style.transform = 'translateY(-20px)';
      setTimeout(() => {
        if (notif.parentNode) {
          notif.remove();
        }
      }, 500);
    }
  }, 8000); // Remove after 8 seconds
  <?php endif; ?>
});

// Driver status toggle functionality
function toggleDriverStatus() {
  const toggle = document.getElementById('statusToggle');
  const icon = document.getElementById('statusIcon');
  const text = document.getElementById('statusText');
  
  const isOnline = toggle.classList.contains('online');
  const confirmMessage = isOnline 
    ? 'Going offline will prevent you from receiving new trip requests. Continue?' 
    : 'Go online to start receiving trip requests?';
  
  if (!confirm(confirmMessage)) {
    return;
  }
  
  // Disable button during request
  toggle.disabled = true;
  toggle.style.opacity = '0.6';
  text.textContent = 'Updating...';
  
  // Send AJAX request
  fetch('status-update-handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update UI
      if (data.is_online) {
        toggle.classList.remove('offline');
        toggle.classList.add('online');
        text.textContent = 'Online';
      } else {
        toggle.classList.remove('online');
        toggle.classList.add('offline');
        text.textContent = 'Offline';
      }
      showStatusMessage(data.message, 'success');
    } else {
      showStatusMessage(data.message, 'error');
    }
  })
  .catch(error => {
    showStatusMessage('Failed to update status. Please try again.', 'error');
    console.error('Error:', error);
  })
  .finally(() => {
    // Re-enable button
    toggle.disabled = false;
    toggle.style.opacity = '1';
  });
}

function showStatusMessage(message, type) {
  // Create toast notification
  const toast = document.createElement('div');
  toast.className = `alert alert-${type === 'success' ? 'success' : 'warning'} alert-dismissible fade show position-fixed`;
  toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
  toast.innerHTML = `
    <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  
  document.body.appendChild(toast);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    if (toast.parentNode) {
      toast.remove();
    }
  }, 5000);
}
</script>

<div class="attendance-section mt-4">
  <div class="glass-card">
    <h3 class="mb-3">
      <i class="bi bi-clock-history"></i> Recent Attendance
    </h3>
    
    <div id="attendanceRecords">
      <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.attendance-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
}

.glass-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.attendance-record {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  margin-bottom: 12px;
  background: rgba(255, 255, 255, 0.7);
  border-radius: 12px;
  border-left: 4px solid;
  transition: all 0.3s ease;
}

.attendance-record:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.attendance-record.clock-in {
  border-left-color: #10b981;
}

.attendance-record.clock-out {
  border-left-color: #ef4444;
}

.attendance-info {
  flex: 1;
}

.attendance-time {
  font-size: 0.875rem;
  color: #6b7280;
  margin-top: 4px;
}

.attendance-badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
}

.badge-clock-in {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.badge-clock-out {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.no-records {
  text-align: center;
  padding: 40px 20px;
  color: #9ca3af;
}
</style>

<script>
// Fetch attendance records
function loadAttendanceRecords() {
  const driverId = <?= $user_id ?>; // From PHP session
  
  fetch(`../../pages/driver/rfid-attendance-handler.php?driver_id=${driverId}&limit=5`)
    .then(response => response.json())
    .then(data => {
      const container = document.getElementById('attendanceRecords');
      
      if (data.success && data.records.length > 0) {
        let html = '';
        
        data.records.forEach(record => {
          const action = record.action;
          const actionClass = action === 'online' ? 'clock-in' : 'clock-out';
          const badgeClass = action === 'online' ? 'badge-clock-in' : 'badge-clock-out';
          const actionText = action === 'online' ? 'Clocked In' : 'Clocked Out';
          const icon = action === 'online' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right';
          
          const date = new Date(record.timestamp);
          const formattedTime = date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
          });
          
          html += `
            <div class="attendance-record ${actionClass}">
              <div class="attendance-info">
                <div class="d-flex align-items-center">
                  <i class="bi ${icon} me-2" style="font-size: 1.5rem;"></i>
                  <div>
                    <strong>${record.driver_name}</strong>
                    <div class="attendance-time">${formattedTime}</div>
                  </div>
                </div>
              </div>
              <span class="attendance-badge ${badgeClass}">${actionText}</span>
            </div>
          `;
        });
        
        container.innerHTML = html;
      } else {
        container.innerHTML = `
          <div class="no-records">
            <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
            <p class="mt-3">No attendance records yet</p>
            <small>Tap your RFID card to clock in/out</small>
          </div>
        `;
      }
    })
    .catch(error => {
      console.error('Error loading attendance:', error);
      document.getElementById('attendanceRecords').innerHTML = `
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i> Failed to load attendance records
        </div>
      `;
    });
}

// Auto-refresh attendance - check every 5 seconds for new records
let attendanceRefreshInterval = setInterval(loadAttendanceRecords, 5000);

// Load on page load
document.addEventListener('DOMContentLoaded', loadAttendanceRecords);

// Refresh attendance when page becomes visible (user switches back to tab)
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    loadAttendanceRecords();
  }
});

// Real-time notification when RFID is tapped (optional - requires WebSocket or polling)
function checkNewAttendance() {
  // This can be enhanced with WebSocket for real-time updates
  loadAttendanceRecords();
}
</script>

</body>
</html>