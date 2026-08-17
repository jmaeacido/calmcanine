<?php
declare(strict_types=1);

function cc_root(): string {
    return dirname(__DIR__);
}

function cc_read_json(string $path): array {
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function cc_send_json(int $status, array $body): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function cc_cors(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

function cc_catalog(): array {
    static $catalog = null;
    if ($catalog === null) {
        $catalog = cc_read_json(cc_root() . '/api/config/catalog.json');
    }
    return $catalog;
}

function cc_tax_config(): array {
    static $tax = null;
    if ($tax === null) {
        $tax = cc_read_json(cc_root() . '/api/config/tax-rates.json');
    }
    return $tax;
}

function cc_round_money(float $amount): float {
    return round($amount, 2);
}

function cc_normalize_item(array $item): array {
    $catalog = cc_catalog();
    $product = $catalog['products'][$item['productId'] ?? ''] ?? $catalog['products']['calm-canine'];
    $purchaseType = ($item['purchaseType'] ?? '') === 'subscribe' ? 'subscribe' : 'onetime';
    $quantity = max(1, min((int)($catalog['maxQuantity'] ?? 10), (int)($item['quantity'] ?? 1)));

    return [
        'productId' => $product['id'],
        'name' => $product['name'],
        'sku' => $product['sku'],
        'image' => $product['image'],
        'purchaseType' => $purchaseType,
        'deliveryPlan' => $item['deliveryPlan'] ?? '1_month',
        'quantity' => $quantity,
        'unitPrice' => $product['prices'][$purchaseType],
        'taxable' => $product['taxable'] ?? true,
    ];
}

function cc_line_total(array $item): float {
    return cc_round_money($item['unitPrice'] * $item['quantity']);
}

function cc_calc_shipping(float $subtotal): float {
    $shipping = cc_catalog()['shipping'];
    if ($subtotal <= 0) return 0.0;
    return $subtotal >= $shipping['freeShippingMin'] ? 0.0 : (float)$shipping['flatRate'];
}

function cc_calc_tax(float $subtotal, string $state, array $items): float {
    $taxConfig = cc_tax_config();
    $code = strtoupper(trim($state));
    $rate = $taxConfig['rates'][$code] ?? $taxConfig['defaultRate'] ?? 0;
    $taxable = 0.0;
    foreach ($items as $item) {
        if (($item['taxable'] ?? true) !== false) {
            $taxable += cc_line_total($item);
        }
    }
    return cc_round_money($taxable * $rate);
}

function cc_build_quote(array $items, string $state): array {
    $normalized = array_map('cc_normalize_item', $items);
    $subtotal = cc_round_money(array_reduce($normalized, fn($sum, $item) => $sum + cc_line_total($item), 0.0));
    $shippingCost = cc_round_money(cc_calc_shipping($subtotal));
    $tax = cc_calc_tax($subtotal, $state, $normalized);
    $total = cc_round_money($subtotal + $shippingCost + $tax);

    $lines = array_map(function ($item) {
        $item['lineTotal'] = cc_line_total($item);
        return $item;
    }, $normalized);

    return compact('lines', 'subtotal', 'shippingCost', 'tax', 'total') + ['items' => $lines];
}

function cc_ensure_dir(string $segment): string {
    $dir = cc_root() . '/data/' . $segment;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function cc_save_order(array $order): array {
    $path = cc_ensure_dir('orders') . '/' . $order['id'] . '.json';
    file_put_contents($path, json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $order;
}

function cc_get_order(string $orderId): ?array {
    $path = cc_ensure_dir('orders') . '/' . $orderId . '.json';
    if (!is_file($path)) return null;
    $order = json_decode(file_get_contents($path), true);
    return is_array($order) ? $order : null;
}

function cc_generate_order_id(): string {
    return 'CC-' . strtoupper(base_convert((string)time(), 10, 36));
}

function cc_validate_payload(array $payload): string {
    if (empty($payload['items']) || !is_array($payload['items'])) return 'Cart is empty.';

    $email = trim($payload['customer']['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';

    $phone = trim($payload['customer']['phone'] ?? '');
    if (!preg_match('/^[\d\s().+-]{7,20}$/', $phone)) return 'Enter a valid phone number.';

    $name = trim($payload['customer']['name'] ?? '');
    if (strlen($name) < 2) return 'Enter your full name.';

    $shipping = $payload['shipping'] ?? [];
    if (strlen(trim($shipping['address1'] ?? '')) < 4) return 'Enter a valid street address.';
    if (!trim($shipping['city'] ?? '')) return 'Enter a city.';
    $state = strtoupper(trim($shipping['state'] ?? ''));
    if (strlen($state) !== 2) return 'Select a state.';
    if (!preg_match('/^\d{5}(-\d{4})?$/', trim($shipping['zip'] ?? ''))) return 'Enter a valid ZIP code.';

    if (in_array($state, cc_catalog()['restrictedStates'], true)) {
        return 'We cannot ship CBD products to your state.';
    }

    $last4 = trim($payload['paymentMethod']['last4'] ?? '');
    if (!preg_match('/^\d{4}$/', $last4)) return 'Enter a valid card number.';

    if (empty($payload['acceptTerms'])) return 'Please accept the terms to place your order.';

    return '';
}

function cc_process_payment_stub(array $order, array $paymentMethod): array {
    return [
        'provider' => 'stub',
        'status' => 'authorized_stub',
        'reference' => 'STUB-' . strtoupper(base_convert((string)time(), 10, 36)),
        'brand' => $paymentMethod['brand'] ?? 'Card',
        'last4' => $paymentMethod['last4'] ?? '0000',
    ];
}

function cc_queue_email(array $order, string $suffix = ''): void {
    $dir = cc_ensure_dir('email-queue');
    $payload = [
        'type' => $suffix === '-ops' ? 'ops_new_order' : 'order_confirmation',
        'queuedAt' => gmdate('c'),
        'to' => $order['customer']['email'],
        'orderId' => $order['id'],
    ];
    file_put_contents($dir . '/' . $order['id'] . $suffix . '.json', json_encode($payload, JSON_PRETTY_PRINT));
}

function cc_enqueue_fulfillment(array $order): void {
    $entry = [
        'orderId' => $order['id'],
        'createdAt' => $order['createdAt'],
        'status' => 'pending',
        'customer' => [
            'name' => $order['customer']['name'],
            'email' => $order['customer']['email'],
        ],
        'shipping' => $order['shipping'],
        'items' => array_map(fn($item) => [
            'sku' => $item['sku'],
            'name' => $item['name'],
            'quantity' => $item['quantity'],
            'purchaseType' => $item['purchaseType'],
            'deliveryPlan' => $item['deliveryPlan'] ?? '1_month',
        ], $order['items']),
        'subscriptions' => $order['subscriptions'] ?? [],
        'totals' => [
            'subtotal' => $order['subtotal'],
            'shippingCost' => $order['shippingCost'],
            'tax' => $order['tax'],
            'total' => $order['total'],
        ],
    ];
    file_put_contents(cc_ensure_dir('fulfillment') . '/queue.jsonl', json_encode($entry) . PHP_EOL, FILE_APPEND);
}

function cc_public_order(array $order): array {
    return [
        'id' => $order['id'],
        'createdAt' => $order['createdAt'],
        'status' => $order['status'],
        'items' => $order['items'],
        'customer' => ['email' => $order['customer']['email'], 'name' => $order['customer']['name']],
        'shipping' => $order['shipping'],
        'subtotal' => $order['subtotal'],
        'shippingCost' => $order['shippingCost'],
        'tax' => $order['tax'],
        'total' => $order['total'],
        'subscriptions' => $order['subscriptions'] ?? [],
        'payment' => [
            'brand' => $order['payment']['brand'],
            'last4' => $order['payment']['last4'],
            'provider' => $order['payment']['provider'],
            'status' => $order['payment']['status'],
        ],
        'email' => [
            'sent' => false,
            'queued' => true,
        ],
    ];
}

function cc_read_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cc_create_order(array $payload): array {
    $error = cc_validate_payload($payload);
    if ($error !== '') {
        cc_send_json(400, ['error' => $error]);
    }

    $quote = cc_build_quote($payload['items'], $payload['shipping']['state'] ?? '');
    $orderId = cc_generate_order_id();
    $subscriptions = array_values(array_map(fn($item) => [
        'productId' => $item['productId'],
        'sku' => $item['sku'],
        'plan' => $item['deliveryPlan'] ?? '1_month',
        'quantity' => $item['quantity'],
        'unitPrice' => $item['unitPrice'],
        'status' => 'pending_activation',
    ], array_filter($quote['items'], fn($item) => $item['purchaseType'] === 'subscribe')));

    $order = [
        'id' => $orderId,
        'createdAt' => gmdate('c'),
        'status' => 'confirmed',
        'items' => $quote['items'],
        'customer' => [
            'email' => trim($payload['customer']['email']),
            'phone' => trim($payload['customer']['phone']),
            'name' => trim($payload['customer']['name']),
        ],
        'shipping' => [
            'name' => trim($payload['shipping']['name']),
            'address1' => trim($payload['shipping']['address1']),
            'address2' => trim($payload['shipping']['address2'] ?? ''),
            'city' => trim($payload['shipping']['city']),
            'state' => strtoupper(trim($payload['shipping']['state'])),
            'zip' => trim($payload['shipping']['zip']),
        ],
        'subtotal' => $quote['subtotal'],
        'shippingCost' => $quote['shippingCost'],
        'tax' => $quote['tax'],
        'total' => $quote['total'],
        'subscriptions' => $subscriptions,
        'payment' => cc_process_payment_stub([], $payload['paymentMethod']),
        'email' => ['customer' => ['sent' => false, 'queued' => true], 'ops' => ['sent' => false, 'queued' => true]],
        'fulfillment' => ['status' => 'pending'],
    ];

    cc_queue_email($order);
    cc_queue_email($order, '-ops');
    cc_enqueue_fulfillment($order);
    cc_save_order($order);

    return cc_public_order($order);
}
