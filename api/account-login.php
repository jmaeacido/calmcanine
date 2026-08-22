<?php
require __DIR__ . '/bootstrap.php';
cc_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cc_send_json(405, ['error' => 'Method not allowed']);
}

$body = cc_read_body();
$result = cc_account_login((string)($body['email'] ?? ''), (string)($body['password'] ?? ''));
if (!$result['ok']) {
    cc_send_json((int)$result['status'], ['error' => $result['error']]);
}

cc_send_json(200, ['authenticated' => true, 'user' => $result['user']]);
