<?php
require __DIR__ . '/bootstrap.php';
cc_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    cc_send_json(405, ['error' => 'Method not allowed']);
}

$email = cc_normalize_email((string)($_GET['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    cc_send_json(400, ['error' => 'Enter a valid email address.']);
}

cc_send_json(200, ['subscribed' => cc_newsletter_has($email)]);
