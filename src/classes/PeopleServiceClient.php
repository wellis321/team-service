<?php
/**
 * People Service API Client (used by Team Service)
 *
 * Reads PEOPLE_SERVICE_URL and PEOPLE_SERVICE_API_KEY from .env / constants.
 */
class PeopleServiceClient
{
    public static function enabled(): bool
    {
        return PEOPLE_SERVICE_URL !== '' && PEOPLE_SERVICE_API_KEY !== '';
    }

    /**
     * Fetch all active people from the People Service.
     * Returns a flat array of ['id', 'name', 'ref'] items ready for the search UI.
     */
    public static function fetchAll(int $orgId = 0): array
    {
        if (!self::enabled()) return [];

        $url = PEOPLE_SERVICE_URL . '/api/people-data.php?status=active'
             . ($orgId ? '&organisation_id=' . $orgId : '');
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . PEOPLE_SERVICE_API_KEY . "\r\n" .
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
}
