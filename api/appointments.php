<?php
/**
 * Randevu API
 * GET  /api/appointments.php       - Tüm randevular (admin)
 * POST /api/appointments.php       - Yeni randevu oluştur (public)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        requireAdmin();
        $stmt = $db->query("SELECT * FROM appointments ORDER BY created_at DESC");
        $appointments = $stmt->fetchAll();
        foreach ($appointments as &$a) {
            $a['date'] = $a['appointment_date'];
            $a['time'] = $a['appointment_time'];
            $a['fullName'] = $a['full_name'];
            $a['serviceType'] = $a['service_type'];
        }
        jsonResponse($appointments);
        break;

    case 'POST':
        $data = getRequestBody();
        $stmt = $db->prepare("
            INSERT INTO appointments (full_name, email, phone, service_type, appointment_date, appointment_time, message, price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $data['fullName'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['serviceType'] ?? '',
            $data['date'] ?? '',
            $data['time'] ?? '',
            $data['message'] ?? '',
            $data['price'] ?? 0
        ]);
        $id = $db->lastInsertId();
        jsonResponse(['success' => true, 'id' => $id, 'message' => 'Randevu kaydedildi'], 201);
        break;

    default:
        jsonResponse(['error' => 'Desteklenmeyen metod'], 405);
}
