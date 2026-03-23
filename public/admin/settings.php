<?php
/**
 * Team Service — Admin Settings
 *
 * Manages:
 *   • Team Types  (create, edit, toggle is_staff_only, delete)
 *   • Team Roles  (create, edit, delete)
 *   • API Keys    (create, revoke) for service-to-service authentication
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

Auth::requireLogin();
if (!RBAC::isOrganisationAdmin() && !RBAC::isSuperAdmin()) {
    header('Location: ' . url('index.php'));
    exit;
}

$organisationId = Auth::getOrganisationId();
$db             = getDbConnection();
$error          = '';
$success        = '';

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validatePost()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Team types ────────────────────────────────────────────────────
        if ($action === 'create_type') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isStaffOnly = !empty($_POST['is_staff_only']);
            if ($name === '') {
                $error = 'Team type name is required.';
            } else {
                try {
                    TeamType::create($organisationId, $name, $isStaffOnly, $description);
                    $success = 'Team type created.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($action === 'edit_type') {
            $id          = (int) ($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isStaffOnly = !empty($_POST['is_staff_only']);
            if (!$id || $name === '') {
                $error = 'Invalid data.';
            } else {
                try {
                    TeamType::update($id, $organisationId, ['name' => $name, 'description' => $description, 'is_staff_only' => $isStaffOnly]);
                    $success = 'Team type updated.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($action === 'delete_type') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                try {
                    TeamType::delete($id, $organisationId);
                    $success = 'Team type deleted.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // ── Team roles ────────────────────────────────────────────────────
        if ($action === 'create_role') {
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $appliesTo   = $_POST['applies_to']   ?? 'staff';
            $accessLevel = $_POST['access_level'] ?? 'team';
            if ($name === '') {
                $error = 'Role name is required.';
            } else {
                try {
                    TeamRole::create($organisationId, $name, $appliesTo, $accessLevel, $description);
                    $success = 'Team role created.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($action === 'edit_role') {
            $id          = (int) ($_POST['id'] ?? 0);
            $name        = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $appliesTo   = $_POST['applies_to']   ?? 'staff';
            $accessLevel = $_POST['access_level'] ?? 'team';
            if (!$id || $name === '') {
                $error = 'Invalid data.';
            } else {
                try {
                    TeamRole::update($id, $organisationId, [
                        'name'         => $name,
                        'description'  => $description,
                        'applies_to'   => $appliesTo,
                        'access_level' => $accessLevel,
                    ]);
                    $success = 'Team role updated.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        if ($action === 'delete_role') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                try {
                    TeamRole::delete($id, $organisationId);
                    $success = 'Team role deleted.';
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        // ── Staff Service integration ─────────────────────────────────────
        if ($action === 'save_staff_service') {
            $url    = trim($_POST['staff_service_url']     ?? '');
            $apiKey = trim($_POST['staff_service_api_key'] ?? '');
            $existing = OrgSettings::get($organisationId, 'staff_service_api_key');
            if ($apiKey === '' && $existing !== '') $apiKey = $existing;
            OrgSettings::setMany($organisationId, [
                'staff_service_url'     => $url,
                'staff_service_api_key' => $apiKey,
            ]);
            // Auto-resolve remote org ID by domain
            if ($url && $apiKey) {
                $orgRow = $db->prepare('SELECT domain FROM organisations WHERE id = ? LIMIT 1');
                $orgRow->execute([$organisationId]);
                $domain = $orgRow->fetchColumn();
                if ($domain) {
                    $remoteId = StaffServiceClient::orgLookup($url, $apiKey, $domain);
                    if ($remoteId) OrgSettings::set($organisationId, 'staff_service_org_id', (string) $remoteId);
                }
            }
            $success = 'Staff Service settings saved.';
        }

        if ($action === 'test_staff_service') {
            $url    = trim($_POST['staff_service_url']     ?? '');
            $apiKey = trim($_POST['staff_service_api_key'] ?? '');
            $testTarget = 'staff';
            $testResult = ($url && $apiKey)
                ? (StaffServiceClient::testConnection($url, $apiKey) ? 'success' : 'fail')
                : 'missing';
        }

        if ($action === 'clear_staff_service') {
            OrgSettings::setMany($organisationId, ['staff_service_url' => '', 'staff_service_api_key' => '']);
            $success = 'Staff Service disconnected.';
        }

        // ── People Service integration ────────────────────────────────────
        if ($action === 'save_people_service') {
            $url    = trim($_POST['people_service_url']     ?? '');
            $apiKey = trim($_POST['people_service_api_key'] ?? '');
            $existing = OrgSettings::get($organisationId, 'people_service_api_key');
            if ($apiKey === '' && $existing !== '') $apiKey = $existing;
            OrgSettings::setMany($organisationId, [
                'people_service_url'     => $url,
                'people_service_api_key' => $apiKey,
            ]);
            // Auto-resolve remote org ID by domain
            if ($url && $apiKey) {
                $orgRow = $db->prepare('SELECT domain FROM organisations WHERE id = ? LIMIT 1');
                $orgRow->execute([$organisationId]);
                $domain = $orgRow->fetchColumn();
                if ($domain) {
                    $remoteId = PeopleServiceClient::orgLookup($url, $apiKey, $domain);
                    if ($remoteId) OrgSettings::set($organisationId, 'people_service_org_id', (string) $remoteId);
                }
            }
            $success = 'People Service settings saved.';
        }

        if ($action === 'test_people_service') {
            $url    = trim($_POST['people_service_url']     ?? '');
            $apiKey = trim($_POST['people_service_api_key'] ?? '');
            $testTarget = 'people';
            $testResult = ($url && $apiKey)
                ? (PeopleServiceClient::testConnection($url, $apiKey) ? 'success' : 'fail')
                : 'missing';
        }

        if ($action === 'clear_people_service') {
            OrgSettings::setMany($organisationId, ['people_service_url' => '', 'people_service_api_key' => '']);
            $success = 'People Service disconnected.';
        }

        // ── API keys ──────────────────────────────────────────────────────
        if ($action === 'create_api_key') {
            $name    = trim($_POST['api_key_name']    ?? '');
            $service = trim($_POST['connected_service'] ?? '');
            if ($name === '') {
                $error = 'API key name is required.';
            } else {
                $rawKey  = bin2hex(random_bytes(32));
                $keyHash = hash('sha256', $rawKey);
                $stmt    = $db->prepare('
                    INSERT INTO api_keys (organisation_id, name, connected_service, key_hash)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([$organisationId, $name, $service ?: null, $keyHash]);
                // Show the raw key once — it cannot be retrieved again
                $success = 'API key created. Copy this key now — it will not be shown again: <code style="word-break:break-all;">' . htmlspecialchars($rawKey) . '</code>';
            }
        }

        if ($action === 'revoke_api_key') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $db->prepare('UPDATE api_keys SET is_active = FALSE WHERE id = ? AND organisation_id = ?');
                $stmt->execute([$id, $organisationId]);
                $success = 'API key revoked.';
            }
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$teamTypes = TeamType::findByOrganisation($organisationId);
$teamRoles = TeamRole::findByOrganisation($organisationId);

$testResult = null;
$testTarget = '';

$staffUrl    = OrgSettings::get($organisationId, 'staff_service_url',      STAFF_SERVICE_URL);
$staffKeySet = OrgSettings::get($organisationId, 'staff_service_api_key',  STAFF_SERVICE_API_KEY) !== '';
$peopleUrl    = OrgSettings::get($organisationId, 'people_service_url',    PEOPLE_SERVICE_URL);
$peopleKeySet = OrgSettings::get($organisationId, 'people_service_api_key', PEOPLE_SERVICE_API_KEY) !== '';

$stmt = $db->prepare('
    SELECT id, name, connected_service, is_active, last_used_at, created_at
    FROM   api_keys
    WHERE  organisation_id = ?
    ORDER  BY created_at DESC
');
$stmt->execute([$organisationId]);
$apiKeys = $stmt->fetchAll();

$pageTitle = 'Settings';
include INCLUDES_PATH . '/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Settings</h1>
        <p class="text-light" style="margin-top: 0.25rem;">Manage team types, roles, and API access.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
<?php endif; ?>
<?php if ($testResult === 'success'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i>
        Connection successful — <?php echo $testTarget === 'staff' ? 'Staff Service' : 'People Service'; ?> is reachable.</div>
<?php elseif ($testResult === 'fail'): ?>
    <div class="alert alert-error"><i class="fa-solid fa-times-circle"></i> Connection failed — check the URL and API key.</div>
<?php elseif ($testResult === 'missing'): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-exclamation-triangle"></i> Please enter both a URL and API key to test.</div>
<?php endif; ?>

<!-- ── Staff Service Integration ──────────────────────────────────────────── -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
        <div>
            <h2 style="font-size:1.1rem; font-weight:600;"><i class="fa-solid fa-users" style="color:var(--primary)"></i> Staff Service Integration</h2>
            <p class="text-light text-small" style="margin-top:.25rem">Connect to the Staff Service so members can be searched and added to teams by name.</p>
        </div>
        <?php if ($staffUrl && $staffKeySet): ?>
            <span class="badge badge-green">Connected</span>
        <?php else: ?>
            <span class="badge badge-grey">Not configured</span>
        <?php endif; ?>
    </div>
    <form method="POST">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="save_staff_service">
        <div class="form-group">
            <label>Staff Service URL</label>
            <input type="url" name="staff_service_url" class="form-control" id="staff_service_url"
                   placeholder="https://your-staff-service.hostingersite.com/public"
                   value="<?php echo htmlspecialchars($staffUrl); ?>">
        </div>
        <div class="form-group">
            <label>API Key</label>
            <input type="password" name="staff_service_api_key" class="form-control" id="staff_service_api_key"
                   placeholder="<?php echo $staffKeySet ? '(saved — leave blank to keep)' : 'Paste key from Staff Service → Admin → Settings → API Keys'; ?>">
            <?php if ($staffKeySet): ?><div class="form-hint">Leave blank to keep the existing key.</div><?php endif; ?>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save</button>
            <button type="submit" form="test-staff-form" class="btn btn-secondary"><i class="fa-solid fa-plug"></i> Test</button>
            <?php if ($staffUrl || $staffKeySet): ?>
            <button type="submit" form="clear-staff-form" class="btn btn-danger"
                    onclick="return confirm('Disconnect the Staff Service?')">
                <i class="fa-solid fa-unlink"></i> Disconnect
            </button>
            <?php endif; ?>
        </div>
    </form>
    <form id="test-staff-form" method="POST" style="display:none">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="test_staff_service">
        <input type="hidden" name="staff_service_url" id="test_staff_url" value="<?php echo htmlspecialchars($staffUrl); ?>">
        <input type="hidden" name="staff_service_api_key" id="test_staff_key" value="">
    </form>
    <form id="clear-staff-form" method="POST" style="display:none">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="clear_staff_service">
    </form>
</div>

<!-- ── People Service Integration ─────────────────────────────────────────── -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
        <div>
            <h2 style="font-size:1.1rem; font-weight:600;"><i class="fa-solid fa-heart-pulse" style="color:var(--success)"></i> People Service Integration</h2>
            <p class="text-light text-small" style="margin-top:.25rem">Connect to the People Service so people supported can be searched and added to teams by name.</p>
        </div>
        <?php if ($peopleUrl && $peopleKeySet): ?>
            <span class="badge badge-green">Connected</span>
        <?php else: ?>
            <span class="badge badge-grey">Not configured</span>
        <?php endif; ?>
    </div>
    <form method="POST">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="save_people_service">
        <div class="form-group">
            <label>People Service URL</label>
            <input type="url" name="people_service_url" class="form-control" id="people_service_url"
                   placeholder="https://your-people-service.hostingersite.com/public"
                   value="<?php echo htmlspecialchars($peopleUrl); ?>">
        </div>
        <div class="form-group">
            <label>API Key</label>
            <input type="password" name="people_service_api_key" class="form-control" id="people_service_api_key"
                   placeholder="<?php echo $peopleKeySet ? '(saved — leave blank to keep)' : 'Paste key from People Service → Admin → Settings → API Keys'; ?>">
            <?php if ($peopleKeySet): ?><div class="form-hint">Leave blank to keep the existing key.</div><?php endif; ?>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save</button>
            <button type="submit" form="test-people-form" class="btn btn-secondary"><i class="fa-solid fa-plug"></i> Test</button>
            <?php if ($peopleUrl || $peopleKeySet): ?>
            <button type="submit" form="clear-people-form" class="btn btn-danger"
                    onclick="return confirm('Disconnect the People Service?')">
                <i class="fa-solid fa-unlink"></i> Disconnect
            </button>
            <?php endif; ?>
        </div>
    </form>
    <form id="test-people-form" method="POST" style="display:none">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="test_people_service">
        <input type="hidden" name="people_service_url" id="test_people_url" value="<?php echo htmlspecialchars($peopleUrl); ?>">
        <input type="hidden" name="people_service_api_key" id="test_people_key" value="">
    </form>
    <form id="clear-people-form" method="POST" style="display:none">
        <?php echo CSRF::tokenField(); ?>
        <input type="hidden" name="action" value="clear_people_service">
    </form>
</div>

<script>
document.querySelector('[form="test-staff-form"]')?.addEventListener('click', function() {
    document.getElementById('test_staff_url').value  = document.getElementById('staff_service_url').value;
    document.getElementById('test_staff_key').value  = document.getElementById('staff_service_api_key').value;
});
document.querySelector('[form="test-people-form"]')?.addEventListener('click', function() {
    document.getElementById('test_people_url').value = document.getElementById('people_service_url').value;
    document.getElementById('test_people_key').value = document.getElementById('people_service_api_key').value;
});
</script>

<!-- ── Team Types ─────────────────────────────────────────────────────────── -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 600;">Team Types</h2>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-type')">
            <i class="fa-solid fa-plus"></i> New Type
        </button>
    </div>

    <?php if (empty($teamTypes)): ?>
        <p class="text-light" style="text-align: center; padding: 2rem;">No team types yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Staff Only</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamTypes as $tt): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tt['name']); ?></td>
                        <td class="text-light text-small"><?php echo htmlspecialchars($tt['description'] ?? ''); ?></td>
                        <td>
                            <?php if ($tt['is_staff_only']): ?>
                                <span class="badge badge-amber">Staff only</span>
                            <?php else: ?>
                                <span class="badge badge-blue">Mixed</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn btn-secondary btn-sm"
                                    onclick="openEditType(<?php echo htmlspecialchars(json_encode($tt)); ?>)">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <form method="POST" style="display: inline;"
                                  onsubmit="return confirm('Delete this team type?');">
                                <?php echo CSRF::tokenField(); ?>
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="id"     value="<?php echo $tt['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ── Team Roles ─────────────────────────────────────────────────────────── -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 600;">Team Roles</h2>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-role')">
            <i class="fa-solid fa-plus"></i> New Role
        </button>
    </div>

    <?php if (empty($teamRoles)): ?>
        <p class="text-light" style="text-align: center; padding: 2rem;">No roles yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Applies To</th>
                        <th>Access Level</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamRoles as $tr): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($tr['name']); ?>
                            <?php if ($tr['description']): ?>
                                <div class="text-light text-small"><?php echo htmlspecialchars($tr['description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $chips = ['staff' => 'badge-blue', 'person' => 'badge-green', 'both' => 'badge-grey'];
                            $chip  = $chips[$tr['applies_to']] ?? 'badge-grey';
                            ?>
                            <span class="badge <?php echo $chip; ?>"><?php echo htmlspecialchars($tr['applies_to']); ?></span>
                        </td>
                        <td>
                            <?php if ($tr['access_level'] === 'organisation'): ?>
                                <span class="badge badge-amber">Organisation</span>
                            <?php else: ?>
                                <span class="badge badge-grey">Team</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn btn-secondary btn-sm"
                                    onclick="openEditRole(<?php echo htmlspecialchars(json_encode($tr)); ?>)">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <form method="POST" style="display: inline;"
                                  onsubmit="return confirm('Delete this role?');">
                                <?php echo CSRF::tokenField(); ?>
                                <input type="hidden" name="action" value="delete_role">
                                <input type="hidden" name="id"     value="<?php echo $tr['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ── API Keys ───────────────────────────────────────────────────────────── -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.1rem; font-weight: 600;">API Keys</h2>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-key')">
            <i class="fa-solid fa-plus"></i> New Key
        </button>
    </div>

    <p class="text-light text-small" style="margin-bottom: 1rem;">
        API keys allow other services (Staff Service, Contracts) to query this service.
        Keys are shown only once on creation.
    </p>

    <?php if (empty($apiKeys)): ?>
        <p class="text-light" style="text-align: center; padding: 2rem;">No API keys.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Service</th>
                        <th>Last Used</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apiKeys as $ak): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ak['name']); ?></td>
                        <td class="text-light"><?php echo htmlspecialchars($ak['connected_service'] ?? '—'); ?></td>
                        <td class="text-light text-small">
                            <?php echo $ak['last_used_at'] ? date('d M Y H:i', strtotime($ak['last_used_at'])) : 'Never'; ?>
                        </td>
                        <td>
                            <?php if ($ak['is_active']): ?>
                                <span class="badge badge-green">Active</span>
                            <?php else: ?>
                                <span class="badge badge-red">Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($ak['is_active']): ?>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Revoke this API key? This cannot be undone.');">
                                    <?php echo CSRF::tokenField(); ?>
                                    <input type="hidden" name="action" value="revoke_api_key">
                                    <input type="hidden" name="id"     value="<?php echo $ak['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
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

<!-- ════════════════════ Modals ═══════════════════════════════════════════════ -->

<?php
$csrfField = CSRF::tokenField();
?>

<!-- Create Team Type -->
<div id="modal-create-type" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:480px; position:relative;">
        <h3 style="font-weight:600; margin-bottom:1.25rem;">New Team Type</h3>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="create_type">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" name="is_staff_only" id="ct_staff_only" value="1">
                <label for="ct_staff_only" style="margin:0; font-weight:400;">Staff only (no people supported)</label>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-type')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Type -->
<div id="modal-edit-type" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:480px; position:relative;">
        <h3 style="font-weight:600; margin-bottom:1.25rem;">Edit Team Type</h3>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="edit_type">
            <input type="hidden" name="id"     id="et_id">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="et_name" class="form-control" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" id="et_desc" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" name="is_staff_only" id="et_staff_only" value="1">
                <label for="et_staff_only" style="margin:0; font-weight:400;">Staff only</label>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-type')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Team Role -->
<div id="modal-create-role" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:480px;">
        <h3 style="font-weight:600; margin-bottom:1.25rem;">New Team Role</h3>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="create_role">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Applies to</label>
                <select name="applies_to" class="form-control">
                    <option value="staff">Staff</option>
                    <option value="person">Person supported</option>
                    <option value="both">Both</option>
                </select>
            </div>
            <div class="form-group">
                <label>Access level</label>
                <select name="access_level" class="form-control">
                    <option value="team">Team (own team + children)</option>
                    <option value="organisation">Organisation (all teams)</option>
                </select>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-role')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Role -->
<div id="modal-edit-role" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:480px;">
        <h3 style="font-weight:600; margin-bottom:1.25rem;">Edit Team Role</h3>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="edit_role">
            <input type="hidden" name="id"     id="er_id">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="er_name" class="form-control" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <textarea name="description" id="er_desc" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Applies to</label>
                <select name="applies_to" id="er_applies" class="form-control">
                    <option value="staff">Staff</option>
                    <option value="person">Person supported</option>
                    <option value="both">Both</option>
                </select>
            </div>
            <div class="form-group">
                <label>Access level</label>
                <select name="access_level" id="er_access" class="form-control">
                    <option value="team">Team (own team + children)</option>
                    <option value="organisation">Organisation (all teams)</option>
                </select>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-role')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Create API Key -->
<div id="modal-create-key" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:200; align-items:center; justify-content:center;">
    <div class="card" style="width:100%; max-width:480px;">
        <h3 style="font-weight:600; margin-bottom:1.25rem;">New API Key</h3>
        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="create_api_key">
            <div class="form-group">
                <label>Key name</label>
                <input type="text" name="api_key_name" class="form-control" required
                       placeholder="e.g. Staff Service Production" maxlength="100">
            </div>
            <div class="form-group">
                <label>Connected service (optional)</label>
                <input type="text" name="connected_service" class="form-control"
                       placeholder="e.g. Staff Service" maxlength="100">
            </div>
            <div class="alert alert-warning" style="font-size:0.8rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                The key is shown once after creation. Store it securely.
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-key')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
// Close on backdrop click
document.querySelectorAll('[id^="modal-"]').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === m) closeModal(m.id);
    });
});

function openEditType(data) {
    document.getElementById('et_id').value        = data.id;
    document.getElementById('et_name').value      = data.name;
    document.getElementById('et_desc').value      = data.description || '';
    document.getElementById('et_staff_only').checked = !!parseInt(data.is_staff_only);
    openModal('modal-edit-type');
}

function openEditRole(data) {
    document.getElementById('er_id').value         = data.id;
    document.getElementById('er_name').value       = data.name;
    document.getElementById('er_desc').value       = data.description || '';
    document.getElementById('er_applies').value    = data.applies_to;
    document.getElementById('er_access').value     = data.access_level;
    openModal('modal-edit-role');
}
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>
