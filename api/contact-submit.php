<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

cc_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cc_send_json(405, ['error' => 'Method not allowed.']);
}

$payload = cc_read_body();
$name = trim((string)($payload['name'] ?? ''));
$email = cc_normalize_email((string)($payload['email'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$website = trim((string)($payload['website'] ?? ''));

// Quietly accept bot submissions caught by the hidden honeypot field.
if ($website !== '') {
    cc_send_json(200, ['ok' => true, 'message' => 'Thanks — your message has been sent.']);
}

if ($name === '' || mb_strlen($name) > 120) {
    cc_send_json(400, ['error' => 'Enter your name.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    cc_send_json(400, ['error' => 'Enter a valid email address.']);
}
if (mb_strlen($phone) > 40) {
    cc_send_json(400, ['error' => 'Enter a valid phone number.']);
}
if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    cc_send_json(400, ['error' => 'Enter a message between 10 and 5,000 characters.']);
}

$subject = 'Calm Canine contact form — ' . preg_replace('/[\r\n]+/', ' ', $name);
$text = "New Calm Canine contact message\n\nName: {$name}\nEmail: {$email}\nPhone: " . ($phone !== '' ? $phone : 'Not provided') . "\n\nMessage:\n{$message}\n";
$html = cc_mail_wrap_html('New contact message',
    '<p style="margin:0 0 8px;line-height:1.6;"><strong>Name:</strong> ' . cc_mail_escape($name) . '</p>'
    . '<p style="margin:0 0 8px;line-height:1.6;"><strong>Email:</strong> <a href="mailto:' . cc_mail_escape($email) . '">' . cc_mail_escape($email) . '</a></p>'
    . '<p style="margin:0 0 20px;line-height:1.6;"><strong>Phone:</strong> ' . cc_mail_escape($phone !== '' ? $phone : 'Not provided') . '</p>'
    . '<p style="margin:0;white-space:pre-wrap;line-height:1.6;">' . cc_mail_escape($message) . '</p>'
);

$result = cc_mail_send('info@serum72.com', $subject, $text, $html, $email);
if (!$result['ok']) {
    error_log('Calm Canine contact email failed: ' . ($result['error'] ?? 'Unknown error'));
    cc_send_json(502, ['error' => 'We could not send your message right now. Please email info@serum72.com directly.']);
}

cc_send_json(200, ['ok' => true, 'message' => 'Thanks — your message has been sent.']);
