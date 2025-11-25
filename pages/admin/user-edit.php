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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    $userType = $_POST['user_type'];
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone is required";
    }
    
    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email is already taken by another user";
    }
    $stmt->close();
    
    if (empty($errors)) {
        // Get old values for comparison
        $oldStmt = $conn->prepare("SELECT name, email, phone, status, user_type FROM users WHERE user_id = ?");
        $oldStmt->bind_param("i", $userId);
        $oldStmt->execute();
        $oldData = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();
        
        // Update user
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, status = ?, user_type = ? WHERE user_id = ?");
        $stmt->bind_param("sssssi", $name, $email, $phone, $status, $userType, $userId);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Build change details
            $changes = [];
            if ($oldData['name'] != $name) $changes[] = "name: '{$oldData['name']}' → '{$name}'";
            if ($oldData['email'] != $email) $changes[] = "email: '{$oldData['email']}' → '{$email}'";
            if ($oldData['phone'] != $phone) $changes[] = "phone: '{$oldData['phone']}' → '{$phone}'";
            if ($oldData['status'] != $status) $changes[] = "status: '{$oldData['status']}' → '{$status}'";
            if ($oldData['user_type'] != $userType) $changes[] = "type: '{$oldData['user_type']}' → '{$userType}'";
            
            if (!empty($changes)) {
                // Log to admin_action_logs
                $adminId = $_SESSION['user_id'];
                $actionType = 'user_edit';
                $actionDetails = "Updated user: {$name} (ID: {$userId}). Changes: " . implode(', ', $changes);
                
                $logStmt = $conn->prepare("INSERT INTO admin_action_logs (admin_id, action_type, target_user_id, action_details) VALUES (?, ?, ?, ?)");
                $logStmt->bind_param("isis", $adminId, $actionType, $userId, $actionDetails);
                $logStmt->execute();
                $logStmt->close();
            }
            
            $_SESSION['success_message'] = 'User information updated successfully!';
            header("Location: user-details.php?id=$userId");
            exit;
        } else {
            $errors[] = "Failed to update user: " . $conn->error;
        }
        $stmt->close();
    }
}

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

renderAdminHeader("Edit User - " . htmlspecialchars($user['name']), "users");
?>

<!-- Success/Error Messages -->
<?php if (isset($errors) && count($errors) > 0): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <strong><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following errors:</strong>
    <ul class="mb-0 mt-2">
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Edit User Form -->
<div class="content-card">
  <h3>
    <i class="bi bi-person-gear"></i>
    Edit User Information
  </h3>
  
  <form method="POST" action="">
    <div class="row">
      <div class="col-md-12 mb-3">
        <label for="name" class="form-label">
          <i class="bi bi-person"></i> Full Name <span class="text-danger">*</span>
        </label>
        <input type="text" 
               class="form-control" 
               id="name" 
               name="name" 
               value="<?= htmlspecialchars($user['name']) ?>" 
               required>
      </div>

      <div class="col-md-6 mb-3">
        <label for="email" class="form-label">
          <i class="bi bi-envelope"></i> Email Address <span class="text-danger">*</span>
        </label>
        <input type="email" 
               class="form-control" 
               id="email" 
               name="email" 
               value="<?= htmlspecialchars($user['email']) ?>" 
               required>
      </div>

      <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">
          <i class="bi bi-phone"></i> Phone Number <span class="text-danger">*</span>
        </label>
        <input type="text" 
               class="form-control" 
               id="phone" 
               name="phone" 
               value="<?= htmlspecialchars($user['phone']) ?>" 
               required>
      </div>

      <div class="col-md-6 mb-3">
        <label for="user_type" class="form-label">
          <i class="bi bi-person-badge"></i> User Type <span class="text-danger">*</span>
        </label>
        <select class="form-select" id="user_type" name="user_type" required>
          <option value="passenger" <?= $user['user_type'] === 'passenger' ? 'selected' : '' ?>>Passenger</option>
          <option value="driver" <?= $user['user_type'] === 'driver' ? 'selected' : '' ?>>Driver</option>
        </select>
        <small class="text-muted">
          <i class="bi bi-info-circle"></i> Changing user type may affect access to features
        </small>
      </div>

      <div class="col-md-6 mb-3">
        <label for="status" class="form-label">
          <i class="bi bi-toggle-on"></i> Account Status <span class="text-danger">*</span>
        </label>
        <select class="form-select" id="status" name="status" required>
          <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <small class="text-muted">
          <i class="bi bi-info-circle"></i> Inactive/Suspended users cannot log in
        </small>
      </div>

      <div class="col-md-12">
        <div class="alert alert-info">
          <i class="bi bi-info-circle-fill"></i> 
          <strong>Note:</strong> User ID: #<?= $user['user_id'] ?> | 
          Registered: <?= date('F d, Y', strtotime($user['created_at'])) ?>
        </div>
      </div>
    </div>

    <div class="d-flex gap-3 justify-content-end mt-4">
      <a href="user-details.php?id=<?= $userId ?>" class="btn btn-secondary">
        <i class="bi bi-x-circle"></i> Cancel
      </a>
      <button type="submit" class="btn btn-custom">
        <i class="bi bi-check-circle-fill"></i> Save Changes
      </button>
    </div>
  </form>
</div>

<!-- Danger Zone -->
<div class="content-card mt-4" style="border: 2px solid #dc3545;">
  <h3 style="color: #dc3545;">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Danger Zone
  </h3>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <strong style="font-size: 16px;">Delete User Account</strong>
      <p class="text-muted mb-0">Once deleted, this user's data cannot be recovered.</p>
    </div>
    <a href="user-delete-handler.php?id=<?= $userId ?>" 
       class="btn btn-danger"
       onclick="return confirm('⚠️ WARNING: This will permanently delete the user and all associated data!\n\nUser: <?= htmlspecialchars($user['name']) ?>\nEmail: <?= htmlspecialchars($user['email']) ?>\n\nAre you absolutely sure?')">
      <i class="bi bi-trash-fill"></i> Delete Account
    </a>
  </div>
</div>

<?php 
renderAdminFooter();
$db->closeConnection();
?>
