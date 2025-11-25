<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'TrycKaSaken') ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <?php if (isset($cssFiles) && is_array($cssFiles)): ?>
        <?php foreach ($cssFiles as $cssFile): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="public/css/navbar.css">
    
    <?php if (isset($inlineStyles)): ?>
        <style><?= $inlineStyles ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-tryckasaken">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                 TrycKaSaken
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home" onclick="smoothScroll(event)">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about" onclick="smoothScroll(event)">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services" onclick="smoothScroll(event)">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#team" onclick="smoothScroll(event)">Team</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact" onclick="smoothScroll(event)">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php 
    // Load the main content
    if (isset($contentFile) && file_exists($contentFile)) {
        include $contentFile;
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (isset($jsFiles) && is_array($jsFiles)): ?>
        <?php foreach ($jsFiles as $jsFile): ?>
            <script src="<?= htmlspecialchars($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineScripts)): ?>
        <script><?= $inlineScripts ?></script>
    <?php endif; ?>

    <script>
        function smoothScroll(e) {
            e.preventDefault();
            const href = e.currentTarget.getAttribute('href');
            
            if (href.startsWith('#')) {
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Close mobile menu if open
                    const navbarCollapse = document.querySelector('.navbar-collapse');
                    if (navbarCollapse.classList.contains('show')) {
                        const toggle = document.querySelector('.navbar-toggler');
                        toggle.click();
                    }
                }
            }
        }

        // Add active class to navbar links on scroll
        window.addEventListener('scroll', function() {
            let current = '';
            const sections = document.querySelectorAll('section[id]');
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
