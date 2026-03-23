<?php
/**
 * Staff Service API Client (used by Team Service)
 *
 * Reads STAFF_SERVICE_URL and STAFF_SERVICE_API_KEY from .env / constants.
 */
class StaffServiceClient
{
    public static function enabled(): bool
    {
        return STAFF_SERVICE_URL !== '' && STAFF_SERVICE_API_KEY !== '';
    }

    /**
     * Fetch all active staff from the Staff Service.
     * Returns a flat array of ['id', 'name', 'ref'] items ready for the search UI.
     */
    public static function fetchAll(): array
    {
        if (!self::enabled()) return [];

        $url = STAFF_SERVICE_URL . '/api/staff-data.php?limit=100&include_inactive=0';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Authorization: Bearer ' . STAFF_SERVICE_API_KEY . "\r\n" .
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
            'id'  => (int) $s['id'],
            'name' => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
            'ref'  => $s['employee_reference'] ?? '',
        ], $rows);
    }
}
