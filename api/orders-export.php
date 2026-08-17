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

$expected = getenv('FULFILLMENT_EXPORT_KEY') ?: '';
if ($expected !== '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearer = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
    $queryKey = $_GET['key'] ?? '';
    if ($bearer !== $expected && $queryKey !== $expected) {
        cc_send_json(401, ['error' => 'Unauthorized.']);
    }
}

$format = strtolower($_GET['format'] ?? 'json');
$file = cc_ensure_dir('fulfillment') . '/queue.jsonl';
$orders = [];

if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) $orders[] = $decoded;
    }
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=fulfillment-export.csv');
    $headers = ['orderId','createdAt','status','customerName','customerEmail','shipName','shipAddress1','shipAddress2','shipCity','shipState','shipZip','sku','productName','quantity','purchaseType','deliveryPlan','subtotal','shipping','tax','total'];
    echo implode(',', $headers) . PHP_EOL;
    foreach ($orders as $order) {
        foreach ($order['items'] as $item) {
            echo implode(',', [
                $order['orderId'],
                $order['createdAt'],
                $order['status'],
                '"' . str_replace('"', '""', $order['customer']['name']) . '"',
                $order['customer']['email'],
                '"' . str_replace('"', '""', $order['shipping']['name']) . '"',
                '"' . str_replace('"', '""', $order['shipping']['address1']) . '"',
                '"' . str_replace('"', '""', $order['shipping']['address2'] ?? '') . '"',
                $order['shipping']['city'],
                $order['shipping']['state'],
                $order['shipping']['zip'],
                $item['sku'],
                '"' . str_replace('"', '""', $item['name']) . '"',
                $item['quantity'],
                $item['purchaseType'],
                $item['deliveryPlan'] ?? '',
                $order['totals']['subtotal'],
                $order['totals']['shippingCost'],
                $order['totals']['tax'],
                $order['totals']['total'],
            ]) . PHP_EOL;
        }
    }
    exit;
}

cc_send_json(200, ['orders' => $orders]);
