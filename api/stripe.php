<?php
declare(strict_types=1);

function cc_stripe_key(): string {
    return (string)(getenv('STRIPE_SECRET_KEY') ?: '');
}

function cc_stripe_request(string $method, string $path, array $params = []): array {
    $key = cc_stripe_key();
    if ($key === '' || !str_starts_with($key, 'sk_')) {
        throw new RuntimeException('Stripe is not configured.');
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    if ($method === 'GET' && $params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => $key . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Stripe connection failed: ' . $curlError);
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Stripe returned an invalid response.');
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException((string)($data['error']['message'] ?? 'Stripe request failed.'));
    }
    return $data;
}

function cc_site_url(): string {
    $configured = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    if ($configured !== '') return $configured;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api'));
    $base = preg_replace('#/api$#', '', $script) ?: '';
    return $scheme . '://' . $host . $base;
}

function cc_checkout_dir(): string {
    return cc_ensure_dir('checkouts');
}

function cc_checkout_path(string $token): string {
    return cc_checkout_dir() . '/' . $token . '.json';
}

function cc_checkout_session(array $payload): array {
    $error = cc_validate_payload($payload, false);
    if ($error !== '') cc_send_json(400, ['error' => $error]);

    $quote = cc_build_quote($payload['items'], $payload['shipping']['state'] ?? '');
    $hasSubscription = count(array_filter($quote['items'], fn($item) => $item['purchaseType'] === 'subscribe')) > 0;
    $token = bin2hex(random_bytes(20));
    $params = [
        'mode' => $hasSubscription ? 'subscription' : 'payment',
        'success_url' => cc_site_url() . '/order?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => cc_site_url() . '/checkout?payment=cancelled',
        'customer_email' => trim($payload['customer']['email']),
        'client_reference_id' => $token,
        'metadata' => ['checkout_token' => $token],
        'payment_method_types' => ['card'],
    ];

    $line = 0;
    foreach ($quote['items'] as $item) {
        $priceData = [
            'currency' => 'usd',
            'unit_amount' => (int)round($item['unitPrice'] * 100),
            'product_data' => [
                'name' => $item['name'],
                'metadata' => ['sku' => $item['sku'], 'purchase_type' => $item['purchaseType']],
            ],
        ];
        if ($item['purchaseType'] === 'subscribe') $priceData['recurring'] = ['interval' => 'month'];
        $params['line_items'][$line++] = ['quantity' => $item['quantity'], 'price_data' => $priceData];
    }
    foreach ([['Shipping', $quote['shippingCost']], ['Estimated tax', $quote['tax']]] as [$name, $amount]) {
        if ($amount <= 0) continue;
        $params['line_items'][$line++] = [
            'quantity' => 1,
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => (int)round($amount * 100),
                'product_data' => ['name' => $name],
            ],
        ];
    }

    file_put_contents(cc_checkout_path($token), json_encode([
        'createdAt' => gmdate('c'),
        'payload' => $payload,
        'quote' => $quote,
        'orderId' => null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    try {
        $session = cc_stripe_request('POST', 'checkout/sessions', $params);
    } catch (Throwable $e) {
        @unlink(cc_checkout_path($token));
        throw $e;
    }
    return ['url' => $session['url'], 'sessionId' => $session['id']];
}

function cc_complete_checkout(string $sessionId): array {
    if (!preg_match('/^cs_(test_|live_)?[A-Za-z0-9_]+$/', $sessionId)) {
        cc_send_json(400, ['error' => 'Invalid checkout session.']);
    }
    $session = cc_stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId), ['expand' => ['payment_intent', 'subscription']]);
    if (($session['status'] ?? '') !== 'complete' || ($session['payment_status'] ?? '') !== 'paid') {
        cc_send_json(409, ['error' => 'Payment has not been completed.']);
    }
    $token = (string)($session['metadata']['checkout_token'] ?? $session['client_reference_id'] ?? '');
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) cc_send_json(400, ['error' => 'Checkout record not found.']);
    $path = cc_checkout_path($token);
    $fp = fopen($path, 'c+');
    if ($fp === false || !flock($fp, LOCK_EX)) cc_send_json(500, ['error' => 'Unable to finalize checkout.']);
    $record = json_decode(stream_get_contents($fp) ?: '', true);
    if (!is_array($record)) { flock($fp, LOCK_UN); fclose($fp); cc_send_json(404, ['error' => 'Checkout record not found.']); }
    if (!empty($record['orderId'])) {
        $existing = cc_get_order((string)$record['orderId']);
        flock($fp, LOCK_UN); fclose($fp);
        if ($existing) return cc_public_order($existing);
    }
    $payment = [
        'provider' => 'stripe',
        'status' => 'paid',
        'reference' => $sessionId,
        'brand' => 'Card',
        'last4' => '••••',
        'subscriptionId' => is_array($session['subscription'] ?? null) ? ($session['subscription']['id'] ?? null) : ($session['subscription'] ?? null),
    ];
    $order = cc_create_order($record['payload'], $payment);
    $record['orderId'] = $order['id'];
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $order;
}
