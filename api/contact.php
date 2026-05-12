<?php
/**
 * İletişim & Bülten API
 * POST /api/contact.php?action=message    - İletişim mesajı
 * POST /api/contact.php?action=newsletter - Bülten aboneliği
 * GET  /api/contact.php?action=messages   - Mesajları listele (admin)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'POST' && $action === 'message') {
    $data = getRequestBody();
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';

    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message]);

    // Admin'e bildirim maili
    $adminBody = "
        <p><strong>Gönderen:</strong> {$name}</p>
        <p><strong>E-posta:</strong> {$email}</p>
        <p><strong>Konu:</strong> {$subject}</p>
        <hr style='border:none;border-top:1px solid #e2e8f0;margin:16px 0;'>
        <p>{$message}</p>
        <br>
        <p style='color:#64748b;font-size:13px;'>Admin panelden tüm mesajları görebilirsiniz: <a href='" . SITE_URL . "/admin.html'>Admin Panel</a></p>
    ";
    notifyAdmin("Yeni İletişim Mesajı: {$subject}", $adminBody, $email);

    // Gönderene teşekkür maili
    $thankBody = "
        <p>Merhaba <strong>{$name}</strong>,</p>
        <p>Mesajınız başarıyla iletildi. En kısa sürede size dönüş yapacağız.</p>
        <br>
        <p style='color:#64748b;font-size:13px;'><strong>Mesajınız:</strong></p>
        <p style='color:#64748b;'>{$message}</p>
        <br>
        <p>Teşekkürler,<br><strong>TK Danışmanlık</strong></p>
    ";
    sendMail($email, "Mesajınız Alındı - TK Danışmanlık", $thankBody);

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

        // Hoş geldin maili
        $welcomeBody = "
            <p>Merhaba,</p>
            <p>TK Danışmanlık bültenine başarıyla abone oldunuz!</p>
            <p>Bundan sonra yeni blog yazıları, danışmanlık içerikleri ve özel duyurulardan ilk siz haberdar olacaksınız.</p>
            <br>
            <p>Teşekkürler,<br><strong>TK Danışmanlık</strong></p>
        ";
        sendMail($email, "Bültene Hoş Geldiniz! - TK Danışmanlık", $welcomeBody);
    } catch (PDOException $e) {
        // Duplicate - zaten kayıtlı
    }
    jsonResponse(['success' => true, 'message' => 'Bültene abone oldunuz']);
}

if ($method === 'POST' && $action === 'review') {
    $data = getRequestBody();
    $stmt = $db->prepare("INSERT INTO reviews (name, role, text, stars, approved) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([
        $data['name'] ?? '',
        $data['role'] ?? 'Danışan',
        $data['text'] ?? '',
        (int)($data['stars'] ?? 5)
    ]);
    jsonResponse(['success' => true, 'message' => 'Yorumunuz gönderildi']);
}

if ($method === 'GET' && $action === 'reviews') {
    // Public: sadece onaylı yorumlar
    $stmt = $db->query("SELECT id, name, role, text, stars, created_at FROM reviews WHERE approved = 1 ORDER BY created_at DESC");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'GET' && $action === 'reviews_all') {
    // Admin: tüm yorumlar
    requireAdmin();
    $stmt = $db->query("SELECT * FROM reviews ORDER BY created_at DESC");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'PUT' && $action === 'review') {
    // Admin: yorumu onayla/reddet
    requireAdmin();
    $data = getRequestBody();
    $id = (int)($data['id'] ?? 0);
    $approved = (int)($data['approved'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
    $db->prepare("UPDATE reviews SET approved = ? WHERE id = ?")->execute([$approved, $id]);
    jsonResponse(['success' => true, 'message' => $approved ? 'Yorum onaylandı' : 'Yorum reddedildi']);
}

if ($method === 'DELETE' && $action === 'review') {
    // Admin: yorum sil
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
    $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Yorum silindi']);
}

if ($method === 'GET' && $action === 'messages') {
    requireAdmin();
    $messages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
    jsonResponse($messages);
}

if ($method === 'DELETE' && $action === 'message') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
    $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Mesaj silindi']);
}

if ($method === 'PUT' && $action === 'read_message') {
    requireAdmin();
    $data = getRequestBody();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
    $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Mesaj okundu olarak işaretlendi']);
}

if ($method === 'GET' && $action === 'subscribers') {
    requireAdmin();
    $subscribers = $db->query("SELECT * FROM newsletter ORDER BY created_at DESC")->fetchAll();
    jsonResponse($subscribers);
}

jsonResponse(['error' => 'Geçersiz istek'], 400);
