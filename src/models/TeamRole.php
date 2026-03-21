<?php
/**
 * TeamRole model
 *
 * Per-organisation roles that members can hold within a team.
 * 'applies_to' controls whether the role is for staff, people supported, or both.
 * 'access_level' controls how much of the organisation the role holder can see:
 *   'team'         — their own team and its children
 *   'organisation' — all teams across the organisation
 */
class TeamRole
{
    // ── Queries ───────────────────────────────────────────────────────────────

    public static function findByOrganisation(int $organisationId, bool $includeInactive = false): array
    {
        $db   = getDbConnection();
        $sql  = 'SELECT * FROM team_roles WHERE organisation_id = ?';
        $args = [$organisationId];
        if (!$includeInactive) {
            $sql .= ' AND is_active = TRUE';
        }
        $sql .= ' ORDER BY display_order ASC, name ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT * FROM team_roles WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function belongsToOrganisation(int $id, int $organisationId): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM team_roles WHERE id = ? AND organisation_id = ?');
        $stmt->execute([$id, $organisationId]);
        return (bool) $stmt->fetch();
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    public static function create(
        int    $organisationId,
        string $name,
        string $appliesTo   = 'staff',
        string $accessLevel = 'team',
        ?string $description = null,
        int    $displayOrder = 0
    ): int {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT INTO team_roles (organisation_id, name, description, applies_to, access_level, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$organisationId, $name, $description, $appliesTo, $accessLevel, $displayOrder]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'description', 'applies_to', 'access_level', 'display_order', 'is_active'];
        $sets    = [];
        $values  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($sets)) {
            return false;
        }
        $values[] = $id;
        $db   = getDbConnection();
        $stmt = $db->prepare('UPDATE team_roles SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a role only if it is not assigned to any team members.
     */
    public static function delete(int $id): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM team_members WHERE team_role_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Cannot delete a role that is currently assigned to team members.');
        }
        $stmt = $db->prepare('DELETE FROM team_roles WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Seed defaults ─────────────────────────────────────────────────────────

    /**
     * Create the standard default roles for a new organisation.
     * Safe to call multiple times (INSERT IGNORE).
     */
    public static function initializeDefaults(int $organisationId): void
    {
        $defaults = [
            // [name, applies_to, access_level, description, display_order]
            ['Care Worker',   'staff',  'team',         'Delivers care directly to people supported',                    10],
            ['Team Leader',   'staff',  'team',         'Leads a care team and supervises care workers',                 20],
            ['Coordinator',   'staff',  'organisation', 'Coordinates across teams within the organisation',              30],
            ['Manager',       'staff',  'organisation', 'Manages teams and has full organisational visibility',          40],
            ['Service User',  'person', 'team',         'Person supported — member of this care team',                   10],
            ['Key Person',    'person', 'team',         'Primary care contact for this person within the team',          20],
        ];

        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT IGNORE INTO team_roles
                (organisation_id, name, description, applies_to, access_level, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        foreach ($defaults as [$name, $appliesTo, $access, $desc, $order]) {
            $stmt->execute([$organisationId, $name, $desc, $appliesTo, $access, $order]);
        }
    }
}
