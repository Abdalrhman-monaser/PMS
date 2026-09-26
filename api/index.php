<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// معالجة طلبات Preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// تحميل ملفات النظام الأساسية
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/repositories/BaseRepository.php';
require_once __DIR__ . '/../includes/repositories/ProjectRepository.php';
require_once __DIR__ . '/../includes/repositories/TaskRepository.php';

require_once __DIR__ . '/middleware/ApiAuth.php';
require_once __DIR__ . '/controllers/TaskApiController.php';

use Api\Middleware\ApiAuth;
use Api\Controllers\TaskApiController;

// التحقق الأمني من التوثيق (Bearer Token)
ApiAuth::authenticate();

// استخراج المسار وتوجيه الطلب
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$controller = new TaskApiController();

if (str_contains($uri, '/api/tasks')) {
    if ($method === 'GET') {
        $controller->index();
    } elseif ($method === 'POST') {
        $controller->store();
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    }
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'API endpoint not found']);
}