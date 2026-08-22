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

cc_require_admin();

$orderId = $_GET['id'] ?? '';
if ($orderId === '') {
    cc_send_json(400, ['error' => 'Order ID is required.']);
}

$order = cc_get_order($orderId);
if ($order === null) {
    cc_send_json(404, ['error' => 'Order not found.']);
}

cc_send_json(200, ['order' => cc_admin_order($order)]);
