<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        if ($action === 'suspend') {
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ? AND user_type = 'driver'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = 'Driver suspended successfully!';
        } elseif ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND user_type = 'driver'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = 'Driver activated successfully!';
        }
        
        header("Location: drivers-list.php");
        exit();
    }
}

// Get only verified drivers with online status and active trip info
$query = "SELECT 
    u.*, 
    d.driver_id,
    d.license_number,
    d.tricycle_info,
    d.verification_status, 
    d.is_online,
    COUNT(CASE WHEN b.status = 'accepted' THEN 1 END) as active_trips,
    COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed_trips
FROM rfid_drivers d
INNER JOIN users u ON d.user_id = u.user_id
LEFT JOIN tricycle_bookings b ON d.driver_id = b.driver_id
WHERE d.verification_status = 'verified'
GROUP BY d.driver_id, u.user_id, u.user_type, u.name, u.email, u.phone, u.password, 
         d.license_number, d.tricycle_info, d.verification_status, u.is_verified, u.is_active, 
         u.created_at, u.status, d.is_online
ORDER BY d.is_online DESC, u.user_id DESC";
$result = $conn->query($query);
$drivers = $result->fetch_all(MYSQLI_ASSOC);

// Separate active and suspended drivers
$active_drivers = array_filter($drivers, function($driver) {
    return $driver['status'] === 'active';
});
$suspended_drivers = array_filter($drivers, function($driver) {
    return $driver['status'] === 'suspended';
});

renderAdminHeader("Driver Management", "drivers");
?>
<link rel="stylesheet" href="../../public/css/drivers-list.css">

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
  <?php showAlert('success', $_SESSION['success_message']); ?>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
  <?php showAlert('danger', $_SESSION['error_message']); ?>
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<?php
// Categorize drivers by status (only active drivers)
$online_drivers = [];
$on_trip_drivers = [];
$offline_drivers = [];

foreach ($active_drivers as $driver) {
    if ($driver['active_trips'] > 0) {
        $on_trip_drivers[] = $driver;
    } elseif ($driver['is_online']) {
        $online_drivers[] = $driver;
    } else {
        $offline_drivers[] = $driver;
    }
}

$total_drivers = count($drivers);
$active_count = count($active_drivers);
$suspended_count = count($suspended_drivers);
$online_count = count($online_drivers);
$on_trip_count = count($on_trip_drivers);
$offline_count = count($offline_drivers);
?>

<!-- Stats Summary -->
<div class="row mb-4">
  <div class="col-md-3 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <i class="bi bi-car-front"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $total_drivers ?></div>
        <div class="stat-label">Total Drivers</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
        <i class="bi bi-circle-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $online_count ?></div>
        <div class="stat-label">Online & Available</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <i class="bi bi-car-front-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $on_trip_count ?></div>
        <div class="stat-label">On Trip</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);">
        <i class="bi bi-circle"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $offline_count ?></div>
        <div class="stat-label">Offline</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6 mb-3">
    <div class="stat-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
        <i class="bi bi-person-x"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $suspended_count ?></div>
        <div class="stat-label">Suspended</div>
      </div>
    </div>
  </div>
</div>

<div class="row mb-3">
  <div class="col-md-6">
    <a href="drivers-verification.php" class="btn btn-custom">
      <i class="bi bi-shield-check"></i> Driver Verification
    </a>
  </div>
  <div class="col-md-6 text-end">
    <small class="text-muted">
      <i class="bi bi-info-circle"></i> Only verified drivers are shown here
    </small>
  </div>
</div>

