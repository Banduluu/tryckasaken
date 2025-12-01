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

// Get initial driver data for server-side rendering (fallback)
$query = "SELECT 
    d.driver_id,
    u.user_id,
    u.name,
    u.email,
    u.phone,
    d.tricycle_info,
    d.license_number,
    d.is_online,
    COUNT(DISTINCT CASE WHEN b.status IN ('accepted', 'in-transit') THEN b.id END) as active_trips,
    dl.latitude,
    dl.longitude,
    dl.accuracy,
    dl.timestamp as location_timestamp,
    IF(dl.latitude IS NOT NULL, 1, 0) as has_location,
    IF(dl.timestamp IS NOT NULL, TIMESTAMPDIFF(SECOND, dl.timestamp, NOW()), NULL) as location_age_seconds
FROM rfid_drivers d
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN tricycle_bookings b ON d.driver_id = b.driver_id AND b.status IN ('accepted', 'in-transit')
LEFT JOIN driver_locations dl ON d.driver_id = dl.driver_id AND dl.id = (
    SELECT MAX(id) FROM driver_locations WHERE driver_id = d.driver_id AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
)
WHERE d.verification_status = 'verified' AND u.status = 'active' AND d.is_online = 1
GROUP BY d.driver_id, u.user_id
ORDER BY dl.timestamp DESC, u.name ASC";

$result = $conn->query($query);
$drivers_data = [];

while ($row = $result->fetch_assoc()) {
    $drivers_data[] = $row;
}

$online_drivers = $drivers_data;

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
        <div class="stat-value"><?= count($online_drivers) ?></div>
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
                  Lat: <?= number_format($driver['latitude'], 4) ?>, Lng: <?= number_format($driver['longitude'], 4) ?>
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
let currentMarkers = {};
let refreshInterval = null;

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

// Fetch driver locations from API
function fetchDriverLocations() {
  fetch('api-get-driver-locations.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('API returned status ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      console.log('API Response:', data);
      if (data.success) {
        console.log('✓ Fetched ' + data.count + ' drivers at ' + data.timestamp);
        console.log('Total drivers in response: ' + data.drivers.length);
        if (data.drivers && data.drivers.length > 0) {
          let withLocation = 0;
          data.drivers.forEach((d, i) => {
            if (d.has_location) {
              withLocation++;
              console.log(`  ✓ ${d.name}: Lat ${d.latitude}, Lng ${d.longitude}, Age: ${d.location_age_seconds}s`);
            } else {
              console.log(`  ✗ ${d.name}: No location yet`);
            }
          });
          console.log(`Total with location: ${withLocation}/${data.drivers.length}`);
        } else {
          console.warn('No drivers returned');
        }
        updateMapWithDrivers(data.drivers);
        updateDriversList(data.drivers);
      } else {
        console.error('✗ API Error:', data.error);
      }
    })
    .catch(error => {
      console.error('✗ Error fetching driver locations:', error);
    });
}

// Update map with driver markers
function updateMapWithDrivers(drivers) {
  // Remove old markers
  Object.values(currentMarkers).forEach(marker => {
    map.removeLayer(marker);
  });
  currentMarkers = {};

  if (!drivers || drivers.length === 0) {
    console.warn('No online drivers found');
    return;
  }

  let markersAdded = 0;

  // Add new markers for each driver with valid location
  drivers.forEach(driver => {
    // Only add markers for drivers with actual location data
    if (!driver.has_location || driver.has_location === 0) {
      console.log('Driver ' + driver.name + ' is online but no GPS data yet');
      return;
    }

    const lat = parseFloat(driver.latitude);
    const lng = parseFloat(driver.longitude);
    
    // Skip default coordinates
    if ((lat === 13.941876 && lng === 121.164421)) {
      console.log('Skipping driver ' + driver.name + ' - using default coordinates');
      return;
    }

    const icon = driver.active_trips > 0 ? tripIcon : onlineIcon;
    const status = driver.active_trips > 0 ? 'On Trip' : 'Available';
    
    const marker = L.marker([lat, lng], { icon: icon })
      .bindPopup(`
        <div style="min-width: 220px;">
          <h6 style="margin-bottom: 8px; color: #1f2937; font-weight: 600;">
            <i class="bi bi-person"></i> ${escapeHtml(driver.name)}
          </h6>
          <small style="display: block; margin-bottom: 4px;">
            <strong>Status:</strong> <span style="color: ${driver.active_trips > 0 ? '#f59e0b' : '#10b981'};">${status}</span>
          </small>
          <small style="display: block; margin-bottom: 4px;">
            <strong>Phone:</strong> ${escapeHtml(driver.phone)}
          </small>
          <small style="display: block; margin-bottom: 4px;">
            <strong>Tricycle:</strong> ${escapeHtml(driver.tricycle_info || 'N/A')}
          </small>
          <small style="display: block; margin-bottom: 4px;">
            <strong>Active Trips:</strong> ${driver.active_trips}
          </small>
          <small style="display: block; margin-bottom: 8px; color: #666;">
            <strong>Coords:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}
          </small>
          <small style="display: block; margin-bottom: 4px; color: #999; font-size: 0.75rem;">
            <strong>Accuracy:</strong> ±${parseFloat(driver.accuracy).toFixed(0)}m
          </small>
          <small style="display: block; margin-bottom: 4px; color: ${driver.location_age_seconds > 30 ? '#ef4444' : '#10b981'}; font-size: 0.75rem; font-weight: bold;">
            <strong>Updated:</strong> ${driver.location_age_seconds}s ago
          </small>
          <small style="display: block; margin-bottom: 8px; color: #999; font-size: 0.75rem;">
            ${driver.location_timestamp ? new Date(driver.location_timestamp).toLocaleTimeString() : 'N/A'}
          </small>
          <a href="user-details.php?id=${driver.user_id}" class="btn btn-sm btn-primary" style="width: 100%; text-decoration: none; padding: 4px 8px; background-color: #3b82f6; color: white; border-radius: 4px; text-align: center; font-size: 0.875rem;">
            <i class="bi bi-eye"></i> View Details
          </a>
        </div>
      `)
      .addTo(map);
    
    currentMarkers[driver.user_id] = marker;
    markersAdded++;
  });

  console.log('Added ' + markersAdded + ' markers on map');
}

