<?php
/**
 * iyzico Ödeme API
 * POST /api/payment.php?action=initialize  - Ödeme başlat
 * POST /api/payment.php?action=callback    - iyzico callback
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'initialize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $db = getDB();

    // Read current price & name from settings.json instead of trusting frontend
    $serviceType = $data['serviceType'] ?? '';
    $settingsPrice = 0;
    $serviceName = 'Danismanlik Hizmeti';
    $settingsFile = __DIR__ . '/../data/settings.json';
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
        if (!empty($settings['services'])) {
            foreach ($settings['services'] as $svc) {
                if (($svc['id'] ?? '') === $serviceType) {
                    $settingsPrice = (float)($svc['price'] ?? 0);
                    $serviceName = $svc['name'] ?? $serviceName;
                    break;
                }
            }
        }
    }

    if ($settingsPrice <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Gecersiz hizmet veya tutar'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Randevuyu kaydet
    $stmt = $db->prepare("
        INSERT INTO appointments (full_name, email, phone, service_type, appointment_date, appointment_time, message, price, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $data['fullName'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $serviceType,
        $data['date'] ?? '',
        $data['time'] ?? '',
        $data['message'] ?? '',
        $settingsPrice
    ]);
    $appointmentId = $db->lastInsertId();

    // iyzico Checkout Form Initialize
    $price = number_format($settingsPrice, 2, '.', '');
    $basketId = 'R' . $appointmentId;
    $conversationId = 'C' . $appointmentId;

    $nameParts = explode(' ', trim($data['fullName'] ?? 'Ad Soyad'), 2);
    $firstName = $nameParts[0] ?? 'Ad';
    $lastName = $nameParts[1] ?? 'Soyad';

    $request = [
        'locale' => 'tr',
        'conversationId' => $conversationId,
        'price' => $price,
        'paidPrice' => $price,
        'currency' => 'TRY',
        'basketId' => $basketId,
        'paymentGroup' => 'PRODUCT',
        'callbackUrl' => SITE_URL . '/api/payment.php?action=callback',
        'buyer' => [
            'id' => 'BY' . $appointmentId,
            'name' => $firstName,
            'surname' => $lastName,
            'gsmNumber' => preg_replace('/\s/', '', $data['phone'] ?? ''),
            'email' => $data['email'] ?? 'info@tkdanismanlik.com',
            'identityNumber' => '11111111111',
            'registrationAddress' => 'Istanbul, Turkey',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'city' => 'Istanbul',
            'country' => 'Turkey'
        ],
        'shippingAddress' => [
            'contactName' => $data['fullName'] ?? '',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'address' => 'Istanbul, Turkey'
        ],
        'billingAddress' => [
            'contactName' => $data['fullName'] ?? '',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'address' => 'Istanbul, Turkey'
        ],
        'basketItems' => [
            [
                'id' => $serviceType ?: 'danismanlik',
                'name' => $serviceName,
                'category1' => 'Danismanlik',
                'itemType' => 'VIRTUAL',
                'price' => $price
            ]
        ]
    ];

    // iyzico API call
    $jsonRequest = json_encode($request);
    $uri = '/payment/iyzipos/checkoutform/initialize/ecom';
    $result = iyzicoRequest($uri, $jsonRequest);

    // Diagnostik log (sadece basarisiz durumlarda)
    if (!$result || ($result['status'] ?? '') !== 'success') {
        $logDir = __DIR__ . '/../data';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        @file_put_contents(
            $logDir . '/iyzico-error.log',
            '[' . date('c') . '] appt=' . $appointmentId
            . ' req=' . $jsonRequest
            . ' resp=' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }

    if ($result && isset($result['checkoutFormContent'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $result['status'] ?? 'failure',
            'token' => $result['token'] ?? '',
            'checkoutFormContent' => $result['checkoutFormContent'] ?? '',
            'appointmentId' => $appointmentId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // iyzico hata verdi - randevu pending olarak kaldi
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'status' => 'failure',
            'appointmentId' => $appointmentId,
            'errorCode'    => $result['errorCode']    ?? 'NO_RESPONSE',
            'errorMessage' => $result['errorMessage'] ?? 'iyzico baglanti kurulamadi',
            'errorGroup'   => $result['errorGroup']   ?? ''
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'callback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (empty($token)) {
        header('Location: /odeme-basarisiz.html');
        exit;
    }

    // Retrieve checkout form result
    $jsonRequest = json_encode([
        'locale' => 'tr',
        'token' => $token
    ]);
    $uri = '/payment/iyzipos/checkoutform/auth/ecom/detail';
    $result = iyzicoRequest($uri, $jsonRequest);

    $db = getDB();

    if ($result && ($result['paymentStatus'] ?? '') === 'SUCCESS') {
        $basketId = $result['basketId'] ?? '';
        $appointmentId = str_replace('R', '', $basketId);

        $db->prepare("UPDATE appointments SET status = 'paid', payment_id = ? WHERE id = ?")
           ->execute([$result['paymentId'] ?? '', (int)$appointmentId]);

        header('Location: /odeme-basarili.html');
    } else {
        header('Location: /odeme-basarisiz.html');
    }
    exit;
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Gecersiz istek']);

// ========== iyzico HTTP Helper (v2 HMAC auth) ==========
function iyzicoRequest($uri, $jsonBody) {
    $apiKey    = IYZICO_API_KEY;
    $secretKey = IYZICO_SECRET_KEY;
    $baseUrl   = IYZICO_BASE_URL;

    $randomKey = uniqid('', true);
    $signature = hash_hmac('sha256', $randomKey . $uri . $jsonBody, $secretKey);
    $authString = 'apiKey:' . $apiKey . '&randomKey:' . $randomKey . '&signature:' . $signature;
    $authHeader = 'IYZWSv2 ' . base64_encode($authString);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: ' . $authHeader,
        'x-iyzi-rnd: ' . $randomKey,
        'x-iyzi-client-version: tkdanismanlik-php/1.0'
    ];

    $ch = curl_init($baseUrl . $uri);
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
