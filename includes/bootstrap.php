<?php
/**
 * Loaded first by every page: config, secure session, security headers, helpers.
 */
define('ROOT_PATH', dirname(__DIR__));

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) { $cfg = require ROOT_PATH . '/config/config.php'; }
    return $cfg;
}

$__cfg = app_config();
error_reporting(E_ALL);
ini_set('display_errors', $__cfg['debug'] ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Africa/Kigali');

// ---- Secure session settings ----
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BOOSTRWANDA');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();

// ---- Security headers ----
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/wallet_lib.php';
require_once ROOT_PATH . '/includes/tasks_lib.php';
require_once ROOT_PATH . '/includes/reports_lib.php';

// Idle timeout
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $__cfg['session_idle_secs']) {
        $_SESSION = []; session_destroy(); session_start();
        flash('error', 'Your session expired. Please log in again.');
    }
    $_SESSION['last_activity'] = time();
}

// Friendly handler for unexpected errors (details go to the PHP error log only)
set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo app_config()['debug'] ? '<pre>' . htmlspecialchars((string)$e) . '</pre>'
        : 'Something went wrong. Please try again later.';
});
