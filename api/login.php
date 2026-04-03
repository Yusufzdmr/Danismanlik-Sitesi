<?php
/**
 * Admin Login API
 * POST /api/login.php
 */

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST gerekli'], 405);
}

if (checkAdminAuth()) {
    jsonResponse(['success' => true, 'message' => 'Giriş başarılı']);
} else {
    jsonResponse(['error' => 'Geçersiz kullanıcı adı veya şifre'], 401);
}
