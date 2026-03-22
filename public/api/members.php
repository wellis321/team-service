<?php
/**
 * Team Service REST API — Team Members
 *
 * ── Read endpoints (GET) ─────────────────────────────────────────────────────
 *
 *   GET /api/members.php?team_id=<id>
 *       All active members of a team.
 *       Optional: &member_type=staff|person|user
 *
 *   GET /api/members.php?member_type=<type>&external_id=<id>
 *       All active teams a specific member belongs to.
 *       Required: &organisation_id=<id>  (or comes from the API key)
 *
 *   GET /api/members.php?member_type=<type>&external_id=<id>&accessible_teams=1
 *       Team IDs the member has access to (respects hierarchy + access_level).
 *       Returns {"data": null} when org-level access (no restriction).
 *
 *   GET /api/members.php?team_id=<id>&history=1
 *       Full membership history for a team, including former members.
 *
 * ── Write endpoints (POST) ───────────────────────────────────────────────────
 *
 *   POST /api/members.php
 *   Body: {"action": "add",    "team_id": 1, "member_type": "staff", "external_id": 42,
 *          "display_name": "Jane Smith", "display_ref": "EMP001",
 *          "team_role_id": 3, "is_primary_team": false, "joined_at": "2025-01-15"}
 *
 *   POST /api/members.php
 *   Body: {"action": "remove", "team_id": 1, "member_type": "staff", "external_id": 42,
 *          "left_at": "2025-06-30"}
 *
 *   POST /api/members.php
 *   Body: {"action": "refresh_display",
 *          "member_type": "staff", "external_id": 42,
 *          "display_name": "Jane Walsh", "display_ref": "EMP001"}
 *       Lets the source service push a name change.
 *
 * ── Authentication ───────────────────────────────────────────────────────────
 *   Bearer <api-key> or X-API-Key header (or browser session).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$auth = ApiAuth::requireAuth();

$organisationId = $auth['organisation_id'];
if ($organisationId === null) {
    $organisationId = isset($_GET['organisation_id']) ? (int) $_GET['organisation_id'] : null;
}

$method = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════════════════════════════════════════════════
// GET
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET') {

    // ── History for a team ────────────────────────────────────────────────
    if (isset($_GET['team_id'], $_GET['history'])) {
        $teamId = (int) $_GET['team_id'];
        $history = TeamMember::getHistoryForTeam($teamId);
        ApiAuth::json(['data' => $history, 'total' => count($history)]);
    }

    // ── Members of a team ─────────────────────────────────────────────────
    if (isset($_GET['team_id'])) {
        $teamId     = (int) $_GET['team_id'];
        $memberType = $_GET['member_type'] ?? null;

        // Validate member_type if provided
        $allowed = ['staff', 'person', 'user'];
        if ($memberType !== null && !in_array($memberType, $allowed, true)) {
            ApiAuth::json(['error' => 'member_type must be staff, person, or user'], 400);
        }

        $members = TeamMember::getForTeam($teamId, $memberType);
        ApiAuth::json(['data' => $members, 'total' => count($members)]);
    }

    // ── All memberships for an org (e.g. to build grouped staff list) ────
    if (isset($_GET['all'], $_GET['member_type'])) {
        if (!$organisationId) {
            ApiAuth::json(['error' => 'organisation_id is required'], 400);
        }
        $memberType = $_GET['member_type'];
        $allowed    = ['staff', 'person', 'user'];
        if (!in_array($memberType, $allowed, true)) {
            ApiAuth::json(['error' => 'member_type must be staff, person, or user'], 400);
        }
        $memberships = TeamMember::getAllForOrg($organisationId, $memberType);
        ApiAuth::json(['data' => $memberships, 'total' => count($memberships)]);
    }

    // ── Teams for a specific member ───────────────────────────────────────
    if (isset($_GET['member_type'], $_GET['external_id'])) {
        if (!$organisationId) {
            ApiAuth::json(['error' => 'organisation_id is required'], 400);
        }
        $memberType = $_GET['member_type'];
        $externalId = (int) $_GET['external_id'];

        $allowed = ['staff', 'person', 'user'];
        if (!in_array($memberType, $allowed, true)) {
            ApiAuth::json(['error' => 'member_type must be staff, person, or user'], 400);
        }

        // Accessible team IDs (for RBAC enforcement)
        if (isset($_GET['accessible_teams'])) {
            $ids = TeamMember::getAccessibleTeamIds($memberType, $externalId, $organisationId);
            ApiAuth::json(['data' => $ids]);   // null = unrestricted
        }

        $teams = TeamMember::getTeamsForMember($memberType, $externalId, $organisationId);
        ApiAuth::json(['data' => $teams, 'total' => count($teams)]);
    }

    ApiAuth::json(['error' => 'Missing required parameters. See endpoint documentation.'], 400);
}

// ════════════════════════════════════════════════════════════════════════════
// POST
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        ApiAuth::json(['error' => 'Request body must be valid JSON'], 400);
    }

    $action = $body['action'] ?? '';

    // ── add ───────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $required = ['team_id', 'member_type', 'external_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                ApiAuth::json(['error' => "Field '{$field}' is required"], 400);
            }
        }

        $teamId     = (int) $body['team_id'];
        $memberType = $body['member_type'];
        $externalId = (int) $body['external_id'];

        // Determine organisation_id — prefer key's org, then body field, then query param
        $orgId = $organisationId ?? (isset($body['organisation_id']) ? (int) $body['organisation_id'] : null);
        if (!$orgId) {
            ApiAuth::json(['error' => 'organisation_id is required'], 400);
        }

        $allowed = ['staff', 'person', 'user'];
        if (!in_array($memberType, $allowed, true)) {
            ApiAuth::json(['error' => 'member_type must be staff, person, or user'], 400);
        }

        // Validate team belongs to the same organisation
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM teams WHERE id = ? AND organisation_id = ? AND is_active = TRUE');
        $stmt->execute([$teamId, $orgId]);
        if (!$stmt->fetch()) {
            ApiAuth::json(['error' => 'Team not found or not in your organisation'], 404);
        }

        $memberId = TeamMember::add(
            teamId:        $teamId,
            organisationId: $orgId,
            memberType:    $memberType,
            externalId:    $externalId,
            displayName:   $body['display_name']   ?? null,
            displayRef:    $body['display_ref']     ?? null,
            teamRoleId:    isset($body['team_role_id']) ? (int) $body['team_role_id'] : null,
            isPrimaryTeam: !empty($body['is_primary_team']),
            joinedAt:      $body['joined_at']       ?? null,
            notes:         $body['notes']           ?? null
        );

        ApiAuth::json(['success' => true, 'id' => $memberId], 201);
    }

    // ── remove ────────────────────────────────────────────────────────────
    if ($action === 'remove') {
        $required = ['team_id', 'member_type', 'external_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                ApiAuth::json(['error' => "Field '{$field}' is required"], 400);
            }
        }

        $ok = TeamMember::remove(
            teamId:     (int) $body['team_id'],
            memberType: $body['member_type'],
            externalId: (int) $body['external_id'],
            leftAt:     $body['left_at'] ?? null
        );

        if (!$ok) {
            ApiAuth::json(['error' => 'Membership not found or already removed'], 404);
        }
        ApiAuth::json(['success' => true]);
    }

    // ── refresh_display ───────────────────────────────────────────────────
    if ($action === 'refresh_display') {
        $required = ['member_type', 'external_id', 'display_name'];
        foreach ($required as $field) {
            if (!isset($body[$field])) {
                ApiAuth::json(['error' => "Field '{$field}' is required"], 400);
            }
        }

        $updated = TeamMember::refreshDisplayCache(
            memberType:  $body['member_type'],
            externalId:  (int) $body['external_id'],
            displayName: $body['display_name'],
            displayRef:  $body['display_ref'] ?? ''
        );

        ApiAuth::json(['success' => true, 'rows_updated' => $updated]);
    }

    ApiAuth::json(['error' => 'Unknown action. Use: add, remove, refresh_display'], 400);
}

ApiAuth::json(['error' => 'Method not allowed'], 405);
