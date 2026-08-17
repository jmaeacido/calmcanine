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
$quote = cc_build_quote($body['items'] ?? [], $body['state'] ?? '');
cc_send_json(200, [
    'items' => $quote['items'],
    'subtotal' => $quote['subtotal'],
    'shippingCost' => $quote['shippingCost'],
    'tax' => $quote['tax'],
    'total' => $quote['total'],
]);
