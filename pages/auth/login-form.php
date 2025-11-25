<?php
session_start();
require_once '../../config/Database.php';

$error = '';
$logged_in_user_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $requested_user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 'passenger';
    
    $sql = "SELECT user_id, user_type, name, email, password, status FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            if ($user['status'] != 'active') {
                $error = "Your account has been suspended or deactivated.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_type'] = $user['user_type']; 
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $logged_in_user_type = $user['user_type'];
                
                // Determine redirect URL based on actual user type in database
                $redirect_url = '';
                if ($user['user_type'] == 'driver') {
                    $driver_sql = "SELECT verification_status FROM rfid_drivers WHERE user_id = ?";
                    $driver_stmt = $conn->prepare($driver_sql);
                    $driver_stmt->bind_param("i", $user['user_id']);
                    $driver_stmt->execute();
                    $driver_result = $driver_stmt->get_result();
                    
                    if ($driver_result->num_rows > 0) {
                        $driver = $driver_result->fetch_assoc();
                        $_SESSION['verification_status'] = $driver['verification_status'];
                    }
                    
                    $driver_stmt->close();
                    $redirect_url = '../../pages/driver/login-form.php';
                } elseif ($user['user_type'] == 'admin') {
                    $redirect_url = '../../pages/admin/dashboard.php';
                } else {
                    $redirect_url = '../passenger/login-form.php';
                }
                
                $stmt->close();
                $conn->close();
                header("Location: $redirect_url");
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TrycKaSaken</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/login-form.css">
</head>
<body>
    <div class="login-container">
      <div>
        <a href="../../index.php" class="back-link">
          <i class="bi bi-arrow-left"></i> Back to Home
        </a>

        <div class="login-card">
          <div class="login-header">
            <div class="logo">
              <i class="bi bi-truck"></i>
            </div>
            <h1>Sign in</h1>
            <?php if ($logged_in_user_type): ?>
              <p class="user-type-info">
                Logged in as: <strong><?php echo ucfirst($logged_in_user_type); ?></strong>
              </p>
            <?php endif; ?>
          </div>

          <?php if ($error): ?>
            <div class="error-alert">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span><?php echo htmlspecialchars($error); ?></span>
            </div>
          <?php endif; ?>

          <!-- Passenger Login Form -->
          <div class="tab-content active" id="passenger-tab">
            <form action="login-form.php" method="POST" class="login-form">
              <input type="hidden" name="user_type" value="passenger">
              <div class="form-group">
                <label for="passenger-email" class="form-label">
                  <i class="bi bi-envelope"></i> Email Address
                </label>
                <input type="email" 
                       class="form-control" 
                       id="passenger-email" 
                       name="email" 
                       placeholder="Enter your email"
                       value="<?php echo isset($_POST['email']) && (!isset($_POST['user_type']) || $_POST['user_type'] == 'passenger') ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
              </div>

              <div class="form-group">
                <label for="passenger-password" class="form-label">
                  <i class="bi bi-lock"></i> Password
                </label>
                <input type="password" 
                       class="form-control" 
                       id="passenger-password" 
                       name="password" 
                       placeholder="Enter your password"
                       required>
              </div>

              <button type="submit" class="submit-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i> Sign In as Passenger
              </button>
            </form>
          </div>

          <!-- Driver Login Form -->
          <div class="tab-content" id="driver-tab">
            <form action="login-form.php" method="POST" class="login-form">
              <input type="hidden" name="user_type" value="driver">
              <div class="form-group">
                <label for="driver-email" class="form-label">
                  <i class="bi bi-envelope"></i> Email Address
                </label>
                <input type="email" 
                       class="form-control" 
                       id="driver-email" 
                       name="email" 
                       placeholder="Enter your email"
                       value="<?php echo isset($_POST['email']) && isset($_POST['user_type']) && $_POST['user_type'] == 'driver' ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
              </div>

              <div class="form-group">
                <label for="driver-password" class="form-label">
                  <i class="bi bi-lock"></i> Password
                </label>
                <input type="password" 
                       class="form-control" 
                       id="driver-password" 
                       name="password" 
                       placeholder="Enter your password"
                       required>
              </div>

              <button type="submit" class="submit-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i> Sign In as Driver
              </button>
            </form>
          </div>

          <!-- Admin Login Form -->
          <div class="tab-content" id="admin-tab">
            <form action="login-form.php" method="POST" class="login-form">
              <input type="hidden" name="user_type" value="admin">
              <div class="form-group">
                <label for="admin-email" class="form-label">
                  <i class="bi bi-envelope"></i> Email Address
                </label>
                <input type="email" 
                       class="form-control" 
                       id="admin-email" 
                       name="email" 
                       placeholder="Enter your email"
                       value="<?php echo isset($_POST['email']) && isset($_POST['user_type']) && $_POST['user_type'] == 'admin' ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
              </div>

              <div class="form-group">
                <label for="admin-password" class="form-label">
                  <i class="bi bi-lock"></i> Password
                </label>
                <input type="password" 
                       class="form-control" 
                       id="admin-password" 
                       name="password" 
                       placeholder="Enter your password"
                       required>
              </div>

              <button type="submit" class="submit-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i> Sign In as Admin
              </button>
            </form>
          </div>

          <div class="login-footer">
            <p class="mb-0">Don't have an account? <a href="register-form.php">Create one now</a></p>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
