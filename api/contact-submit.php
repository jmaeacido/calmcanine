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
    '<p style="margin:0 0 18px;font-size:15px;line-height:1.65;color:#53675d;">A visitor sent a message through the Calm Canine contact form.</p>'
    . cc_mail_panel('<p style="margin:0 0 8px;font-size:14px;line-height:1.6;"><strong>Name:</strong> ' . cc_mail_escape($name) . '</p>'
    . '<p style="margin:0 0 8px;font-size:14px;line-height:1.6;"><strong>Email:</strong> <a style="color:#29483a;" href="mailto:' . cc_mail_escape($email) . '">' . cc_mail_escape($email) . '</a></p>'
    . '<p style="margin:0;font-size:14px;line-height:1.6;"><strong>Phone:</strong> ' . cc_mail_escape($phone !== '' ? $phone : 'Not provided') . '</p>')
    . '<p style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:.1em;color:#718078;text-transform:uppercase;">Message</p>'
    . '<div style="padding:16px 18px;border-left:3px solid #d8c7a2;background:#fbf8f5;font-size:15px;white-space:pre-wrap;line-height:1.7;color:#253d32;">' . cc_mail_escape($message) . '</div>'
);

// Preserve the submission before notification delivery, matching the Java Lava flow.
cc_email_archive_save([
    'id' => 'contact:' . bin2hex(random_bytes(12)),
    'direction' => 'inbound',
    'mailbox' => 'Contact form',
    'from' => $name . ' <' . $email . '>',
    'to' => 'info@nativeceuticals.com',
    'subject' => $subject,
    'date' => gmdate('c'),
    'preview' => mb_substr($message, 0, 500),
    'status' => 'submitted',
]);

$result = cc_mail_send('info@nativeceuticals.com', $subject, $text, $html, $email);
if (!$result['ok']) {
    error_log('Calm Canine contact email failed: ' . ($result['error'] ?? 'Unknown error'));
    cc_send_json(502, ['error' => 'We could not send your message right now. Please email info@nativeceuticals.com directly.']);
}

$replySubject = 'We received your Calm Canine message';
$replyText = "Hi {$name},\n\nThanks for contacting Calm Canine. We received your message and our team will follow up soon.\n\nYour message:\n{$message}\n\nCalm Canine\n";
$replyHtml = cc_mail_wrap_html('Message received',
    '<p style="margin:0 0 12px;font-size:16px;line-height:1.65;">Hi ' . cc_mail_escape($name) . ',</p>'
    . '<p style="margin:0;font-size:15px;line-height:1.7;color:#53675d;">Thanks for reaching out. Your message is safely with our team, and we will follow up as soon as we can.</p>'
    . cc_mail_panel('<p style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:.1em;color:#718078;text-transform:uppercase;">Your message</p>'
    . '<p style="margin:0;white-space:pre-wrap;font-size:14px;line-height:1.7;color:#253d32;">' . cc_mail_escape($message) . '</p>')
);
$autoReply = cc_mail_send($email, $replySubject, $replyText, $replyHtml, 'info@nativeceuticals.com');
if (!$autoReply['ok']) {
    error_log('Calm Canine contact auto-reply failed: ' . ($autoReply['error'] ?? 'Unknown error'));
}

cc_send_json(200, ['ok' => true, 'message' => 'Thanks — your message has been sent.']);
