<?php
/**
 * Ziyaretçi Takip API
 * POST /api/visitors.php?action=track     - Ziyaret kaydet (public)
 * GET  /api/visitors.php?action=stats     - İstatistikler (admin)
 * GET  /api/visitors.php?action=chart     - Grafik verisi (admin)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// Tabloyu oluştur (yoksa)
$db->exec("
    CREATE TABLE IF NOT EXISTS visitors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        page VARCHAR(500) NOT NULL DEFAULT '/',
        referrer VARCHAR(1000) DEFAULT '',
        user_agent VARCHAR(1000) DEFAULT '',
        session_id VARCHAR(64) DEFAULT '',
        visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_visited_at (visited_at),
        INDEX idx_session_id (session_id),
        INDEX idx_page (page(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Ziyaret kaydet (public - auth gerekmez)
if ($method === 'POST' && $action === 'track') {
    $data = getRequestBody();
    $page = $data['page'] ?? '/';
    $referrer = $data['referrer'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sessionId = $data['session_id'] ?? '';

    // IP adresini al
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    // Aynı session + sayfa için son 30 dk içinde kayıt varsa tekrar ekleme (spam önleme)
    if ($sessionId) {
        $check = $db->prepare("SELECT id FROM visitors WHERE session_id = ? AND page = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1");
        $check->execute([$sessionId, $page]);
        if ($check->fetch()) {
            jsonResponse(['success' => true, 'message' => 'Zaten kaydedildi']);
        }
    }

    $stmt = $db->prepare("INSERT INTO visitors (ip_address, page, referrer, user_agent, session_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$ip, $page, $referrer, $userAgent, $sessionId]);

    jsonResponse(['success' => true]);
}

// İstatistikler (admin)
if ($method === 'GET' && $action === 'stats') {
    requireAdmin();

    // Bugün
    $today = $db->query("SELECT COUNT(*) as total FROM visitors WHERE DATE(visited_at) = CURDATE()")->fetch();
    $todayUnique = $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(session_id,''), ip_address)) as total FROM visitors WHERE DATE(visited_at) = CURDATE()")->fetch();

    // Bu hafta
    $week = $db->query("SELECT COUNT(*) as total FROM visitors WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch();
    $weekUnique = $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(session_id,''), ip_address)) as total FROM visitors WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch();

    // Bu ay
    $month = $db->query("SELECT COUNT(*) as total FROM visitors WHERE YEAR(visited_at) = YEAR(CURDATE()) AND MONTH(visited_at) = MONTH(CURDATE())")->fetch();
    $monthUnique = $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(session_id,''), ip_address)) as total FROM visitors WHERE YEAR(visited_at) = YEAR(CURDATE()) AND MONTH(visited_at) = MONTH(CURDATE())")->fetch();

    // Toplam
    $total = $db->query("SELECT COUNT(*) as total FROM visitors")->fetch();
    $totalUnique = $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(session_id,''), ip_address)) as total FROM visitors")->fetch();

    // En çok ziyaret edilen sayfalar
    $topPages = $db->query("SELECT page, COUNT(*) as views FROM visitors GROUP BY page ORDER BY views DESC LIMIT 10")->fetchAll();

    // Bugün aktif olan saatler
    $hourly = $db->query("SELECT HOUR(visited_at) as hour, COUNT(*) as views FROM visitors WHERE DATE(visited_at) = CURDATE() GROUP BY HOUR(visited_at) ORDER BY hour")->fetchAll();

    jsonResponse([
        'today' => (int)$today['total'],
        'todayUnique' => (int)$todayUnique['total'],
        'week' => (int)$week['total'],
        'weekUnique' => (int)$weekUnique['total'],
        'month' => (int)$month['total'],
        'monthUnique' => (int)$monthUnique['total'],
        'total' => (int)$total['total'],
        'totalUnique' => (int)$totalUnique['total'],
        'topPages' => $topPages,
        'hourly' => $hourly
    ]);
}

// Son 30 gün grafik verisi (admin)
if ($method === 'GET' && $action === 'chart') {
    requireAdmin();

    $rows = $db->query("
        SELECT DATE(visited_at) as date,
               COUNT(*) as views,
               COUNT(DISTINCT COALESCE(NULLIF(session_id,''), ip_address)) as unique_visitors
        FROM visitors
        WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(visited_at)
        ORDER BY date
    ")->fetchAll();

    jsonResponse($rows);
}

jsonResponse(['error' => 'Geçersiz istek'], 400);
