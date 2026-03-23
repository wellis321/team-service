<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : ''; ?><?php echo APP_NAME; ?></title>
    <meta name="description" content="Team Service — organise your teams, assign staff and people, and manage your whole team structure in one place.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
<header>
    <div class="container">
        <nav>
            <a href="<?php echo url('landing.php'); ?>" class="logo">
                <i class="fas fa-people-group" style="font-size:1.75rem"></i>
                <span class="logo-text"><?php echo APP_NAME; ?></span>
            </a>
            <button class="mobile-menu-toggle" onclick="this.nextElementSibling.classList.toggle('active')" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="nav-links">
                <li><a href="<?php echo url('landing.php'); ?>" <?php echo basename($_SERVER['PHP_SELF']) === 'landing.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-home"></i> Home</a></li>
                <li><a href="<?php echo url('services.php'); ?>" <?php echo basename($_SERVER['PHP_SELF']) === 'services.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-cubes"></i> Our Platform</a></li>
                <li><a href="<?php echo url('contact.php'); ?>" <?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-envelope"></i> Contact</a></li>
                <?php if (Auth::isLoggedIn()): ?>
                <li><a href="<?php echo url('index.php'); ?>" class="btn-nav">
                    <i class="fas fa-gauge"></i> Dashboard</a></li>
                <?php else: ?>
                <li><a href="<?php echo url('login.php'); ?>" class="btn-nav">
                    <i class="fas fa-sign-in-alt"></i> Sign In</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main>
