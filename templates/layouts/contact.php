<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  function validate($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }

  $name = validate($_POST['name']);
  $email = validate($_POST['email']);
  $subject = validate($_POST['subject']);
  $message = validate($_POST['message']);

  $successMsg = "Thank you, $name! Your message has been received.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us</title>

  <!-- External CSS -->
  <link rel="stylesheet" href="contact.css">

  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="container">
    <h1>Contact Us</h1>

    <p class="intro">
      Have a question or want to reach out to our team?  
      Fill out the form below or contact us through our social media channels.
    </p>

    <?php if (!empty($successMsg)) : ?>
      <div class="success-message"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <form action="" method="POST" class="contact-form">
      <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" placeholder="Enter your full name" required>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
      </div>

      <div class="form-group">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" placeholder="Enter subject" required>
      </div>

      <div class="form-group">
        <label for="message">Message</label>
        <textarea id="message" name="message" placeholder="Write your message..." rows="5" required></textarea>
      </div>

      <button type="submit" class="btn">Send Message</button>
    </form>

    <div class="contact-info">
      <h3>Or reach us at</h3>
      <p><i class="fa-solid fa-envelope"></i> tryckasakin@gmail.com</p>
      <p><i class="fa-solid fa-phone"></i> +63 912 345 6789</p>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-linkedin-in"></i></a>
      </div>
    </div>
  </div>
</body>
</html>
