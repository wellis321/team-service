<?php
/**
 * Team Service REST API — Teams
 *
 * Endpoints:
 *   GET  /api/teams.php                     List all active teams for the organisation
 *   GET  /api/teams.php?id=<id>             Get a single team with member counts
 *   GET  /api/teams.php?tree=1              Full team hierarchy tree
 *   GET  /api/teams.php?staff_only=1        Staff-only teams
 *   GET  /api/teams.php?mixed=1             Mixed (staff + people) teams
 *
 * Authentication: Bearer <api-key> or X-API-Key header (or browser session).
 * Multi-tenancy:  organisation_id comes from the API key (or logged-in session).
 *                 Optionally override with ?organisation_id= when the key is
 *                 system-wide (no fixed org).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$auth = ApiAuth::requireAuth();

// Resolve organisation_id — key may be system-wide (NULL)
$organisationId = $auth['organisation_id'];
if ($organisationId === null) {
    $organisationId = isset($_GET['organisation_id']) ? (int) $_GET['organisation_id'] : null;
}
if (!$organisationId) {
    ApiAuth::json(['error' => 'organisation_id is required'], 400);
}

$db     = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method !== 'GET') {
    ApiAuth::json(['error' => 'Method not allowed'], 405);
}

// Single team
if (isset($_GET['id'])) {
    $teamId = (int) $_GET['id'];
    $stmt   = $db->prepare('
        SELECT t.*,
               tt.name         AS type_name,
               tt.is_staff_only,
               (SELECT COUNT(*) FROM team_members tm
                WHERE tm.team_id = t.id AND tm.left_at IS NULL
                  AND tm.member_type = "staff")  AS staff_count,
               (SELECT COUNT(*) FROM team_members tm
                WHERE tm.team_id = t.id AND tm.left_at IS NULL
                  AND tm.member_type = "person") AS people_count
        FROM   teams t
        LEFT JOIN team_types tt ON t.team_type_id = tt.id
        WHERE  t.id = ? AND t.organisation_id = ? AND t.is_active = TRUE
    ');
    $stmt->execute([$teamId, $organisationId]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$team) {
        ApiAuth::json(['error' => 'Team not found'], 404);
    }
    $team['ancestor_path'] = Team::getAncestorPath($teamId);
    ApiAuth::json(['data' => $team]);
}

// Hierarchy tree
if (isset($_GET['tree'])) {
    $tree = Team::getTree($organisationId);
    ApiAuth::json(['data' => $tree]);
}

// ── List ──────────────────────────────────────────────────────────────────────
$sql  = '
    SELECT t.id,
           t.organisation_id,
           t.parent_team_id,
           t.team_type_id,
           t.name,
           t.description,
           t.is_active,
           t.created_at,
           t.updated_at,
           tt.name         AS type_name,
           tt.is_staff_only,
           (SELECT COUNT(*) FROM team_members tm
            WHERE tm.team_id = t.id AND tm.left_at IS NULL
              AND tm.member_type = "staff")  AS staff_count,
           (SELECT COUNT(*) FROM team_members tm
            WHERE tm.team_id = t.id AND tm.left_at IS NULL
              AND tm.member_type = "person") AS people_count
    FROM   teams t
    LEFT JOIN team_types tt ON t.team_type_id = tt.id
    WHERE  t.organisation_id = ? AND t.is_active = TRUE
';
$args = [$organisationId];

if (isset($_GET['staff_only'])) {
    $sql  .= ' AND tt.is_staff_only = TRUE';
}
if (isset($_GET['mixed'])) {
    $sql  .= ' AND (tt.is_staff_only = FALSE OR tt.is_staff_only IS NULL)';
}

$sql .= ' ORDER BY t.name ASC';

$stmt = $db->prepare($sql);
$stmt->execute($args);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

ApiAuth::json([
    'data'  => $teams,
    'total' => count($teams),
]);
