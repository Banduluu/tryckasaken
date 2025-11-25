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

// Fetch ALL the user's bookings
$stmt = $conn->prepare("SELECT * FROM tricycle_bookings WHERE user_id = ? ORDER BY booking_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
  <!-- Passenger Trips History CSS -->
  <link rel="stylesheet" href="../../public/css/passenger-trips-history.css">
</head>
<body>

<!-- Fixed Navigation Bar -->
<nav class="navbar-fixed">
  <div class="navbar-content">
    <a href="../../pages/passenger/login-form.php" class="navbar-brand">
      <i class="bi bi-truck"></i>
      <span>TrycKaSaken</span>
    </a>
    <button class="menu-toggle" onclick="toggleMenu()">
      <i class="bi bi-list"></i>
    </button>
    <div class="navbar-links" id="navbarLinks">
      <a href="../../pages/passenger/login-form.php" class="nav-link-btn nav-link-secondary">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="../../pages/passenger/dashboard-lipa.php" class="nav-link-btn nav-link-secondary">
        <i class="bi bi-plus-circle"></i> New Booking
      </a>
      <a href="../../pages/auth/logout-handler.php" class="nav-link-btn nav-link-primary">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<div class="container">

  <!-- Page Header -->
  <div class="page-header">
    <h2><i class="bi bi-clock-history"></i> Trip History</h2>
    <p>View all your booking history and track your trips</p>
  </div>

  <?php
  // Calculate statistics
  $total_trips = count($bookings);
  $completed_trips = 0;
  $pending_trips = 0;
  $cancelled_trips = 0;

  foreach ($bookings as $booking) {
    $status = strtolower($booking['status']);
    if ($status === 'completed') $completed_trips++;
    elseif ($status === 'pending') $pending_trips++;
    elseif ($status === 'declined' || $status === 'cancelled') $cancelled_trips++;
  }
  ?>

  <!-- Statistics Summary -->
  <div class="stats-summary">
    <div class="stat-box">
      <div class="stat-icon" style="color: #667eea;">
        <i class="bi bi-geo-alt"></i>
      </div>
      <div class="stat-value"><?php echo $total_trips; ?></div>
      <div class="stat-label">Total Trips</div>
    </div>

    <div class="stat-box">
      <div class="stat-icon" style="color: #10b981;">
        <i class="bi bi-check-circle"></i>
      </div>
      <div class="stat-value"><?php echo $completed_trips; ?></div>
      <div class="stat-label">Completed</div>
    </div>

    <div class="stat-box">
      <div class="stat-icon" style="color: #f59e0b;">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div class="stat-value"><?php echo $pending_trips; ?></div>
      <div class="stat-label">Pending</div>
    </div>

    <div class="stat-box">
      <div class="stat-icon" style="color: #ef4444;">
        <i class="bi bi-x-circle"></i>
      </div>
      <div class="stat-value"><?php echo $cancelled_trips; ?></div>
      <div class="stat-label">Cancelled</div>
    </div>
  </div>

  <!-- Trip Cards -->
  <?php if (count($bookings) > 0): ?>
    <?php foreach ($bookings as $booking): 
      $status = strtolower($booking['status']);
      $status_class = 'status-pending';
      $status_icon = 'bi-hourglass-split';
      
      if ($status === 'accepted') {
        $status_class = 'status-accepted';
        $status_icon = 'bi-arrow-right-circle';
      } elseif ($status === 'completed') {
        $status_class = 'status-completed';
        $status_icon = 'bi-check-circle-fill';
      } elseif ($status === 'declined' || $status === 'cancelled') {
        $status_class = 'status-declined';
        $status_icon = 'bi-x-circle-fill';
      }
    ?>
    <div class="trip-card">
      <div class="trip-header">
        <h5>
          <i class="bi bi-geo-alt-fill"></i>
          Trip #<?php echo htmlspecialchars($booking['id']); ?>
        </h5>
        <span class="status-badge <?php echo $status_class; ?>">
          <i class="bi <?php echo $status_icon; ?>"></i>
          <?php echo htmlspecialchars(ucfirst($status)); ?>
        </span>
      </div>
      
      <div class="trip-body">
        <div class="trip-detail-row">
          <div class="trip-detail">
            <i class="bi bi-pin-map-fill" style="color: #10b981;"></i>
            <div>
              <div class="detail-label">Pickup Location</div>
              <div class="detail-value"><?php echo htmlspecialchars(
                (!empty($booking['pickup_lat']) && !empty($booking['pickup_lng'])) 
                  ? getLocationFromCoordinates($booking['pickup_lat'], $booking['pickup_lng'])
                  : $booking['location']
              ); ?></div>
            </div>
          </div>
          
          <div class="trip-detail">
            <i class="bi bi-geo-fill" style="color: #ef4444;"></i>
            <div>
              <div class="detail-label">Dropoff Location</div>
              <div class="detail-value"><?php echo htmlspecialchars(
                (!empty($booking['dest_lat']) && !empty($booking['dest_lng'])) 
                  ? getLocationFromCoordinates($booking['dest_lat'], $booking['dest_lng'])
                  : $booking['destination']
              ); ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="trip-footer">
        <div class="trip-date">
          <i class="bi bi-calendar3"></i>
          <?php echo date('F j, Y \a\t g:i A', strtotime($booking['booking_time'])); ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

  <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
      <div class="empty-state-icon" style="color: #e0e0e0;">
        <i class="bi bi-inbox"></i>
      </div>
      <h4>No Bookings Yet</h4>
      <p>Your booking history will appear here once you make your first trip.</p>
      <a href="../../pages/passenger/dashboard-lipa.php" class="btn-custom">
        <i class="bi bi-plus-circle"></i> Book a Ride
      </a>
    </div>
  <?php endif; ?>
</div>

<script>
  // Mobile menu toggle
  function toggleMenu() {
    const menu = document.getElementById('navbarLinks');
    const menuIcon = document.querySelector('.menu-toggle i');
    
    menu.classList.toggle('active');
    
    // Toggle icon
    if (menu.classList.contains('active')) {
      menuIcon.className = 'bi bi-x-lg';
    } else {
      menuIcon.className = 'bi bi-list';
    }
  }

  // Close menu when clicking outside
  document.addEventListener('click', function(event) {
    const menu = document.getElementById('navbarLinks');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (!menu.contains(event.target) && !menuToggle.contains(event.target)) {
      menu.classList.remove('active');
      document.querySelector('.menu-toggle i').className = 'bi bi-list';
    }
  });

  // Close menu when clicking on a link
  document.querySelectorAll('.navbar-links a').forEach(link => {
    link.addEventListener('click', function() {
      document.getElementById('navbarLinks').classList.remove('active');
      document.querySelector('.menu-toggle i').className = 'bi bi-list';
    });
  });
</script>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $database->closeConnection(); ?>
