<?php

require_once __DIR__ . '/database/DatabaseSchema.php';
require_once __DIR__ . '/includes/database-setup.php';

// Setup database silently
setupDatabase();

// Handle page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Configure page variables based on requested page
switch($page) {
    case 'about':
        $pageTitle = 'About Us | TrycKaSaken';
        $cssFiles = ['public/css/style.css', 'public/css/landing.css'];
        $contentFile = __DIR__ . '/templates/layouts/about.php';
        break;
    case 'services':
        $pageTitle = 'Services | TrycKaSaken';
        $cssFiles = ['public/css/style.css', 'public/css/landing.css'];
        $contentFile = __DIR__ . '/templates/layouts/services.php';
        break;
    case 'team':
        $pageTitle = 'Team | TrycKaSaken';
        $cssFiles = ['public/css/style.css', 'public/css/landing.css'];
        $contentFile = __DIR__ . '/templates/layouts/team.php';
        break;
    case 'contact':
        $pageTitle = 'Contact Us | TrycKaSaken';
        $cssFiles = ['public/css/style.css', 'public/css/landing.css'];
        $contentFile = __DIR__ . '/templates/layouts/contact.php';
        break;
    default:
        $pageTitle = 'TrycKaSaken - Tricycle Booking System';
        $cssFiles = ['public/css/style.css', 'public/css/landing.css'];
        $contentFile = __DIR__ . '/templates/pages/landing.php';
        break;
}

// Load main layout
include __DIR__ . '/templates/layouts/main.php';