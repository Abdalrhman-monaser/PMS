<?php
/**
 * Core application bootstrap: env, error handling, session, constants,
 * autoload of helpers and repositories.     
 */

require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

define('APP_ENV', env('APP_ENV', 'local'));

/* ---- Error handling (Phase 7) ----
 * local:      show errors on screen — convenient while developing on XAMPP.
 * production: hide errors from visitors, log them to storage/logs/error.log
 *             instead so nothing sensitive leaks but nothing is lost either. */
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/error.log');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

/* ---- Session cookie hardening (Phase 7) ---- */
if (session_status() === PHP_SESSION_NONE) {
    $forceHttps = env('FORCE_HTTPS', '0') === '1';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $forceHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_NAME', 'PMS');
define('BASE_URL', '/pms'); // change if you place the folder elsewhere under htdocs

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/repositories/BaseRepository.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/repositories/TaskRepository.php';
require_once __DIR__ . '/../includes/repositories/UserRepository.php';
require_once __DIR__ . '/../includes/repositories/ActivityRepository.php';
require_once __DIR__ . '/../includes/repositories/AttachmentRepository.php';
require_once __DIR__ . '/../includes/repositories/PermissionRepository.php';
require_once __DIR__ . '/../includes/repositories/NotificationRepository.php';
