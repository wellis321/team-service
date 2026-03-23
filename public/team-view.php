<?php
/**
 * Team detail — members list with add/remove for staff and people supported
 */
require_once dirname(__DIR__) . '/config/config.php';

Auth::requireLogin();

$organisationId = Auth::getOrganisationId();
$isAdmin        = RBAC::isOrganisationAdmin() || RBAC::isSuperAdmin();
$teamId         = (int) ($_GET['id'] ?? 0);
$error          = '';
$success        = '';

if (!$teamId || !Team::belongsToOrganisation($teamId, $organisationId)) {
    header('Location: ' . url('teams.php'));
    exit;
}

// ── POST actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!CSRF::validatePost()) {
        $error = 'Security token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_member') {
            $memberType  = $_POST['member_type']  ?? '';
            $externalId  = (int) ($_POST['external_id'] ?? 0);
            $displayName = trim($_POST['display_name'] ?? '');
            $displayRef  = trim($_POST['display_ref']  ?? '') ?: null;
            $roleId      = (int) ($_POST['team_role_id'] ?? 0) ?: null;
            $isPrimary   = isset($_POST['is_primary_team']);
            $joinedAt    = $_POST['joined_at'] ?: null;
            $notes       = trim($_POST['notes'] ?? '') ?: null;

            if (!in_array($memberType, ['staff','person','user'], true) || !$externalId || !$displayName) {
                $error = 'Member type, ID and display name are all required.';
            } else {
                TeamMember::add(
                    $teamId, $organisationId, $memberType, $externalId,
                    $displayName, $displayRef, $roleId, $isPrimary, $joinedAt, $notes
                );
                $success = htmlspecialchars($displayName) . ' added to the team.';
            }

        } elseif ($action === 'remove_member') {
            $memberType = $_POST['member_type'] ?? '';
            $externalId = (int) ($_POST['external_id'] ?? 0);
            $leftAt     = $_POST['left_at'] ?: null;

            if (TeamMember::remove($teamId, $memberType, $externalId, $leftAt)) {
                $success = 'Member removed from team.';
            } else {
                $error = 'Could not remove member — they may have already left.';
            }

        } elseif ($action === 'update_member_role') {
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $roleId   = (int) ($_POST['team_role_id'] ?? 0) ?: null;
            $isPrimary = isset($_POST['is_primary_team']);
            TeamMember::update($memberId, ['team_role_id' => $roleId, 'is_primary_team' => $isPrimary]);
            $success = 'Member updated.';
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────
$team       = Team::findById($teamId);
$breadcrumb = Team::getAncestorPath($teamId);

// Fetch member lists from connected services for the search UI
$staffList  = StaffServiceClient::fetchAll();
$peopleList = PeopleServiceClient::fetchAll();
$staffJson  = json_encode($staffList,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$peopleJson = json_encode($peopleList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$staff      = TeamMember::getForTeam($teamId, 'staff');
$people     = TeamMember::getForTeam($teamId, 'person');
$roles      = TeamRole::findByOrganisation($organisationId);
$staffRoles = array_filter($roles, fn($r) => in_array($r['applies_to'], ['staff','both'], true));
$personRoles = array_filter($roles, fn($r) => in_array($r['applies_to'], ['person','both'], true));
$isStaffOnly = (bool) ($team['is_staff_only'] ?? false);

$pageTitle = htmlspecialchars($team['name']);
include INCLUDES_PATH . '/header.php';
?>

<!-- Breadcrumb -->
<div style="margin-bottom: 1.5rem; font-size: 0.875rem; color: var(--text-light);">
    <a href="<?php echo url('teams.php'); ?>" style="color: var(--primary); text-decoration: none;">Teams</a>
    <?php foreach ($breadcrumb as $crumb): ?>
        <span style="margin: 0 0.4rem;">›</span>
        <?php if ((int)$crumb['id'] === $teamId): ?>
            <span style="color: var(--text);"><?php echo htmlspecialchars($crumb['name']); ?></span>
        <?php else: ?>
            <a href="<?php echo url('team-view.php?id=' . $crumb['id']); ?>"
               style="color: var(--primary); text-decoration: none;">
                <?php echo htmlspecialchars($crumb['name']); ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Team header -->
<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.5rem; font-weight:700; display:flex; align-items:center; gap:0.5rem;">
            <?php echo htmlspecialchars($team['name']); ?>
            <?php if ($team['type_name']): ?>
                <span class="badge <?php echo $isStaffOnly ? 'badge-amber' : 'badge-blue'; ?>" style="font-size:0.75rem;">
                    <?php echo htmlspecialchars($team['type_name']); ?>
                    <?php echo $isStaffOnly ? ' · staff only' : ''; ?>
                </span>
            <?php endif; ?>
        </h1>
        <?php if ($team['description']): ?>
            <p class="text-light" style="margin-top:0.25rem;"><?php echo htmlspecialchars($team['description']); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
        <button class="btn btn-primary"
                onclick="document.getElementById('addMemberModal').style.display='flex'">
            <i class="fa-solid fa-user-plus"></i> Add Member
        </button>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Stats strip -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="text-align:center; padding:1rem;">
        <div style="font-size:1.75rem; font-weight:700; color:var(--primary);"><?php echo count($staff); ?></div>
        <div class="text-light text-small">Staff</div>
    </div>
    <?php if (!$isStaffOnly): ?>
    <div class="card" style="text-align:center; padding:1rem;">
        <div style="font-size:1.75rem; font-weight:700; color:var(--success);"><?php echo count($people); ?></div>
        <div class="text-light text-small">People Supported</div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Staff members ───────────────────────────────────────────────────── -->
<div class="card">
    <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">
        <i class="fa-solid fa-user-tie" style="color:var(--primary); margin-right:0.4rem;"></i>
        Staff (<?php echo count($staff); ?>)
    </h2>
    <?php if (empty($staff)): ?>
        <p class="text-light text-small">No staff members in this team yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Reference</th>
                        <th>Role in Team</th>
                        <th>Primary Team</th>
                        <th>Joined</th>
                        <?php if ($isAdmin): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $m): ?>
                    <tr>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($m['display_name'] ?? '—'); ?></td>
                        <td class="text-light"><?php echo htmlspecialchars($m['display_ref'] ?? '—'); ?></td>
                        <td>
                            <?php if ($m['role_name']): ?>
                                <span class="badge badge-blue"><?php echo htmlspecialchars($m['role_name']); ?></span>
                            <?php else: ?>
                                <span class="text-light">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['is_primary_team']): ?>
                                <span class="badge badge-green"><i class="fa-solid fa-star"></i> Primary</span>
                            <?php else: ?>
                                <span class="text-light">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-light">
                            <?php echo $m['joined_at'] ? date('d M Y', strtotime($m['joined_at'])) : '—'; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td style="text-align:right; white-space:nowrap;">
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Remove this staff member from the team?')">
                                <?php echo CSRF::tokenField(); ?>
                                <input type="hidden" name="action"      value="remove_member">
                                <input type="hidden" name="member_type" value="staff">
                                <input type="hidden" name="external_id" value="<?php echo $m['external_id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-user-minus"></i> Remove
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ── People supported ────────────────────────────────────────────────── -->
<?php if (!$isStaffOnly): ?>
<div class="card mt-2">
    <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">
        <i class="fa-solid fa-heart" style="color:var(--success); margin-right:0.4rem;"></i>
        People Supported (<?php echo count($people); ?>)
    </h2>
    <?php if (empty($people)): ?>
        <p class="text-light text-small">No people supported linked to this team yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Reference</th>
                        <th>Role / Status</th>
                        <th>Joined</th>
                        <?php if ($isAdmin): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $m): ?>
                    <tr>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($m['display_name'] ?? '—'); ?></td>
                        <td class="text-light"><?php echo htmlspecialchars($m['display_ref'] ?? '—'); ?></td>
                        <td>
                            <?php if ($m['role_name']): ?>
                                <span class="badge badge-green"><?php echo htmlspecialchars($m['role_name']); ?></span>
                            <?php else: ?>
                                <span class="text-light">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-light">
                            <?php echo $m['joined_at'] ? date('d M Y', strtotime($m['joined_at'])) : '—'; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Remove this person from the team?')">
                                <?php echo CSRF::tokenField(); ?>
                                <input type="hidden" name="action"      value="remove_member">
                                <input type="hidden" name="member_type" value="person">
                                <input type="hidden" name="external_id" value="<?php echo $m['external_id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-user-minus"></i> Remove
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Add Member Modal (admin) ───────────────────────────────────────── -->
<?php if ($isAdmin): ?>
<div id="addMemberModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> Add Member</h3>
            <button onclick="this.closest('.modal-overlay').style.display='none'" class="modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="post">
            <?php echo CSRF::tokenField(); ?>
            <input type="hidden" name="action" value="add_member">

            <div class="form-group">
                <label>Member Type *</label>
                <select name="member_type" class="form-control" id="memberTypeSelect" onchange="updateMemberUI()">
                    <option value="staff">Staff member (from Staff Service)</option>
                    <?php if (!$isStaffOnly): ?>
                    <option value="person">Person supported (from People Service)</option>
                    <?php endif; ?>
                    <option value="user">System user</option>
                </select>
            </div>

            <!-- Hidden fields populated by search selection -->
            <input type="hidden" name="external_id" id="modalExternalId">
            <input type="hidden" name="display_name" id="modalDisplayName">
            <input type="hidden" name="display_ref"  id="modalDisplayRef">

            <!-- Search UI (shown when service is connected) -->
            <div class="form-group" id="memberSearchGroup">
                <label>Search <span id="memberTypeLabel">Member</span> *</label>
                <input type="text" id="memberSearchInput" class="form-control"
                       placeholder="Type a name to search…" autocomplete="off">
                <div id="memberSearchResults" class="member-search-results"></div>
                <div id="memberSelected" class="member-selected" style="display:none">
                    <i class="fa-solid fa-circle-check" style="color:var(--success)"></i>
                    <span id="memberSelectedName"></span>
                    <button type="button" onclick="clearMemberSelection()" class="btn-clear-selection">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Manual fallback (shown when service not configured) -->
            <div class="form-group" id="manualIdGroup" style="display:none">
                <label>External ID * <small class="text-light">(their ID in the source service)</small></label>
                <input type="number" id="manualExternalId" class="form-control" min="1" placeholder="e.g. 42"
                       oninput="document.getElementById('modalExternalId').value=this.value">
            </div>
            <div class="form-group" id="manualNameGroup" style="display:none">
                <label>Display Name * <small class="text-light">(full name for display)</small></label>
                <input type="text" id="manualDisplayName" class="form-control" placeholder="e.g. Jane Smith"
                       oninput="document.getElementById('modalDisplayName').value=this.value">
            </div>
            <div class="form-group" id="manualRefGroup" style="display:none">
                <label>Reference <small class="text-light">(employee ref, CHI number, etc.)</small></label>
                <input type="text" id="manualDisplayRef" class="form-control" placeholder="Optional"
                       oninput="document.getElementById('modalDisplayRef').value=this.value">
            </div>

            <div class="form-group">
                <label>Role in Team</label>
                <select name="team_role_id" class="form-control" id="roleSelect">
                    <option value="">— no specific role —</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"
                                data-applies="<?php echo $r['applies_to']; ?>">
                            <?php echo htmlspecialchars($r['name']); ?>
                            (<?php echo $r['applies_to'] === 'both' ? 'staff &amp; people' : $r['applies_to']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="primaryTeamGroup">
                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" name="is_primary_team" value="1">
                    This is their primary team
                </label>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div class="form-group">
                    <label>Joined Date</label>
                    <input type="date" name="joined_at" class="form-control"
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional…"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                <button type="button" class="btn btn-secondary"
                        onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Add
                </button>
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
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
    background: var(--bg); position: sticky; top: 0;
}
.modal-header h3 { font-size: 1rem; font-weight: 600; margin: 0; }
.modal-close {
    background: none; border: none; cursor: pointer; color: var(--text-light);
    font-size: 1.125rem; padding: 0.25rem;
}
.modal-close:hover { color: var(--text); }
.modal-box form { padding: 1.5rem; }
.member-search-results {
    border: 1px solid var(--border); border-top: none; border-radius: 0 0 0.375rem 0.375rem;
    max-height: 200px; overflow-y: auto; background: #fff;
    display: none;
}
.member-search-results.open { display: block; }
.member-result-item {
    padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.875rem;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
}
.member-result-item:last-child { border-bottom: none; }
.member-result-item:hover { background: var(--bg); }
.member-result-ref { font-size: 0.75rem; color: var(--text-light); }
.member-result-empty { padding: 0.75rem; font-size: 0.875rem; color: var(--text-light); text-align: center; }
.member-selected {
    margin-top: 0.5rem; padding: 0.5rem 0.75rem;
    background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 0.375rem;
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;
}
.member-selected span { flex: 1; font-weight: 500; }
.btn-clear-selection {
    background: none; border: none; cursor: pointer; color: var(--text-light);
    padding: 0.125rem; line-height: 1;
}
.btn-clear-selection:hover { color: var(--danger); }
</style>

