<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Team | TrycKaSaken</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="public/css/layout-header.css">
  <link rel="stylesheet" href="public/css/navbar.css">
  
  <style>
    body {
      background: linear-gradient(135deg, #e0fae8, #d7f7e0);
      color: #2d4b3f;
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      margin-bottom: 30px;
    }
    .team-img {
      width: 130px;
      height: 130px;
      object-fit: cover;
      border-radius: 50%;
      border: 8px solid transparent;
      margin-top: -65px;
      background: #fff;
    }
    .card:nth-child(1) .team-img,
    .card:nth-child(3) .team-img {
      border-color: #0a8d3f;
    }
    .card:nth-child(2) .team-img,
    .card:nth-child(4) .team-img {
      border-color: #0a8d3f;
    }
    .social-icons a {
      color: #555;
      margin: 0 5px;
      font-size: 1.1rem;
      transition: color 0.3s;
    }
    .social-icons a:hover {
      color: #0a8d3f;
    }
  </style>
</head>
<body>

  <div class="container py-5">
    <h1 class="text-center text-uppercase mb-5" style="color:#0a8d3f;">Meet Our Team</h1>
    <div class="row justify-content-center g-4">

      <div class="col-md-6 col-lg-3 d-flex align-items-stretch">
        <div class="card text-center w-100">
          <div class="card-body">
            <img src="https://i.pinimg.com/564x/0f/ae/d3/0faed34aa628da9e0873c3edd6c2144d.jpg" alt="John Vincent Dipasupil" class="team-img shadow">
            <h3 class="mt-3 mb-1" style="color:#0a8d3f;">Vincent</h3>
            <p class="text-secondary fw-bold mb-2">Project Manager</p>
            <p class="text-muted small">
              Vincent leads the team with strong organization and clear direction. 
              He ensures every project runs smoothly from start to finish.
            </p>
            <div class="social-icons mt-2">
              <a href="#"><i class="fab fa-facebook-f"></i></a>
              <a href="#"><i class="fab fa-twitter"></i></a>
              <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 d-flex align-items-stretch">
        <div class="card text-center w-100">
          <div class="card-body">
            <img src="https://i.mydramalist.com/dbV8e_5f.jpg" alt="Mark Anthony Bunsay" class="team-img shadow">
            <h3 class="mt-3 mb-1" style="color:#0a8d3f;">Mark</h3>
            <p class="text-secondary fw-bold mb-2">Front-End Developer</p>
            <p class="text-muted small">
              Mark builds beautiful and responsive interfaces that make user 
              interaction smooth and enjoyable across all devices.
            </p>
            <div class="social-icons mt-2">
              <a href="#"><i class="fab fa-facebook-f"></i></a>
              <a href="#"><i class="fab fa-twitter"></i></a>
              <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 d-flex align-items-stretch">
        <div class="card text-center w-100">
          <div class="card-body">
            <img src="https://i.pinimg.com/originals/9f/d6/e2/9fd6e2c532bb9003cded155af3573415.jpg" alt="Jc Jade Nealega" class="team-img shadow">
            <h3 class="mt-3 mb-1" style="color:#0a8d3f;">Jc</h3>
            <p class="text-secondary fw-bold mb-2">Back-End Developer</p>
            <p class="text-muted small">
              Jc manages the system’s logic and databases. He ensures 
              everything behind the scenes works securely and efficiently.
            </p>
            <div class="social-icons mt-2">
              <a href="#"><i class="fab fa-facebook-f"></i></a>
              <a href="#"><i class="fab fa-twitter"></i></a>
              <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-3 d-flex align-items-stretch">
        <div class="card text-center w-100">
          <div class="card-body">
            <img src="https://i.mydramalist.com/Qw0VW_5f.jpg" alt="Jerick Andrei Herrera" class="team-img shadow">
            <h3 class="mt-3 mb-1" style="color:#0a8d3f;">Jerick</h3>
            <p class="text-secondary fw-bold mb-2">UI/UX Designer</p>
            <p class="text-muted small">
              Jerick focuses on user experience and modern design trends, 
              ensuring the system looks great and feels intuitive.
            </p>
            <div class="social-icons mt-2">
              <a href="#"><i class="fab fa-facebook-f"></i></a>
              <a href="#"><i class="fab fa-twitter"></i></a>
              <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>