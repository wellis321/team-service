<?php
/**
 * Org Lookup API
 *
 * GET /api/org-lookup.php?domain=acme.com
 *
 * Returns the local organisation ID for a given domain.
 * Used by other services to resolve org IDs during cross-service calls.
 * Requires API key authentication.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

$auth = ApiAuth::authenticate();
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$domain = trim(strtolower($_GET['domain'] ?? ''));
if (!$domain) {
    http_response_code(400);
    echo json_encode(['error' => 'domain parameter required']);
    exit;
}

$db   = getDbConnection();
$stmt = $db->prepare('SELECT id, name, domain FROM organisations WHERE domain = ? LIMIT 1');
$stmt->execute([$domain]);
$org  = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$org) {
    http_response_code(404);
    echo json_encode(['error' => 'Organisation not found']);
    exit;
}

echo json_encode([
    'org_id' => (int) $org['id'],
    'name'   => $org['name'],
    'domain' => $org['domain'],
]);
