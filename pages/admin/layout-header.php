<?php
// Admin Layout Header - Include this at the top of admin pages
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

function renderAdminHeader($pageTitle = "Admin Dashboard", $currentPage = "dashboard") {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> | TrycKaSaken</title>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../../public/css/style.css">
  <link rel="stylesheet" href="../../public/css/layout-header.css">
  
</head>
<body>

<!-- Admin Header -->
<div class="admin-header">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h2 class="mb-1">
          <i class="bi bi-speedometer2"></i> <?= htmlspecialchars($pageTitle) ?>
        </h2>
      </div>
      <div class="d-flex gap-2">

        <!-- ✅ ADDED MESSAGES BUTTON HERE -->
        <a href="../../pages/admin/messages.php" class="btn btn-primary btn-sm">
          <i class="bi bi-chat-dots"></i> Messages
        </a>

        <!-- LOGOUT BUTTON -->
        <a href="../../pages/auth/logout-handler.php" class="btn btn-light btn-sm">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>

      </div>
    </div>
  </div>
</div>

<div class="container">
  <!-- Navigation Menu -->
  <div class="admin-nav">
    <div class="row">
      <div class="col-md-4">
        <div class="nav-section">
          <h5><i class="bi bi-house"></i> Main</h5>
          <div class="nav-links">
            <a href="dashboard.php" class="nav-link-btn <?= $currentPage === 'admin' || $currentPage === 'dashboard' ? 'active' : '' ?>">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="bookings-list.php" class="nav-link-btn <?= $currentPage === 'bookings' ? 'active' : '' ?>">
              <i class="bi bi-calendar-check"></i> Bookings
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="nav-section">
          <h5><i class="bi bi-people"></i> Management</h5>
          <div class="nav-links">
            <a href="passengers-list.php" class="nav-link-btn <?= $currentPage === 'users' ? 'active' : '' ?>">
              <i class="bi bi-person"></i> Passengers
            </a>
            <a href="admin-accounts.php" class="nav-link-btn <?= $currentPage === 'admin_management' ? 'active' : '' ?>">
              <i class="bi bi-gear"></i> Admins
            </a>
            <a href="drivers-list.php" class="nav-link-btn <?= $currentPage === 'drivers' || $currentPage === 'driver_management' ? 'active' : '' ?>">
              <i class="bi bi-car-front"></i> Drivers
            </a>
            <a href="drivers-verification.php" class="nav-link-btn <?= $currentPage === 'verification' || $currentPage === 'driver_verification' ? 'active' : '' ?>">
              <i class="bi bi-shield-check"></i> Verification
            </a>
            <a href="rfid-management.php" class="nav-link-btn <?= $currentPage === 'rfid' ? 'active' : '' ?>">
              <i class="bi bi-credit-card-2-front"></i> RFID Cards
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="nav-section">
          <h5><i class="bi bi-graph-up"></i> Analytics & Logs</h5>
          <div class="nav-links">
            <a href="analytics-dashboard.php" class="nav-link-btn <?= $currentPage === 'analytics' ? 'active' : '' ?>">
              <i class="bi bi-bar-chart"></i> Analytics
            </a>
            <a href="action-logs.php" class="nav-link-btn <?= $currentPage === 'logs' ? 'active' : '' ?>">
              <i class="bi bi-journal-text"></i> Action Logs
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php
}

function renderAdminFooter() {
?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function showAlert($type, $message) {
  $icon = '';
  switch($type) {
    case 'success': $icon = 'bi-check-circle-fill'; break;
    case 'danger': $icon = 'bi-exclamation-triangle-fill'; break;
    case 'warning': $icon = 'bi-exclamation-triangle'; break;
    default: $icon = 'bi-info-circle-fill'; break;
  }
  
  echo '<div class="alert-custom alert-' . $type . '">';
  echo '<i class="bi ' . $icon . ' me-2"></i>';
  echo '<strong>' . ucfirst($type) . ':</strong> ' . htmlspecialchars($message);
  echo '</div>';
}
?>
