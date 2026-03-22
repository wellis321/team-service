<?php
/**
 * Team Service — Super Admin: Organisations
 *
 * Super admins can view all organisations and create new ones
 * with a first admin user in a single step.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

Auth::requireLogin();
if (!RBAC::isSuperAdmin()) {
    header('Location: ' . url('index.php'));
    exit;
}

$db      = getDbConnection();
$error   = '';
$success = '';

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validatePost()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_org') {
            $name      = trim($_POST['org_name']    ?? '');
            $domain    = trim(strtolower($_POST['org_domain'] ?? ''));
            $firstName = trim($_POST['first_name']  ?? '');
            $lastName  = trim($_POST['last_name']   ?? '');
            $email     = trim(strtolower($_POST['email'] ?? ''));
            $password  = $_POST['password'] ?? '';

            if (!$name || !$domain || !$firstName || !$lastName || !$email || !$password) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                try {
                    $db->beginTransaction();

                    $db->prepare('INSERT INTO organisations (name, domain) VALUES (?, ?)')
                       ->execute([$name, $domain]);
                    $orgId = (int) $db->lastInsertId();

                    $db->prepare('
                        INSERT INTO users
                            (organisation_id, email, password_hash, first_name, last_name, is_active, email_verified)
                        VALUES (?, ?, ?, ?, ?, 1, 1)
                    ')->execute([$orgId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);
                    $userId = (int) $db->lastInsertId();

                    RBAC::assignRole($userId, 'organisation_admin');

                    $db->commit();
                    $success = "Organisation <strong>" . htmlspecialchars($name) . "</strong> created. Admin: " . htmlspecialchars($email);
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = str_contains($e->getMessage(), 'Duplicate')
                        ? 'That domain or email address is already in use.'
                        : 'Could not create organisation: ' . $e->getMessage();
                }
            }
        }
    }
}

// ── Load orgs ─────────────────────────────────────────────────────────────────
$orgs = $db->query('
    SELECT o.*,
           COUNT(u.id)       AS user_count,
           MAX(u.last_login) AS last_activity
    FROM   organisations o
    LEFT   JOIN users u ON u.organisation_id = o.id
    GROUP  BY o.id
    ORDER  BY o.name
')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Organisations';
include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-building"></i> Organisations</h1>
        <p class="text-light text-small" style="margin-top:.25rem">All organisations across this service.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modal-create').style.display='flex'">
        <i class="fa-solid fa-plus"></i> New Organisation
    </button>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <?php if (empty($orgs)): ?>
        <p class="text-light" style="text-align:center;padding:2rem">No organisations yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Domain</th>
                    <th>Users</th>
                    <th>Last activity</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orgs as $org): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($org['name']); ?></strong></td>
                    <td class="text-light"><?php echo htmlspecialchars($org['domain']); ?></td>
                    <td><?php echo $org['user_count']; ?></td>
                    <td class="text-light text-small">
                        <?php echo $org['last_activity'] ? date('d M Y', strtotime($org['last_activity'])) : 'Never'; ?>
                    </td>
                    <td class="text-light text-small"><?php echo date('d M Y', strtotime($org['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Create Organisation Modal ─────────────────────────────────────────── -->
<div id="modal-create" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;">
    <div class="card" style="width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
        <h3 style="font-weight:600;margin-bottom:1.25rem">New Organisation</h3>
        <form method="POST">
            <?php echo CSRF::tokenField(); ?>
            <input type="hidden" name="action" value="create_org">

            <h4 style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:.75rem">Organisation</h4>
            <div class="form-group">
                <label>Organisation name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="org_name" class="form-control" required maxlength="255"
                       placeholder="e.g. Acme Care Ltd">
            </div>
            <div class="form-group">
                <label>Domain / identifier <span style="color:var(--danger)">*</span></label>
                <input type="text" name="org_domain" class="form-control" required maxlength="255"
                       placeholder="e.g. acme or acme.com">
                <div class="form-hint">Unique identifier — used internally. Must be unique across all organisations.</div>
            </div>

            <h4 style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin:.75rem 0">First Admin User</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                <div class="form-group">
                    <label>First name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="first_name" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Last name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="last_name" class="form-control" required maxlength="100">
                </div>
            </div>
            <div class="form-group">
                <label>Email address <span style="color:var(--danger)">*</span></label>
                <input type="email" name="email" class="form-control" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Password <span style="color:var(--danger)">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="8">
                <div class="form-hint">Minimum 8 characters. Share this with the new admin securely.</div>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem">
                <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('modal-create').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('modal-create').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
