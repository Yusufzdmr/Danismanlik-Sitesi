<?php
/**
 * TKDanışmanlık.com - Konfigürasyon ÖRNEĞİ
 *
 * Kurulum:
 *   1. Bu dosyayı kopyalayıp `config.php` olarak yeniden adlandırın
 *   2. Aşağıdaki değerleri kendi ortamınıza göre doldurun
 *   3. `config.php` repo'ya eklenmez (.gitignore'da)
 */

// Veritabanı
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');

// Site
define('SITE_URL', 'https://tkdanismanlik.com');
define('SITE_NAME', 'TK Danışmanlık');

// Admin
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'change_me');
define('ADMIN_EMAIL', 'info@tkdanismanlik.com');

// iyzico
// Production: https://api.iyzipay.com
// Sandbox:    https://sandbox-api.iyzipay.com
define('IYZICO_API_KEY', 'your_iyzico_api_key');
define('IYZICO_SECRET_KEY', 'your_iyzico_secret_key');
define('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com');

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');
