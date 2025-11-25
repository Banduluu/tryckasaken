<?php
// Include database connection file
require_once __DIR__ . '/../../config/Database.php';

$response = [
    'success' => false,
    'message' => ''
];

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Check if connection failed
if (!$conn) {
    $response['message'] = "Database connection failed. Please try again later.";
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize input
    function validate($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    $name    = validate($_POST['name'] ?? '');
    $email   = validate($_POST['email'] ?? '');
    $subject = validate($_POST['subject'] ?? '');
    $message = validate($_POST['message'] ?? '');

    // Basic server-side validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $response['message'] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "Invalid email format.";
    } else {
        try {
            // Insert into database using prepared statement (safe from SQL injection)
            $sql = "INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $subject, $message);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Thank you, $name! Your message has been received. We'll get back to you soon.";
                } else {
                    $response['message'] = "Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "Prepare error: " . $conn->error;
            }
        } catch (Exception $e) {
            $response['message'] = "Error: " . $e->getMessage();
        }
    }
} else {
    $response['message'] = "Invalid request method.";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
