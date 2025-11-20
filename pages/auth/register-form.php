<?php
session_start();
require_once '../../config/Database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        $user_type = $_POST['user_type'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Driver-specific fields
        $license_number = '';
        $tricycle_info = '';
        if ($user_type === 'driver') {
            $license_number = trim($_POST['license_number'] ?? '');
            $tricycle_info = trim($_POST['tricycle_info'] ?? '');
        }

        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif ($user_type === 'driver' && empty($license_number)) {
            $error = "License number is required for drivers!";
        } elseif ($user_type === 'driver' && empty($tricycle_info)) {
            $error = "Tricycle information is required for drivers!";
        } elseif ($user_type === 'driver' && (!isset($_FILES['license_file']) || $_FILES['license_file']['error'] !== UPLOAD_ERR_OK)) {
            $error = "Driver's license document is required!";
        } elseif ($user_type === 'driver' && (!isset($_FILES['or_cr_file']) || $_FILES['or_cr_file']['error'] !== UPLOAD_ERR_OK)) {
            $error = "OR/CR document is required!";
        } elseif ($user_type === 'driver' && (!isset($_FILES['picture_file']) || $_FILES['picture_file']['error'] !== UPLOAD_ERR_OK)) {
            $error = "Driver photo is required!";
        } else {
            // Check if email exists
            $check_sql = "SELECT email FROM users WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Email already registered!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create uploads directory if it doesn't exist
                $upload_dir = '../../public/uploads/drivers/';
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        $error = "Failed to create upload directory.";
                    } else {
                        // Directory created successfully, proceed with registration
                        $directory_created = true;
                    }
                } else {
                    $directory_created = true;
                }

                if (empty($error)) {

                $conn->begin_transaction();
                
                try {
                    // Insert user record
                    $sql = "INSERT INTO users (user_type, name, email, phone, password, license_number, tricycle_info, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $verification_status = ($user_type === 'driver') ? 'pending' : null;
                    $stmt->bind_param("ssssssss", $user_type, $name, $email, $phone, $hashed_password, $license_number, $tricycle_info, $verification_status);
                    
                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;
                        
                        // If driver, handle file uploads and create driver record
                        if ($user_type === 'driver') {
                            $upload_success = true;
                            $file_paths = [];
                            $uploaded_files = []; // Track uploaded files for cleanup on error
                            
                            // Define allowed file types
                            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                            $max_size = 5 * 1024 * 1024; // 5MB
                            
                            // Handle each file upload
                            $files = [
                                'license_file' => 'license',
                                'or_cr_file' => 'or_cr', 
                                'picture_file' => 'picture'
                            ];
                            
                            foreach ($files as $file_input => $file_type) {
                                if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
                                    $error = "Error uploading " . str_replace('_', ' ', $file_type) . " file. Please try again.";
                                    $upload_success = false;
                                    break;
                                }
                                
                                $file = $_FILES[$file_input];
                                
                                // Additional validation
                                if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                                    $error = "Invalid " . str_replace('_', ' ', $file_type) . " file upload.";
                                    $upload_success = false;
                                    break;
                                }
                                
                                // Validate file type using both MIME and extension
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $detected_type = finfo_file($finfo, $file['tmp_name']);
                                finfo_close($finfo);
                                
                                if (!in_array($detected_type, $allowed_types) && !in_array($file['type'], $allowed_types)) {
                                    $error = "Invalid file type for " . str_replace('_', ' ', $file_type) . ". Only JPG, PNG, and PDF files are allowed.";
                                    $upload_success = false;
                                    break;
                                }
                                
                                if ($file['size'] > $max_size) {
                                    $error = "File size too large for " . str_replace('_', ' ', $file_type) . ". Maximum size is 5MB.";
                                    $upload_success = false;
                                    break;
                                }
                                
                                // Generate unique filename
                                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                $filename = $user_id . '_' . $file_type . '_' . time() . '_' . uniqid() . '.' . $extension;
                                $full_path = $upload_dir . $filename;
                                $relative_path = 'public/uploads/drivers/' . $filename;
                                
                                // Move uploaded file
                                if (move_uploaded_file($file['tmp_name'], $full_path)) {
                                    $file_paths[$file_type . '_path'] = $relative_path;
                                    $uploaded_files[] = $full_path; // Track for cleanup
                                } else {
                                    $error = "Failed to upload " . str_replace('_', ' ', $file_type) . " file. Check directory permissions.";
                                    $upload_success = false;
                                    break;
                                }
                            }
                            
                            if ($upload_success) {
                                // Insert driver record
                                $driver_sql = "INSERT INTO drivers (user_id, or_cr_path, license_path, picture_path, verification_status) VALUES (?, ?, ?, ?, 'pending')";
                                $driver_stmt = $conn->prepare($driver_sql);
                                $driver_stmt->bind_param("isss", $user_id, $file_paths['or_cr_path'], $file_paths['license_path'], $file_paths['picture_path']);
                                
                                if ($driver_stmt->execute()) {
                                    $conn->commit();
                                    $success = "Driver registration successful! Your documents have been uploaded and are pending verification. You will be notified once your account is approved.";
                                } else {
                                    $conn->rollback();
                                    $error = "Failed to create driver record: " . $driver_stmt->error;
                                    
                                    // Clean up uploaded files
                                    foreach ($uploaded_files as $uploaded_file) {
                                        if (file_exists($uploaded_file)) {
                                            unlink($uploaded_file);
                                        }
                                    }
                                }
                                $driver_stmt->close();
                            } else {
                                $conn->rollback();
                                
                                // Clean up any uploaded files
                                foreach ($uploaded_files as $uploaded_file) {
                                    if (file_exists($uploaded_file)) {
                                        unlink($uploaded_file);
                                    }
                                }
                            }
                        } else {
                            // For non-drivers, just commit the user record
                            $conn->commit();
                            $success = "Registration successful! You can now login.";
                        }
                    } else {
                        $conn->rollback();
                        $error = "Registration failed. Please try again.";
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Registration failed: " . $e->getMessage();
                }
                }
            }
            $check_stmt->close();
        }
        $conn->close();
    } else {
        $error = "Database connection failed.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TrycKaSaken</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/register-form.css">
</head>
<body>
    <div class="register-container">
      <div>
        <a href="../../index.php" class="back-link">
          <i class="bi bi-arrow-left"></i> Back to Home
        </a>

        <div class="register-card">
          <div class="register-header">
            <div class="logo">
              <i class="bi bi-truck"></i>
            </div>
            <h1>Join TrycKaSaken</h1>
            <p>Create your account today</p>
          </div>

          <?php if ($error): ?>
            <div class="alert-message alert-error">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span><?php echo htmlspecialchars($error); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if ($success): ?>
            <div class="alert-message alert-success">
              <i class="bi bi-check-circle-fill"></i>
              <span><?php echo htmlspecialchars($success); ?></span>
            </div>
          <?php endif; ?>

          <!-- Registration Tabs -->
          <div class="register-tabs">
            <button type="button" class="tab-button active" data-tab="passenger">
              <i class="bi bi-person"></i> Passenger
            </button>
            <button type="button" class="tab-button" data-tab="driver">
              <i class="bi bi-car-front"></i> Driver
            </button>
          </div>

          <!-- Passenger Registration Form -->
          <div class="tab-content active" id="passenger-tab">
            <form action="register-form.php" method="POST" class="register-form">
              <input type="hidden" name="user_type" value="passenger">
              
              <div class="form-group">
                <label for="passenger-name" class="form-label">
                  <i class="bi bi-person"></i> Full Name
                </label>
                <input type="text" 
                       class="form-control" 
                       id="passenger-name" 
                       name="name" 
                       placeholder="Your full name"
                       value="<?php echo isset($_POST['name']) && (!isset($_POST['user_type']) || $_POST['user_type'] == 'passenger') ? htmlspecialchars($_POST['name']) : ''; ?>"
                       required>
              </div>

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
                <label for="passenger-phone" class="form-label">
                  <i class="bi bi-telephone"></i> Phone Number
                </label>
                <input type="tel" 
                       class="form-control" 
                       id="passenger-phone" 
                       name="phone" 
                       placeholder="Your phone number"
                       value="<?php echo isset($_POST['phone']) && (!isset($_POST['user_type']) || $_POST['user_type'] == 'passenger') ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       required>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="passenger-password" class="form-label">
                    <i class="bi bi-lock"></i> Password
                  </label>
                  <input type="password" 
                         class="form-control" 
                         id="passenger-password" 
                         name="password" 
                         placeholder="Create a password"
                         required>
                </div>

                <div class="form-group">
                  <label for="passenger-confirm-password" class="form-label">
                    <i class="bi bi-lock-check"></i> Confirm Password
                  </label>
                  <input type="password" 
                         class="form-control" 
                         id="passenger-confirm-password" 
                         name="confirm_password" 
                         placeholder="Confirm your password"
                         required>
                </div>
              </div>

              <button type="submit" class="submit-btn">
                <i class="bi bi-person-plus me-2"></i> Register as Passenger
              </button>
            </form>
          </div>

          <!-- Driver Registration Form -->
          <div class="tab-content" id="driver-tab">
            <form action="register-form.php" method="POST" enctype="multipart/form-data" class="register-form">
              <input type="hidden" name="user_type" value="driver">
              
              <div class="form-group">
                <label for="driver-name" class="form-label">
                  <i class="bi bi-person"></i> Full Name
                </label>
                <input type="text" 
                       class="form-control" 
                       id="driver-name" 
                       name="name" 
                       placeholder="Your full name"
                       value="<?php echo isset($_POST['name']) && isset($_POST['user_type']) && $_POST['user_type'] == 'driver' ? htmlspecialchars($_POST['name']) : ''; ?>"
                       required>
              </div>

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
                <label for="driver-phone" class="form-label">
                  <i class="bi bi-telephone"></i> Phone Number
                </label>
                <input type="tel" 
                       class="form-control" 
                       id="driver-phone" 
                       name="phone" 
                       placeholder="Your phone number"
                       value="<?php echo isset($_POST['phone']) && isset($_POST['user_type']) && $_POST['user_type'] == 'driver' ? htmlspecialchars($_POST['phone']) : ''; ?>"
                       required>
              </div>

              <div class="form-group">
                <label for="license-number" class="form-label">
                  <i class="bi bi-card-text"></i> Driver's License Number
                </label>
                <input type="text" 
                       class="form-control" 
                       id="license-number" 
                       name="license_number" 
                       placeholder="Enter your license number"
                       value="<?php echo isset($_POST['license_number']) && isset($_POST['user_type']) && $_POST['user_type'] == 'driver' ? htmlspecialchars($_POST['license_number']) : ''; ?>"
                       required>
              </div>

              <div class="form-group">
                <label for="tricycle-info" class="form-label">
                  <i class="bi bi-car-front"></i> Tricycle Information
                </label>
                <input type="text" 
                       class="form-control" 
                       id="tricycle-info" 
                       name="tricycle_info" 
                       placeholder="Tricycle model, color, plate number, etc."
                       value="<?php echo isset($_POST['tricycle_info']) && isset($_POST['user_type']) && $_POST['user_type'] == 'driver' ? htmlspecialchars($_POST['tricycle_info']) : ''; ?>"
                       required>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="driver-password" class="form-label">
                    <i class="bi bi-lock"></i> Password
                  </label>
                  <input type="password" 
                         class="form-control" 
                         id="driver-password" 
                         name="password" 
                         placeholder="Create a password"
                         required>
                </div>

                <div class="form-group">
                  <label for="driver-confirm-password" class="form-label">
                    <i class="bi bi-lock-check"></i> Confirm Password
                  </label>
                  <input type="password" 
                         class="form-control" 
                         id="driver-confirm-password" 
                         name="confirm_password" 
                         placeholder="Confirm your password"
                         required>
                </div>
              </div>

              <!-- Document Uploads Section -->
              <div class="documents-section">
                <h5 class="documents-title">
                  <i class="bi bi-file-earmark-text"></i> Required Documents
                </h5>
                <p class="documents-subtitle">Please upload the following documents for verification:</p>
                
                <div class="form-group">
                  <label for="license-file" class="form-label">
                    <i class="bi bi-file-earmark-image"></i> Driver's License (Image/PDF)
                  </label>
                  <input type="file" 
                         class="form-control file-input" 
                         id="license-file" 
                         name="license_file" 
                         accept=".jpg,.jpeg,.png,.pdf"
                         required>
                  <small class="form-text text-muted">Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                </div>

                <div class="form-group">
                  <label for="or-cr-file" class="form-label">
                    <i class="bi bi-file-earmark-text"></i> OR/CR Document (Image/PDF)
                  </label>
                  <input type="file" 
                         class="form-control file-input" 
                         id="or-cr-file" 
                         name="or_cr_file" 
                         accept=".jpg,.jpeg,.png,.pdf"
                         required>
                  <small class="form-text text-muted">Official Receipt / Certificate of Registration</small>
                </div>

                <div class="form-group">
                  <label for="picture-file" class="form-label">
                    <i class="bi bi-person-square"></i> Driver Photo (Image)
                  </label>
                  <input type="file" 
                         class="form-control file-input" 
                         id="picture-file" 
                         name="picture_file" 
                         accept=".jpg,.jpeg,.png"
                         required>
                  <small class="form-text text-muted">Recent photo for identification purposes</small>
                </div>
              </div>

              <div class="alert-message" style="background: rgba(22, 163, 74, 0.1); border: 1px solid rgba(22, 163, 74, 0.3); color: #16a34a;">
                <i class="bi bi-info-circle-fill"></i>
                <span><strong>Note:</strong> Your account will be reviewed by administrators after registration. You'll be able to accept rides once your documents are verified.</span>
              </div>

              <button type="submit" class="submit-btn">
                <i class="bi bi-person-plus me-2"></i> Register as Driver
              </button>
            </form>
          </div>

          <div class="register-footer">
            <p class="mb-0">Already have an account? <a href="login-form.php">Sign in here</a></p>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Tab switching functionality
      document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        // Handle tab switching
        tabButtons.forEach(button => {
          button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
          });
        });

        // Auto-select tab based on error state or URL parameter
        <?php if (isset($_POST['user_type'])): ?>
          const userType = '<?php echo $_POST['user_type']; ?>';
          const targetButton = document.querySelector(`[data-tab="${userType}"]`);
          if (targetButton) {
            targetButton.click();
          }
        <?php endif; ?>

        // Handle URL parameters for direct tab access
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam && ['passenger', 'driver'].includes(tabParam)) {
          const targetButton = document.querySelector(`[data-tab="${tabParam}"]`);
          if (targetButton) {
            targetButton.click();
          }
        }
      });
    </script>
</body>
</html>
