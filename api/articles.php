<?php
/**
 * Ücretli Makaleler API
 *
 * Public:
 *   GET  /api/articles.php                          - Yayındaki makaleler (content/pdf gizli)
 *   GET  /api/articles.php?id=5                     - Tek makale önizleme (content/pdf gizli)
 *   GET  /api/articles.php?id=5&token=XYZ           - Token geçerliyse içerik açılır
 *   GET  /api/articles.php?action=download&token=XYZ - PDF indir
 *   POST /api/articles.php?action=purchase          - iyzico ödeme başlat
 *   POST /api/articles.php?action=callback          - iyzico callback (HTML redirect)
 *
 * Admin:
 *   GET    /api/articles.php?admin=1                - Tüm makaleler (taslak dahil)
 *   GET    /api/articles.php?id=5&admin=1           - Tüm alanlar
 *   POST   /api/articles.php                        - Yeni makale
 *   PUT    /api/articles.php?id=5                   - Güncelle
 *   DELETE /api/articles.php?id=5                   - Sil
 *   GET    /api/articles.php?action=purchases&admin=1 - Satın alma listesi
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isAdmin = isset($_GET['admin']);
$db = getDB();

// Tablolar yoksa otomatik oluştur (lazy migration)
ensureArticleTables($db);

function ensureArticleTables($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS paid_articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(500) NOT NULL DEFAULT '',
                summary TEXT,
                content LONGTEXT,
                pdf_path VARCHAR(500) DEFAULT NULL,
                cover_image VARCHAR(500) DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                read_time VARCHAR(50) NOT NULL DEFAULT '5 dk',
                published TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS article_purchases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                full_name VARCHAR(255) NOT NULL DEFAULT '',
                email VARCHAR(255) NOT NULL DEFAULT '',
                phone VARCHAR(50) NOT NULL DEFAULT '',
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                token VARCHAR(128) DEFAULT NULL,
                payment_id VARCHAR(255) DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                paid_at TIMESTAMP NULL DEFAULT NULL,
                downloaded_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_token (token),
                INDEX idx_article (article_id),
                INDEX idx_email (email),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Eski install.php'de downloaded_at sütunu yoktu — eksikse ekle
        try {
            $db->exec("ALTER TABLE article_purchases ADD COLUMN downloaded_at TIMESTAMP NULL DEFAULT NULL");
        } catch (Exception $e) { /* zaten var */ }
    } catch (Exception $e) {
        // Tablo oluşturulamadıysa hatayı yutma — endpoint'lerde göstereceğiz
    }
}

// =================== ACTION ROUTES ===================

if ($action === 'download' && $method === 'GET') {
    handleDownload($db);
    exit;
}

if ($action === 'purchase' && $method === 'POST') {
    handlePurchase($db);
    exit;
}

if ($action === 'callback' && $method === 'POST') {
    handleCallback($db);
    exit;
}

