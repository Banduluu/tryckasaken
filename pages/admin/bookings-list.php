<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$whereConditions = [];
$params = [];
$types = '';

if ($statusFilter !== 'all') {
    $whereConditions[] = "b.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($searchQuery) {
    $whereConditions[] = "(b.id = ? OR p.name LIKE ? OR p.email LIKE ? OR b.location LIKE ? OR b.destination LIKE ?)";
    $searchId = is_numeric($searchQuery) ? intval($searchQuery) : 0;
    $searchLike = "%$searchQuery%";
    $params = array_merge($params, [$searchId, $searchLike, $searchLike, $searchLike, $searchLike]);
    $types .= 'issss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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

// Get bookings with passenger info
$query = "SELECT b.*, p.name as passenger_name, p.email as passenger_email, p.phone as passenger_phone,
                 d.name as driver_name
          FROM tricycle_bookings b 
          LEFT JOIN users p ON b.user_id = p.user_id 
          LEFT JOIN users d ON b.driver_id = d.user_id
          $whereClause
          ORDER BY b.booking_time DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$bookings = $result->fetch_all(MYSQLI_ASSOC);

renderAdminHeader("Booking Management", "bookings");
?>

<!-- Filters -->
<div class="content-card">
  <h5><i class="bi bi-funnel"></i> Filters</h5>
  <form method="GET" class="row g-3">
    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>
    </div>
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search by ID, name, email, or location..." value="<?= htmlspecialchars($searchQuery) ?>">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-custom w-100">
        <i class="bi bi-search"></i> Filter
      </button>
    </div>
  </form>
</div>

<!-- Bookings Table -->
<div class="content-card">
  <h3>
    <i class="bi bi-calendar-check"></i>
    All Bookings (<?= count($bookings) ?>)
  </h3>
  
  <?php if (count($bookings) > 0): ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><i class="bi bi-hash"></i> ID</th>
            <th><i class="bi bi-person"></i> Passenger</th>
            <th><i class="bi bi-geo-alt"></i> Route</th>
            <th><i class="bi bi-car-front"></i> Driver</th>
            <th><i class="bi bi-circle"></i> Status</th>
            <th><i class="bi bi-calendar"></i> Date</th>
            <th><i class="bi bi-gear"></i> Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $booking): ?>
            <tr>
              <td><strong>#<?= $booking['id'] ?></strong></td>
              <td>
                <div>
                  <strong><?= htmlspecialchars($booking['passenger_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($booking['passenger_email']) ?></small>
                </div>
              </td>
              <td>
                <div>
                  <i class="bi bi-geo-alt text-success"></i> <?= htmlspecialchars(
                    (!empty($booking['pickup_lat']) && !empty($booking['pickup_lng'])) 
                      ? getLocationFromCoordinates($booking['pickup_lat'], $booking['pickup_lng'])
                      : $booking['location']
                  ) ?><br>
                  <i class="bi bi-flag text-primary"></i> <?= htmlspecialchars(
                    (!empty($booking['dest_lat']) && !empty($booking['dest_lng'])) 
                      ? getLocationFromCoordinates($booking['dest_lat'], $booking['dest_lng'])
                      : $booking['destination']
                  ) ?>
                </div>
              </td>
              <td>
                <?php if ($booking['driver_name']): ?>
                  <span class="status-badge status-active">
                    <i class="bi bi-person-check"></i> <?= htmlspecialchars($booking['driver_name']) ?>
                  </span>
                <?php else: ?>
                  <span class="status-badge status-inactive">
                    <i class="bi bi-person-dash"></i> Unassigned
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge status-<?= $booking['status'] ?>">
                  <?= ucfirst($booking['status']) ?>
                </span>
              </td>
              <td><?= date('M d, Y H:i', strtotime($booking['booking_time'])) ?></td>
              <td>
                <a href="booking-details.php?id=<?= $booking['id'] ?>" class="action-btn">
                  <i class="bi bi-eye"></i> View
                </a>
                <?php if ($booking['status'] === 'pending' && !$booking['driver_id']): ?>
                  <button type="button" class="action-btn btn-info" 
                          onclick="showAssignDriverModal(<?= $booking['id'] ?>, '<?= htmlspecialchars(addslashes($booking['passenger_name'])) ?>')">
                    <i class="bi bi-person-plus"></i> Assign
                  </button>
                <?php endif; ?>
                <?php if (in_array($booking['status'], ['pending', 'accepted'])): ?>
                  <button type="button" class="action-btn btn-danger" 
                          onclick="cancelBooking(<?= $booking['id'] ?>, this)">
                    <i class="bi bi-x-circle"></i> Cancel
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-calendar-x"></i>
      <h5>No Bookings Found</h5>
      <p>Bookings will appear here once passengers make reservations.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Assign Driver Modal -->
<div class="modal fade" id="assignDriverModal" tabindex="-1" aria-labelledby="assignDriverModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignDriverModalLabel">
          <i class="bi bi-person-plus"></i> Assign Driver
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="bookingInfo" class="mb-3"></p>
        <div id="driverListContainer">
          <div class="text-center">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading available drivers...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>

<script>
// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const iconMap = {
        success: 'check-circle-fill text-success',
        error: 'x-circle-fill text-danger',
        info: 'info-circle-fill text-info',
        warning: 'exclamation-triangle-fill text-warning'
    };
    
    const bgMap = {
        success: 'rgba(16, 185, 129, 0.95)',
        error: 'rgba(220, 38, 38, 0.95)',
        info: 'rgba(13, 202, 240, 0.95)',
        warning: 'rgba(245, 158, 11, 0.95)'
    };
    
    const toastHTML = `
        <div id="${toastId}" style="
            background: ${bgMap[type]};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        ">
            <i class="bi bi-${iconMap[type]}" style="font-size: 1.2rem;"></i>
            <span style="flex: 1; font-weight: 600;">${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                padding: 0;
                opacity: 0.8;
            ">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    setTimeout(() => {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

let currentBookingId = null;

// Show assign driver modal
function showAssignDriverModal(bookingId, passengerName) {
    currentBookingId = bookingId;
    document.getElementById('bookingInfo').innerHTML = `
        <strong>Booking #${bookingId}</strong> - ${passengerName}
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('assignDriverModal'));
    modal.show();
    
    // Load available drivers
    loadAvailableDrivers();
}

