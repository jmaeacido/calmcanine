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

cc_require_admin();

$orderId = $_GET['id'] ?? '';
$kind = $_GET['kind'] ?? '';
$action = $_GET['action'] ?? '';

if ($orderId === '' || $kind === '' || $action === '') {
    cc_send_json(400, ['error' => 'Order ID, email kind, and action are required.']);
}

if (cc_email_kind_suffix($kind) === null) {
    cc_send_json(400, ['error' => 'Email kind must be customer or ops.']);
}

if ($action === 'mark-sent') {
    $order = cc_mark_email_sent($orderId, $kind);
} elseif ($action === 'requeue') {
    $order = cc_requeue_email($orderId, $kind);
} else {
    cc_send_json(400, ['error' => 'Action must be mark-sent or requeue.']);
}

if ($order === null) {
    cc_send_json(404, ['error' => 'Order not found.']);
}

cc_send_json(200, ['order' => cc_admin_order($order)]);
