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

// Lipa City, Batangas boundaries
// Center: 13.7430°N, 121.3127°E (City of Lipa, Batangas)
// Radius: ~8km to cover city limits
$LIPA_CENTER_LAT = 13.941876;
$LIPA_CENTER_LNG = 121.164421;
$LIPA_RADIUS_KM = 8;

// Handle cancel booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_booking_id'])) {
    $booking_id = intval($_POST['cancel_booking_id']);
    
    // Check if booking exists and is cancellable (pending or accepted but check driver acceptance)
    $check_stmt = $conn->prepare("SELECT status, driver_id FROM tricycle_bookings WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $booking = $check_stmt->get_result()->fetch_assoc();
    
    if ($booking && (strtolower($booking['status']) === 'pending' || (strtolower($booking['status']) === 'accepted' && !$booking['driver_id']))) {
        $cancel_stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'cancelled' WHERE id = ?");
        $cancel_stmt->bind_param("i", $booking_id);
        if ($cancel_stmt->execute()) {
            $_SESSION['success_message'] = 'Booking cancelled successfully!';
        }
        $cancel_stmt->close();
    }
    $check_stmt->close();
    header("Location: dashboard-lipa.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['cancel_booking_id'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $destination = trim($_POST['destination']);
    $pickup_lat = isset($_POST['pickup_lat']) ? floatval($_POST['pickup_lat']) : null;
    $pickup_lng = isset($_POST['pickup_lng']) ? floatval($_POST['pickup_lng']) : null;
    $dest_lat = isset($_POST['dest_lat']) ? floatval($_POST['dest_lat']) : null;
    $dest_lng = isset($_POST['dest_lng']) ? floatval($_POST['dest_lng']) : null;

    if (empty($name) || empty($location) || empty($destination)) {
        $_SESSION['error_message'] = 'Please fill in all fields!';
        header("Location: dashboard-lipa.php");
        exit;
    }

    // Verify both locations are within Lipa city bounds
    if ($pickup_lat === null || $pickup_lng === null || $dest_lat === null || $dest_lng === null) {
        $_SESSION['error_message'] = 'Please select pickup and destination on the map!';
        header("Location: dashboard-lipa.php");
        exit;
    }

    // Calculate distance from Lipa center using Haversine formula
    $pickup_distance = calculateDistance($LIPA_CENTER_LAT, $LIPA_CENTER_LNG, $pickup_lat, $pickup_lng);
    $dest_distance = calculateDistance($LIPA_CENTER_LAT, $LIPA_CENTER_LNG, $dest_lat, $dest_lng);

    if ($pickup_distance > $LIPA_RADIUS_KM || $dest_distance > $LIPA_RADIUS_KM) {
        $_SESSION['error_message'] = 'Both pickup and destination must be within Lipa city limits!';
        header("Location: dashboard-lipa.php");
        exit;
    }

    // Insert booking with user_id and coordinates
    $stmt = $conn->prepare("INSERT INTO tricycle_bookings (user_id, name, location, destination, pickup_lat, pickup_lng, dest_lat, dest_lng, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    if ($stmt === false) {
        $_SESSION['error_message'] = 'Database error: ' . $conn->error;
        header("Location: dashboard-lipa.php");
        exit;
    }
    
    $stmt->bind_param("isssdddd", $user_id, $name, $location, $destination, $pickup_lat, $pickup_lng, $dest_lat, $dest_lng);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Booking successful!';
        header("Location: dashboard-lipa.php");
    } else {
        $_SESSION['error_message'] = 'Booking failed: ' . $stmt->error;
        header("Location: dashboard-lipa.php");
    }

    $stmt->close();
    exit;
}

// Function to calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth's radius in kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

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

