<?php
/**
 * DB Kurulum - Ücretli Makaleler Tabloları
 * install.php 403 alıyorsa bunu kullanın.
 * Kullanım: Tarayıcıda /db-setup.php?key=tk2026setup
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

// Basit anahtar koruması — link tahmin edilemesin
if (($_GET['key'] ?? '') !== 'tk2026setup') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

echo "<h2>TK Danışmanlık - Ücretli Makaleler Kurulumu</h2>";

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green;'>Veritabanı bağlantısı başarılı.</p>";

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
    echo "<p>&#10004; <strong>paid_articles</strong> tablosu hazır.</p>";

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
    echo "<p>&#10004; <strong>article_purchases</strong> tablosu hazır.</p>";

    // Eski kayıtlar için eksik sütun ekle
    try {
        $db->exec("ALTER TABLE article_purchases ADD COLUMN downloaded_at TIMESTAMP NULL DEFAULT NULL");
        echo "<p>&#10004; downloaded_at sütunu eklendi.</p>";
    } catch (Exception $e) {
        echo "<p style='color:#666;'>downloaded_at sütunu zaten mevcut.</p>";
    }

    // data/articles klasörünü ve .htaccess'i oluştur
    $articlesDir = __DIR__ . '/data/articles';
    if (!is_dir($articlesDir)) { @mkdir($articlesDir, 0755, true); }
    $ht = $articlesDir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Order allow,deny\nDeny from all\n");
    }
    echo "<p>&#10004; data/articles/ klasörü ve .htaccess hazır.</p>";

    echo "<hr>";
    echo "<p style='color:green;font-weight:bold;font-size:18px;'>Kurulum tamamlandı!</p>";
    echo "<p><a href='/admin.html'>Admin Panele Git &rarr;</a></p>";
    echo "<p style='color:#888;font-size:12px;'>Güvenlik: Kurulumdan sonra bu dosyayı sunucudan silebilirsiniz.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>HATA: " . htmlspecialchars($e->getMessage()) . "</p>";
}
