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

$orders = array_map('cc_admin_order_summary', cc_list_orders());
cc_send_json(200, ['orders' => $orders]);
