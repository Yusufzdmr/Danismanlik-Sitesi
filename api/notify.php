<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['type'])) {
    jsonResponse(['error' => 'Geçersiz veri'], 400);
}

if ($data['type'] === 'new_appointment') {
    $to = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@tkdanismanlik.com';
    $subject = 'Yeni Randevu: ' . ($data['fullName'] ?? 'Bilinmeyen');

    $body = "Yeni bir randevu talebi geldi!\n\n";
    $body .= "Ad Soyad: " . ($data['fullName'] ?? '-') . "\n";
    $body .= "E-posta: " . ($data['email'] ?? '-') . "\n";
    $body .= "Telefon: " . ($data['phone'] ?? '-') . "\n";
    $body .= "Hizmet: " . ($data['service'] ?? '-') . "\n";
    $body .= "Tarih: " . ($data['date'] ?? '-') . "\n";
    $body .= "Saat: " . ($data['time'] ?? '-') . "\n";
    $body .= "Tutar: ₺" . number_format($data['price'] ?? 0, 0, ',', '.') . "\n\n";
    $body .= "Admin Panelden kontrol edin: " . SITE_URL . "/admin.html\n";

    $headers = "From: noreply@tkdanismanlik.com\r\n";
    $headers .= "Reply-To: " . ($data['email'] ?? 'noreply@tkdanismanlik.com') . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $mailSent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

    jsonResponse([
        'success' => true,
        'mailSent' => $mailSent,
        'message' => $mailSent ? 'Bildirim gönderildi' : 'Mail gönderilemedi, sunucu mail ayarlarını kontrol edin'
    ]);
}

jsonResponse(['error' => 'Bilinmeyen bildirim tipi'], 400);
