<?php
/**
 * TKDanışmanlık - Mail Helper
 */

require_once __DIR__ . '/config.php';

function sendMail($to, $subject, $body, $replyTo = null) {
    $from = 'noreply@tkdanismanlik.com';
    $headers = "From: " . SITE_NAME . " <{$from}>\r\n";
    if ($replyTo) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $htmlBody = getMailTemplate($subject, $body);

    return @mail($to, $encodedSubject, $htmlBody, $headers);
}

function getMailTemplate($title, $content) {
    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:linear-gradient(135deg,#1a1a2e,#16213e);padding:30px;text-align:center;">
            <h1 style="color:#f0a500;margin:0;font-size:24px;">TK<span style="color:#ffffff;">Danışmanlık</span></h1>
        </td>
    </tr>
    <tr>
        <td style="padding:32px;">
            <h2 style="color:#1a1a2e;margin:0 0 20px 0;font-size:20px;">' . $title . '</h2>
            <div style="color:#334155;font-size:15px;line-height:1.7;">' . $content . '</div>
        </td>
    </tr>
    <tr>
        <td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="color:#94a3b8;font-size:12px;margin:0;">&copy; ' . date('Y') . ' TKDanışmanlık.com - Tüm hakları saklıdır.</p>
        </td>
    </tr>
</table>
</td></tr></table>
</body></html>';
}

function notifyAdmin($subject, $body, $replyTo = null) {
    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@tkdanismanlik.com';
    return sendMail($adminEmail, $subject, $body, $replyTo);
}
