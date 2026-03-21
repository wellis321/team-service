<?php
/**
 * Team model
 *
 * Manages teams and their hierarchy.
 * Teams are the core unit — staff and people supported are linked via TeamMember.
 */
class Team
{
    // ── Queries ───────────────────────────────────────────────────────────────

    public static function findByOrganisation(int $organisationId, bool $includeInactive = false): array
    {
        $db  = getDbConnection();
        $sql = '
            SELECT t.*,
                   tt.name  AS type_name,
                   tt.is_staff_only,
                   pt.name  AS parent_name
            FROM   teams t
            LEFT JOIN team_types tt ON t.team_type_id    = tt.id
            LEFT JOIN teams      pt ON t.parent_team_id  = pt.id
            WHERE  t.organisation_id = ?
        ';
        $args = [$organisationId];
        if (!$includeInactive) {
            $sql .= ' AND t.is_active = TRUE';
        }
        $sql .= ' ORDER BY tt.display_order ASC, t.name ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT t.*,
                   tt.name        AS type_name,
                   tt.is_staff_only,
                   tt.display_order AS type_display_order,
                   pt.name        AS parent_name
            FROM   teams t
            LEFT JOIN team_types tt ON t.team_type_id   = tt.id
            LEFT JOIN teams      pt ON t.parent_team_id = pt.id
            WHERE  t.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function belongsToOrganisation(int $id, int $organisationId): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM teams WHERE id = ? AND organisation_id = ?');
        $stmt->execute([$id, $organisationId]);
        return (bool) $stmt->fetch();
    }

    // ── Hierarchy ─────────────────────────────────────────────────────────────

    /**
     * Recursively get all descendant team IDs (children, grandchildren, etc.).
     * Optionally include the team itself.
     *
     * @return int[]
     */
    public static function getDescendantIds(int $teamId, bool $includeSelf = false): array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM teams WHERE parent_team_id = ? AND is_active = TRUE');

        $ids = $includeSelf ? [$teamId] : [];
        $queue = [$teamId];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $stmt->execute([$current]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
                $ids[]   = (int) $childId;
                $queue[] = (int) $childId;
            }
        }
        return array_unique($ids);
    }

    /**
     * Get the full ancestor path as an array, from root down to this team.
     * e.g. [Region, Area, Team]
     */
    public static function getAncestorPath(int $teamId): array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id, name, parent_team_id FROM teams WHERE id = ?');
        $path = [];
        $id   = $teamId;

        while ($id) {
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) break;
            array_unshift($path, $row);
            $id = $row['parent_team_id'];
        }
        return $path;
    }

    /**
     * Human-readable breadcrumb string: "Region > Area > Team"
     */
    public static function getHierarchyPath(int $teamId): string
    {
        $path = self::getAncestorPath($teamId);
        return implode(' > ', array_column($path, 'name'));
    }

    /**
     * Build a nested tree of all teams for an organisation.
     * Returns top-level teams with 'children' arrays recursively populated.
     */
    public static function getTree(int $organisationId): array
    {
        $all  = self::findByOrganisation($organisationId);
        return self::buildTree($all, null);
    }

    private static function buildTree(array $all, ?int $parentId): array
    {
        $nodes = [];
        foreach ($all as $team) {
            if ((int) $team['parent_team_id'] === (int) $parentId ||
                ($parentId === null && $team['parent_team_id'] === null)) {
                $team['children'] = self::buildTree($all, (int) $team['id']);
                $nodes[] = $team;
            }
        }
        return $nodes;
    }

    // ── Member counts ─────────────────────────────────────────────────────────

    /**
     * Return counts of active members per type for a single team.
     * ['staff' => int, 'person' => int, 'total' => int]
     */
    public static function getMemberCounts(int $teamId): array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            SELECT member_type, COUNT(*) AS cnt
            FROM   team_members
            WHERE  team_id = ? AND left_at IS NULL
            GROUP  BY member_type
        ');
        $stmt->execute([$teamId]);
        $counts = ['staff' => 0, 'person' => 0, 'user' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['member_type']] = (int) $row['cnt'];
        }
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public static function create(
        int     $organisationId,
        string  $name,
        ?int    $teamTypeId    = null,
        ?int    $parentTeamId  = null,
        ?string $description   = null
    ): int {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT INTO teams (organisation_id, name, team_type_id, parent_team_id, description)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$organisationId, $name, $teamTypeId, $parentTeamId, $description]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'description', 'team_type_id', 'parent_team_id', 'is_active'];
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
        $stmt = $db->prepare('UPDATE teams SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deactivate a team (soft delete).
     * Does not remove members — their left_at is not automatically set.
     */
    public static function deactivate(int $id): bool
    {
        return self::update($id, ['is_active' => false]);
    }
}
