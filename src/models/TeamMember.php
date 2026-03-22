<?php
/**
 * TeamMember model
 *
 * Manages membership of teams — both staff (from PMS) and people supported
 * (from the People Service), and optionally plain users (shared-auth users).
 *
 * Cross-service identity:
 *   member_type 'staff'  → external_id = PMS people.id
 *   member_type 'person' → external_id = People Service people.id
 *   member_type 'user'   → external_id = shared-auth users.id
 *
 * left_at IS NULL  = currently active in this team
 * left_at NOT NULL = has left (historical record kept)
 */
class TeamMember
{
    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * Get all active members of a team (left_at IS NULL).
     * Optionally filter by member_type ('staff', 'person', 'user').
     */
    public static function getForTeam(int $teamId, ?string $memberType = null): array
    {
        $db   = getDbConnection();
        $sql  = '
            SELECT tm.*,
                   tr.name         AS role_name,
                   tr.access_level AS role_access_level,
                   tr.applies_to   AS role_applies_to
            FROM   team_members tm
            LEFT JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE  tm.team_id = ?
              AND  tm.left_at IS NULL
        ';
        $args = [$teamId];
        if ($memberType !== null) {
            $sql  .= ' AND tm.member_type = ?';
            $args[] = $memberType;
        }
        $sql .= ' ORDER BY tm.member_type ASC, tm.display_name ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    /**
     * Get all teams a specific external member belongs to (active memberships only).
     */
    public static function getTeamsForMember(
        string $memberType,
        int    $externalId,
        int    $organisationId
    ): array {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT tm.*,
                   t.name          AS team_name,
                   t.description   AS team_description,
                   tt.name         AS type_name,
                   tt.is_staff_only,
                   tr.name         AS role_name,
                   tr.access_level AS role_access_level
            FROM   team_members tm
            JOIN   teams      t  ON tm.team_id      = t.id
            LEFT JOIN team_types tt ON t.team_type_id = tt.id
            LEFT JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE  tm.member_type     = ?
              AND  tm.external_id     = ?
              AND  tm.organisation_id = ?
              AND  tm.left_at         IS NULL
              AND  t.is_active        = TRUE
            ORDER BY tm.is_primary_team DESC, t.name ASC
        ');
        $stmt->execute([$memberType, $externalId, $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Find a specific membership row (any status).
     */
    public static function findMembership(
        int    $teamId,
        string $memberType,
        int    $externalId
    ): ?array {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT * FROM team_members
            WHERE team_id = ? AND member_type = ? AND external_id = ?
        ');
        $stmt->execute([$teamId, $memberType, $externalId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Check whether a member is currently active in a team.
     */
    public static function isActive(int $teamId, string $memberType, int $externalId): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT id FROM team_members
            WHERE team_id = ? AND member_type = ? AND external_id = ? AND left_at IS NULL
        ');
        $stmt->execute([$teamId, $memberType, $externalId]);
        return (bool) $stmt->fetch();
    }

    /**
     * Get all team IDs a staff member has access to, considering hierarchy.
     * Returns null if the member has organisation-level access (no restriction).
     *
     * @return int[]|null
     */
    public static function getAccessibleTeamIds(
        string $memberType,
        int    $externalId,
        int    $organisationId
    ): ?array {
        $memberships = self::getTeamsForMember($memberType, $externalId, $organisationId);

        // If any membership grants organisation-level access, no restriction needed
        foreach ($memberships as $m) {
            if (($m['role_access_level'] ?? '') === 'organisation') {
                return null;
            }
        }

        // Otherwise collect own teams + all descendants
        $teamIds = [];
        foreach ($memberships as $m) {
            $descendants = Team::getDescendantIds((int) $m['team_id'], true);
            foreach ($descendants as $id) {
                $teamIds[$id] = true;
            }
        }
        return array_keys($teamIds);
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /**
     * Add a member to a team. If they previously left, re-activates them.
     */
    public static function add(
        int     $teamId,
        int     $organisationId,
        string  $memberType,
        int     $externalId,
        ?string $displayName  = null,
        ?string $displayRef   = null,
        ?int    $teamRoleId   = null,
        bool    $isPrimaryTeam = false,
        ?string $joinedAt     = null,
        ?string $notes        = null
    ): int {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT INTO team_members
                (team_id, organisation_id, member_type, external_id,
                 display_name, display_ref, team_role_id, is_primary_team, joined_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                display_name    = VALUES(display_name),
                display_ref     = VALUES(display_ref),
                team_role_id    = VALUES(team_role_id),
                is_primary_team = VALUES(is_primary_team),
                joined_at       = VALUES(joined_at),
                notes           = VALUES(notes),
                left_at         = NULL,
                updated_at      = NOW()
        ');
        $stmt->execute([
            $teamId, $organisationId, $memberType, $externalId,
            $displayName, $displayRef, $teamRoleId, $isPrimaryTeam,
            $joinedAt ?? date('Y-m-d'), $notes,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update an existing membership (role, primary flag, notes).
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = ['team_role_id', 'is_primary_team', 'joined_at', 'left_at', 'notes',
                    'display_name', 'display_ref'];
        $sets    = [];
        $values  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($sets)) return false;
        $values[] = $id;
        $db   = getDbConnection();
        $stmt = $db->prepare('UPDATE team_members SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Record that a member has left the team (sets left_at, keeps history).
     */
    public static function remove(
        int    $teamId,
        string $memberType,
        int    $externalId,
        ?string $leftAt = null
    ): bool {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            UPDATE team_members
            SET    left_at = ?, updated_at = NOW()
            WHERE  team_id     = ?
              AND  member_type = ?
              AND  external_id = ?
              AND  left_at     IS NULL
        ');
        $stmt->execute([$leftAt ?? date('Y-m-d'), $teamId, $memberType, $externalId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Refresh cached display_name / display_ref from the source service.
     * Called by the source service API when a person's name changes.
     */
    public static function refreshDisplayCache(
        string $memberType,
        int    $externalId,
        string $displayName,
        string $displayRef = ''
    ): int {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            UPDATE team_members
            SET    display_name = ?, display_ref = ?, updated_at = NOW()
            WHERE  member_type  = ? AND external_id = ?
        ');
        $stmt->execute([$displayName, $displayRef, $memberType, $externalId]);
        return $stmt->rowCount();
    }

    // ── History ───────────────────────────────────────────────────────────────

    /**
     * Get all active memberships for an organisation, optionally filtered by type.
     * Returns each membership with team name — used by PMS to build the grouped staff list.
     */
    public static function getAllForOrg(int $organisationId, string $memberType = 'staff'): array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT tm.external_id,
                   tm.display_name,
                   tm.display_ref,
                   tm.is_primary_team,
                   tm.joined_at,
                   tm.team_role_id,
                   t.id    AS team_id,
                   t.name  AS team_name,
                   tr.name AS role_name
            FROM   team_members tm
            JOIN   teams t ON tm.team_id = t.id
            LEFT JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE  tm.organisation_id = ?
              AND  tm.member_type     = ?
              AND  tm.left_at         IS NULL
              AND  t.is_active        = TRUE
            ORDER BY t.name ASC, tm.display_name ASC
        ');
        $stmt->execute([$organisationId, $memberType]);
        return $stmt->fetchAll();
    }

    /**
     * Get full membership history (including former members) for a team.
     */
    public static function getHistoryForTeam(int $teamId): array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT tm.*,
                   tr.name AS role_name
            FROM   team_members tm
            LEFT JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE  tm.team_id = ?
            ORDER  BY tm.left_at IS NULL DESC, tm.joined_at DESC
        ');
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }
}