// Update drivers list in sidebar
function updateDriversList(drivers) {
  const driversList = document.getElementById('driversList');
  
  if (drivers.length === 0) {
    driversList.innerHTML = `
      <div class="empty-state" style="padding: 40px 20px; text-align: center;">
        <i class="bi bi-geo-alt" style="font-size: 3rem; color: #ccc; display: block; margin-bottom: 15px;"></i>
        <h5>No Online Drivers</h5>
        <p class="text-muted">No drivers are currently online.</p>
      </div>
    `;
    return;
  }

  driversList.innerHTML = drivers.map(driver => {
    const hasLocation = driver.has_location && driver.has_location !== 0;
    const locationAge = driver.location_age_seconds || 0;
    const isStale = locationAge > 30;
    
    return `
      <div class="driver-item ${hasLocation ? '' : 'opacity-50'} ${isStale ? 'opacity-75' : ''}" onclick="${hasLocation ? `centerMapOnDriver(${parseFloat(driver.latitude)}, ${parseFloat(driver.longitude)}, this)` : 'void(0)'}" style="cursor: ${hasLocation ? 'pointer' : 'default'};">
        <div class="driver-info">
          <h6 class="mb-1">
            ${escapeHtml(driver.name)}
            ${driver.active_trips > 0 ? `<span class="badge bg-warning text-dark ms-2" style="font-size: 0.75rem;"><i class="bi bi-car-front-fill"></i> ${driver.active_trips} trip(s)</span>` : ''}
            ${!hasLocation ? `<span class="badge bg-secondary ms-2" style="font-size: 0.75rem;"><i class="bi bi-clock"></i> Waiting GPS</span>` : isStale ? `<span class="badge bg-danger ms-2" style="font-size: 0.75rem;"><i class="bi bi-exclamation-triangle"></i> Stale</span>` : ''}
          </h6>
          <small class="text-muted d-block">
            <i class="bi bi-telephone"></i> ${escapeHtml(driver.phone)}
          </small>
          ${hasLocation ? `
            <small class="text-muted d-block">
              <i class="bi bi-geo-alt"></i> 
              Lat: ${parseFloat(driver.latitude).toFixed(6)}, Lng: ${parseFloat(driver.longitude).toFixed(6)}
            </small>
            <small class="text-muted d-block" style="font-size: 0.8rem;">
              <i class="bi bi-bullseye"></i> 
              Accuracy: ±${parseFloat(driver.accuracy).toFixed(0)}m
            </small>
            <small class="text-muted d-block" style="font-size: 0.8rem; ${isStale ? 'color: #dc2626;' : ''}">
              <i class="bi bi-clock"></i> 
              Updated: ${locationAge}s ago
            </small>
          ` : `
            <small class="text-warning d-block">
              <i class="bi bi-hourglass-split"></i> 
              Waiting for GPS data...
            </small>
          `}
        </div>
        <div class="driver-status">
          <span class="status-badge status-active">
            <i class="bi bi-circle-fill"></i> ${driver.active_trips > 0 ? 'On Trip' : 'Available'}
          </span>
        </div>
      </div>
    `;
  }).join('');

  // Update online drivers count
  document.querySelector('.stat-value').textContent = drivers.length;
  
  // Update active trips count
  const activeTripsCount = drivers.filter(d => d.active_trips > 0).length;
  const statCards = document.querySelectorAll('.stat-value');
  if (statCards.length >= 2) {
    statCards[1].textContent = activeTripsCount;
  }
}

// Center map on driver location
function centerMapOnDriver(lat, lng, element) {
  map.setView([lat, lng], 15);
  
  // Update active state
  document.querySelectorAll('.driver-item').forEach(item => {
    item.classList.remove('active');
  });
  element.classList.add('active');
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

// Refresh map data
function refreshMap() {
  fetchDriverLocations();
}

// Initialize map on page load
document.addEventListener('DOMContentLoaded', function() {
  fetchDriverLocations();
  
  // Auto-refresh every 5 seconds to match driver location update interval
  refreshInterval = setInterval(fetchDriverLocations, 5000);
});

// Clear interval on page unload
window.addEventListener('beforeunload', function() {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});
</script>

<?php renderAdminFooter(); ?>