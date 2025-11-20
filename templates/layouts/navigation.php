<!-- ======= NAVIGATION BAR ======= -->
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    nav {
        width: 100%;
        background: transparent;
        padding: 18px 60px;
        position: absolute;
        top: 0;
        left: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: Arial, sans-serif;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 26px;
        font-weight: bold;
        color: #0a8d3f;
        line-height: 1;
        letter-spacing: 0;
    }

    .logo img {
        width: 40px;
        height: 40px;
    }

    .nav-links {
        display: flex;
        gap: 35px;
        list-style: none;
    }

    .nav-links a {
        text-decoration: none;
        font-size: 17px;
        color: #0a8d3f;
        font-weight: 500;
        transition: 0.3s;
    }

    .nav-links a:hover {
        color: #067a36;
    }

    /* Mobile Menu */
    .menu-btn {
        display: none;
        font-size: 28px;
        cursor: pointer;
        color: #0a8d3f;
    }

    @media (max-width: 900px) {
        nav {
            padding: 15px 25px;
        }

        .nav-links {
            position: absolute;
            top: 70px;
            right: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            flex-direction: column;
            text-align: center;
            gap: 20px;
            padding: 20px 0;
            display: none;
        }

        .menu-btn {
            display: block;
        }
    }
</style>

<nav>
    <a href="index.php" class="logo" style="text-decoration:none;">
        <img src="your-logo.png" alt="">
        TrycKaSaken
    </a>

    <div class="menu-btn" onclick="toggleMenu()">☰</div>

    <ul class="nav-links" id="menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="services.php">Services</a></li>
        <li><a href="team.php">Team</a></li>
        <li><a href="contact.php">Contact Us</a></li>
    </ul>
</nav>

<script>
    function toggleMenu() {
        document.getElementById("menu").classList.toggle("active");
    }
</script>
