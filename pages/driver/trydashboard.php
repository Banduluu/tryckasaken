<?php
session_start();
require_once '../../config/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'driver') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$driver_name = $_SESSION['name'];

// Get the actual driver_id from rfid_drivers table
$driver_query = $conn->prepare("SELECT driver_id FROM rfid_drivers WHERE user_id = ?");
$driver_query->bind_param("i", $user_id);
$driver_query->execute();
$driver_result = $driver_query->get_result()->fetch_assoc();
$driver_query->close();

if ($driver_result) {
    $driver_id = $driver_result['driver_id'];
} else {
    // If no driver record, use user_id as driver_id
    $driver_id = $user_id;
}

// Lipa City, Batangas boundaries (same as passenger dashboard)
$LIPA_CENTER_LAT = 13.941876;
$LIPA_CENTER_LNG = 121.164421;
$LIPA_RADIUS_KM = 8;

// Handle accept booking
if (isset($_POST['accept_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if driver already has an active booking (accepted or in-transit)
    $active_check = $conn->prepare("SELECT COUNT(*) as active_count FROM tricycle_bookings WHERE driver_id = ? AND (LOWER(status) = 'accepted' OR LOWER(status) = 'in-transit')");
    $active_check->bind_param("i", $driver_id);
    $active_check->execute();
    $active_result = $active_check->get_result()->fetch_assoc();
    $active_check->close();
    
    if ($active_result['active_count'] > 0) {
        $_SESSION['error_message'] = "You already have an active booking! Complete it before accepting a new one.";
    } else {
        // Check if booking exists and is pending
        $check_stmt = $conn->prepare("SELECT status FROM tricycle_bookings WHERE id = ?");
        $check_stmt->bind_param("i", $booking_id);
        $check_stmt->execute();
        $booking = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($booking && strtolower($booking['status']) === 'pending') {
            // Update booking to accepted and assign driver
            $update_stmt = $conn->prepare("UPDATE tricycle_bookings SET driver_id = ?, status = 'accepted' WHERE id = ?");
            $update_stmt->bind_param("ii", $driver_id, $booking_id);
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Booking accepted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to accept booking.";
            }
            $update_stmt->close();
        } else {
            $_SESSION['error_message'] = "Booking is no longer available.";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle pick up booking (change status from accepted to in-transit)
if (isset($_POST['pickup_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if booking exists and is accepted by this driver
    $check_stmt = $conn->prepare("SELECT status, driver_id FROM tricycle_bookings WHERE id = ?");
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $booking = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($booking && strtolower($booking['status']) === 'accepted' && $booking['driver_id'] == $driver_id) {
        // Update booking to in-transit
        $update_stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'in-transit' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Picked up passenger! Heading to destination.";
        } else {
            $_SESSION['error_message'] = "Failed to update trip status.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Booking is no longer available or you are not assigned to this ride.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle complete booking
if (isset($_POST['complete_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if booking exists and is in-transit by this driver
    $check_stmt = $conn->prepare("SELECT status, driver_id FROM tricycle_bookings WHERE id = ?");
    $check_stmt->bind_param("i", $booking_id);
    $check_stmt->execute();
    $booking = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($booking && (strtolower($booking['status']) === 'in-transit' || strtolower($booking['status']) === 'accepted') && $booking['driver_id'] == $driver_id) {
        // Update booking to completed
        $update_stmt = $conn->prepare("UPDATE tricycle_bookings SET status = 'completed' WHERE id = ?");
        $update_stmt->bind_param("i", $booking_id);
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Ride completed successfully! Thank you for the service.";
        } else {
            $_SESSION['error_message'] = "Failed to complete ride.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Ride is no longer available or you are not assigned to this ride.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get active bookings for this driver (accepted and in-transit trips)
$bookings_query = "SELECT tb.*, 
                   u.name as passenger_name, 
                   u.phone as passenger_phone
                   FROM tricycle_bookings tb
                   LEFT JOIN users u ON tb.user_id = u.user_id
                   WHERE tb.driver_id = ? 
                   AND (LOWER(tb.status) = 'accepted' OR LOWER(tb.status) = 'in-transit')
                   ORDER BY tb.booking_time DESC";
$stmt = $conn->prepare($bookings_query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$bookings_result = $stmt->get_result();
$active_bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if driver has active booking
$has_active_booking = count($active_bookings) > 0;

// Get all pending bookings for reference
$pending_query = "SELECT * FROM tricycle_bookings 
                  WHERE LOWER(status) = 'pending'
                  ORDER BY booking_time DESC
                  LIMIT 20";
$pending_result = $conn->query($pending_query);
$pending_bookings = $pending_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Map Dashboard - TrycKaSaken</title>
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../../public/css/trydashboard.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar-custom">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center">
      <a href="#" class="navbar-brand-custom">
        <i class="bi bi-map"></i>
        <span>Driver Map</span>
      </a>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted" style="font-size: 0.9rem;">
          <i class="bi bi-person-circle"></i> <?= htmlspecialchars($driver_name) ?>
        </span>
        <a href="../../pages/driver/login-form.php" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-arrow-left"></i> Dashboard
        </a>
        <a href="../../pages/auth/logout-handler.php" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid py-4">
  <!-- Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
      <i class="bi bi-check-circle-fill"></i> <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
      <i class="bi bi-exclamation-triangle-fill"></i> <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
  <?php endif; ?>

  <!-- Map Container -->
  <div class="map-container">
    <div class="map-header">
      <h4>
        <i class="bi bi-geo-alt-fill"></i>
        Passenger Locations
      </h4>
      <div class="map-controls">
        <button class="btn-control btn-control-primary" onclick="focusAllBookings()">
          <i class="bi bi-arrow-up-left-circle"></i> Focus All
        </button>
        <button class="btn-control btn-control-secondary" onclick="clearMapOverlay()">
          <i class="bi bi-arrow-clockwise"></i> Clear
        </button>
      </div>
    </div>
    <div id="map"></div>
  </div>

  <!-- Active Bookings -->
  <?php if (count($active_bookings) > 0): ?>
    <div class="info-panel">
      <div class="info-section-title">
        <i class="bi bi-check-circle-fill"></i>
        Active Trips (<?= count($active_bookings) ?>)
      </div>
      <div class="booking-list">
        <?php foreach ($active_bookings as $booking): ?>
          <div class="booking-card active" onclick="focusBooking(<?= htmlspecialchars(json_encode($booking)) ?>, this)">
            <h6><?= htmlspecialchars($booking['passenger_name']) ?></h6>
            <small class="text-muted">Booking #<?= htmlspecialchars($booking['id']); ?></small>
            
            <div class="info">
              <i class="bi bi-telephone"></i>
              <span><?= htmlspecialchars($booking['passenger_phone']) ?></span>
            </div>

            <div class="info">
              <i class="bi bi-geo-alt-fill"></i>
              <span>
                <strong>Pickup:</strong>
                <?php if (!empty($booking['pickup_lat']) && !empty($booking['pickup_lng'])): ?>
                  Lat: <?= htmlspecialchars($booking['pickup_lat']); ?>, Lng: <?= htmlspecialchars($booking['pickup_lng']); ?>
                  <br><small class="text-muted"><?= htmlspecialchars(substr($booking['location'], 0, 40)) ?></small>
                <?php else: ?>
                  <?= htmlspecialchars(substr($booking['location'], 0, 40)) ?>
                <?php endif; ?>
              </span>
            </div>

            <div class="info">
              <i class="bi bi-flag"></i>
              <span>
                <strong>Destination:</strong>
                <?php if (!empty($booking['dest_lat']) && !empty($booking['dest_lng'])): ?>
                  Lat: <?= htmlspecialchars($booking['dest_lat']); ?>, Lng: <?= htmlspecialchars($booking['dest_lng']); ?>
                  <br><small class="text-muted"><?= htmlspecialchars(substr($booking['destination'], 0, 40)) ?></small>
                <?php else: ?>
                  <?= htmlspecialchars(substr($booking['destination'], 0, 40)) ?>
                <?php endif; ?>
              </span>
            </div>

            <div class="info">
              <i class="bi bi-clock"></i>
              <span><?= date('M d, h:i A', strtotime($booking['booking_time'])) ?></span>
            </div>

            <span class="status-badge status-accepted">
              <i class="bi bi-check-circle"></i> <?= strtolower($booking['status']) === 'in-transit' ? 'In Transit' : 'Accepted' ?>
            </span>

            <form method="POST" style="margin-top: 12px; display: flex; gap: 8px;">
              <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']); ?>">
              <?php if (strtolower($booking['status']) === 'accepted'): ?>
                <button type="submit" name="pickup_booking" class="btn btn-sm btn-info flex-grow-1" style="background: linear-gradient(135deg, #3b82f6, #1e40af); border: none; color: white; font-weight: 600;">
                  <i class="bi bi-geo-alt-fill"></i> Pick Up Passenger
                </button>
              <?php elseif (strtolower($booking['status']) === 'in-transit'): ?>
                <button type="submit" name="complete_booking" class="btn btn-sm btn-success flex-grow-1" style="background: linear-gradient(135deg, #10b981, #047857); border: none; color: white; font-weight: 600;">
                  <i class="bi bi-check2-circle"></i> Complete Ride
                </button>
              <?php endif; ?>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="route-info" id="routeInfo">
        <h6 style="margin-bottom: 12px;">
          <i class="bi bi-route"></i> Route Information
        </h6>
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
    </div>
  <?php else: ?>
    <div class="info-panel">
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h5>No Active Trips</h5>
        <p class="text-muted">You don't have any active trips right now. Go to the dashboard to accept bookings.</p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Pending Bookings -->
  <?php if (count($pending_bookings) > 0): ?>
    <div class="info-panel">
      <div class="info-section-title">
        <i class="bi bi-clock-fill"></i>
        Pending Bookings (<?= count($pending_bookings) ?>)
      </div>
      <?php if ($has_active_booking): ?>
        <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 16px; border-radius: 12px; margin-bottom: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
          <i class="bi bi-exclamation-circle-fill"></i> You have an active booking. Please complete it before accepting a new one.
        </div>
      <?php endif; ?>
      <div class="booking-list">
        <?php foreach ($pending_bookings as $booking): ?>
          <div class="booking-card" onclick="focusBooking(<?= htmlspecialchars(json_encode($booking)) ?>, this)">
            <h6><?= htmlspecialchars($booking['name']) ?></h6>
            <small class="text-muted">Booking #<?= htmlspecialchars($booking['id']); ?></small>
            
            <div class="info">
              <i class="bi bi-geo-alt-fill"></i>
              <span>
                <strong>Pickup:</strong>
                <?php if (!empty($booking['pickup_lat']) && !empty($booking['pickup_lng'])): ?>
                  Lat: <?= htmlspecialchars($booking['pickup_lat']); ?>, Lng: <?= htmlspecialchars($booking['pickup_lng']); ?>
                  <br><small class="text-muted"><?= htmlspecialchars(substr($booking['location'], 0, 40)) ?></small>
                <?php else: ?>
                  <?= htmlspecialchars(substr($booking['location'], 0, 40)) ?>
                <?php endif; ?>
              </span>
            </div>

            <div class="info">
              <i class="bi bi-flag"></i>
              <span>
                <strong>Destination:</strong>
                <?php if (!empty($booking['dest_lat']) && !empty($booking['dest_lng'])): ?>
                  Lat: <?= htmlspecialchars($booking['dest_lat']); ?>, Lng: <?= htmlspecialchars($booking['dest_lng']); ?>
                  <br><small class="text-muted"><?= htmlspecialchars(substr($booking['destination'], 0, 40)) ?></small>
                <?php else: ?>
                  <?= htmlspecialchars(substr($booking['destination'], 0, 40)) ?>
                <?php endif; ?>
              </span>
            </div>

            <div class="info">
              <i class="bi bi-clock"></i>
              <span><?= date('M d, h:i A', strtotime($booking['booking_time'])) ?></span>
            </div>

            <span class="status-badge status-pending">
              <i class="bi bi-clock-history"></i> Pending
            </span>

            <form method="POST" style="margin-top: 12px;">
              <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['id']); ?>">
              <button type="submit" name="accept_booking" class="btn btn-sm btn-success w-100" <?= $has_active_booking ? 'disabled' : '' ?> <?= $has_active_booking ? 'title="You have an active booking. Complete it first."' : '' ?>>
                <i class="bi bi-check-circle-fill"></i> <?= $has_active_booking ? 'Have Active Booking' : 'Accept Booking' ?>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  let map;
  let markers = {};
  let routeLayer = null;

  // Lipa City coordinates
  const LIPA_CENTER_LAT = <?= $LIPA_CENTER_LAT; ?>;
  const LIPA_CENTER_LNG = <?= $LIPA_CENTER_LNG; ?>;
  const LIPA_RADIUS_KM = <?= $LIPA_RADIUS_KM; ?>;

  // Initialize map
  function initMap() {
    map = L.map('map').setView([LIPA_CENTER_LAT, LIPA_CENTER_LNG], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    // Draw Lipa City boundary circle
    L.circle([LIPA_CENTER_LAT, LIPA_CENTER_LNG], {
      radius: LIPA_RADIUS_KM * 1000, // Convert km to meters
      color: '#667eea',
      fillColor: '#667eea',
      fillOpacity: 0.1,
      weight: 2,
      dashArray: '5, 5'
    }).addTo(map);
  }

  // Add markers for all active bookings
  function addBookingMarkers() {
    const bookings = <?= json_encode($active_bookings) ?>;
    
    bookings.forEach((booking, index) => {
      if (booking.pickup_lat && booking.pickup_lng) {
        // Pickup marker (green) with label
        const pickupMarker = L.marker([booking.pickup_lat, booking.pickup_lng], {
          icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
          })
        }).addTo(map);

        pickupMarker.bindPopup(`
          <div style="min-width: 200px;">
            <h6 style="margin-bottom: 8px; color: #667eea;">${booking.passenger_name}</h6>
            <p style="margin: 4px 0; font-size: 0.85rem;"><strong>Pickup Location (Point A)</strong></p>
            <p style="margin: 4px 0; font-size: 0.8rem;">Lat: ${booking.pickup_lat.toFixed(4)}, Lng: ${booking.pickup_lng.toFixed(4)}</p>
          </div>
        `);

        // Add label to marker
        const pickupLabel = L.marker([booking.pickup_lat, booking.pickup_lng], {
          icon: L.divIcon({
            className: 'marker-label',
            html: '<div style="background: #10b981; color: white; padding: 2px 6px; border-radius: 12px; font-size: 12px; font-weight: bold; white-space: nowrap;">Point A</div>',
            iconSize: [50, 20],
            iconAnchor: [25, 30]
          })
        }).addTo(map);

        markers['pickup_' + booking.id] = pickupMarker;
      }

      if (booking.dest_lat && booking.dest_lng) {
        // Destination marker (red) with label
        const destMarker = L.marker([booking.dest_lat, booking.dest_lng], {
          icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
          })
        }).addTo(map);

        destMarker.bindPopup(`
          <div style="min-width: 200px;">
            <h6 style="margin-bottom: 8px; color: #667eea;">${booking.passenger_name}</h6>
            <p style="margin: 4px 0; font-size: 0.85rem;"><strong>Destination (Point B)</strong></p>
            <p style="margin: 4px 0; font-size: 0.8rem;">Lat: ${booking.dest_lat.toFixed(4)}, Lng: ${booking.dest_lng.toFixed(4)}</p>
          </div>
        `);

        // Add label to marker
        const destLabel = L.marker([booking.dest_lat, booking.dest_lng], {
          icon: L.divIcon({
            className: 'marker-label',
            html: '<div style="background: #dc2626; color: white; padding: 2px 6px; border-radius: 12px; font-size: 12px; font-weight: bold; white-space: nowrap;">Point B</div>',
            iconSize: [50, 20],
            iconAnchor: [25, 30]
          })
        }).addTo(map);

        markers['dest_' + booking.id] = destMarker;
      }
    });
  }

  // Focus on specific booking
  function focusBooking(booking, element) {
    // Remove active class from all cards
    document.querySelectorAll('.booking-card').forEach(card => {
      card.classList.remove('active');
    });

    // Add active class to current card
    element.classList.add('active');

    // Fit map to this booking's markers
    if (booking.pickup_lat && booking.pickup_lng && booking.dest_lat && booking.dest_lng) {
      const bounds = L.latLngBounds(
        [booking.pickup_lat, booking.pickup_lng],
        [booking.dest_lat, booking.dest_lng]
      );
      map.fitBounds(bounds, { padding: [100, 100] });

      // Calculate and show route
      calculateRoute(booking.pickup_lat, booking.pickup_lng, booking.dest_lat, booking.dest_lng);
    }
  }

  // Focus all bookings
  function focusAllBookings() {
    const bookings = <?= json_encode($active_bookings) ?>;
    
    if (bookings.length === 0) return;

    let allBounds = null;
    bookings.forEach(booking => {
      if (booking.pickup_lat && booking.pickup_lng) {
        const point = L.latLng(booking.pickup_lat, booking.pickup_lng);
        if (!allBounds) {
          allBounds = L.latLngBounds(point, point);
        } else {
          allBounds.extend(point);
        }
      }
      if (booking.dest_lat && booking.dest_lng) {
        const point = L.latLng(booking.dest_lat, booking.dest_lng);
        if (!allBounds) {
          allBounds = L.latLngBounds(point, point);
        } else {
          allBounds.extend(point);
        }
      }
    });

    if (allBounds) {
      map.fitBounds(allBounds, { padding: [100, 100] });
    }

    // Clear route info
    document.getElementById('routeInfo').classList.remove('active');
    clearMapOverlay();
  }

  // Calculate and draw route
  function calculateRoute(pickupLat, pickupLng, destLat, destLng) {
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
            color: '#667eea',
            weight: 5,
            opacity: 0.8
          }).addTo(map);

          // Display route information
          const distance = (route.distance / 1000).toFixed(2);
          const duration = Math.round(route.duration / 60);

          document.getElementById('routeDistance').textContent = distance + ' km';
          document.getElementById('routeDuration').textContent = duration + ' min';
          document.getElementById('routeInfo').classList.add('active');
        }
      })
      .catch(error => console.error('Error calculating route:', error));
  }

  // Clear map overlay
  function clearMapOverlay() {
    if (routeLayer) {
      map.removeLayer(routeLayer);
      routeLayer = null;
    }
    document.getElementById('routeInfo').classList.remove('active');
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    initMap();
    addBookingMarkers();
    
    // If there are active bookings, focus on the first one
    const firstCard = document.querySelector('.booking-card.active');
    if (firstCard) {
      const bookings = <?= json_encode($active_bookings) ?>;
      if (bookings.length > 0) {
        focusBooking(bookings[0], firstCard);
      }
    }
  });

  // Auto-refresh once
  setTimeout(function() {
    location.reload();
  }, 300000);
</script>

</body>
</html>

<?php
$conn->close();
?>
