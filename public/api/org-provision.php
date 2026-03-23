<?php
/**
 * Org Provision API
 *
 * POST /api/org-provision.php
 * Body: { name, domain, first_name, last_name, email, password }
 *
 * Creates a new organisation with a first admin user.
 * Called by the Staff Service super admin when provisioning a new org
 * across all connected services.
 *
 * Secured by PROVISION_SECRET — a shared secret set in each service's .env.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify provision secret
if (!PROVISION_SECRET) {
    http_response_code(503);
    echo json_encode(['error' => 'Provisioning not configured on this service — set PROVISION_SECRET in .env']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $m) || !hash_equals(PROVISION_SECRET, trim($m[1]))) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid provision secret']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$name      = trim($input['name']       ?? '');
$domain    = trim(strtolower($input['domain']      ?? ''));
$firstName = trim($input['first_name'] ?? '');
$lastName  = trim($input['last_name']  ?? '');
$email     = trim(strtolower($input['email']       ?? ''));
$password  = $input['password']        ?? '';

if (!$name || !$domain || !$firstName || !$lastName || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required: name, domain, first_name, last_name, email, password']);
    exit;
}

$db = getDbConnection();

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

    echo json_encode(['success' => true, 'org_id' => $orgId, 'name' => $name, 'domain' => $domain]);

} catch (PDOException $e) {
    $db->rollBack();
    if (str_contains($e->getMessage(), 'Duplicate')) {
        http_response_code(409);
        echo json_encode(['error' => 'An organisation with that domain already exists on this service']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error during provisioning']);
    }
}
