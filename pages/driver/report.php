<?php
// Include database connection file
require_once __DIR__ . '/../../config/Database.php';

$successMsg = '';
$errorMsg = '';

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Check if connection failed
if (!$conn) {
    $errorMsg = "Database connection failed. Please try again later.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize input
    function validate($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $name    = validate($_POST['name']);
    $email   = validate($_POST['email']);
    $subject = validate($_POST['subject']);
    $message = validate($_POST['message']);

    // Basic server-side validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $errorMsg = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Invalid email format.";
    } else {
        try {
            // Insert into database using prepared statement (safe from SQL injection)
            $sql = "INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $subject, $message);
                
                if ($stmt->execute()) {
                    $successMsg = "Thank you, $name! Your message has been received. We'll get back to you soon.";
                    // Optional: Clear form after success
                    $_POST = [];
                } else {
                    $errorMsg = "Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMsg = "Prepare error: " . $conn->error;
            }
        } catch (Exception $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}
?>

<style>
    body {
        background-color: #b5efccff;
        min-height: 100vh;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 40px;
        background-color: #067a36;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .dashboard-btn {
        background: white;
        color: #0a8d3f;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .dashboard-btn:hover {
        background: #f0f0f0;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .contact-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
    }

    .contact-container h1 {
        font-size: 2.5rem;
        color: #0a8d3f;
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }

    .intro {
        text-align: center;
        font-size: 1.1rem;
        color: #555;
        margin-bottom: 40px;
        line-height: 1.6;
    }

    .success-message {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
        border-left: 4px solid #28a745;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
        border-left: 4px solid #dc3545;
    }

    .contact-form {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 40px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-family: Arial, sans-serif;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #0a8d3f;
        box-shadow: 0 0 5px rgba(10, 141, 63, 0.3);
    }

    .btn {
        background: #0a8d3f;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
        width: 100%;
    }

    .btn:hover {
        background: #067a36;
    }

    .contact-info {
        background: #f0f0f0;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
    }

    .contact-info h3 {
        color: #0a8d3f;
        margin-bottom: 20px;
        font-size: 1.5rem;
    }

    .contact-info p {
        font-size: 1.1rem;
        margin: 10px 0;
        color: #333;
    }

    .contact-info i {
        margin-right: 10px;
        color: #0a8d3f;
        width: 20px;
    }

    .social-icons {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 20px;
    }

    .social-icons a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #0a8d3f;
        color: white;
        border-radius: 50%;
        text-decoration: none;
        transition: background 0.3s;
    }

    .social-icons a:hover {
        background: #067a36;
    }

    @media (max-width: 768px) {
        .contact-container {
            margin-top: 100px;
            padding: 20px;
        }

        .contact-container h1 {
            font-size: 2rem;
        }

        .contact-form, .contact-info {
            padding: 20px;
        }
    }
</style>

<div class="page-header">
    <h2 style="color: white; margin: 0;">Driver Report</h2>
    <a href="login-form.php" class="dashboard-btn">← Back to Dashboard</a>
</div>

<div class="contact-container">
    <h1>Contact Us</h1>

    <p class="intro">
        Have a question or want to reach out to our team?<br>
        Fill out the form below or contact us through our social media channels.
    </p>

    <?php if (!empty($successMsg)) : ?>
        <div class="success-message"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)) : ?>
        <div class="error-message"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <form action="/tryckasaken/pages/driver/report.php" method="POST" class="contact-form">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" placeholder="Enter your full name" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="Enter your email" required>
        </div>

        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" placeholder="Enter subject" required>
        </div>

        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Write your message..." rows="5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
        </div>

        <button type="submit" class="btn">Send Message</button>
    </form>

    <div class="contact-info">
        <h3>Or reach us at</h3>
        <p><i class="fas fa-envelope"></i> tryckasakin@gmail.com</p>
        <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
        <div class="social-icons">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
    </div>
</div>