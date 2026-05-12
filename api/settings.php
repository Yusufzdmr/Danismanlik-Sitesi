<?php
/**
 * Settings API - JSON dosya tabanlı
 * GET    /api/settings.php        - Ayarları getir (public)
 * POST   /api/settings.php        - Ayarları kaydet (admin)
 */

require_once __DIR__ . '/../includes/auth.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$settingsFile = __DIR__ . '/../data/settings.json';
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (file_exists($settingsFile)) {
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            readfile($settingsFile);
        } else {
            jsonResponse(null);
        }
        break;

    case 'POST':
        requireAdmin();
        $data = getRequestBody();
        if (empty($data)) {
            jsonResponse(['error' => 'Boş veri gönderilemez'], 400);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($settingsFile, $json) === false) {
            jsonResponse(['error' => 'Dosya yazılamadı'], 500);
        }
        jsonResponse(['message' => 'Ayarlar kaydedildi']);
        break;

    default:
        jsonResponse(['error' => 'Desteklenmeyen metod'], 405);
}