// Fetch current ACTIVE booking with driver information (exclude cancelled and declined)
$stmt = $conn->prepare("
    SELECT 
        tb.*,
        u.name as driver_name,
        u.phone as driver_phone,
        u.tricycle_info as vehicle_info
    FROM tricycle_bookings tb
    LEFT JOIN users u ON tb.driver_id = u.user_id
    WHERE tb.user_id = ? 
    AND LOWER(tb.status) NOT IN ('cancelled', 'declined')
    ORDER BY tb.booking_time DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$latestBooking = $result->fetch_assoc();
$stmt->close();

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book a Tricycle in Lipa City | TrycKaSaken</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <!-- Passenger Dashboard Lipa CSS -->
  <link rel="stylesheet" href="../../public/css/passenger-dashboard-lipa.css">
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
      <a href="../../pages/passenger/trips-history.php" class="nav-link-btn nav-link-secondary">
        <i class="bi bi-clock-history"></i> Trip History
      </a>
      <a href="../../pages/auth/logout-handler.php" class="nav-link-btn nav-link-primary">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

  <div class="book-container">
    <a href="../../pages/passenger/login-form.php" class="back-link">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <div class="page-header">
      <h1><i class="bi bi-truck"></i> Book a Tricycle</h1>
      <p>Your reliable tricycle booking service in Lipa City, Batangas</p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div style="background: rgba(16,185,129,0.95); border: 2px solid #10b981; color: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
        <i class="bi bi-check-circle-fill"></i> <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
      <div style="background: rgba(220,38,38,0.95); border: 2px solid #dc2626; color: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(220,38,38,0.3);">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
      </div>
    <?php endif; ?>

    <?php
    // Show form only if no active booking (pending or accepted with no driver)
    $hasActiveBooking = $latestBooking && (
        (strtolower($latestBooking['status']) === 'pending') || 
        (strtolower($latestBooking['status']) === 'accepted' && $latestBooking['driver_id'])
    );
    
    if (!$hasActiveBooking):
    ?>
      <section class="form-section">
        <h3><i class="bi bi-plus-circle-fill"></i> Create New Booking</h3>
        
        <form method="post" id="bookingForm" style="margin-bottom: 24px;">
          <div class="mb-3">
            <label for="name" class="form-label" style="color: #16a34a; font-weight: 600;">
              <i class="bi bi-person-fill"></i> Full Name
            </label>
            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user_name); ?>" required>
          </div>
          <div class="mb-3">
            <label for="location" class="form-label" style="color: #16a34a; font-weight: 600;">
              <i class="bi bi-geo-alt-fill"></i> Pickup Location
            </label>
            <input type="text" class="form-control" name="location" id="location" placeholder="Selected on map..." required>
          </div>
          <div class="mb-3">
            <label for="destination" class="form-label" style="color: #16a34a; font-weight: 600;">
              <i class="bi bi-flag-fill"></i> Destination
            </label>
            <input type="text" class="form-control" name="destination" id="destination" placeholder="Selected on map..." required>
          </div>
          
          <!-- Hidden inputs for coordinates -->
          <input type="hidden" name="pickup_lat" id="form_pickup_lat">
          <input type="hidden" name="pickup_lng" id="form_pickup_lng">
          <input type="hidden" name="dest_lat" id="form_dest_lat">
          <input type="hidden" name="dest_lng" id="form_dest_lng">
          
          <div class="text-center">
            <button type="submit" class="btn-book">
              <i class="bi bi-calendar-check-fill"></i> Book Now
            </button>
          </div>
        </form>
        
        <div class="map-instructions">
          <i class="bi bi-info-circle-fill"></i>
          <strong>How to use:</strong> Click on the map twice - first for your pickup location (green marker), then for your destination (red marker). Both locations must be within Lipa City limits.
        </div>

        <div id="map"></div>

        <div class="route-info" id="routeInfo">
          <h5 style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-route"></i> Route Information
          </h5>
          <div class="route-stats">
            <div class="route-stat">
              <span class="value" id="routeDistance">-</span>
              <span class="label">Distance</span>
            </div>
            <div class="route-stat">
              <span class="value" id="routeDuration">-</span>
              <span class="label">Est. Duration</span>
            </div>
          </div>
        </div>

        <button class="btn-clear" onclick="clearMap()">
          <i class="bi bi-arrow-clockwise"></i> Clear Map & Start Over
        </button>

        <div class="location-info">
          <div class="location-card" id="pickupCard" style="display: none;">
            <strong>📍 Pickup Location</strong>
            <div class="coords" id="pickupCoords">Waiting for selection...</div>
            <input type="hidden" id="pickup_lat" name="pickup_lat">
            <input type="hidden" id="pickup_lng" name="pickup_lng">
          </div>

          <div class="location-card" id="destCard" style="display: none;">
            <strong>🏁 Destination</strong>
            <div class="coords" id="destCoords">Waiting for selection...</div>
            <input type="hidden" id="dest_lat" name="dest_lat">
            <input type="hidden" id="dest_lng" name="dest_lng">
          </div>
        </div>

        <button class="btn-clear" onclick="clearMap()">
          <i class="bi bi-arrow-clockwise"></i> Clear Map & Start Over
        </button>
      </section>
    <?php else: ?>
      <div style="background: rgba(245,158,11,0.95); border: 2px solid #f59e0b; color: white; padding: 24px; border-radius: 12px; margin-bottom: 32px; font-weight: 600; box-shadow: 0 4px 12px rgba(245,158,11,0.3);">
        <i class="bi bi-exclamation-triangle-fill" style="font-size: 24px; margin-right: 15px;"></i>
        <strong>Active Booking in Progress</strong>
        <p style="margin-top: 8px; margin-bottom: 0;">You already have an active booking. Please wait for it to be completed before creating a new one.</p>
      </div>
    <?php endif; ?>

    <?php if ($latestBooking): ?>
    <section class="status-card" id="currentBookingSection">
      <h4 style="color: #16a34a; font-weight: 700; margin-bottom: 20px;">
        <i class="bi bi-card-text"></i> Current Booking Status
      </h4>
      <div id="bookingContent">
      <div class="row align-items-start">
        <div class="col-md-8">
          <h5 style="color: #16a34a; font-weight: 700; margin-bottom: 12px;">
            Booking #<?= htmlspecialchars($latestBooking['id']); ?>
          </h5>
          <p class="mb-2" style="color: #16a34a;">
            <i class="bi bi-geo-alt-fill" style="color: #dc2626;"></i> 
            <strong>Pickup:</strong> <?= htmlspecialchars(
              (!empty($latestBooking['pickup_lat']) && !empty($latestBooking['pickup_lng'])) 
                ? getLocationFromCoordinates($latestBooking['pickup_lat'], $latestBooking['pickup_lng'])
                : $latestBooking['location']
            ); ?>
          </p>
          <p class="mb-2" style="color: #16a34a;">
            <i class="bi bi-flag-fill" style="color: #10b981;"></i> 
            <strong>Destination:</strong> <?= htmlspecialchars(
              (!empty($latestBooking['dest_lat']) && !empty($latestBooking['dest_lng'])) 
                ? getLocationFromCoordinates($latestBooking['dest_lat'], $latestBooking['dest_lng'])
                : $latestBooking['destination']
            ); ?>
          </p>
          <p class="mb-3" style="color: #6c757d;">
            <i class="bi bi-clock-fill"></i> <?= date('M d, Y h:i A', strtotime($latestBooking['booking_time'])); ?>
          </p>

          <?php if (!empty($latestBooking['driver_id'])): ?>
          <!-- Driver Information Section -->
          <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid rgba(102, 126, 234, 0.2);">
            <h6 style="color: #667eea; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
              <i class="bi bi-person-badge"></i>
              Driver Information
            </h6>
            <div style="display: grid; gap: 12px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <i class="bi bi-person-circle" style="color: #667eea; font-size: 1.2rem;"></i>
                <div>
                  <div style="font-size: 0.85rem; color: #6c757d; font-weight: 600;">Driver Name</div>
                  <div style="color: #2c3e50; font-weight: 600;"><?= htmlspecialchars($latestBooking['driver_name'] ?? 'N/A'); ?></div>
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 10px;">
                <i class="bi bi-telephone-fill" style="color: #10b981; font-size: 1.2rem;"></i>
                <div>
                  <div style="font-size: 0.85rem; color: #6c757d; font-weight: 600;">Phone Number</div>
                  <div style="color: #2c3e50; font-weight: 600;">
                    <?php if (!empty($latestBooking['driver_phone'])): ?>
                      <a href="tel:<?= htmlspecialchars($latestBooking['driver_phone']); ?>" style="color: #10b981; text-decoration: none;">
                        <?= htmlspecialchars($latestBooking['driver_phone']); ?>
                      </a>
                    <?php else: ?>
                      N/A
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php if (!empty($latestBooking['vehicle_info'])): ?>
              <div style="display: flex; align-items: center; gap: 10px;">
                <i class="bi bi-truck" style="color: #ef4444; font-size: 1.2rem;"></i>
                <div>
                  <div style="font-size: 0.85rem; color: #6c757d; font-weight: 600;">Vehicle Info</div>
                  <div style="color: #2c3e50; font-weight: 600;"><?= htmlspecialchars($latestBooking['vehicle_info']); ?></div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="col-md-4 text-center">
          <?php 
          $status = strtolower($latestBooking['status']);
          $badge_style = '';
          $icon = '';
          $status_text = '';
          
          if ($status == 'pending') {
              $badge_style = 'background: rgba(245,158,11,0.3); color: #f59e0b; border: 1px solid #f59e0b;';
              $icon = '<i class="bi bi-clock-fill"></i>';
              $status_text = 'Waiting for driver...';
          } elseif ($status == 'accepted') {
              $badge_style = 'background: rgba(16,185,129,0.3); color: #10b981; border: 1px solid #10b981;';
              $icon = '<i class="bi bi-check-circle-fill"></i>';
              $status_text = 'Driver en route';
          } elseif ($status == 'completed') {
              $badge_style = 'background: rgba(34,197,94,0.3); color: #22c55e; border: 1px solid #22c55e;';
              $icon = '<i class="bi bi-check-double"></i>';
              $status_text = 'Ride completed';
          } elseif ($status == 'cancelled') {
              $badge_style = 'background: rgba(220,38,38,0.3); color: #dc2626; border: 1px solid #dc2626;';
              $icon = '<i class="bi bi-x-circle-fill"></i>';
              $status_text = 'Booking cancelled';
          }
          ?>
          <span style="display: inline-block; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; <?= $badge_style; ?>">
            <?= $icon; ?> <?= htmlspecialchars(ucfirst($latestBooking['status'])); ?>
          </span>
          <p style="margin-top: 12px; color: #6c757d; font-weight: 500;"><?= $status_text; ?></p>
          
          <!-- Added cancel booking button for pending bookings without driver -->
          <?php if (strtolower($latestBooking['status']) === 'pending' || (strtolower($latestBooking['status']) === 'accepted' && !$latestBooking['driver_id'])): ?>
            <form method="post" style="margin-top: 16px;">
              <input type="hidden" name="cancel_booking_id" value="<?= $latestBooking['id'] ?>">
              <button type="submit" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this booking?');">
                <i class="bi bi-x-circle"></i> Cancel Booking
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // City of Lipa, Batangas coordinates
    const LIPA_CENTER_LAT = <?= $LIPA_CENTER_LAT; ?>;
    const LIPA_CENTER_LNG = <?= $LIPA_CENTER_LNG; ?>;
    const LIPA_RADIUS_KM = <?= $LIPA_RADIUS_KM; ?>;

    let map, pickupMarker, destMarker, routeLayer, lipaCircle;
    let pickupSet = false;

    // Initialize map centered on Lipa City
    map = L.map('map').setView([LIPA_CENTER_LAT, LIPA_CENTER_LNG], 13);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    // Draw Lipa City boundary circle
    lipaCircle = L.circle([LIPA_CENTER_LAT, LIPA_CENTER_LNG], {
      radius: LIPA_RADIUS_KM * 1000, // Convert km to meters
      color: '#10b981',
      fillColor: '#10b981',
      fillOpacity: 0.1,
      weight: 2,
      dashArray: '5, 5'
    }).addTo(map);

    // Custom marker icons
    const pickupIcon = L.icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    const destIcon = L.icon({
      iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    });

    // Calculate distance between two points (Haversine)
    function calculateDistance(lat1, lon1, lat2, lon2) {
      const R = 6371; // Earth's radius in km
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }

    // Reverse geocoding: Convert coordinates to location name
    async function getLocationName(lat, lng) {
      try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, {
          headers: {
            'User-Agent': 'TrycKaSaken/1.0'
          }
        });
        const data = await response.json();
        
        if (data && data.address) {
          const address = data.address;
          const locationParts = [];
          
          if (address.road) locationParts.push(address.road);
          if (address.village) locationParts.push(address.village);
          if (address.suburb) locationParts.push(address.suburb);
          if (address.city) locationParts.push(address.city);
          
          if (locationParts.length > 0) {
            return locationParts.join(', ');
          }
        }
        
        return `Lat: ${lat}, Lng: ${lng}`;
      } catch (error) {
        console.error('Geocoding error:', error);
        return `Lat: ${lat}, Lng: ${lng}`;
      }
    }

    // Map click handler
    map.on('click', async function(e) {
      const lat = e.latlng.lat.toFixed(6);
      const lng = e.latlng.lng.toFixed(6);

      // Check if location is within Lipa city bounds
      const distance = calculateDistance(LIPA_CENTER_LAT, LIPA_CENTER_LNG, lat, lng);
      if (distance > LIPA_RADIUS_KM) {
        alert('Location is outside Lipa City limits! Please select a location within the dashed circle.');
        return;
      }

      // Get location name from coordinates
      const locationName = await getLocationName(lat, lng);

      if (!pickupSet) {
        // Set pickup location (first click)
        if (pickupMarker) {
          map.removeLayer(pickupMarker);
        }
        
        pickupMarker = L.marker([lat, lng], { icon: pickupIcon })
          .addTo(map)
          .bindPopup('<strong>Pickup Location</strong><br>' + locationName)
          .openPopup();

        // Update UI
        document.getElementById('pickupCard').style.display = 'block';
        document.getElementById('pickupCoords').textContent = locationName;
        document.getElementById('pickup_lat').value = lat;
        document.getElementById('pickup_lng').value = lng;
        document.getElementById('form_pickup_lat').value = lat;
        document.getElementById('form_pickup_lng').value = lng;
        document.getElementById('location').value = locationName;

        pickupSet = true;
      } else {
        // Set destination (second click)
        if (destMarker) {
          map.removeLayer(destMarker);
        }

        destMarker = L.marker([lat, lng], { icon: destIcon })
          .addTo(map)
          .bindPopup('<strong>Destination</strong><br>' + locationName)
          .openPopup();

        // Update UI
        document.getElementById('destCard').style.display = 'block';
        document.getElementById('destCoords').textContent = locationName;
        document.getElementById('dest_lat').value = lat;
        document.getElementById('dest_lng').value = lng;
        document.getElementById('form_dest_lat').value = lat;
        document.getElementById('form_dest_lng').value = lng;
        document.getElementById('destination').value = locationName;

        // Calculate route using OSRM
        getRoute();
      }
    });

    // Get route from OSRM
    function getRoute() {
      const pickupLat = document.getElementById('pickup_lat').value;
      const pickupLng = document.getElementById('pickup_lng').value;
      const destLat = document.getElementById('dest_lat').value;
      const destLng = document.getElementById('dest_lng').value;

      // OSRM API endpoint
      const url = `https://router.project-osrm.org/route/v1/driving/${pickupLng},${pickupLat};${destLng},${destLat}?overview=full&geometries=geojson`;

      fetch(url)
        .then(response => response.json())
        .then(data => {
          if (data.code === 'Ok') {
            // Remove existing route layer
            if (routeLayer) {
              map.removeLayer(routeLayer);
            }

            // Draw route on map
            const route = data.routes[0];
            const coordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);
            
            routeLayer = L.polyline(coordinates, {
              color: '#10b981',
              weight: 5,
              opacity: 0.8
            }).addTo(map);

            // Fit map to route bounds
            map.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });

            // Display route information
            const distance = (route.distance / 1000).toFixed(2);
            const duration = Math.round(route.duration / 60);

            document.getElementById('routeDistance').textContent = distance + ' km';
            document.getElementById('routeDuration').textContent = duration + ' min';
            document.getElementById('routeInfo').classList.add('active');
          } else {
            alert('Could not calculate route. Please try different locations.');
          }
        })
        .catch(error => {
          console.error('Error fetching route:', error);
          alert('Error calculating route. Please try again.');
        });
    }

    // Clear map and reset
    function clearMap() {
      if (pickupMarker) {
        map.removeLayer(pickupMarker);
        pickupMarker = null;
      }
      if (destMarker) {
        map.removeLayer(destMarker);
        destMarker = null;
      }
      if (routeLayer) {
        map.removeLayer(routeLayer);
        routeLayer = null;
      }

      // Reset UI
      document.getElementById('pickupCard').style.display = 'none';
      document.getElementById('destCard').style.display = 'none';
      document.getElementById('routeInfo').classList.remove('active');
      
      // Clear inputs
      document.getElementById('pickup_lat').value = '';
      document.getElementById('pickup_lng').value = '';
      document.getElementById('dest_lat').value = '';
      document.getElementById('dest_lng').value = '';
      document.getElementById('form_pickup_lat').value = '';
      document.getElementById('form_pickup_lng').value = '';
      document.getElementById('form_dest_lat').value = '';
      document.getElementById('form_dest_lng').value = '';
      document.getElementById('location').value = '';
      document.getElementById('destination').value = '';

      pickupSet = false;
      
      // Reset map view
      map.setView([LIPA_CENTER_LAT, LIPA_CENTER_LNG], 13);
    }

    // Try to get user's current location
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function(position) {
          const userLat = position.coords.latitude;
          const userLng = position.coords.longitude;
          
          // Check if user is within Lipa city
          const distance = calculateDistance(LIPA_CENTER_LAT, LIPA_CENTER_LNG, userLat, userLng);
          if (distance <= LIPA_RADIUS_KM) {
            map.setView([userLat, userLng], 15);
            
            L.marker([userLat, userLng], {
              icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
              })
            }).addTo(map).bindPopup('Your Current Location').openPopup();
          }
        },
        function(error) {
          console.log('Geolocation error:', error);
        }
      );
    }

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
            
            if (previousStatus !== null) {
              if (previousStatus !== currentStatus) {
                showStatusNotification('Booking status updated to: ' + currentStatus.toUpperCase());
                setTimeout(() => window.location.reload(), 2000);
                return;
              }
              
              if (previousDriverId === null && currentDriverId !== null) {
                showStatusNotification('A driver has been assigned to your booking!');
                setTimeout(() => window.location.reload(), 2000);
                return;
              }
            }
            
            previousStatus = currentStatus;
            previousDriverId = currentDriverId;
          } else if (!data.booking && previousStatus !== null) {
            showStatusNotification('Your booking has been updated!');
            setTimeout(() => window.location.reload(), 2000);
          }
        })
        .catch(error => {
          console.error('Status check error:', error);
        });
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

    <?php if ($latestBooking): ?>
    setInterval(checkBookingStatus, 15000);
    previousStatus = '<?= strtolower($latestBooking['status']); ?>';
    previousDriverId = <?= !empty($latestBooking['driver_id']) ? $latestBooking['driver_id'] : 'null'; ?>;
    <?php endif; ?>

    // Mobile menu toggle
    function toggleMenu() {
      const menu = document.getElementById('navbarLinks');
      const menuIcon = document.querySelector('.menu-toggle i');
      
      menu.classList.toggle('active');
      
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

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
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

  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php $database->closeConnection(); ?>