// Load available drivers
function loadAvailableDrivers() {
    fetch('api-admin-actions.php?action=get_available_drivers')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('driverListContainer');
            
            if (data.success && data.data.length > 0) {
                let html = '<div class="list-group">';
                data.data.forEach(driver => {
                    const onlineStatus = driver.is_online == 1 
                        ? '<span class="badge bg-success">Online</span>' 
                        : '<span class="badge bg-secondary">Offline</span>';
                    
                    html += `
                        <button type="button" class="list-group-item list-group-item-action" 
                                onclick="assignDriver(${driver.user_id}, '${driver.name}')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><i class="bi bi-person-circle"></i> ${driver.name}</h6>
                                    <small class="text-muted">
                                        <i class="bi bi-telephone"></i> ${driver.phone}
                                        ${driver.tricycle_info ? `<br><i class="bi bi-truck"></i> ${driver.tricycle_info}` : ''}
                                    </small>
                                </div>
                                <div>
                                    ${onlineStatus}
                                </div>
                            </div>
                        </button>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        No verified drivers available at the moment.
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('driverListContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i>
                    Failed to load drivers. Please try again.
                </div>
            `;
            console.error('Error:', error);
        });
}

// Assign driver to booking
function assignDriver(driverId, driverName) {
    if (!confirm(`Assign ${driverName} to booking #${currentBookingId}?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'assign_driver');
    formData.append('booking_id', currentBookingId);
    formData.append('driver_id', driverId);
    
    fetch('api-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('assignDriverModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Cancel booking
function cancelBooking(bookingId, button) {
    if (!confirm('Are you sure you want to cancel this booking?')) return;
    
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    
    fetch('api-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        console.error('Error:', error);
    });
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
    .list-group-item { cursor: pointer; transition: all 0.2s; }
    .list-group-item:hover { background-color: rgba(13, 202, 240, 0.1); }
`;
document.head.appendChild(style);
</script>

<?php renderAdminFooter(); ?>
