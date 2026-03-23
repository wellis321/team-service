</main>
<footer>
    <div class="container">
        <div class="footer-section">
            <i class="fas fa-people-group" style="font-size:2rem;color:#2563eb;margin-bottom:.75rem;display:block"></i>
            <h3><?php echo APP_NAME; ?></h3>
            <p>Organise your teams, assign staff and people you support, and manage your whole organisational structure in one place.</p>
        </div>
        <div class="footer-section">
            <h3>Service</h3>
            <ul>
                <li><a href="<?php echo url('landing.php'); ?>">About</a></li>
                <li><a href="<?php echo url('services.php'); ?>">Our Platform</a></li>
                <li><a href="<?php echo url('contact.php'); ?>">Contact</a></li>
                <li><a href="<?php echo url('login.php'); ?>">Sign In</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Our Platform</h3>
            <ul>
                <?php if (defined('STAFF_SERVICE_URL') && STAFF_SERVICE_URL): ?>
                <li><a href="<?php echo htmlspecialchars(STAFF_SERVICE_URL); ?>/landing.php" target="_blank" rel="noopener"><i class="fas fa-id-card-clip" style="color:#d97706;margin-right:.3rem"></i> Staff Service</a></li>
                <?php endif; ?>
                <?php if (defined('PEOPLE_SERVICE_URL') && PEOPLE_SERVICE_URL): ?>
                <li><a href="<?php echo htmlspecialchars(PEOPLE_SERVICE_URL); ?>/landing.php" target="_blank" rel="noopener"><i class="fas fa-heart-pulse" style="color:#7c3aed;margin-right:.3rem"></i> People Service</a></li>
                <?php endif; ?>
                <li><a href="<?php echo url('api/teams.php'); ?>">Teams API</a></li>
                <li><a href="<?php echo url('api/members.php'); ?>">Members API</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