<script>
const staffData  = <?php echo $staffJson; ?>;
const peopleData = <?php echo $peopleJson; ?>;
const hasStaff   = staffData.length > 0;
const hasPeople  = peopleData.length > 0;

function updateMemberUI() {
    const type    = document.getElementById('memberTypeSelect').value;
    const select  = document.getElementById('roleSelect');
    const primary = document.getElementById('primaryTeamGroup');
    const label   = document.getElementById('memberTypeLabel');
    const searchGroup = document.getElementById('memberSearchGroup');
    const manualId    = document.getElementById('manualIdGroup');
    const manualName  = document.getElementById('manualNameGroup');
    const manualRef   = document.getElementById('manualRefGroup');

    // Primary team only for staff
    primary.style.display = type === 'staff' ? '' : 'none';

    // Filter role options
    Array.from(select.options).forEach(opt => {
        if (!opt.value) return;
        const applies = opt.dataset.applies;
        opt.hidden = !(applies === 'both' || applies === type);
    });
    if (select.selectedOptions[0]?.hidden) select.value = '';

    // Show search or manual inputs
    const useSearch = (type === 'staff' && hasStaff) || (type === 'person' && hasPeople) || (type === 'user');
    searchGroup.style.display = (useSearch && type !== 'user') ? '' : 'none';
    manualId.style.display    = (!useSearch || type === 'user') ? '' : 'none';
    manualName.style.display  = (!useSearch || type === 'user') ? '' : 'none';
    manualRef.style.display   = (!useSearch || type === 'user') ? '' : 'none';

    label.textContent = type === 'staff' ? 'Staff Member' : type === 'person' ? 'Person Supported' : 'Member';

    clearMemberSelection();
    document.getElementById('memberSearchInput').value = '';
    document.getElementById('memberSearchResults').classList.remove('open');
    document.getElementById('memberSearchResults').innerHTML = '';
}

