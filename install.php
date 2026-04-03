<?php
/**
 * TuncerYazıyor.com - Veritabanı Kurulumu
 * Bu dosyayı bir kere çalıştırın, sonra silin.
 * Kullanım: php install.php (terminal) veya tarayıcıdan açın
 */

require_once __DIR__ . '/includes/config.php';

try {
    // Önce veritabanı olmadan bağlan
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Veritabanını oluştur
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    echo "Veritabani olusturuldu: " . DB_NAME . "<br>\n";

    // Blog tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `blog_posts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(500) NOT NULL,
            `category` VARCHAR(100) DEFAULT 'Genel',
            `content` TEXT NOT NULL,
            `summary` VARCHAR(500),
            `icon` VARCHAR(50) DEFAULT 'pen',
            `read_time` VARCHAR(20) DEFAULT '5 dk',
            `published` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tablo olusturuldu: blog_posts<br>\n";

    // Randevu tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `appointments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(200) NOT NULL,
            `email` VARCHAR(200) NOT NULL,
            `phone` VARCHAR(30),
            `service_type` VARCHAR(50) NOT NULL,
            `appointment_date` DATE NOT NULL,
            `appointment_time` VARCHAR(10) NOT NULL,
            `message` TEXT,
            `price` DECIMAL(10,2) DEFAULT 0,
            `status` ENUM('pending','paid','failed','cancelled') DEFAULT 'pending',
            `payment_id` VARCHAR(100),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tablo olusturuldu: appointments<br>\n";

    // İletişim mesajları
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `contact_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `email` VARCHAR(200) NOT NULL,
            `subject` VARCHAR(300),
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tablo olusturuldu: contact_messages<br>\n";

    // Bülten aboneleri
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `newsletter` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(200) NOT NULL UNIQUE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Tablo olusturuldu: newsletter<br>\n";

    // Örnek blog yazıları ekle
    $check = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    if ($check == 0) {
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, category, content, summary, icon, read_time, published, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");

        $stmt->execute([
            'Başarıya Giden Yolda En Önemli 5 Adım',
            'Kişisel Gelişim',
            "Hedeflerinize ulaşmak için atmanız gereken temel adımları bu yazımda paylaşıyorum.\n\n1. **Net Hedefler Belirleyin** - Ne istediğinizi tam olarak bilin.\n2. **Plan Yapın** - Hedefe giden yolu küçük adımlara bölün.\n3. **Disiplinli Olun** - Her gün küçük de olsa bir adım atın.\n4. **Öğrenmeye Devam Edin** - Bilgi en büyük yatırımdır.\n5. **Pes Etmeyin** - Zorluklar geçicidir, kararlılık kalıcıdır.",
            'Hedeflerinize ulaşmak için atmanız gereken temel adımları bu yazımda paylaşıyorum...',
            'lightbulb', '5 dk', '2026-04-01'
        ]);

        $stmt->execute([
            'Doğru Kararlar Almak İçin Stratejik Düşünme',
            'Strateji',
            "Kararsızlık anlarında size yol gösterecek stratejik düşünme teknikleri hakkında bilgiler.\n\nStratejik düşünme, sadece iş hayatında değil, hayatın her alanında kritik bir beceridir.",
            'Kararsızlık anlarında size yol gösterecek stratejik düşünme teknikleri...',
            'brain', '7 dk', '2026-03-25'
        ]);

        $stmt->execute([
            'Etkili İletişimin Sırları',
            'İletişim',
            "İş ve özel hayatınızda fark yaratacak iletişim becerileri üzerine düşüncelerim.\n\nİletişim sadece konuşmak değildir. Dinlemek, anlamak ve doğru zamanda doğru şeyi söylemek de iletişimin önemli parçalarıdır.",
            'İş ve özel hayatınızda fark yaratacak iletişim becerileri üzerine düşüncelerim...',
            'handshake', '4 dk', '2026-03-18'
        ]);

        echo "Ornek blog yazilari eklendi.<br>\n";
    }

    echo "<br>\n<strong>Kurulum tamamlandi! Bu dosyayi silin: install.php</strong><br>\n";

} catch (PDOException $e) {
    echo "HATA: " . $e->getMessage() . "<br>\n";
}
