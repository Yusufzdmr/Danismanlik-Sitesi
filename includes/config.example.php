<?php
/**
 * TuncerYazıyor.com - Konfigürasyon
 * Bu dosyayı config.php olarak kopyalayıp bilgilerinizi girin.
 * GoDaddy cPanel > MySQL Databases bölümünden veritabanı oluşturun.
 */

// Veritabanı (GoDaddy cPanel'den alın)
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adi');
define('DB_USER', 'kullanici_adi');
define('DB_PASS', 'sifre');
define('DB_CHARSET', 'utf8mb4');

// Site URL (sonunda slash yok)
define('SITE_URL', 'https://www.tunceryaziyor.com');
define('SITE_NAME', 'TuncerYazıyor');

// Admin giriş bilgileri (değiştirin!)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'guclu_sifre_yazin');

// iyzico API (merchant.iyzipay.com'dan alın)
// Test: sandbox-merchant.iyzipay.com
// Canlı: merchant.iyzipay.com
define('IYZICO_API_KEY', 'your_api_key');
define('IYZICO_SECRET_KEY', 'your_secret_key');
define('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com');
// Canlı için: https://api.iyzipay.com

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');