if ($action === 'purchases' && $method === 'GET') {
    requireAdmin();
    $stmt = $db->query("
        SELECT ap.*, pa.title AS article_title
        FROM article_purchases ap
        LEFT JOIN paid_articles pa ON pa.id = ap.article_id
        ORDER BY ap.created_at DESC
        LIMIT 500
    ");
    jsonResponse($stmt->fetchAll());
}

if ($action === 'delete-purchase' && $method === 'POST') {
    requireAdmin();
    $d = getRequestBody();
    $pid = (int)($d['id'] ?? 0);
    if ($pid <= 0) jsonResponse(['error' => 'ID gerekli'], 400);
    $db->prepare("DELETE FROM article_purchases WHERE id = ?")->execute([$pid]);
    jsonResponse(['message' => 'Kayıt silindi']);
}

// =================== CRUD ROUTES ===================

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM paid_articles WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            if (!$article) jsonResponse(['error' => 'Makale bulunamadı'], 404);

            if ($isAdmin) {
                requireAdmin();
                $article['published'] = (bool)$article['published'];
                $article['price']     = (float)$article['price'];
                jsonResponse($article);
            }

            if (!$article['published']) jsonResponse(['error' => 'Makale bulunamadı'], 404);

            // Public preview - token varsa içerik aç
            $token = trim($_GET['token'] ?? '');
            $unlocked = false;
            if ($token !== '') {
                $p = $db->prepare("SELECT id FROM article_purchases WHERE article_id = ? AND token = ? AND status = 'paid' LIMIT 1");
                $p->execute([$id, $token]);
                $unlocked = (bool)$p->fetch();
            }

            $resp = [
                'id'          => (int)$article['id'],
                'title'       => $article['title'],
                'summary'     => $article['summary'],
                'cover_image' => $article['cover_image'],
                'price'       => (float)$article['price'],
                'read_time'   => $article['read_time'],
                'created_at'  => $article['created_at'],
                'unlocked'    => $unlocked
            ];
            if ($unlocked) {
                $resp['content']      = $article['content'];
                $resp['has_pdf']      = !empty($article['pdf_path']);
                $resp['download_url'] = !empty($article['pdf_path'])
                    ? 'api/articles.php?action=download&token=' . urlencode($token)
                    : null;
            }
            jsonResponse($resp);
        }

        // Liste
        try {
            if ($isAdmin) {
                requireAdmin();
                $stmt = $db->query("SELECT id, title, summary, cover_image, price, published, read_time, created_at FROM paid_articles ORDER BY created_at DESC");
            } else {
                $stmt = $db->query("SELECT id, title, summary, cover_image, price, read_time, created_at FROM paid_articles WHERE published = 1 ORDER BY created_at DESC");
            }
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['price'] = (float)$r['price'];
                if (isset($r['published'])) $r['published'] = (bool)$r['published'];
            }
            jsonResponse($rows);
        } catch (Exception $e) {
            // Tablo yok / DB hatası — boş dön ki frontend kırılmasın
            jsonResponse([]);
        }
        break;

    case 'POST':
        requireAdmin();
        $d = getRequestBody();
        $stmt = $db->prepare("
            INSERT INTO paid_articles (title, summary, content, pdf_path, cover_image, price, read_time, published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($d['title'] ?? ''),
            trim($d['summary'] ?? ''),
            $d['content'] ?? '',
            !empty($d['pdf_path']) ? $d['pdf_path'] : null,
            !empty($d['cover_image']) ? $d['cover_image'] : null,
            (float)($d['price'] ?? 0),
            $d['read_time'] ?? '5 dk',
            !empty($d['published']) ? 1 : 0
        ]);
        jsonResponse(['id' => $db->lastInsertId(), 'message' => 'Makale eklendi'], 201);
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $d = getRequestBody();
        $map = [
            'title' => 'title',
            'summary' => 'summary',
            'content' => 'content',
            'pdf_path' => 'pdf_path',
            'cover_image' => 'cover_image',
            'price' => 'price',
            'read_time' => 'read_time'
        ];
        $fields = [];
        $values = [];
        foreach ($map as $jsonKey => $col) {
            if (array_key_exists($jsonKey, $d)) {
                $fields[] = "`$col` = ?";
                $values[] = ($jsonKey === 'price') ? (float)$d[$jsonKey] : $d[$jsonKey];
            }
        }
        if (array_key_exists('published', $d)) {
            $fields[] = "`published` = ?";
            $values[] = $d['published'] ? 1 : 0;
        }
        if (empty($fields)) jsonResponse(['error' => 'Güncellenecek alan yok'], 400);
        $values[] = $id;
        $db->prepare("UPDATE paid_articles SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
        jsonResponse(['message' => 'Makale güncellendi']);
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $db->prepare("DELETE FROM paid_articles WHERE id = ?")->execute([$id]);
        jsonResponse(['message' => 'Makale silindi']);
        break;

    default:
        jsonResponse(['error' => 'Desteklenmeyen metod'], 405);
}

// =================== HANDLERS ===================

