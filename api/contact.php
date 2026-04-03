<?php
/**
 * İletişim & Bülten API
 * POST /api/contact.php?action=message    - İletişim mesajı
 * POST /api/contact.php?action=newsletter - Bülten aboneliği
 * GET  /api/contact.php?action=messages   - Mesajları listele (admin)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'POST' && $action === 'message') {
    $data = getRequestBody();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $data['name'] ?? '',
        $data['email'] ?? '',
        $data['subject'] ?? '',
        $data['message'] ?? ''
    ]);
    jsonResponse(['success' => true, 'message' => 'Mesajınız gönderildi']);
}

if ($method === 'POST' && $action === 'newsletter') {
    $data = getRequestBody();
    $email = $data['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Geçersiz e-posta'], 400);
    }
    try {
        $db->prepare("INSERT INTO newsletter (email) VALUES (?)")->execute([$email]);
    } catch (PDOException $e) {
        // Duplicate - zaten kayıtlı
    }
    jsonResponse(['success' => true, 'message' => 'Bültene abone oldunuz']);
}

if ($method === 'GET' && $action === 'messages') {
    requireAdmin();
    $messages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    jsonResponse($messages);
}

jsonResponse(['error' => 'Geçersiz istek'], 400);
