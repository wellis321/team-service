<?php
/**
 * Team Service — Login
 */
require_once dirname(__DIR__) . '/config/config.php';

// Already logged in
if (Auth::isLoggedIn()) {
    header('Location: ' . url('index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validatePost()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';
        $result   = Auth::login($email, $password);

        if ($result === true) {
            session_regenerate_id(true);
            header('Location: ' . url('index.php'));
            exit;
        } elseif (is_array($result) && !empty($result['message'])) {
            $error = $result['message'];
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign in';
include INCLUDES_PATH . '/header.php';
?>

<div style="max-width: 420px; margin: 4rem auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <i class="fa-solid fa-people-group" style="font-size: 2.5rem; color: var(--primary);"></i>
            <h1 style="font-size: 1.35rem; font-weight: 700; margin-top: 0.75rem;"><?php echo APP_NAME; ?></h1>
            <p class="text-light" style="margin-top: 0.25rem; font-size: 0.875rem;">Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo CSRF::tokenField(); ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       required autofocus autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign in
            </button>
        </form>
    </div>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
