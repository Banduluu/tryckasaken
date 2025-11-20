<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$database = new Database();
$conn = $database->getConnection();

// Get passengers
$passengersQuery = "SELECT * FROM users WHERE user_type = 'passenger' ORDER BY created_at DESC";
$passengersResult = $conn->query($passengersQuery);
$passengers = $passengersResult->fetch_all(MYSQLI_ASSOC);

$conn->close();

renderAdminHeader("Passenger Management", "users");
?>

<!-- Main Content -->
<div class="content-card">
  <h3>
    <i class="bi bi-people"></i>
    Passenger Management (<?= count($passengers) ?>)
  </h3>
  
  <div class="row mb-3">
    <div class="col-md-6">
      <p class="text-muted">
        <i class="bi bi-info-circle"></i> Manage passenger accounts and bookings
      </p>
    </div>
    <div class="col-md-6 text-end">
      <a href="admin-accounts.php" class="btn btn-outline-secondary">
        <i class="bi bi-gear"></i> Manage Admins
      </a>
    </div>
  </div>
  
  <?php if (count($passengers) > 0): ?>
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
          <?php foreach ($passengers as $user): ?>
            <tr>
              <td><strong>#<?= $user['user_id'] ?></strong></td>
              <td><?= htmlspecialchars($user['name']) ?></td>
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
                <?php if ($user['status'] === 'active'): ?>
                  <button type="button" class="action-btn btn-danger" 
                          onclick="suspendUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', this)">
                    <i class="bi bi-person-x"></i> Suspend
                  </button>
                <?php else: ?>
                  <button type="button" class="action-btn btn-success" 
                          onclick="activateUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', this)">
                    <i class="bi bi-person-check"></i> Activate
                  </button>
                <?php endif; ?>
                <button type="button" class="action-btn btn-dark" 
                        onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', this)">
                  <i class="bi bi-trash"></i> Delete
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-person"></i>
      <h5>No Passengers Found</h5>
      <p>Passengers will appear here once they register.</p>
    </div>
  <?php endif; ?>
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

// Suspend user
function suspendUser(userId, userName, button) {
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

// Activate user
function activateUser(userId, userName, button) {
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

// Delete user
function deleteUser(userId, userName, button) {
    if (!confirm(`⚠️ WARNING: Are you sure you want to permanently delete ${userName}? This action cannot be undone!`)) return;
    
    // Double confirmation for delete
    if (!confirm('This will delete all associated data including bookings. Type YES to confirm.')) return;
    
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    fetch('api-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Fade out the row
            const row = button.closest('tr');
            row.style.animation = 'fadeOut 0.5s ease-out';
            setTimeout(() => {
                row.remove();
                // Reload if no users left
                if (document.querySelectorAll('tbody tr').length === 0) {
                    location.reload();
                }
            }, 500);
        } else {
            showToast(data.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-trash"></i> Delete';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-trash"></i> Delete';
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
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
`;
document.head.appendChild(style);
</script>

<?php renderAdminFooter(); ?>


