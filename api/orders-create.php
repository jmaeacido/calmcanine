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
$order = cc_create_order($body);
cc_send_json(201, ['order' => $order]);