<?php if (count($drivers) > 0): ?>
  
  <!-- Tabbed Interface -->
  <div class="content-card">
    <div class="driver-tabs">
      <button class="tab-btn active" data-tab="all">
        <i class="bi bi-list-ul"></i>
        All Drivers (<?= $total_drivers ?>)
      </button>
      <button class="tab-btn" data-tab="online">
        <i class="bi bi-circle-fill"></i>
        Online (<?= $online_count ?>)
      </button>
      <button class="tab-btn" data-tab="on-trip">
        <i class="bi bi-car-front-fill"></i>
        On Trip (<?= $on_trip_count ?>)
      </button>
      <button class="tab-btn" data-tab="offline">
        <i class="bi bi-circle"></i>
        Offline (<?= $offline_count ?>)
      </button>
      <button class="tab-btn" data-tab="suspended">
        <i class="bi bi-person-x"></i>
        Suspended (<?= $suspended_count ?>)
      </button>
    </div>

    <!-- All Drivers Table -->
    <div class="tab-content active" id="all-tab">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th><i class="bi bi-hash"></i> ID</th>
              <th><i class="bi bi-person"></i> Name</th>
              <th><i class="bi bi-envelope"></i> Email</th>
              <th><i class="bi bi-telephone"></i> Phone</th>
              <th><i class="bi bi-wifi"></i> Online Status</th>
              <th><i class="bi bi-circle"></i> Status</th>
              <th><i class="bi bi-gear"></i> Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($drivers as $driver): ?>
              <?php 
                $has_active = $driver['active_trips'] > 0;
                $is_online = $driver['is_online'];
                
                if ($has_active) {
                  $status_class = 'status-warning';
                  $status_icon = 'car-front-fill';
                  $status_text = 'On Trip';
                } elseif ($is_online) {
                  $status_class = 'status-verified';
                  $status_icon = 'circle-fill';
                  $status_text = 'Online';
                } else {
                  $status_class = 'status-inactive';
                  $status_icon = 'circle';
                  $status_text = 'Offline';
                }
              ?>
              <tr>
                <td><strong>#<?= $driver['user_id'] ?></strong></td>
                <td>
                  <?= htmlspecialchars($driver['name']) ?>
                  <?php if ($has_active): ?>
                    <br><small class="text-muted">
                      <i class="bi bi-info-circle"></i> <?= $driver['active_trips'] ?> active trip(s)
                    </small>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($driver['email']) ?></td>
                <td><?= htmlspecialchars($driver['phone']) ?></td>
                <td>
                  <span class="status-badge <?= $status_class ?>">
                    <i class="bi bi-<?= $status_icon ?>"></i>
                    <?= $status_text ?>
                  </span>
                </td>
                <td>
                  <span class="status-badge status-<?= $driver['status'] ?>">
                    <?= ucfirst($driver['status']) ?>
                  </span>
                </td>
                <td>
                  <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="action-btn">
                    <i class="bi bi-eye"></i> View
                  </a>
                  <?php if ($driver['status'] === 'active'): ?>
                    <button type="button" class="action-btn btn-danger" 
                            onclick="suspendDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                      <i class="bi bi-person-x"></i> Suspend
                    </button>
                  <?php elseif ($driver['status'] === 'suspended'): ?>
                    <button type="button" class="action-btn btn-success" 
                            onclick="activateDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                      <i class="bi bi-person-check"></i> Activate
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Online Drivers Table -->
    <div class="tab-content" id="online-tab">
      <?php if (count($online_drivers) > 0): ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th><i class="bi bi-hash"></i> ID</th>
                <th><i class="bi bi-person"></i> Name</th>
                <th><i class="bi bi-envelope"></i> Email</th>
                <th><i class="bi bi-telephone"></i> Phone</th>
                <th><i class="bi bi-circle"></i> Status</th>
                <th><i class="bi bi-gear"></i> Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($online_drivers as $driver): ?>
                <tr>
                  <td><strong>#<?= $driver['user_id'] ?></strong></td>
                  <td><?= htmlspecialchars($driver['name']) ?></td>
                  <td><?= htmlspecialchars($driver['email']) ?></td>
                  <td><?= htmlspecialchars($driver['phone']) ?></td>
                  <td>
                    <span class="status-badge status-<?= $driver['status'] ?>">
                      <?= ucfirst($driver['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="action-btn">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <?php if ($driver['status'] === 'active'): ?>
                      
                        
                        
                        <button type="button" class="action-btn btn-danger" 
                                onclick="suspendDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-x"></i> Suspend
                        </button>
                      
                    <?php elseif ($driver['status'] === 'suspended'): ?>
                      
                        
                        
                        <button type="button" class="action-btn" 
                                onclick="activateDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-check"></i> Activate
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
          <i class="bi bi-circle-fill"></i>
          <h5>No Online Drivers</h5>
          <p>There are currently no drivers online and available.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- On Trip Drivers Table -->
    <div class="tab-content" id="on-trip-tab">
      <?php if (count($on_trip_drivers) > 0): ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th><i class="bi bi-hash"></i> ID</th>
                <th><i class="bi bi-person"></i> Name</th>
                <th><i class="bi bi-envelope"></i> Email</th>
                <th><i class="bi bi-telephone"></i> Phone</th>
                <th><i class="bi bi-car-front"></i> Active Trips</th>
                <th><i class="bi bi-circle"></i> Status</th>
                <th><i class="bi bi-gear"></i> Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($on_trip_drivers as $driver): ?>
                <tr>
                  <td><strong>#<?= $driver['user_id'] ?></strong></td>
                  <td><?= htmlspecialchars($driver['name']) ?></td>
                  <td><?= htmlspecialchars($driver['email']) ?></td>
                  <td><?= htmlspecialchars($driver['phone']) ?></td>
                  <td>
                    <span class="badge bg-warning text-dark">
                      <i class="bi bi-car-front-fill"></i> <?= $driver['active_trips'] ?> trip(s)
                    </span>
                  </td>
                  <td>
                    <span class="status-badge status-<?= $driver['status'] ?>">
                      <?= ucfirst($driver['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="action-btn">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <?php if ($driver['status'] === 'active'): ?>
                      
                        
                        
                        <button type="button" class="action-btn btn-danger" 
                                onclick="suspendDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-x"></i> Suspend
                        </button>
                      
                    <?php elseif ($driver['status'] === 'suspended'): ?>
                      
                        
                        
                        <button type="button" class="action-btn" 
                                onclick="activateDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-check"></i> Activate
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
          <i class="bi bi-car-front-fill"></i>
          <h5>No Drivers On Trip</h5>
          <p>There are currently no drivers on active trips.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Offline Drivers Table -->
    <div class="tab-content" id="offline-tab">
      <?php if (count($offline_drivers) > 0): ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th><i class="bi bi-hash"></i> ID</th>
                <th><i class="bi bi-person"></i> Name</th>
                <th><i class="bi bi-envelope"></i> Email</th>
                <th><i class="bi bi-telephone"></i> Phone</th>
                <th><i class="bi bi-circle"></i> Status</th>
                <th><i class="bi bi-gear"></i> Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($offline_drivers as $driver): ?>
                <tr>
                  <td><strong>#<?= $driver['user_id'] ?></strong></td>
                  <td><?= htmlspecialchars($driver['name']) ?></td>
                  <td><?= htmlspecialchars($driver['email']) ?></td>
                  <td><?= htmlspecialchars($driver['phone']) ?></td>
                  <td>
                    <span class="status-badge status-<?= $driver['status'] ?>">
                      <?= ucfirst($driver['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="action-btn">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <?php if ($driver['status'] === 'active'): ?>
                      
                        
                        
                        <button type="button" class="action-btn btn-danger" 
                                onclick="suspendDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-x"></i> Suspend
                        </button>
                      
                    <?php elseif ($driver['status'] === 'suspended'): ?>
                      
                        
                        
                        <button type="button" class="action-btn" 
                                onclick="activateDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                          <i class="bi bi-person-check"></i> Activate
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
          <i class="bi bi-circle"></i>
          <h5>No Offline Drivers</h5>
          <p>All verified drivers are currently online or on trip.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Suspended Drivers Table -->
    <div class="tab-content" id="suspended-tab">
      <?php if (count($suspended_drivers) > 0): ?>
        <div class="alert alert-warning mb-3">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <strong>Suspended Drivers:</strong> These drivers have been temporarily suspended and cannot accept new bookings.
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th><i class="bi bi-hash"></i> ID</th>
                <th><i class="bi bi-person"></i> Name</th>
                <th><i class="bi bi-envelope"></i> Email</th>
                <th><i class="bi bi-telephone"></i> Phone</th>
                <th><i class="bi bi-calendar"></i> Suspended Date</th>
                <th><i class="bi bi-gear"></i> Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($suspended_drivers as $driver): ?>
                <tr style="background-color: #fef2f2;">
                  <td><strong>#<?= $driver['user_id'] ?></strong></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-person-x text-danger"></i>
                      <?= htmlspecialchars($driver['name']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($driver['email']) ?></td>
                  <td><?= htmlspecialchars($driver['phone']) ?></td>
                  <td>
                    <small class="text-muted">
                      <i class="bi bi-clock"></i>
                      <?= date('M d, Y', strtotime($driver['created_at'])) ?>
                    </small>
                  </td>
                  <td>
                    <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="action-btn">
                      <i class="bi bi-eye"></i> View
                    </a>
                    <button type="button" class="action-btn btn-success" 
                            onclick="activateDriver(<?= $driver['user_id'] ?>, '<?= htmlspecialchars(addslashes($driver['name'])) ?>', this)">
                      <i class="bi bi-person-check"></i> Activate
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-check-circle text-success"></i>
          <h5>No Suspended Drivers</h5>
          <p>All verified drivers are currently active. Good job!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <div class="content-card">
    <div class="empty-state">
      <i class="bi bi-car-front"></i>
      <h5>No Verified Drivers Found</h5>
      <p>Verified drivers will appear here once they are approved.</p>
      <a href="drivers-verification.php" class="btn btn-custom mt-2">
        <i class="bi bi-shield-check"></i> Check Pending Verifications
      </a>
    </div>
  </div>
<?php endif; ?>

<div class="refresh-indicator" id="refreshIndicator" style="display: none;">
  <i class="bi bi-arrow-clockwise"></i>
  <span>Refreshing in <span id="countdown">30</span>s</span>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(button => {
  button.addEventListener('click', () => {
    const tabName = button.getAttribute('data-tab');
    
    // Remove active class from all buttons and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked button and corresponding content
    button.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
  });
});

// Auto-refresh every 30 seconds
let countdown = 30;
const refreshIndicator = document.getElementById('refreshIndicator');
const countdownElement = document.getElementById('countdown');

function updateCountdown() {
  countdown--;
  countdownElement.textContent = countdown;
  
  if (countdown <= 5) {
    refreshIndicator.style.display = 'flex';
  }
  
  if (countdown <= 0) {
    location.reload();
  }
}

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
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

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999;';
    document.body.appendChild(container);
    return container;
}

// Suspend driver
function suspendDriver(userId, userName, button) {
    if (!confirm(`Are you sure you want to suspend ${userName}?`)) return;
    
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('action', 'suspend_user');
    formData.append('user_id', userId);
    
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
            button.innerHTML = '<i class="bi bi-person-x"></i> Suspend';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-person-x"></i> Suspend';
        console.error('Error:', error);
    });
}

// Activate driver
function activateDriver(userId, userName, button) {
    if (!confirm(`Are you sure you want to activate ${userName}?`)) return;
    
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('action', 'activate_user');
    formData.append('user_id', userId);
    
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
            button.innerHTML = '<i class="bi bi-person-check"></i> Activate';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-person-check"></i> Activate';
        console.error('Error:', error);
    });
}

// Add animations CSS
const animStyle = document.createElement('style');
animStyle.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(animStyle);

// Show indicator in last 5 seconds
setTimeout(() => {
  setInterval(updateCountdown, 1000);
}, 25000);

// Start countdown after 25 seconds
setInterval(() => {
  countdown = 30;
  refreshIndicator.style.display = 'none';
}, 30000);
</script>

<?php renderAdminFooter(); ?>