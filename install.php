<?php
/**
 * Veritabanı Kurulum Scripti
 * Tabloları sıfırdan oluşturur.
 * Kullanım: Tarayıcıda /install.php adresine gidin.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';

echo "<h2>TK Danışmanlık - Veritabanı Kurulumu</h2>";
echo "<p>Host: " . DB_HOST . "</p>";
echo "<p>DB: " . DB_NAME . "</p>";
echo "<p>User: " . DB_USER . "</p>";

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green;font-weight:bold;'>Veritabanı bağlantısı başarılı!</p>";

    // ========== TABLOLARI OLUŞTUR ==========

    // 1. appointments
    $db->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            service_type VARCHAR(255) NOT NULL DEFAULT '',
            appointment_date VARCHAR(50) NOT NULL DEFAULT '',
            appointment_time VARCHAR(50) NOT NULL DEFAULT '',
            message TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            payment_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>&#10004; <strong>appointments</strong> tablosu oluşturuldu.</p>";

    // 2. blog_posts
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(500) NOT NULL DEFAULT '',
            category VARCHAR(255) NOT NULL DEFAULT 'Genel',
            content LONGTEXT,
            summary TEXT,
            icon VARCHAR(100) NOT NULL DEFAULT 'pen',
            read_time VARCHAR(50) NOT NULL DEFAULT '5 dk',
            published TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>&#10004; <strong>blog_posts</strong> tablosu oluşturuldu.</p>";

    // 3. contact_messages
    $db->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            subject VARCHAR(500) NOT NULL DEFAULT '',
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>&#10004; <strong>contact_messages</strong> tablosu oluşturuldu.</p>";

    // 4. newsletter
    $db->exec("
        CREATE TABLE IF NOT EXISTS newsletter (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>&#10004; <strong>newsletter</strong> tablosu oluşturuldu.</p>";

    // 5. visitors
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
    echo "<p>&#10004; <strong>visitors</strong> tablosu oluşturuldu.</p>";

    // 6. paid_articles - Ücretli makaleler
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
    echo "<p>&#10004; <strong>paid_articles</strong> tablosu oluşturuldu.</p>";

    // 7. article_purchases - Makale satın alımları
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
            UNIQUE KEY uniq_token (token),
            INDEX idx_article (article_id),
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>&#10004; <strong>article_purchases</strong> tablosu oluşturuldu.</p>";

    echo "<hr>";
    echo "<p style='color:green;font-weight:bold;font-size:18px;'>Tüm tablolar başarıyla oluşturuldu!</p>";
    echo "<p><a href='/admin.html'>Admin Panele Git &rarr;</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold;'>HATA: " . htmlspecialchars($e->getMessage()) . "</p>";
}
