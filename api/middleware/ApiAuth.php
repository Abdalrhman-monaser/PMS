<?php

namespace Api\Middleware;

class ApiAuth
{
    /**
     * رمز الوصول الثابت للأغراض التجريبية أو الربط
     * يمكن قراءته مستقبلاً من متغيرات البيئة أو قاعدة البيانات
     */
    private const VALID_TOKEN = 'pms_secret_bearer_token_2026';

    public static function authenticate(): bool
    {
        $headers = self::getRequestHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            self::sendUnauthorizedResponse('Authorization Bearer token is missing');
            return false;
        }

        $token = $matches[1];
        if ($token !== self::VALID_TOKEN) {
            self::sendUnauthorizedResponse('Invalid or expired bearer token');
            return false;
        }

        return true;
    }

    private static function sendUnauthorizedResponse(string $message): void
    {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'code' => 401,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}