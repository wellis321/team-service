<?php
/**
 * Teams — list and management page
 * Admins can create/edit/deactivate teams and team types here.
 */
require_once dirname(__DIR__) . '/config/config.php';

Auth::requireLogin();

$organisationId = Auth::getOrganisationId();
$isAdmin        = RBAC::isOrganisationAdmin() || RBAC::isSuperAdmin();
$error          = '';
$success        = '';

// ── POST actions (admin only) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!CSRF::validatePost()) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Create team ──────────────────────────────────────────────────
        if ($action === 'create_team') {
            $name        = trim($_POST['name']          ?? '');
            $typeId      = (int) ($_POST['team_type_id']   ?? 0) ?: null;
            $parentId    = (int) ($_POST['parent_team_id'] ?? 0) ?: null;
            $description = trim($_POST['description']   ?? '') ?: null;

            if (empty($name)) {
                $error = 'Team name is required.';
            } else {
                Team::create($organisationId, $name, $typeId, $parentId, $description);
                $success = "Team \"{$name}\" created.";
            }

        // ── Update team ──────────────────────────────────────────────────
        } elseif ($action === 'update_team') {
            $teamId      = (int) ($_POST['team_id'] ?? 0);
            $name        = trim($_POST['name']          ?? '');
            $typeId      = (int) ($_POST['team_type_id']   ?? 0) ?: null;
            $parentId    = (int) ($_POST['parent_team_id'] ?? 0) ?: null;
            $description = trim($_POST['description']   ?? '') ?: null;

            if (!$teamId || !Team::belongsToOrganisation($teamId, $organisationId)) {
                $error = 'Team not found.';
            } elseif (empty($name)) {
                $error = 'Team name is required.';
            } else {
                Team::update($teamId, compact('name', 'description') + [
                    'team_type_id'   => $typeId,
                    'parent_team_id' => $parentId,
                ]);
                $success = "Team updated.";
            }

        // ── Deactivate team ──────────────────────────────────────────────
        } elseif ($action === 'deactivate_team') {
            $teamId = (int) ($_POST['team_id'] ?? 0);
            if (!$teamId || !Team::belongsToOrganisation($teamId, $organisationId)) {
                $error = 'Team not found.';
            } else {
                Team::deactivate($teamId);
                $success = 'Team deactivated.';
            }

        // ── Create team type ─────────────────────────────────────────────
        } elseif ($action === 'create_type') {
            $name        = trim($_POST['type_name']    ?? '');
            $staffOnly   = isset($_POST['is_staff_only']);
            $description = trim($_POST['description']  ?? '') ?: null;

            if (empty($name)) {
                $error = 'Type name is required.';
            } else {
                TeamType::create($organisationId, $name, $staffOnly, $description);
                $success = "Team type \"{$name}\" created.";
            }

        // ── Delete team type ─────────────────────────────────────────────
        } elseif ($action === 'delete_type') {
            $typeId = (int) ($_POST['type_id'] ?? 0);
            if (!$typeId || !TeamType::belongsToOrganisation($typeId, $organisationId)) {
                $error = 'Team type not found.';
            } else {
                try {
                    TeamType::delete($typeId);
                    $success = 'Team type deleted.';
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────
$teams     = Team::findByOrganisation($organisationId);
$teamTypes = TeamType::findByOrganisation($organisationId);

// Enrich with member counts
$db = getDbConnection();
foreach ($teams as &$team) {
    $stmt = $db->prepare('
        SELECT member_type, COUNT(*) AS cnt
        FROM   team_members
        WHERE  team_id = ? AND left_at IS NULL
        GROUP  BY member_type
    ');
    $stmt->execute([$team['id']]);
    $counts = ['staff' => 0, 'person' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['member_type']] = (int) $row['cnt'];
    }
    $team['staff_count']  = $counts['staff'];
    $team['person_count'] = $counts['person'];
}
unset($team);

$pageTitle = 'Teams';
include INCLUDES_PATH . '/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Teams</h1>
        <p class="text-light" style="margin-top: 0.25rem;"><?php echo count($teams); ?> active team<?php echo count($teams) !== 1 ? 's' : ''; ?></p>
    </div>
    <?php if ($isAdmin): ?>
        <button class="btn btn-primary" onclick="document.getElementById('createTeamModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> New Team
        </button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Teams table -->
<div class="card">
    <?php if (empty($teams)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fa-solid fa-people-group" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
            <p>No teams yet.</p>
            <?php if ($isAdmin): ?>
                <button class="btn btn-primary" style="margin-top: 1rem;"
                        onclick="document.getElementById('createTeamModal').style.display='flex'">
                    <i class="fa-solid fa-plus"></i> Create your first team
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Type</th>
                        <th>Parent</th>
                        <th style="text-align:center;">Staff</th>
                        <th style="text-align:center;">People</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                    <tr>
                        <td>
                            <a href="<?php echo url('team-view.php?id=' . $team['id']); ?>"
                               style="font-weight: 500; color: var(--primary); text-decoration: none;">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </a>
                            <?php if ($team['description']): ?>
                                <div class="text-light text-small" style="margin-top: 0.15rem;">
                                    <?php echo htmlspecialchars(mb_strimwidth($team['description'], 0, 70, '…')); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($team['type_name']): ?>
                                <span class="badge <?php echo $team['is_staff_only'] ? 'badge-amber' : 'badge-blue'; ?>">
                                    <?php echo htmlspecialchars($team['type_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-light">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-light"><?php echo $team['parent_name'] ? htmlspecialchars($team['parent_name']) : '—'; ?></td>
                        <td style="text-align:center;">
                            <span class="badge badge-blue"><?php echo $team['staff_count']; ?></span>
                        </td>
                        <td style="text-align:center;">
                            <?php if (!$team['is_staff_only']): ?>
                                <span class="badge badge-green"><?php echo $team['person_count']; ?></span>
                            <?php else: ?>
                                <span class="text-light text-small">n/a</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="<?php echo url('team-view.php?id=' . $team['id']); ?>"
                               class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if ($isAdmin): ?>
                                <button class="btn btn-secondary btn-sm"
                                        onclick="openEditTeam(<?php echo htmlspecialchars(json_encode($team)); ?>)">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <form method="post" style="display:inline"
                                      onsubmit="return confirm('Deactivate this team?')">
                                    <?php echo CSRF::tokenField(); ?>
                                    <input type="hidden" name="action"  value="deactivate_team">
                                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Team Types panel (admin) -->
<?php if ($isAdmin): ?>
<div class="card mt-3">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1rem; font-weight: 600;">Team Types</h2>
        <button class="btn btn-secondary btn-sm"
                onclick="document.getElementById('createTypeModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> New Type
        </button>
    </div>
    <?php if (empty($teamTypes)): ?>
        <p class="text-light text-small">No team types defined yet.</p>
    <?php else: ?>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <?php foreach ($teamTypes as $type): ?>
                <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg);
                            padding: 0.4rem 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border);">
                    <span class="badge <?php echo $type['is_staff_only'] ? 'badge-amber' : 'badge-blue'; ?>">
                        <?php echo htmlspecialchars($type['name']); ?>
                    </span>
                    <?php if ($type['is_staff_only']): ?>
                        <span class="text-light text-small">staff only</span>
                    <?php endif; ?>
                    <form method="post" style="margin:0"
                          onsubmit="return confirm('Delete this team type?')">
                        <?php echo CSRF::tokenField(); ?>
                        <input type="hidden" name="action"  value="delete_type">
                        <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.15rem 0.4rem;">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Modals ──────────────────────────────────────────────────────────────── -->
<?php if ($isAdmin): ?>

<!-- Create Team Modal -->
<div id="createTeamModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus"></i> New Team</h3>
            <button onclick="this.closest('.modal-overlay').style.display='none'" class="modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="post">
            <?php echo CSRF::tokenField(); ?>
            <input type="hidden" name="action" value="create_team">
            <div class="form-group">
                <label>Team Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Home Care North">
            </div>
            <div class="form-group">
                <label>Team Type</label>
                <select name="team_type_id" class="form-control">
                    <option value="">— select —</option>
                    <?php foreach ($teamTypes as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['name']); ?>
                            <?php echo $t['is_staff_only'] ? ' (staff only)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Parent Team</label>
                <select name="parent_team_id" class="form-control">
                    <option value="">— none (top-level) —</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional…"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Modal -->
<div id="editTeamModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen"></i> Edit Team</h3>
            <button onclick="this.closest('.modal-overlay').style.display='none'" class="modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="post" id="editTeamForm">
            <?php echo CSRF::tokenField(); ?>
            <input type="hidden" name="action"  value="update_team">
            <input type="hidden" name="team_id" id="editTeamId">
            <div class="form-group">
                <label>Team Name *</label>
                <input type="text" name="name" id="editTeamName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Team Type</label>
                <select name="team_type_id" id="editTeamTypeId" class="form-control">
                    <option value="">— none —</option>
                    <?php foreach ($teamTypes as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Parent Team</label>
                <select name="parent_team_id" id="editParentId" class="form-control">
                    <option value="">— none —</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editTeamDesc" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Team Type Modal -->
<div id="createTypeModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus"></i> New Team Type</h3>
            <button onclick="this.closest('.modal-overlay').style.display='none'" class="modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="post">
            <?php echo CSRF::tokenField(); ?>
            <input type="hidden" name="action" value="create_type">
            <div class="form-group">
                <label>Type Name *</label>
                <input type="text" name="type_name" class="form-control" required placeholder="e.g. Care Team">
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" name="is_staff_only" value="1">
                    Staff only (cannot contain people supported)
                </label>
                <small class="text-light">Tick for functional teams like HR, Finance, IT.</small>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional…"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 200; display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(3px);
}
.modal-box {
    background: #fff; border-radius: var(--radius); width: 90%; max-width: 520px;
    box-shadow: 0 8px 24px rgba(0,0,0,.2); overflow: hidden;
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
    background: var(--bg);
}
.modal-header h3 { font-size: 1rem; font-weight: 600; margin: 0; }
.modal-close {
    background: none; border: none; cursor: pointer; color: var(--text-light);
    font-size: 1.125rem; padding: 0.25rem;
}
.modal-close:hover { color: var(--text); }
.modal-box form { padding: 1.5rem; }
</style>

<script>
function openEditTeam(team) {
    document.getElementById('editTeamId').value   = team.id;
    document.getElementById('editTeamName').value = team.name;
    document.getElementById('editTeamDesc').value = team.description || '';
    document.getElementById('editTeamTypeId').value = team.team_type_id || '';
    document.getElementById('editParentId').value   = team.parent_team_id || '';
    document.getElementById('editTeamModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
