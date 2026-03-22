<?php
/**
 * OrgSettings — per-organisation key/value settings store.
 *
 * Wraps the `organisation_settings` table (created on first use).
 * Used by service clients to read connection URLs and API keys
 * per organisation, falling back to .env values as defaults.
 *
 * Usage:
 *   OrgSettings::get($orgId, 'team_service_url', getenv('TEAM_SERVICE_URL'))
 *   OrgSettings::set($orgId, 'team_service_url', 'https://...')
 */
class OrgSettings
{
    /** In-process cache so we only query DB once per org per request */
    private static array $cache = [];

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get(int $orgId, string $key, string $default = ''): string
    {
        if (!isset(self::$cache[$orgId])) {
            self::load($orgId);
        }
        return self::$cache[$orgId][$key] ?? $default;
    }

    public static function getAll(int $orgId): array
    {
        if (!isset(self::$cache[$orgId])) {
            self::load($orgId);
        }
        return self::$cache[$orgId] ?? [];
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function set(int $orgId, string $key, string $value): void
    {
        self::ensureTable();
        $db = getDbConnection();
        $db->prepare('
            INSERT INTO organisation_settings (organisation_id, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ')->execute([$orgId, $key, $value]);

        // Update cache
        if (!isset(self::$cache[$orgId])) {
            self::$cache[$orgId] = [];
        }
        self::$cache[$orgId][$key] = $value;
    }

    public static function setMany(int $orgId, array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set($orgId, $key, (string) $value);
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function load(int $orgId): void
    {
        self::ensureTable();
        $db   = getDbConnection();
        $stmt = $db->prepare('SELECT setting_key, setting_value FROM organisation_settings WHERE organisation_id = ?');
        $stmt->execute([$orgId]);

        self::$cache[$orgId] = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            self::$cache[$orgId][$row['setting_key']] = (string) $row['setting_value'];
        }
    }

    private static function ensureTable(): void
    {
        static $created = false;
        if ($created) return;

        getDbConnection()->exec('
            CREATE TABLE IF NOT EXISTS organisation_settings (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organisation_id INT UNSIGNED NOT NULL,
                setting_key     VARCHAR(255) NOT NULL,
                setting_value   TEXT         NULL,
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_org_setting (organisation_id, setting_key),
                INDEX idx_org (organisation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $created = true;
    }
}
