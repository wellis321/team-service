<?php
/**
 * Staff Service API Client (used by Team Service)
 *
 * Connection settings are read per-organisation from the organisation_settings
 * table (configured via Admin → Settings → Integrations), falling back to
 * .env constants STAFF_SERVICE_URL and STAFF_SERVICE_API_KEY.
 */
class StaffServiceClient
{
    private static function baseUrl(int $orgId): string
    {
        return rtrim(
            OrgSettings::get($orgId, 'staff_service_url', STAFF_SERVICE_URL),
            '/'
        );
    }

    private static function apiKey(int $orgId): string
    {
        return OrgSettings::get($orgId, 'staff_service_api_key', STAFF_SERVICE_API_KEY);
    }

    public static function enabled(int $orgId): bool
    {
        return self::baseUrl($orgId) !== '' && self::apiKey($orgId) !== '';
    }

    /**
     * Fetch all active staff. Returns [['id', 'name', 'ref'], ...] or [].
     */
    public static function fetchAll(int $orgId): array
    {
        if (!self::enabled($orgId)) return [];

        $url = self::baseUrl($orgId) . '/api/staff-data.php?limit=100&include_inactive=0';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . self::apiKey($orgId) . "\r\n" .
                                   'Accept: application/json' . "\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return [];

        $decoded = json_decode($body, true);
        $rows    = $decoded['data'] ?? (is_array($decoded) ? $decoded : []);

        return array_map(fn($s) => [
            'id'   => (int) $s['id'],
            'name' => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
            'ref'  => $s['employee_reference'] ?? '',
        ], $rows);
    }

    /**
     * Look up the remote org ID for a given domain (used by settings page on save).
     */
    public static function orgLookup(string $url, string $apiKey, string $domain): ?int
    {
        $url = rtrim($url, '/') . '/api/org-lookup.php?domain=' . urlencode($domain);
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . $apiKey . "\r\nAccept: application/json\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;
        $data = json_decode($body, true);
        return isset($data['org_id']) ? (int) $data['org_id'] : null;
    }

    /**
     * Test a connection with explicit URL + key (used by settings page).
     */
    public static function testConnection(string $url, string $apiKey): bool
    {
        $url = rtrim($url, '/') . '/api/staff-data.php?limit=1';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . $apiKey . "\r\nAccept: application/json\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false && json_decode($body) !== null;
    }
}
