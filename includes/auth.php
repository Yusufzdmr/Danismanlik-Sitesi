<?php
require_once __DIR__ . '/config.php';

function checkAdminAuth() {
    // Session based auth
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['admin_logged_in'])) {
        return true;
    }

    // Basic auth (API calls)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (strpos($auth, 'Basic ') === 0) {
            $decoded = base64_decode(substr($auth, 6));
            list($user, $pass) = explode(':', $decoded, 2);
            if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
                return true;
            }
        }
    }

    return false;
}

function requireAdmin() {
    if (!checkAdminAuth()) {
        http_response_code(401);
        echo json_encode(['error' => 'Yetkisiz erişim']);
        exit;
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getRequestBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}
