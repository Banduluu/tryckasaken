<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Services | TrycKaSaken</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #e0fae8, #d7f7e0);
            min-height: 100vh;
            color: #2d4b3f;
        }

        /* Page Title */
        .header-section {
            text-align: center;
            margin-top: 120px;
        }

        .header-section h1 {
            font-size: 50px;
            margin-bottom: 10px;
            color: #0a8d3f;
        }

        .header-section p {
            font-size: 18px;
            color: #577a6a;
        }

        /* Content Boxes */
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #0a8d3f;
            margin-top: 0;
        }

        .card p {
            line-height: 1.7;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 25px;
            margin-top: 40px;
            background: rgba(255, 255, 255, 0.6);
            color: #2d4b3f;
        }
    </style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<?php include 'navigation.php'; ?>

<!-- ====== HEADER SECTION ====== -->
<div class="header-section">
    <h1>Our Services</h1>
    <p>Your Reliable Tricycle Booking Service</p>
</div>

<!-- ====== CONTENT ====== -->
<div class="container">

    <div class="card">
        <h2>🛵 Passenger Services</h2>
        <p>
            <strong>What Passengers Can Do:</strong><br>
            ✔ Request a tricycle ride<br>
            ✔ Check rider availability<br>
            ✔ View estimated travel time<br>
            ✔ Learn safety guidelines<br>
            ✔ Use cash or cashless payment options<br>
        </p>
    </div>

    <div class="card">
        <h2>How to Use as a Passenger</h2>
        <p>
            1. Open the app and allow location access.<br>
            2. Enter your pickup and drop-off location.<br>
            3. Check route preview.<br>
            4. Confirm your request.<br>
            5. Wait for your rider and follow safety instructions.
        </p>
    </div>

    <div class="card">
        <h2>🚙 Driver Services</h2>
        <p>
            <strong>What Drivers Can Do:</strong><br>
            ✔ Accept passenger ride requests<br>
            ✔ View real-time navigation<br>
            ✔ Check daily earnings<br>
            ✔ Manage ride history<br>
            ✔ Follow safety and compliance guidelines<br>
        </p>
    </div>

    <div class="card">
        <h2>How to Use as a Driver</h2>
        <p>
            1. Log in and scan your RFID card.<br>
            2. Wait for incoming ride requests.<br>
            3. Accept a request and navigate to pickup point.<br>
            4. Follow the suggested route to destination.<br>
            5. Complete the ride and receive payment.
        </p>
    </div>

</div>

<footer>
    © 2025 TrycKaSaken — All Rights Reserved
</footer>

</body>
</html>