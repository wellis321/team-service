<?php
/**
 * Team Service — Dashboard
 */
require_once dirname(__DIR__) . '/config/config.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . url('landing.php'));
    exit;
}

$organisationId = Auth::getOrganisationId();
$db             = getDbConnection();

// ── Stats ──────────────────────────────────────────────────────────────────
$stats = [];

$stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE organisation_id = ? AND is_active = TRUE');
$stmt->execute([$organisationId]);
$stats['teams'] = (int) $stmt->fetchColumn();

$stmt = $db->prepare('
    SELECT COUNT(*) FROM team_members tm
    JOIN teams t ON tm.team_id = t.id
    WHERE t.organisation_id = ? AND tm.left_at IS NULL AND tm.member_type = "staff"
');
$stmt->execute([$organisationId]);
$stats['staff'] = (int) $stmt->fetchColumn();

$stmt = $db->prepare('
    SELECT COUNT(*) FROM team_members tm
    JOIN teams t ON tm.team_id = t.id
    WHERE t.organisation_id = ? AND tm.left_at IS NULL AND tm.member_type = "person"
');
$stmt->execute([$organisationId]);
$stats['people'] = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM team_types WHERE organisation_id = ? AND is_active = TRUE');
$stmt->execute([$organisationId]);
$stats['types'] = (int) $stmt->fetchColumn();

// ── Recent teams ───────────────────────────────────────────────────────────
$stmt = $db->prepare('
    SELECT t.*, tt.name AS type_name,
           (SELECT COUNT(*) FROM team_members tm
            WHERE tm.team_id = t.id AND tm.left_at IS NULL) AS member_count
    FROM   teams t
    LEFT JOIN team_types tt ON t.team_type_id = tt.id
    WHERE  t.organisation_id = ? AND t.is_active = TRUE
    ORDER  BY t.created_at DESC
    LIMIT  8
');
$stmt->execute([$organisationId]);
$recentTeams = $stmt->fetchAll();

$pageTitle = 'Dashboard';
include INCLUDES_PATH . '/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Dashboard</h1>
        <p class="text-light" style="margin-top: 0.25rem;">Overview of your organisation's teams and membership.</p>
    </div>
    <?php if (RBAC::isOrganisationAdmin() || RBAC::isSuperAdmin()): ?>
        <a href="<?php echo url('teams.php'); ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Team
        </a>
    <?php endif; ?>
</div>

<!-- Stats row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <?php
    $cards = [
        ['icon' => 'fa-people-group', 'value' => $stats['teams'],  'label' => 'Active Teams',       'colour' => '#2563eb'],
        ['icon' => 'fa-user-tie',     'value' => $stats['staff'],  'label' => 'Staff Members',       'colour' => '#7c3aed'],
        ['icon' => 'fa-heart',        'value' => $stats['people'], 'label' => 'People Supported',    'colour' => '#059669'],
        ['icon' => 'fa-tags',         'value' => $stats['types'],  'label' => 'Team Types',          'colour' => '#d97706'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="card" style="text-align: center; padding: 1.25rem;">
        <i class="fa-solid <?php echo $c['icon']; ?>"
           style="font-size: 1.75rem; color: <?php echo $c['colour']; ?>; margin-bottom: 0.5rem; display: block;"></i>
        <div style="font-size: 2rem; font-weight: 700; line-height: 1;"><?php echo number_format($c['value']); ?></div>
        <div class="text-light text-small" style="margin-top: 0.25rem;"><?php echo $c['label']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent teams -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 600;">Teams</h2>
        <a href="<?php echo url('teams.php'); ?>" class="btn btn-secondary btn-sm">
            View all <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($recentTeams)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fa-solid fa-people-group" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
            <p>No teams yet.</p>
            <?php if (RBAC::isOrganisationAdmin() || RBAC::isSuperAdmin()): ?>
                <a href="<?php echo url('teams.php'); ?>" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fa-solid fa-plus"></i> Create your first team
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Type</th>
                        <th>Members</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTeams as $team): ?>
                    <tr>
                        <td>
                            <a href="<?php echo url('team-view.php?id=' . $team['id']); ?>"
                               style="font-weight: 500; color: var(--primary); text-decoration: none;">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </a>
                            <?php if ($team['description']): ?>
                                <div class="text-light text-small" style="margin-top: 0.15rem;">
                                    <?php echo htmlspecialchars(mb_strimwidth($team['description'], 0, 80, '…')); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($team['type_name']): ?>
                                <span class="badge badge-blue"><?php echo htmlspecialchars($team['type_name']); ?></span>
                            <?php else: ?>
                                <span class="text-light">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-grey"><?php echo $team['member_count']; ?></span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?php echo url('team-view.php?id=' . $team['id']); ?>"
                               class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
