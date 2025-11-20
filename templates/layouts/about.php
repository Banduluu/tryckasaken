<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us | TrycKaSaken</title>
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
    <h1>About TrycKaSaken</h1>
    <p>Your Reliable Tricycle Booking Service</p>
</div>

<!-- ====== CONTENT ====== -->
<div class="container">

    <div class="card">
        <h2>Who We Are</h2>
        <p>
            TrycKaSaken is a modern tricycle booking platform designed to make daily travel simpler, faster, and more convenient for everyone.  
            Whether you're heading to school, work, or running quick errands, we make sure you can reach your destination safely and easily.
        </p>
    </div>

    <div class="card">
        <h2>Our Mission</h2>
        <p>
            Our mission is to provide a reliable, affordable, and safe transportation service for communities.  
            We aim to empower both passengers and drivers through technology that connects them efficiently.
        </p>
    </div>

    <div class="card">
        <h2>Our Vision</h2>
        <p>
            We envision a future where local transportation is seamless and accessible to all.  
            TrycKaSaken strives to become the number one platform for tricycle mobility solutions in the Philippines.
        </p>
    </div>

    <div class="card">
        <h2>What We Offer</h2>
        <p>
            ✔ Quick and easy tricycle booking  
            ✔ Safe and verified drivers  
            ✔ Fair and transparent pricing  
            ✔ Community-based transportation  
            ✔ Easy-to-use passenger and driver platform  
        </p>
    </div>

</div>

<footer>
    © 2025 TrycKaSaken — All Rights Reserved
</footer>

</body>
</html>