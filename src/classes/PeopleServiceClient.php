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
     * Look up the remote org ID for a given domain (called on integration save).
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
     * Fetch all active people. Returns [['id', 'name', 'ref'], ...] or [].
     * Uses the resolved remote org ID stored in OrgSettings.
     */
    public static function fetchAll(int $orgId): array
    {
        if (!self::enabled($orgId)) return [];

        // Use the resolved remote org ID; fall back to local org ID if not yet resolved
        $remoteOrgId = (int) OrgSettings::get($orgId, 'people_service_org_id', (string) $orgId);
        $url = self::baseUrl($orgId) . '/api/people-data.php?status=active&organisation_id=' . $remoteOrgId;

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
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false && json_decode($body) !== null;
    }
}
