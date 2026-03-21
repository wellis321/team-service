<?php
/**
 * TeamType model
 *
 * Manages the custom team-type categories that an organisation can define
 * (e.g. "Care Team", "Support Team", "HR", "Finance").
 * The is_staff_only flag marks types that can never contain people supported.
 */
class TeamType
{
    // ── Queries ───────────────────────────────────────────────────────────────

    public static function findByOrganisation(int $organisationId, bool $includeInactive = false): array
    {
        $db   = getDbConnection();
        $sql  = 'SELECT * FROM team_types WHERE organisation_id = ?';
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
        $stmt = $db->prepare('SELECT * FROM team_types WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function belongsToOrganisation(int $id, int $organisationId): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT id FROM team_types WHERE id = ? AND organisation_id = ?');
        $stmt->execute([$id, $organisationId]);
        return (bool) $stmt->fetch();
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    public static function create(
        int    $organisationId,
        string $name,
        bool   $isStaffOnly    = false,
        ?string $description   = null,
        int    $displayOrder   = 0
    ): int {
        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT INTO team_types (organisation_id, name, description, is_staff_only, display_order)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$organisationId, $name, $description, $isStaffOnly, $displayOrder]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'description', 'is_staff_only', 'display_order', 'is_active'];
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
        $stmt = $db->prepare('UPDATE team_types SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a team type only if no active teams currently use it.
     * Throws RuntimeException if in use.
     */
    public static function delete(int $id): bool
    {
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE team_type_id = ? AND is_active = TRUE');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Cannot delete a team type that is still assigned to active teams.');
        }
        $stmt = $db->prepare('DELETE FROM team_types WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ── Seed defaults ─────────────────────────────────────────────────────────

    /**
     * Create the default team types for a new organisation.
     * Safe to call multiple times (INSERT IGNORE).
     */
    public static function initializeDefaults(int $organisationId): void
    {
        $defaults = [
            ['Care Team',    'Operational team delivering care to people supported', false, 10],
            ['Support Team', 'Team providing support services',                      false, 20],
            ['HR',           'Human Resources functional team',                      true,  30],
            ['Finance',      'Finance and payroll functional team',                   true,  40],
            ['Management',   'Senior management and leadership team',                 true,  50],
        ];

        $db   = getDbConnection();
        $stmt = $db->prepare('
            INSERT IGNORE INTO team_types (organisation_id, name, description, is_staff_only, display_order)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($defaults as [$name, $desc, $staffOnly, $order]) {
            $stmt->execute([$organisationId, $name, $desc, $staffOnly, $order]);
        }
    }
}
