<?php
/**
 * Team Service — Main Configuration
 */

// Error reporting
$isProduction = getenv('APP_ENV') === 'production';
if ($isProduction) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

date_default_timezone_set('Europe/London');

// Secure session cookies
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', $isProduction ? 1 : 0);

// Paths
define('ROOT_PATH',     dirname(__DIR__));
define('CONFIG_PATH',   ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('SRC_PATH',      ROOT_PATH . '/src');
define('PUBLIC_PATH',   ROOT_PATH . '/public');

require_once __DIR__ . '/env_loader.php';

define('APP_NAME',      getenv('APP_NAME')      ?: 'Team Service');
define('APP_VERSION',   '1.0.0');
define('APP_URL',       rtrim(getenv('APP_URL') ?: 'http://localhost:8001', '/'));
define('CONTACT_EMAIL', getenv('CONTACT_EMAIL') ?: 'admin@example.com');

// CSRF token name — must match shared-auth expectation
define('CSRF_TOKEN_NAME', 'csrf_token');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoloader — shared-auth base classes first, then local models/classes
spl_autoload_register(function (string $class): void {
    $paths = [
        ROOT_PATH . '/shared-auth/src/' . $class . '.php',
        SRC_PATH  . '/models/'          . $class . '.php',
        SRC_PATH  . '/classes/'         . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

require_once CONFIG_PATH . '/database.php';

// URL helper — handles optional /public/ prefix (Hostinger)
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $force = getenv('FORCE_PUBLIC_PREFIX') === '1';
        $base  = $force ? '/public' : '';
        return $base . '/' . ltrim($path, '/');
    }
}
