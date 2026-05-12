<?php
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

requireAdmin();

$publicUploadDir  = __DIR__ . '/../img/uploads/';
$privateUploadDir = __DIR__ . '/../data/articles/';

foreach ([$publicUploadDir, $privateUploadDir] as $d) {
    if (!is_dir($d)) { mkdir($d, 0755, true); }
}

// Private articles klasörüne web erişimini kapat
$htaccess = $privateUploadDir . '.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
}

$maxImageSize = 5 * 1024 * 1024;   // 5MB
$maxVideoSize = 50 * 1024 * 1024;  // 50MB
$maxPdfSize   = 25 * 1024 * 1024;  // 25MB
$allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedVideos = ['video/mp4', 'video/webm'];
$allowedPdf    = ['application/pdf'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    jsonResponse(['error' => 'Dosya bulunamadı'], 400);
}

$kind = $_GET['kind'] ?? '';  // 'article-pdf' => private; aksi halde public (image/video)

$file = $_FILES['file'];
$mime = mime_content_type($file['tmp_name']);
$isImage = in_array($mime, $allowedImages);
$isVideo = in_array($mime, $allowedVideos);
$isPdf   = in_array($mime, $allowedPdf);

if ($kind === 'article-pdf') {
    if (!$isPdf) {
        jsonResponse(['error' => 'Sadece PDF kabul edilir'], 400);
    }
    if ($file['size'] > $maxPdfSize) {
        jsonResponse(['error' => 'PDF çok büyük. Maksimum: 25MB'], 400);
    }
} else {
    if (!$isImage && !$isVideo) {
        jsonResponse(['error' => 'Desteklenmeyen dosya türü. İzin verilen: JPG, PNG, WebP, GIF, MP4, WebM'], 400);
    }
    $maxSize = $isImage ? $maxImageSize : $maxVideoSize;
    if ($file['size'] > $maxSize) {
        $maxMB = $maxSize / (1024 * 1024);
        jsonResponse(['error' => "Dosya çok büyük. Maksimum: {$maxMB}MB"], 400);
    }
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeBase = preg_replace('/[^a-z0-9._-]/', '', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
$safeBase = substr($safeBase, 0, 60);
$safeName = uniqid() . '_' . $safeBase . '.' . $ext;

if ($kind === 'article-pdf') {
    $targetPath = $privateUploadDir . $safeName;
    $url        = 'data/articles/' . $safeName;  // sadece referans, doğrudan açılmaz
    $type       = 'pdf';
} else {
    $targetPath = $publicUploadDir . $safeName;
    $url        = 'img/uploads/' . $safeName;
    $type       = $isImage ? 'image' : 'video';
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(['error' => 'Dosya yüklenemedi'], 500);
}

jsonResponse([
    'success' => true,
    'url'  => $url,
    'type' => $type,
    'name' => $file['name'],
    'size' => $file['size']
]);
