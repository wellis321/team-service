<?php
/**
 * Team Service — Demo Data Seeder
 *
 * Creates team types, roles, and teams for the Sunrise Care demo organisation.
 * Safe to run multiple times — skips if demo data already exists.
 * Super admin access only.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

Auth::requireLogin();
if (!RBAC::isSuperAdmin()) {
    header('Location: ' . url('index.php'));
    exit;
}

$db      = getDbConnection();
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validatePost()) {

    try {
        $db->beginTransaction();

        // ── 1. Find (or create) Sunrise Care org ─────────────────────────────
        $orgRow = $db->prepare('SELECT id FROM organisations WHERE domain = ? LIMIT 1');
        $orgRow->execute(['sunrisecare.demo']);
        $orgId = $orgRow->fetchColumn();

        if (!$orgId) {
            // Create the org locally so team data can be scoped to it
            $db->prepare('INSERT INTO organisations (name, domain) VALUES (?, ?)')
               ->execute(['Sunrise Care', 'sunrisecare.demo']);
            $orgId = (int) $db->lastInsertId();

            // Admin user
            $db->prepare('
                INSERT INTO users (organisation_id, email, password_hash, first_name, last_name, is_active, email_verified)
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ')->execute([
                $orgId,
                'admin@sunrisecare.demo',
                password_hash('Sunrise2024!', PASSWORD_DEFAULT),
                'Demo',
                'Admin',
            ]);
            $adminUserId = (int) $db->lastInsertId();
            RBAC::assignRole($adminUserId, 'organisation_admin');
        } else {
            $orgId = (int) $orgId;
        }

        // Check if demo teams already exist
        $existing = $db->prepare('SELECT COUNT(*) FROM teams WHERE organisation_id = ?');
        $existing->execute([$orgId]);
        if ((int) $existing->fetchColumn() > 0) {
            $db->rollBack();
            $error = 'Demo team data already exists for Sunrise Care. Delete it first if you want to re-seed.';
            goto render;
        }

        // ── 2. Team types ─────────────────────────────────────────────────────
        $insertType = $db->prepare('
            INSERT INTO team_types (organisation_id, name, description, is_staff_only, display_order)
            VALUES (?, ?, ?, ?, ?)
        ');

        $insertType->execute([$orgId, 'Care Team',    'Direct care and support teams',    0, 1]);
        $careTypeId = (int) $db->lastInsertId();

        $insertType->execute([$orgId, 'Management',   'Management and leadership teams',   1, 2]);
        $mgmtTypeId = (int) $db->lastInsertId();

        $insertType->execute([$orgId, 'Support Staff', 'Internal support functions',       1, 3]);

        // ── 3. Team roles ─────────────────────────────────────────────────────
        $insertRole = $db->prepare('
            INSERT INTO team_roles (organisation_id, name, description, applies_to, access_level, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $insertRole->execute([$orgId, 'Key Worker',       'Primary key worker for person supported', 'both',   'team',         1]);
        $insertRole->execute([$orgId, 'Team Lead',        'Leads the team day-to-day',               'staff',  'team',         2]);
        $insertRole->execute([$orgId, 'Support Worker',   'Provides direct support',                 'both',   'team',         3]);
        $insertRole->execute([$orgId, 'Night Worker',     'Night shift support worker',              'staff',  'team',         4]);
        $insertRole->execute([$orgId, 'Registered Manager', 'Responsible for the service',           'staff',  'organisation', 5]);

        // ── 4. Teams ──────────────────────────────────────────────────────────
        $insertTeam = $db->prepare('
            INSERT INTO teams (organisation_id, team_type_id, name, description)
            VALUES (?, ?, ?, ?)
        ');

        $insertTeam->execute([
            $orgId, $careTypeId,
            'Sunrise Community Support',
            'Community-based support team working with people in their own homes.',
        ]);
        $insertTeam->execute([
            $orgId, $careTypeId,
            'Sunrise Residential Support',
            'Residential care team providing 24/7 support at Sunrise House.',
        ]);
        $insertTeam->execute([
            $orgId, $mgmtTypeId,
            'Management Team',
            'Senior leadership and management across all Sunrise Care services.',
        ]);

        $db->commit();

        $message = 'Demo team data created for <strong>Sunrise Care</strong>:'
                 . ' 3 team types, 5 team roles, and 3 teams.'
                 . ' Use the Teams pages to assign members.';

    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}

render:
$pageTitle = 'Seed Demo Data';
include INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-seedling"></i> Seed Demo Data</h1>
        <p class="text-light text-small" style="margin-top:.25rem">
            Creates team types, roles, and teams for <strong>Sunrise Care</strong>.
        </p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
<?php endif; ?>

<div class="card" style="max-width:640px">
    <h3 style="font-weight:600;margin-bottom:.75rem">What this will create</h3>
    <ul style="margin:.5rem 0 1.25rem 1.25rem;line-height:1.8">
        <li>Organisation: <strong>Sunrise Care</strong> (created here if not yet provisioned)</li>
        <li>Team types: Care Team, Management, Support Staff</li>
        <li>Team roles: Key Worker, Team Lead, Support Worker, Night Worker, Registered Manager</li>
        <li>Teams: Sunrise Community Support, Sunrise Residential Support, Management Team</li>
    </ul>
    <p class="text-light text-small" style="margin-bottom:1.25rem">
        Members are not added automatically — use the Teams UI to assign them once the Staff Service and People Service integrations are configured.
    </p>

    <?php if (!$message): ?>
    <form method="POST">
        <?php echo CSRF::tokenField(); ?>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-seedling"></i> Create Demo Data
        </button>
        <a href="<?php echo url('admin/organisations.php'); ?>" class="btn btn-secondary" style="margin-left:.5rem">Cancel</a>
    </form>
    <?php else: ?>
    <a href="<?php echo url('teams.php'); ?>" class="btn btn-primary">
        <i class="fas fa-users"></i> View Teams
    </a>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