function handlePurchase($db) {
    $d = getRequestBody();
    $articleId = (int)($d['articleId'] ?? 0);
    if ($articleId <= 0) jsonResponse(['error' => 'Makale seçilmedi'], 400);

    // DB'den makale + fiyat
    $stmt = $db->prepare("SELECT id, title, price, published FROM paid_articles WHERE id = ?");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch();
    if (!$article || !$article['published']) jsonResponse(['error' => 'Makale bulunamadı'], 404);

    $price = (float)$article['price'];
    if ($price <= 0) jsonResponse(['error' => 'Bu makale satışta değil'], 400);

    $fullName = trim($d['fullName'] ?? '');
    $email    = trim($d['email'] ?? '');
    $phone    = trim($d['phone'] ?? '');
    if ($fullName === '' || $email === '') jsonResponse(['error' => 'Ad ve e-posta zorunlu'], 400);

    // pending purchase oluştur
    $ins = $db->prepare("
        INSERT INTO article_purchases (article_id, full_name, email, phone, price, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $ins->execute([$articleId, $fullName, $email, $phone, $price]);
    $purchaseId = $db->lastInsertId();

    // iyzico checkout form initialize
    $priceStr = number_format($price, 2, '.', '');
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0] ?? 'Ad';
    $lastName  = $nameParts[1] ?? 'Soyad';

    $request = [
        'locale' => 'tr',
        'conversationId' => 'A' . $purchaseId,
        'price' => $priceStr,
        'paidPrice' => $priceStr,
        'currency' => 'TRY',
        'basketId' => 'AP' . $purchaseId,
        'paymentGroup' => 'PRODUCT',
        'callbackUrl' => SITE_URL . '/api/articles.php?action=callback',
        'buyer' => [
            'id' => 'BA' . $purchaseId,
            'name' => $firstName,
            'surname' => $lastName,
            'gsmNumber' => preg_replace('/\s/', '', $phone ?: '+905000000000'),
            'email' => $email,
            'identityNumber' => '11111111111',
            'registrationAddress' => 'Istanbul, Turkey',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'city' => 'Istanbul',
            'country' => 'Turkey'
        ],
        'shippingAddress' => [
            'contactName' => $fullName, 'city' => 'Istanbul', 'country' => 'Turkey', 'address' => 'Istanbul, Turkey'
        ],
        'billingAddress' => [
            'contactName' => $fullName, 'city' => 'Istanbul', 'country' => 'Turkey', 'address' => 'Istanbul, Turkey'
        ],
        'basketItems' => [
            [
                'id'        => 'art' . $articleId,
                'name'      => mb_substr($article['title'] ?: 'Makale', 0, 100),
                'category1' => 'Makale',
                'itemType'  => 'VIRTUAL',
                'price'     => $priceStr
            ]
        ]
    ];

    $jsonRequest = json_encode($request);
    $result = iyzicoApiRequest('/payment/iyzipos/checkoutform/initialize/ecom', $jsonRequest);

    if (!$result || ($result['status'] ?? '') !== 'success') {
        $logDir = __DIR__ . '/../data';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        @file_put_contents(
            $logDir . '/iyzico-error.log',
            '[' . date('c') . '] article purchase=' . $purchaseId
            . ' req=' . $jsonRequest
            . ' resp=' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }

    if ($result && isset($result['checkoutFormContent'])) {
        jsonResponse([
            'status' => $result['status'] ?? 'failure',
            'token' => $result['token'] ?? '',
            'checkoutFormContent' => $result['checkoutFormContent'] ?? '',
            'purchaseId' => $purchaseId
        ]);
    }

    jsonResponse([
        'status'       => 'failure',
        'purchaseId'   => $purchaseId,
        'errorCode'    => $result['errorCode']    ?? 'NO_RESPONSE',
        'errorMessage' => $result['errorMessage'] ?? 'iyzico bağlantı kurulamadı',
        'errorGroup'   => $result['errorGroup']   ?? ''
    ]);
}

function handleCallback($db) {
    $iyzicoToken = $_POST['token'] ?? '';
    if ($iyzicoToken === '') {
        header('Location: /odeme-basarisiz.html');
        exit;
    }

    $jsonRequest = json_encode(['locale' => 'tr', 'token' => $iyzicoToken]);
    $result = iyzicoApiRequest('/payment/iyzipos/checkoutform/auth/ecom/detail', $jsonRequest);

    if (!$result || ($result['paymentStatus'] ?? '') !== 'SUCCESS') {
        header('Location: /odeme-basarisiz.html');
        exit;
    }

    $basketId   = $result['basketId'] ?? '';
    $purchaseId = (int)str_replace('AP', '', $basketId);
    if ($purchaseId <= 0) {
        header('Location: /odeme-basarisiz.html');
        exit;
    }

    // İdempotent: zaten paid ise mevcut token'la yönlendir
    $stmt = $db->prepare("SELECT * FROM article_purchases WHERE id = ?");
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch();
    if (!$purchase) {
        header('Location: /odeme-basarisiz.html');
        exit;
    }

    if ($purchase['status'] !== 'paid' || empty($purchase['token'])) {
        $accessToken = bin2hex(random_bytes(32));
        $db->prepare("
            UPDATE article_purchases
            SET status = 'paid', payment_id = ?, token = ?, paid_at = NOW()
            WHERE id = ?
        ")->execute([$result['paymentId'] ?? '', $accessToken, $purchaseId]);

        // E-posta gönder
        sendPurchaseEmail($db, $purchaseId, $accessToken);
    } else {
        $accessToken = $purchase['token'];
    }

    $redirect = '/odeme-basarili.html?type=article&id=' . $purchase['article_id']
              . '&token=' . urlencode($accessToken);
    header('Location: ' . $redirect);
    exit;
}

function handleDownload($db) {
    $token = trim($_GET['token'] ?? '');
    if ($token === '') {
        http_response_code(400);
        echo 'Token yok'; exit;
    }

    $stmt = $db->prepare("
        SELECT ap.article_id, pa.pdf_path, pa.title
        FROM article_purchases ap
        JOIN paid_articles pa ON pa.id = ap.article_id
        WHERE ap.token = ? AND ap.status = 'paid'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || empty($row['pdf_path'])) {
        http_response_code(404);
        echo 'Dosya bulunamadı'; exit;
    }

    $relative = ltrim($row['pdf_path'], '/');
    $base = realpath(__DIR__ . '/../data/articles');
    $abs  = realpath(__DIR__ . '/../' . $relative);

    // Path traversal koruması
    if (!$abs || !$base || strpos($abs, $base) !== 0 || !is_file($abs)) {
        http_response_code(404);
        echo 'Dosya bulunamadı'; exit;
    }

    // İndirme istatistiği
    $db->prepare("UPDATE article_purchases SET downloaded_at = NOW() WHERE token = ? AND status = 'paid'")
       ->execute([$token]);

    $safeTitle = preg_replace('/[^A-Za-z0-9._-]/', '_', $row['title'] ?: 'makale');
    $filename = $safeTitle . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: private, max-age=0, no-cache');
    readfile($abs);
    exit;
}

function sendPurchaseEmail($db, $purchaseId, $accessToken) {
    $stmt = $db->prepare("
        SELECT ap.full_name, ap.email, ap.price, pa.id AS article_id, pa.title
        FROM article_purchases ap
        JOIN paid_articles pa ON pa.id = ap.article_id
        WHERE ap.id = ?
    ");
    $stmt->execute([$purchaseId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['email'])) return;

    $readUrl     = SITE_URL . '/makale.html?id=' . $row['article_id'] . '&token=' . urlencode($accessToken);
    $downloadUrl = SITE_URL . '/api/articles.php?action=download&token=' . urlencode($accessToken);
    $price = number_format((float)$row['price'], 2, ',', '.');

    $body = '
        <p>Merhaba <strong>' . htmlspecialchars($row['full_name']) . '</strong>,</p>
        <p><strong>' . htmlspecialchars($row['title']) . '</strong> makalesini satın aldığınız için teşekkür ederiz.</p>
        <p>Aşağıdaki link ile makaleye <strong>süresiz</strong> ulaşabilirsiniz. Linki saklayınız.</p>
        <p style="margin:24px 0;">
            <a href="' . $readUrl . '" style="background:#f0a500;color:#fff;padding:14px 24px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:600;">Makaleyi Oku</a>
            &nbsp;
            <a href="' . $downloadUrl . '" style="background:#1a1a2e;color:#fff;padding:14px 24px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:600;">PDF İndir</a>
        </p>
        <p style="color:#64748b;font-size:13px;">Tutar: ' . $price . ' ₺</p>
        <p style="color:#64748b;font-size:13px;">Sorularınız için: ' . ADMIN_EMAIL . '</p>
    ';
    @sendMail($row['email'], 'Makaleniz hazır - ' . $row['title'], $body);

    // Admin bilgilendirme
    $adminBody = '
        <p>Yeni makale satın alımı.</p>
        <ul>
            <li>Makale: ' . htmlspecialchars($row['title']) . '</li>
            <li>Alıcı: ' . htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['email']) . ')</li>
            <li>Tutar: ' . $price . ' ₺</li>
        </ul>
    ';
    @notifyAdmin('Yeni makale satışı', $adminBody);
}

// =================== iyzico HTTP HELPER ===================
function iyzicoApiRequest($uri, $jsonBody) {
    $randomKey = uniqid('', true);
    $signature = hash_hmac('sha256', $randomKey . $uri . $jsonBody, IYZICO_SECRET_KEY);
    $authString = 'apiKey:' . IYZICO_API_KEY . '&randomKey:' . $randomKey . '&signature:' . $signature;
    $authHeader = 'IYZWSv2 ' . base64_encode($authString);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: ' . $authHeader,
        'x-iyzi-rnd: ' . $randomKey,
        'x-iyzi-client-version: tkdanismanlik-php/1.0'
    ];

    $ch = curl_init(IYZICO_BASE_URL . $uri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) return null;
    return json_decode($response, true);
}
