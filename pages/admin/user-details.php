<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

if (!isset($_GET['id'])) {
    header('Location: passengers-list.php');
    exit;
}

$userId = intval($_GET['id']);

$db = new Database();
$conn = $db->getConnection();

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error_message'] = 'User not found';
    header('Location: passengers-list.php');
    exit;
}

// Get driver information if user is a driver
$driverInfo = null;
if ($user['user_type'] === 'driver') {
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $driverInfo = $result->fetch_assoc();
    $stmt->close();
}

// Get booking history
$bookingsQuery = "SELECT * FROM tricycle_bookings WHERE ";
if ($user['user_type'] === 'passenger') {
    $bookingsQuery .= "user_id = ?";
} else {
    $bookingsQuery .= "driver_id = ?";
}
$bookingsQuery .= " ORDER BY booking_time DESC LIMIT 10";

$stmt = $conn->prepare($bookingsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get booking statistics
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
FROM tricycle_bookings WHERE ";

if ($user['user_type'] === 'passenger') {
    $statsQuery .= "user_id = ?";
} else {
    $statsQuery .= "driver_id = ?";
}

$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

renderAdminHeader("View User - " . htmlspecialchars($user['name']), "users");
?>
<link rel="stylesheet" href="../../public/css/user-details.css">

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
  <?php showAlert('success', $_SESSION['success_message']); ?>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
  <?php showAlert('danger', $_SESSION['error_message']); ?>
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- User Profile -->
<div class="row">
  <div class="col-lg-4">
    <!-- Profile Card -->
    <div class="content-card mb-4">
      <div class="text-center">
        <div style="width: 120px; height: 120px; margin: 0 auto 20px; background: linear-gradient(135deg, #16a34a, #22c55e); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 48px;">
          <?php if ($user['user_type'] === 'driver'): ?>
            🚗
          <?php else: ?>
            👤
          <?php endif; ?>
        </div>
        <h4><?= htmlspecialchars($user['name']) ?></h4>
        <p class="text-muted mb-2">
          <span class="badge bg-<?= $user['user_type'] === 'driver' ? 'success' : 'primary' ?>" style="font-size: 14px;">
            <?= ucfirst($user['user_type']) ?>
          </span>
        </p>
        <p class="text-muted mb-3">
          <span class="status-badge status-<?= $user['status'] ?>">
            <?= ucfirst($user['status']) ?>
          </span>
        </p>
        
        <div class="d-flex flex-wrap gap-2 justify-content-center">
          <a href="user-edit.php?id=<?= $user['user_id'] ?>" class="action-btn">
            <i class="bi bi-pencil"></i> Edit
          </a>
          <?php if ($user['status'] === 'active'): ?>
            <form method="POST" action="user-suspend-handler.php?id=<?= $user['user_id'] ?>" style="display: inline;">
              <button type="submit" class="action-btn btn-warning" onclick="return confirm('Suspend this user?')">
                <i class="bi bi-pause-circle"></i> Suspend
              </button>
            </form>
          <?php else: ?>
            <form method="POST" action="user-suspend-handler.php?id=<?= $user['user_id'] ?>&action=activate" style="display: inline;">
              <button type="submit" class="action-btn" onclick="return confirm('Activate this user?')">
                <i class="bi bi-play-circle"></i> Activate
              </button>
            </form>
          <?php endif; ?>
          <a href="user-delete-handler.php?id=<?= $user['user_id'] ?>" class="action-btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
            <i class="bi bi-trash"></i> Delete
          </a>
        </div>
      </div>
    </div>

    <!-- Statistics Card -->
    <div class="content-card">
      <h5><i class="bi bi-graph-up"></i> Booking Statistics</h5>
      <div class="stat-row">
        <span class="stat-label">📊 Total Bookings</span>
        <span class="stat-value"><?= $stats['total_bookings'] ?></span>
      </div>
      <div class="stat-row">
        <span class="stat-label">✅ Completed</span>
        <span class="stat-value"><?= $stats['completed_bookings'] ?></span>
      </div>
      <div class="stat-row">
        <span class="stat-label">⏳ Pending</span>
        <span class="stat-value"><?= $stats['pending_bookings'] ?></span>
      </div>
      <div class="stat-row">
        <span class="stat-label">❌ Cancelled</span>
        <span class="stat-value"><?= $stats['cancelled_bookings'] ?></span>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Personal Information -->
    <div class="content-card mb-4">
      <h5><i class="bi bi-info-circle"></i> Personal Information</h5>
      <div class="info-grid">
        <div class="info-item">
          <span class="info-label"><i class="bi bi-hash"></i> User ID</span>
          <span class="info-value">#<?= $user['user_id'] ?></span>
        </div>
        <div class="info-item">
          <span class="info-label"><i class="bi bi-person"></i> Full Name</span>
          <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label"><i class="bi bi-envelope"></i> Email</span>
          <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label"><i class="bi bi-phone"></i> Phone</span>
          <span class="info-value"><?= htmlspecialchars($user['phone']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label"><i class="bi bi-calendar"></i> Registered Date</span>
          <span class="info-value"><?= date('F d, Y', strtotime($user['created_at'])) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label"><i class="bi bi-clock"></i> Account Age</span>
          <span class="info-value">
            <?php
            $accountAge = floor((time() - strtotime($user['created_at'])) / 86400);
            echo $accountAge . ' day' . ($accountAge != 1 ? 's' : '');
            ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Driver Information (if driver) -->
    <?php if ($user['user_type'] === 'driver' && $driverInfo): ?>
      <div class="content-card mb-4">
        <h5><i class="bi bi-shield-check"></i> Driver Information</h5>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label"><i class="bi bi-hash"></i> Driver ID</span>
            <span class="info-value">#<?= $driverInfo['driver_id'] ?></span>
          </div>
          <div class="info-item">
            <span class="info-label"><i class="bi bi-patch-check"></i> Verification Status</span>
            <span class="info-value">
              <span class="status-badge status-<?= $driverInfo['verification_status'] ?>">
                <?= ucfirst($driverInfo['verification_status']) ?>
              </span>
            </span>
          </div>
        </div>
        
        <!-- Documents -->
        <div class="mt-4">
          <h6 class="mb-3"><i class="bi bi-file-earmark-text"></i> Uploaded Documents</h6>
          <div class="row g-3">
            <?php if ($driverInfo['picture_path']): ?>
              <div class="col-md-4">
                <div class="text-center">
                  <img src="../../<?= htmlspecialchars($driverInfo['picture_path']) ?>" 
                       alt="Driver Photo" 
                       class="document-thumbnail"
                       onclick="viewDocument('../../<?= htmlspecialchars($driverInfo['picture_path']) ?>', 'Driver Photo')">
                  <p class="mt-2 mb-0 text-muted">Driver Photo</p>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if ($driverInfo['license_path']): ?>
              <div class="col-md-4">
                <div class="text-center">
                  <img src="../../<?= htmlspecialchars($driverInfo['license_path']) ?>" 
                       alt="License" 
                       class="document-thumbnail"
                       onclick="viewDocument('../../<?= htmlspecialchars($driverInfo['license_path']) ?>', 'Driver License')">
                  <p class="mt-2 mb-0 text-muted">Driver's License</p>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if ($driverInfo['or_cr_path']): ?>
              <div class="col-md-4">
                <div class="text-center">
                  <img src="../../<?= htmlspecialchars($driverInfo['or_cr_path']) ?>" 
                       alt="OR/CR" 
                       class="document-thumbnail"
                       onclick="viewDocument('../../<?= htmlspecialchars($driverInfo['or_cr_path']) ?>', 'OR/CR Document')">
                  <p class="mt-2 mb-0 text-muted">OR/CR</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Booking History -->
    <div class="content-card">
      <h5><i class="bi bi-clock-history"></i> Recent Booking History</h5>
      <?php if (count($bookings) > 0): ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>From</th>
                <th>To</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td><strong>#<?= $booking['id'] ?></strong></td>
                  <td><?= htmlspecialchars($booking['location']) ?></td>
                  <td><?= htmlspecialchars($booking['destination']) ?></td>
                  <td><?= date('M d, Y H:i', strtotime($booking['booking_time'])) ?></td>
                  <td>
                    <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                      <?= ucfirst($booking['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="text-center mt-3">
          <a href="bookings-list.php?user_id=<?= $user['user_id'] ?>" class="btn btn-custom">
            <i class="bi bi-list"></i> View All Bookings
          </a>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="bi bi-clock-history"></i>
          <p>No booking history available.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Document View Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="documentTitle">Document Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="documentImage" src="" alt="Document" style="max-width: 100%; height: auto; border-radius: 8px;">
      </div>
      <div class="modal-footer">
        <a id="downloadLink" href="" download class="btn btn-custom">
          <i class="bi bi-download"></i> Download
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewDocument(path, title) {
  document.getElementById('documentTitle').textContent = title;
  document.getElementById('documentImage').src = path;
  document.getElementById('downloadLink').href = path;
  
  const modal = new bootstrap.Modal(document.getElementById('documentModal'));
  modal.show();
}
</script>

<?php 
renderAdminFooter();
$db->closeConnection();
?>
