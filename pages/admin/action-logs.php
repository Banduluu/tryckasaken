<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../auth/login-form.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filter by action type
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$whereClause = $actionFilter ? "WHERE al.action_type = ?" : "";

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM admin_action_logs al $whereClause";
if ($actionFilter) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("s", $actionFilter);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
} else {
    $countResult = $conn->query($countQuery);
}
$totalLogs = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $perPage);

// Get logs with admin and target user names
$query = "SELECT 
            al.log_id,
            al.action_type,
            al.action_details,
            al.created_at,
            u1.name AS admin_name,
            u2.name AS target_name
          FROM admin_action_logs al
          JOIN users u1 ON al.admin_id = u1.user_id
          LEFT JOIN users u2 ON al.target_user_id = u2.user_id
          $whereClause
          ORDER BY al.created_at DESC
          LIMIT ? OFFSET ?";

if ($actionFilter) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $actionFilter, $perPage, $offset);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $perPage, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get action types for filter
$typesQuery = "SELECT DISTINCT action_type FROM admin_action_logs ORDER BY action_type";
$typesResult = $conn->query($typesQuery);
$actionTypes = $typesResult->fetch_all(MYSQLI_ASSOC);

renderAdminHeader("Admin Action Logs", "logs");
?>
<link rel="stylesheet" href="../../public/css/rfid-management.css">

<div class="container-fluid">
  <!-- Filter and Stats -->
  <div class="row mb-4">
    <div class="col-md-8">
      <div class="card">
        <div class="card-body">
          <form method="GET" class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Filter by Action Type</label>
              <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <?php foreach ($actionTypes as $type): ?>
                  <option value="<?= htmlspecialchars($type['action_type']) ?>" 
                          <?= $actionFilter === $type['action_type'] ? 'selected' : '' ?>>
                    <?= ucwords(str_replace('_', ' ', $type['action_type'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <?php if ($actionFilter): ?>
                <a href="action-logs.php" class="btn btn-secondary">
                  <i class="bi bi-x-circle"></i> Clear Filter
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon bg-info">
          <i class="bi bi-journal-text"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value"><?= number_format($totalLogs) ?></div>
          <div class="stat-label">Total Logs</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Logs Table -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Log ID</th>
              <th>Timestamp</th>
              <th>Admin</th>
              <th>Action Type</th>
              <th>Target User</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($logs)): ?>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= $log['log_id'] ?></td>
                  <td>
                    <small><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></small>
                  </td>
                  <td>
                    <span class="badge bg-primary">
                      <i class="bi bi-person-badge"></i> <?= htmlspecialchars($log['admin_name']) ?>
                    </span>
                  </td>
                  <td>
                    <?php
                    $actionClass = 'secondary';
                    if (strpos($log['action_type'], 'block') !== false) {
                      $actionClass = 'danger';
                    } elseif (strpos($log['action_type'], 'unblock') !== false) {
                      $actionClass = 'success';
                    } elseif (strpos($log['action_type'], 'rfid') !== false) {
                      $actionClass = 'info';
                    }
                    ?>
                    <span class="badge bg-<?= $actionClass ?>">
                      <?= ucwords(str_replace('_', ' ', $log['action_type'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($log['target_name']): ?>
                      <i class="bi bi-person"></i> <?= htmlspecialchars($log['target_name']) ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <small><?= htmlspecialchars($log['action_details']) ?></small>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4">
                  <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                  <p class="text-muted mt-2">No action logs found.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page - 1 ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>">
                <i class="bi bi-chevron-left"></i> Previous
              </a>
            </li>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page + 1 ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>">
                Next <i class="bi bi-chevron-right"></i>
              </a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php 
renderAdminFooter();
$db->closeConnection();
?>
