<?php
/**
 * One-time production setup — creates the superadmin user.
 * DELETE THIS FILE immediately after use.
 *
 * Protected by a setup token — set SETUP_TOKEN in your .env before uploading.
 * Access: https://your-site.com/public/setup.php?token=<your-token>
 */
require_once dirname(__DIR__) . '/config/config.php';

// ── Token guard ───────────────────────────────────────────────────────────────
$setupToken = getenv('SETUP_TOKEN') ?: '';
if ($setupToken === '' || ($_GET['token'] ?? '') !== $setupToken) {
    http_response_code(403);
    exit('Forbidden. Set SETUP_TOKEN in .env and pass ?token= in the URL.');
}

$message = '';
$success = false;
$rawKey  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email']      ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $orgName   = trim($_POST['org_name']   ?? '');
    $orgDomain = trim($_POST['org_domain'] ?? '');
    $password  = $_POST['password']        ?? '';
    $password2 = $_POST['password2']       ?? '';

    if (!$email || !$firstName || !$lastName || !$orgName || !$orgDomain || !$password) {
        $message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $message = 'Passwords do not match.';
    } else {
        $db = getDbConnection();

        // Seed roles
        $db->prepare('INSERT IGNORE INTO roles (name, description) VALUES
            ("superadmin",         "Super administrator with full system access"),
            ("organisation_admin", "Organisation administrator"),
            ("staff",              "Standard staff member")
        ')->execute();

        // Create or reuse organisation
        $stmt = $db->prepare('SELECT id FROM organisations WHERE domain = ?');
        $stmt->execute([$orgDomain]);
        $org = $stmt->fetch();
        if ($org) {
            $orgId = (int) $org['id'];
        } else {
            $db->prepare('INSERT INTO organisations (name, domain) VALUES (?, ?)')->execute([$orgName, $orgDomain]);
            $orgId = (int) $db->lastInsertId();
        }

        // Check user doesn't already exist
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $message = 'A user with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare('
                INSERT INTO users (organisation_id, email, password_hash, first_name, last_name, is_active, email_verified)
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ')->execute([$orgId, $email, $hash, $firstName, $lastName]);
            $userId = (int) $db->lastInsertId();

            $stmt = $db->prepare('SELECT id FROM roles WHERE name = "superadmin"');
            $stmt->execute();
            $roleId = (int) $stmt->fetchColumn();
            $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);

            // Also generate an API key for PMS integration
            $rawKey  = bin2hex(random_bytes(32));
            $keyHash = hash('sha256', $rawKey);
            $db->prepare('INSERT INTO api_keys (organisation_id, name, connected_service, key_hash) VALUES (?, ?, ?, ?)')
               ->execute([$orgId, 'Staff Service', 'People Management Service', $keyHash]);

            $success = true;
            $message = 'Superadmin created successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Service Setup</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; color: #111827; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 2rem; width: 100%; max-width: 500px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; }
        .sub { color: #6b7280; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 500; font-size: 0.875rem; margin-bottom: 0.3rem; }
        input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .btn { display: block; width: 100%; padding: 0.6rem; background: #2563eb; color: #fff; border: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; margin-top: 1.25rem; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; margin-bottom: 1rem; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .key-box { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 0.375rem; padding: 0.75rem; font-family: monospace; font-size: 0.8rem; word-break: break-all; margin-top: 0.75rem; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 1.25rem 0; }
        .section-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Team Service Setup</h1>
    <p class="sub">Create the first superadmin and organisation. Delete this file after use.</p>

    <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="font-size:0.875rem; margin-bottom:0.75rem;">
            An API key for the <strong>People Management Service</strong> was also generated.
            Copy it now — it will not be shown again:
        </p>
        <div class="key-box"><?php echo htmlspecialchars($rawKey); ?></div>
        <p style="font-size:0.8rem; color:#6b7280; margin-top:0.75rem;">
            Add this to your PMS <code>.env</code> as <code>TEAM_SERVICE_API_KEY</code>,
            then <strong>delete this setup.php file</strong>.
        </p>
        <hr>
        <a href="<?php echo url('login.php'); ?>" style="display:block; text-align:center; color:#2563eb; font-size:0.875rem; text-decoration:none;">
            Go to login &rarr;
        </a>
    <?php else: ?>
        <form method="POST" action="?token=<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">

            <p class="section-label">Organisation</p>
            <div class="form-group">
                <label>Organisation name</label>
                <input type="text" name="org_name" value="<?php echo htmlspecialchars($_POST['org_name'] ?? ''); ?>" required placeholder="e.g. Acme Care Ltd">
            </div>
            <div class="form-group">
                <label>Domain</label>
                <input type="text" name="org_domain" value="<?php echo htmlspecialchars($_POST['org_domain'] ?? ''); ?>" required placeholder="e.g. acmecare.com">
            </div>

            <hr>
            <p class="section-label">Superadmin account</p>

            <div class="form-group">
                <label>First name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? 'William'); ?>" required>
            </div>
            <div class="form-group">
                <label>Last name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? 'Ellis'); ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? 'williamjamesellis@outlook.com'); ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label>Confirm password</label>
                <input type="password" name="password2" required minlength="8">
            </div>

            <button type="submit" class="btn">Create superadmin</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
