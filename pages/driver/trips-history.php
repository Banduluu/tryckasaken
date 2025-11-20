<?php
session_start();
require_once '../../config/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['name'];

// Check driver verification status
$verification_query = "SELECT verification_status FROM drivers WHERE user_id = ?";
$stmt = $conn->prepare($verification_query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$verification_result = $stmt->get_result()->fetch_assoc();
$verification_status = $verification_result ? $verification_result['verification_status'] : 'pending';
$stmt->close();

// Block access for non-verified drivers
if ($verification_status !== 'verified') {
    $message = $verification_status === 'rejected' 
        ? 'Access denied. Your driver verification was rejected.' 
        : 'Access denied. Please wait for your verification to be approved.';
    $_SESSION['error_message'] = $message;
    header("Location: login-form.php");
    exit();
}

// Get driver's completed trips
$sql = "SELECT b.*, u.name as passenger_name, u.phone as passenger_phone 
        FROM tricycle_bookings b 
        JOIN users u ON b.user_id = u.user_id
        WHERE b.driver_id = ? AND LOWER(TRIM(b.status)) = 'completed'
        ORDER BY b.booking_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

// Get trip statistics
$stats_query = "SELECT 
    COUNT(*) as total_completed,
    COUNT(DISTINCT DATE(booking_time)) as days_active,
    MIN(booking_time) as first_trip,
    MAX(booking_time) as last_trip
FROM tricycle_bookings 
WHERE driver_id = ? AND LOWER(TRIM(status)) = 'completed'";

$stmt_stats = $conn->prepare($stats_query);
$stmt_stats->bind_param("i", $driver_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trip History | TrycKaSaken</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../../public/css/trips-history.css">
</head>
<body>

<!-- Fixed Navigation Bar -->
<nav class="navbar-fixed">
  <div class="navbar-content">
    <a href="../../pages/driver/login-form.php" class="navbar-brand">
      <i class="bi bi-truck"></i>
      <span>TrycKaSaken</span>
    </a>
    <button class="menu-toggle" onclick="toggleMenu()">
      <i class="bi bi-list"></i>
    </button>
    <div class="navbar-links" id="navbarLinks">
      <a href="../../pages/driver/login-form.php" class="nav-link-btn nav-link-secondary">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="../../pages/driver/trydashboard.php" class="nav-link-btn nav-link-secondary">
        <i class="bi bi-card-list"></i> Requests
      </a>
      <a href="../../pages/auth/logout-handler.php" class="nav-link-btn nav-link-primary">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h2>🕐 Trip History</h2>
    <p>View your completed trips and track your performance</p>
  </div>

  <!-- Trip Statistics Summary -->
  <?php if ($stats['total_completed'] > 0): ?>
    <div class="stats-summary">
      <div class="stat-box">
        <div class="stat-icon">🚗</div>
        <div class="stat-value"><?= $stats['total_completed'] ?></div>
        <div class="stat-label">Total Trips</div>
      </div>
      
      <div class="stat-box">
        <div class="stat-icon">📅</div>
        <div class="stat-value"><?= $stats['days_active'] ?></div>
        <div class="stat-label">Days Active</div>
      </div>
      
      <div class="stat-box">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= $stats['first_trip'] ? date('M d, Y', strtotime($stats['first_trip'])) : 'N/A' ?></div>
        <div class="stat-label">First Trip</div>
      </div>
      
      <div class="stat-box">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $stats['last_trip'] ? date('M d, Y', strtotime($stats['last_trip'])) : 'N/A' ?></div>
        <div class="stat-label">Latest Trip</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Trip History Cards -->
  <?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
      <div class="trip-card">
        <div class="trip-header">
          <h5>
            <i class="bi bi-receipt"></i>
            Trip #<?= htmlspecialchars($row['id']) ?>
          </h5>
          <span class="status-badge">
            <i class="bi bi-check-circle-fill"></i> Completed
          </span>
        </div>
        
        <div class="trip-body">
          <div class="trip-detail-row">
            <div class="trip-detail">
              <i class="bi bi-person-fill text-primary"></i>
              <div>
                <div class="detail-label">Passenger</div>
                <div class="detail-value">
                  <?= htmlspecialchars($row['passenger_name']) ?>
                  <br>
                  <small class="text-muted">
                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($row['passenger_phone']) ?>
                  </small>
                </div>
              </div>
            </div>
            
            <div class="trip-detail">
              <i class="bi bi-geo-alt text-danger"></i>
              <div>
                <div class="detail-label">Pickup Location</div>
                <div class="detail-value"><?= htmlspecialchars($row['location']) ?></div>
              </div>
            </div>
            
            <div class="trip-detail">
              <i class="bi bi-geo-alt-fill text-success"></i>
              <div>
                <div class="detail-label">Destination</div>
                <div class="detail-value"><?= htmlspecialchars($row['destination']) ?></div>
              </div>
            </div>
          </div>
          
          <?php if ($row['notes']): ?>
            <div class="trip-detail">
              <i class="bi bi-chat-text text-info"></i>
              <div>
                <div class="detail-label">Passenger Notes</div>
                <div class="detail-value"><?= htmlspecialchars($row['notes']) ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="trip-footer">
          <div class="trip-date">
            <i class="bi bi-clock"></i>
            <?= date('F j, Y • g:i A', strtotime($row['booking_time'])) ?>
          </div>
          <small class="text-muted">Status: <strong>Completed</strong></small>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h4>No Trip History Yet</h4>
      <p>Start accepting and completing rides to build your trip history and track your earnings!</p>
    </div>
  <?php endif; ?>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMenu() {
  const navbarLinks = document.getElementById('navbarLinks');
  const menuToggle = document.querySelector('.menu-toggle i');
  navbarLinks.classList.toggle('active');
  
  // Toggle icon between menu and close
  if (navbarLinks.classList.contains('active')) {
    menuToggle.classList.remove('bi-list');
    menuToggle.classList.add('bi-x-lg');
  } else {
    menuToggle.classList.remove('bi-x-lg');
    menuToggle.classList.add('bi-list');
  }
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
  const navbarLinks = document.getElementById('navbarLinks');
  const menuToggle = document.querySelector('.menu-toggle');
  const navbar = document.querySelector('.navbar-fixed');
  
  if (!navbar.contains(event.target) && navbarLinks.classList.contains('active')) {
    navbarLinks.classList.remove('active');
    menuToggle.querySelector('i').classList.remove('bi-x-lg');
    menuToggle.querySelector('i').classList.add('bi-list');
  }
});

// Close menu when clicking a link
document.querySelectorAll('.nav-link-btn').forEach(link => {
  link.addEventListener('click', function() {
    const navbarLinks = document.getElementById('navbarLinks');
    const menuToggle = document.querySelector('.menu-toggle i');
    navbarLinks.classList.remove('active');
    menuToggle.classList.remove('bi-x-lg');
    menuToggle.classList.add('bi-list');
  });
});
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
