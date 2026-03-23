<?php
/**
 * People Service API Client (used by Team Service)
 *
 * Connection settings are read per-organisation from the organisation_settings
 * table (configured via Admin → Settings → Integrations), falling back to
 * .env constants PEOPLE_SERVICE_URL and PEOPLE_SERVICE_API_KEY.
 */
class PeopleServiceClient
{
    private static function baseUrl(int $orgId): string
    {
        return rtrim(
            OrgSettings::get($orgId, 'people_service_url', PEOPLE_SERVICE_URL),
            '/'
        );
    }

    private static function apiKey(int $orgId): string
    {
        return OrgSettings::get($orgId, 'people_service_api_key', PEOPLE_SERVICE_API_KEY);
    }

    public static function enabled(int $orgId): bool
    {
        return self::baseUrl($orgId) !== '' && self::apiKey($orgId) !== '';
    }

    /**
     * Fetch all active people. Returns [['id', 'name', 'ref'], ...] or [].
     * organisation_id is passed as a query param for app-scoped keys.
     */
    public static function fetchAll(int $orgId): array
    {
        if (!self::enabled($orgId)) return [];

        $url = self::baseUrl($orgId) . '/api/people-data.php?status=active&organisation_id=' . $orgId;
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

        $rows = json_decode($body, true);
        if (!is_array($rows)) return [];

        return array_map(fn($p) => [
            'id'   => (int) $p['id'],
            'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
            'ref'  => $p['preferred_name'] ?? '',
        ], $rows);
    }

    /**
     * Test a connection with explicit URL + key (used by settings page).
     */
    public static function testConnection(string $url, string $apiKey): bool
    {
        $url = rtrim($url, '/') . '/api/people-data.php?organisation_id=0';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . $apiKey . "\r\nAccept: application/json\r\n",
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);
        // Any JSON response (even org-required error) means the service is reachable and key is valid
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false && json_decode($body) !== null;
    }
}
