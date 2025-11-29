<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Lipa City, Batangas boundaries
$LIPA_CENTER_LAT = 13.941876;
$LIPA_CENTER_LNG = 121.164421;
$LIPA_RADIUS_KM = 8;

// Function to get location name from coordinates using reverse geocoding
function getLocationFromCoordinates($lat, $lng) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['address'])) {
                $address = $data['address'];
                // Construct location name
                $location = '';
                if (isset($address['road'])) $location .= $address['road'] . ', ';
                if (isset($address['suburb'])) $location .= $address['suburb'] . ', ';
                if (isset($address['city'])) $location .= $address['city'];
                return trim($location, ', ') ?: 'Lipa City, Batangas';
            }
        }
    } catch (Exception $e) {
        return 'Lipa City, Batangas';
    }
    
    return 'Lipa City, Batangas';
}

// Get all online drivers - using default Lipa City coordinates
$query = "SELECT 
    u.user_id,
    u.name,
    u.email,
    u.phone,
    d.driver_id,
    d.is_online,
    d.tricycle_info,
    COUNT(CASE WHEN b.status = 'accepted' THEN 1 END) as active_trips
FROM rfid_drivers d
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN tricycle_bookings b ON d.driver_id = b.driver_id
WHERE d.verification_status = 'verified' AND u.status = 'active' AND d.is_online = 1
GROUP BY d.driver_id, u.user_id
ORDER BY d.is_online DESC";

$result = $conn->query($query);
$drivers = $result->fetch_all(MYSQLI_ASSOC);

// Add mock GPS coordinates and location names for demonstration
$online_drivers = array_map(function($driver, $index) {
    $baseLatitude = 13.941876;
    $baseLongitude = 121.164421;
    // Generate random coordinates around Lipa City
    $driver['latitude'] = $baseLatitude + (rand(-100, 100) / 10000);
    $driver['longitude'] = $baseLongitude + (rand(-100, 100) / 10000);
    $driver['location_name'] = getLocationFromCoordinates($driver['latitude'], $driver['longitude']);
    return $driver;
}, $drivers, array_keys($drivers));

renderAdminHeader("Drivers Location", "drivers");
?>
<link rel="stylesheet" href="../../public/css/drivers-location.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Back Button -->
<div class="row mb-3">
  <div class="col-12">
    <a href="drivers-list.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Back to Drivers
    </a>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-9">
    <h4 class="mb-3">
      <i class="bi bi-geo-alt-fill"></i> Live Driver Locations - Lipa City
    </h4>
  </div>
  <div class="col-md-3 text-end">
    <button class="btn btn-custom btn-sm" onclick="refreshMap()">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
  <div class="col-md-4 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
        <i class="bi bi-circle-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= count($online_drivers) ?></div>
        <div class="stat-label">Online Drivers</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <i class="bi bi-car-front-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= count(array_filter($online_drivers, function($d) { return $d['active_trips'] > 0; })) ?></div>
        <div class="stat-label">On Active Trip</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
        <i class="bi bi-telephone"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= count($drivers) ?></div>
        <div class="stat-label">Total Verified Drivers</div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-8">
    <!-- Map Container -->
    <div class="content-card">
      <div id="map" style="height: 600px; border-radius: 8px;"></div>
    </div>
  </div>
  
  <div class="col-md-4">
    <!-- Drivers List Sidebar -->
    <div class="content-card">
      <h5 class="mb-3">
        <i class="bi bi-list-ul"></i> Online Drivers (<?= count($online_drivers) ?>)
      </h5>
      
      <?php if (count($online_drivers) > 0): ?>
        <div class="drivers-list" id="driversList" style="max-height: 600px; overflow-y: auto;">
          <?php foreach ($online_drivers as $driver): ?>
            <div class="driver-item" onclick="centerMapOnDriver(<?= $driver['latitude'] ?>, <?= $driver['longitude'] ?>, this)" style="cursor: pointer;">
              <div class="driver-info">
                <h6 class="mb-1">
                  <?= htmlspecialchars($driver['name']) ?>
                  <?php if ($driver['active_trips'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-2" style="font-size: 0.75rem;">
                      <i class="bi bi-car-front-fill"></i> <?= $driver['active_trips'] ?> trip(s)
                    </span>
                  <?php endif; ?>
                </h6>
                <small class="text-muted d-block">
                  <i class="bi bi-telephone"></i> <?= htmlspecialchars($driver['phone']) ?>
                </small>
                <small class="text-muted d-block">
                  <i class="bi bi-geo-alt"></i> 
                  <?= htmlspecialchars($driver['location_name']) ?>
                </small>
              </div>
              <div class="driver-status">
                <span class="status-badge status-active">
                  <i class="bi bi-circle-fill"></i> Online
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state" style="padding: 40px 20px; text-align: center;">
          <i class="bi bi-geo-alt" style="font-size: 3rem; color: #ccc; display: block; margin-bottom: 15px;"></i>
          <h5>No Online Drivers</h5>
          <p class="text-muted">No drivers are currently online with location data.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.driver-item {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 12px;
  margin-bottom: 10px;
  transition: all 0.3s ease;
  background-color: #fff;
}

.driver-item:hover {
  background-color: #f9fafb;
  border-color: #10b981;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.driver-item.active {
  background-color: #ecfdf5;
  border-color: #10b981;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.driver-info h6 {
  margin: 0;
  font-weight: 600;
  color: #1f2937;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-badge.status-active {
  background-color: #d1fae5;
  color: #065f46;
}

.leaflet-container {
  border-radius: 8px;
}
</style>

<script>
// Lipa City Coordinates (Batangas)
const LIPA_CENTER_LAT = <?= $LIPA_CENTER_LAT; ?>;
const LIPA_CENTER_LNG = <?= $LIPA_CENTER_LNG; ?>;
const LIPA_RADIUS_KM = <?= $LIPA_RADIUS_KM; ?>;

let map, markers = {}, lipaCircle;

// Initialize map centered on Lipa City
map = L.map('map').setView([LIPA_CENTER_LAT, LIPA_CENTER_LNG], 13);

// Add OpenStreetMap tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors',
  maxZoom: 19
}).addTo(map);