function getList() {
    const type = document.getElementById('memberTypeSelect').value;
    return type === 'staff' ? staffData : type === 'person' ? peopleData : [];
}

document.addEventListener('DOMContentLoaded', function () {
    const input   = document.getElementById('memberSearchInput');
    const results = document.getElementById('memberSearchResults');

    input.addEventListener('input', function () {
        const q    = this.value.trim().toLowerCase();
        const list = getList();
        results.innerHTML = '';

        if (!q) { results.classList.remove('open'); return; }

        const matches = list.filter(m =>
            m.name.toLowerCase().includes(q) ||
            (m.ref && m.ref.toLowerCase().includes(q))
        ).slice(0, 10);

        if (!matches.length) {
            results.innerHTML = '<div class="member-result-empty">No results found</div>';
        } else {
            matches.forEach(m => {
                const div = document.createElement('div');
                div.className = 'member-result-item';
                div.innerHTML = `<span>${escHtml(m.name)}</span>${m.ref ? `<span class="member-result-ref">${escHtml(m.ref)}</span>` : ''}`;
                div.addEventListener('click', () => selectMember(m));
                results.appendChild(div);
            });
        }
        results.classList.add('open');
    });

    // Close results when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#memberSearchGroup')) {
            results.classList.remove('open');
        }
    });
});

function selectMember(m) {
    document.getElementById('modalExternalId').value  = m.id;
    document.getElementById('modalDisplayName').value = m.name;
    document.getElementById('modalDisplayRef').value  = m.ref || '';
    document.getElementById('memberSelectedName').textContent = m.name + (m.ref ? ' — ' + m.ref : '');
    document.getElementById('memberSelected').style.display  = 'flex';
    document.getElementById('memberSearchInput').value = '';
    document.getElementById('memberSearchResults').classList.remove('open');
    document.getElementById('memberSearchResults').innerHTML = '';
}

function clearMemberSelection() {
    document.getElementById('modalExternalId').value  = '';
    document.getElementById('modalDisplayName').value = '';
    document.getElementById('modalDisplayRef').value  = '';
    document.getElementById('memberSelected').style.display = 'none';
    document.getElementById('memberSelectedName').textContent = '';
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Run on load
updateMemberUI();
</script>
<?php endif; ?>

<?php include INCLUDES_PATH . '/footer.php'; ?>
