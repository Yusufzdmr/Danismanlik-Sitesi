<?php
/**
 * Blog API
 * GET    /api/blog.php          - Yayındaki yazıları listele
 * GET    /api/blog.php?id=1     - Tek yazı getir
 * POST   /api/blog.php          - Yeni yazı ekle (admin)
 * PUT    /api/blog.php?id=1     - Yazı güncelle (admin)
 * DELETE /api/blog.php?id=1     - Yazı sil (admin)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$admin = isset($_GET['admin']);
$db = getDB();

switch ($method) {
    case 'GET':
        if ($id) {
            // Tek yazı
            $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if (!$post) jsonResponse(['error' => 'Yazı bulunamadı'], 404);
            $post['published'] = (bool)$post['published'];
            jsonResponse($post);
        } else {
            // Yazı listesi
            if ($admin) {
                requireAdmin();
                $stmt = $db->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
            } else {
                $stmt = $db->query("SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC");
            }
            $posts = $stmt->fetchAll();
            foreach ($posts as &$p) {
                $p['published'] = (bool)$p['published'];
                $p['date'] = $p['created_at'] ? date('Y-m-d', strtotime($p['created_at'])) : null;
                $p['readTime'] = $p['read_time'];
            }
            jsonResponse($posts);
        }
        break;

    case 'POST':
        requireAdmin();
        $data = getRequestBody();
        $stmt = $db->prepare("
            INSERT INTO blog_posts (title, category, content, summary, icon, read_time, published, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $summary = !empty($data['summary']) ? $data['summary'] : mb_substr($data['content'] ?? '', 0, 150) . '...';
        $stmt->execute([
            $data['title'] ?? '',
            $data['category'] ?? 'Genel',
            $data['content'] ?? '',
            $summary,
            $data['icon'] ?? 'pen',
            $data['readTime'] ?? '5 dk',
            isset($data['published']) ? ($data['published'] ? 1 : 0) : 1,
            $data['date'] ?? date('Y-m-d H:i:s')
        ]);
        $newId = $db->lastInsertId();
        jsonResponse(['id' => $newId, 'message' => 'Yazı eklendi'], 201);
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $data = getRequestBody();

        $fields = [];
        $values = [];

        $map = [
            'title' => 'title',
            'category' => 'category',
            'content' => 'content',
            'summary' => 'summary',
            'icon' => 'icon',
            'readTime' => 'read_time',
            'date' => 'created_at'
        ];

        foreach ($map as $jsonKey => $dbKey) {
            if (isset($data[$jsonKey])) {
                $fields[] = "`$dbKey` = ?";
                $values[] = $data[$jsonKey];
            }
        }

        if (isset($data['published'])) {
            $fields[] = "`published` = ?";
            $values[] = $data['published'] ? 1 : 0;
        }

        if (empty($fields)) jsonResponse(['error' => 'Güncellenecek alan yok'], 400);

        $values[] = $id;
        $sql = "UPDATE blog_posts SET " . implode(', ', $fields) . " WHERE id = ?";
        $db->prepare($sql)->execute($values);
        jsonResponse(['message' => 'Yazı güncellendi']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
        jsonResponse(['message' => 'Yazı silindi']);
        break;

    default:
        jsonResponse(['error' => 'Desteklenmeyen metod'], 405);
}
