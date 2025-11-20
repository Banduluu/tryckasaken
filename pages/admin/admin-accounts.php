<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$database = new Database();
$conn = $database->getConnection();

// Get admins
$adminsQuery = "SELECT * FROM users WHERE user_type = 'admin' ORDER BY created_at DESC";
$adminsResult = $conn->query($adminsQuery);
$admins = $adminsResult->fetch_all(MYSQLI_ASSOC);

$conn->close();

renderAdminHeader("Admin Management", "admin_management");
?>
<link rel="stylesheet" href="../../public/css/admin-accounts.css">

<!-- Main Content -->
<div class="content-card">
  <h3>
    <i class="bi bi-gear"></i>
    Admin Management (<?= count($admins) ?>)
  </h3>
  
  <div class="row mb-3">
    <div class="col-md-6">
      <p class="text-muted">
        <i class="bi bi-info-circle"></i> Manage admin accounts and permissions
      </p>
    </div>
    <div class="col-md-6 text-end">
      <a href="passengers-list.php" class="btn btn-outline-secondary">
        <i class="bi bi-people"></i> View Passengers
      </a>
    </div>
  </div>
  
  <?php if (count($admins) > 0): ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><i class="bi bi-hash"></i> ID</th>
            <th><i class="bi bi-person"></i> Name</th>
            <th><i class="bi bi-envelope"></i> Email</th>
            <th><i class="bi bi-telephone"></i> Phone</th>
            <th><i class="bi bi-circle"></i> Status</th>
            <th><i class="bi bi-calendar"></i> Created</th>
            <th><i class="bi bi-gear"></i> Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $user): ?>
            <tr>
              <td><strong>#<?= $user['user_id'] ?></strong></td>
              <td>
                <?= htmlspecialchars($user['name']) ?>
                <span class="badge bg-primary ms-2">
                  <i class="bi bi-gear"></i> Admin
                </span>
              </td>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= htmlspecialchars($user['phone']) ?></td>
              <td>
                <span class="status-badge status-<?= $user['status'] ?>">
                  <?= ucfirst($user['status']) ?>
                </span>
              </td>
              <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
              <td>
                <a href="user-details.php?id=<?= $user['user_id'] ?>" class="action-btn">
                  <i class="bi bi-eye"></i> View
                </a>
                <a href="user-edit.php?id=<?= $user['user_id'] ?>" class="action-btn btn-warning">
                  <i class="bi bi-pencil"></i> Edit
                </a>
                <?php if ($user['status'] === 'active' && $user['user_id'] != $_SESSION['admin_id']): ?>
                  <a href="user-suspend-handler.php?id=<?= $user['user_id'] ?>" class="action-btn btn-danger" 
                     onclick="return confirm('Are you sure you want to suspend this admin?')">
                    <i class="bi bi-person-x"></i> Suspend
                  </a>
                <?php elseif ($user['user_id'] == $_SESSION['admin_id']): ?>
                  <span class="badge bg-info">
                    <i class="bi bi-person-check"></i> You
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-gear"></i>
      <h5>No Admins Found</h5>
      <p>Admin accounts will appear here.</p>
    </div>
  <?php endif; ?>
</div>

<?php renderAdminFooter(); ?>