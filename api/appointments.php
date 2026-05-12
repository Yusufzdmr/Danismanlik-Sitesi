<?php
/**
 * Randevu API
 * GET  /api/appointments.php       - Tüm randevular (admin)
 * POST /api/appointments.php       - Yeni randevu oluştur (public)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        if (isset($_GET['slots'])) {
            // Public: sadece dolu tarih/saat bilgisi
            $stmt = $db->query("SELECT appointment_date, appointment_time FROM appointments WHERE status != 'cancelled'");
            $slots = $stmt->fetchAll();
            foreach ($slots as &$s) {
                $s['date'] = $s['appointment_date'];
                $s['time'] = $s['appointment_time'];
            }
            jsonResponse($slots);
        } else {
            // Admin: tüm randevu detayları
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
        }
        break;

    case 'POST':
        $data = getRequestBody();
        $isAdmin = checkAdminAuth();

        $serviceType = $data['serviceType'] ?? '';
        $price       = 0;
        $settingsFile = __DIR__ . '/../data/settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (!empty($settings['services'])) {
                foreach ($settings['services'] as $svc) {
                    if (($svc['id'] ?? '') === $serviceType) {
                        $price = (float)($svc['price'] ?? 0);
                        break;
                    }
                }
            }
        }

        // Admin manuel kayit icin fiyati ve durumu override edebilir
        $status    = 'pending';
        $paymentId = null;
        if ($isAdmin) {
            if (isset($data['price'])      && is_numeric($data['price'])) $price = (float)$data['price'];
            if (!empty($data['status'])    && in_array($data['status'], ['pending', 'paid', 'cancelled'], true)) {
                $status = $data['status'];
            }
            if (!empty($data['source']) && $data['source'] === 'manual') {
                $paymentId = 'MANUAL';
            }
        }

        $stmt = $db->prepare("
            INSERT INTO appointments (full_name, email, phone, service_type, appointment_date, appointment_time, message, price, status, payment_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['fullName'] ?? '',
            $data['email']    ?? '',
            $data['phone']    ?? '',
            $serviceType,
            $data['date']     ?? '',
            $data['time']     ?? '',
            $data['message']  ?? '',
            $price,
            $status,
            $paymentId
        ]);
        $id = $db->lastInsertId();

        // Admin manuel kayit ise mail bildirimi atma (kendisi girdi)
        if ($isAdmin && $paymentId === 'MANUAL') {
            jsonResponse(['success' => true, 'id' => $id, 'message' => 'Manuel randevu eklendi'], 201);
            break;
        }

        $fullName = $data['fullName'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $service = $serviceType;
        $date = $data['date'] ?? '';
        $time = $data['time'] ?? '';
        // $price is already set from settings.json above

        // Admin'e bildirim
        $adminBody = "
            <p>Yeni bir randevu talebi geldi!</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;width:130px;'>Ad Soyad</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600;'>{$fullName}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>E-posta</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>{$email}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Telefon</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>{$phone}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Hizmet</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;'>{$service}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Tarih</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600;'>{$date}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Saat</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;font-weight:600;'>{$time}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Tutar</td><td style='padding:8px;border-bottom:1px solid #e2e8f0;color:#16a34a;font-weight:600;'>₺" . number_format($price, 0, ',', '.') . "</td></tr>
            </table>
            <p><a href='" . SITE_URL . "/admin.html' style='display:inline-block;padding:10px 24px;background:#f0a500;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;'>Admin Panele Git</a></p>
        ";
        notifyAdmin("Yeni Randevu: {$fullName} - {$date} {$time}", $adminBody, $email);

        // Müşteriye onay maili
        if ($email) {
            $clientBody = "
                <p>Merhaba <strong>{$fullName}</strong>,</p>
                <p>Randevu talebiniz başarıyla alınmıştır. Detaylar aşağıdadır:</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0;background:#f8fafc;border-radius:8px;'>
                    <tr><td style='padding:10px 16px;color:#64748b;'>Hizmet</td><td style='padding:10px 16px;font-weight:600;'>{$service}</td></tr>
                    <tr><td style='padding:10px 16px;color:#64748b;'>Tarih</td><td style='padding:10px 16px;font-weight:600;'>{$date}</td></tr>
                    <tr><td style='padding:10px 16px;color:#64748b;'>Saat</td><td style='padding:10px 16px;font-weight:600;'>{$time}</td></tr>
                    <tr><td style='padding:10px 16px;color:#64748b;'>Tutar</td><td style='padding:10px 16px;color:#16a34a;font-weight:600;'>₺" . number_format($price, 0, ',', '.') . "</td></tr>
                </table>
                <p>En kısa sürede sizinle iletişime geçeceğiz.</p>
                <br>
                <p>Teşekkürler,<br><strong>TK Danışmanlık</strong></p>
            ";
            sendMail($email, "Randevunuz Alındı - TK Danışmanlık", $clientBody);
        }

        jsonResponse(['success' => true, 'id' => $id, 'message' => 'Randevu kaydedildi'], 201);
        break;

    case 'PUT':
        requireAdmin();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $data = getRequestBody();

        // Status-only update (eski davranis: sadece {status:"..."} gonderilirse)
        $keys = array_keys($data);
        if (count($keys) === 1 && $keys[0] === 'status') {
            if (!in_array($data['status'], ['pending', 'paid', 'cancelled'], true)) {
                jsonResponse(['error' => 'Geçersiz durum'], 400);
            }
            $db->prepare("UPDATE appointments SET status = ? WHERE id = ?")->execute([$data['status'], $id]);
            jsonResponse(['success' => true, 'message' => 'Randevu güncellendi']);
        }

        // Full-field update
        $allowed = [
            'full_name'        => $data['fullName']    ?? null,
            'email'            => $data['email']       ?? null,
            'phone'            => $data['phone']       ?? null,
            'service_type'     => $data['serviceType'] ?? null,
            'appointment_date' => $data['date']        ?? null,
            'appointment_time' => $data['time']        ?? null,
            'message'          => $data['message']     ?? null,
            'price'            => isset($data['price']) && is_numeric($data['price']) ? (float)$data['price'] : null,
            'status'           => (!empty($data['status']) && in_array($data['status'], ['pending','paid','cancelled'], true)) ? $data['status'] : null,
        ];

        $setParts = [];
        $values = [];
        foreach ($allowed as $col => $val) {
            if ($val !== null) {
                $setParts[] = "$col = ?";
                $values[] = $val;
            }
        }
        if (empty($setParts)) {
            jsonResponse(['error' => 'Güncellenecek alan yok'], 400);
        }
        $values[] = $id;
        $sql = 'UPDATE appointments SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $db->prepare($sql)->execute($values);

        jsonResponse(['success' => true, 'message' => 'Randevu güncellendi']);
        break;

    case 'DELETE':
        requireAdmin();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) jsonResponse(['error' => 'ID gerekli'], 400);
        $db->prepare("DELETE FROM appointments WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Randevu silindi']);
        break;

    default:
        jsonResponse(['error' => 'Desteklenmeyen metod'], 405);
}