// Draw Lipa City boundary circle
lipaCircle = L.circle([LIPA_CENTER_LAT, LIPA_CENTER_LNG], {
  radius: LIPA_RADIUS_KM * 1000,
  color: '#10b981',
  fillColor: '#10b981',
  fillOpacity: 0.1,
  weight: 2,
  dashArray: '5, 5'
}).addTo(map);

// Custom marker icons
const onlineIcon = L.icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41]
});

const tripIcon = L.icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41]
});

// Driver data
const driverData = <?= json_encode($online_drivers) ?>;

// Add markers for each driver
driverData.forEach(driver => {
  const icon = driver.active_trips > 0 ? tripIcon : onlineIcon;
  const status = driver.active_trips > 0 ? 'On Trip' : 'Available';
  
  const marker = L.marker([driver.latitude, driver.longitude], { icon: icon })
    .bindPopup(`
      <div style="min-width: 200px;">
        <h6 style="margin-bottom: 8px; color: #1f2937;">
          <i class="bi bi-person"></i> ${driver.name}
        </h6>
        <small style="display: block; margin-bottom: 4px;">
          <strong>Status:</strong> ${status}
        </small>
        <small style="display: block; margin-bottom: 4px;">
          <strong>Phone:</strong> ${driver.phone}
        </small>
        <small style="display: block; margin-bottom: 4px;">
          <strong>Tricycle:</strong> ${driver.tricycle_info || 'N/A'}
        </small>
        <small style="display: block; margin-bottom: 8px;">
          <strong>Active Trips:</strong> ${driver.active_trips}
        </small>
        <a href="user-details.php?id=${driver.user_id}" class="btn btn-sm btn-primary" style="width: 100%; text-decoration: none; padding: 4px 8px; background-color: #3b82f6; color: white; border-radius: 4px; text-align: center; font-size: 0.875rem;">
          <i class="bi bi-eye"></i> View Details
        </a>
      </div>
    `)
    .addTo(map);
  
  markers[driver.user_id] = marker;
});

// Center map on driver
function centerMapOnDriver(lat, lng, element) {
  map.setView([lat, lng], 15);
  
  // Update active state
  document.querySelectorAll('.driver-item').forEach(item => {
    item.classList.remove('active');
  });
  element.classList.add('active');
}

// Refresh map data
function refreshMap() {
  location.reload();
}

// Auto-refresh every 30 seconds
setTimeout(() => {
  location.reload();
}, 30000);
</script>

<?php renderAdminFooter(); ?>