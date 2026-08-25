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

function cc_stripe_card_summary(array $session): array {
    $paymentMethod = null;
    $intent = $session['payment_intent'] ?? null;
    if (is_array($intent)) $paymentMethod = $intent['payment_method'] ?? null;

    $subscription = $session['subscription'] ?? null;
    if ($paymentMethod === null && is_array($subscription)) {
        $paymentMethod = $subscription['default_payment_method'] ?? null;
    }

    if (is_string($paymentMethod) && str_starts_with($paymentMethod, 'pm_')) {
        $paymentMethod = cc_stripe_request('GET', 'payment_methods/' . rawurlencode($paymentMethod));
    }

    $card = is_array($paymentMethod) ? ($paymentMethod['card'] ?? null) : null;
    if (!is_array($card)) return ['brand' => 'Card', 'last4' => '----'];

    $brand = trim((string)($card['brand'] ?? ''));
    $last4 = trim((string)($card['last4'] ?? ''));
    return [
        'brand' => $brand !== '' ? ucfirst($brand) : 'Card',
        'last4' => preg_match('/^\d{4}$/', $last4) ? $last4 : '----',
    ];
}

function cc_checkout_dir(): string {
    return cc_ensure_dir('checkouts');
}

function cc_checkout_path(string $token): string {
    return cc_checkout_dir() . '/' . $token . '.json';
}

function cc_quote_amount_cents(array $quote): int {
    return (int)round(((float)($quote['total'] ?? 0)) * 100);
}

function cc_stripe_file_upload(string $absolutePath, string $purpose = 'product_image'): array {
    $key = cc_stripe_key();
    if ($key === '' || !str_starts_with($key, 'sk_')) {
        throw new RuntimeException('Stripe is not configured.');
    }
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Product image file is missing.');
    }

    $mime = mime_content_type($absolutePath) ?: 'image/png';
    $ch = curl_init('https://files.stripe.com/v1/files');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $key . ':',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'purpose' => $purpose,
            'file' => new CURLFile($absolutePath, $mime, basename($absolutePath)),
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Stripe file upload failed: ' . $curlError);
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Stripe returned an invalid file response.');
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException((string)($data['error']['message'] ?? 'Stripe file upload failed.'));
    }
    return $data;
}

function cc_stripe_catalog_image_url(array $item): ?string {
    $relative = str_replace('\\', '/', (string)($item['image'] ?? ''));
    $sku = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($item['sku'] ?? '')) ?: 'product';
    if ($relative === '' || str_contains($relative, '..')) return null;

    $absolute = cc_root() . '/' . ltrim($relative, '/');
    if (!is_file($absolute)) return null;

    $mtime = (string)filemtime($absolute);
    $cachePath = cc_ensure_dir('stripe/product-images') . '/' . $sku . '.json';
    if (is_file($cachePath)) {
        $cached = json_decode((string)file_get_contents($cachePath), true);
        if (
            is_array($cached)
            && ($cached['mtime'] ?? '') === $mtime
            && is_string($cached['url'] ?? null)
            && str_starts_with($cached['url'], 'https://')
        ) {
            return $cached['url'];
        }
    }

    try {
        $file = cc_stripe_file_upload($absolute);
        $link = cc_stripe_request('POST', 'file_links', ['file' => $file['id']]);
        $url = (string)($link['url'] ?? '');
        if ($url === '' || !str_starts_with($url, 'https://')) return null;
        file_put_contents($cachePath, json_encode([
            'sku' => $item['sku'] ?? $sku,
            'fileId' => $file['id'] ?? null,
            'mtime' => $mtime,
            'url' => $url,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $url;
    } catch (Throwable) {
        return null;
    }
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
        $imageUrl = cc_stripe_catalog_image_url($item);
        if ($imageUrl !== null) $priceData['product_data']['images'] = [$imageUrl];
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

    $record = [
        'createdAt' => gmdate('c'),
        'payload' => $payload,
        'quote' => $quote,
        'expectedAmount' => cc_quote_amount_cents($quote),
        'stripeSessionId' => null,
        'orderId' => null,
    ];
    file_put_contents(cc_checkout_path($token), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    try {
        $session = cc_stripe_request('POST', 'checkout/sessions', $params);
    } catch (Throwable $e) {
        @unlink(cc_checkout_path($token));
        throw $e;
    }

    $record['stripeSessionId'] = $session['id'] ?? null;
    file_put_contents(cc_checkout_path($token), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return ['url' => $session['url'], 'sessionId' => $session['id']];
}

function cc_complete_checkout(string $sessionId): array {
    if (!preg_match('/^cs_(test_|live_)?[A-Za-z0-9_]+$/', $sessionId)) {
        cc_send_json(400, ['error' => 'Invalid checkout session.']);
    }
    $session = cc_stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId), [
        'expand' => ['payment_intent.payment_method', 'subscription.default_payment_method'],
    ]);
    if (($session['status'] ?? '') !== 'complete' || ($session['payment_status'] ?? '') !== 'paid') {
        cc_send_json(409, ['error' => 'Payment has not been completed.']);
    }
    $token = (string)($session['metadata']['checkout_token'] ?? $session['client_reference_id'] ?? '');
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) cc_send_json(400, ['error' => 'Checkout record not found.']);
    $path = cc_checkout_path($token);
    if (!is_file($path)) cc_send_json(404, ['error' => 'Checkout record not found.']);
    $fp = fopen($path, 'c+');
    if ($fp === false || !flock($fp, LOCK_EX)) cc_send_json(500, ['error' => 'Unable to finalize checkout.']);
    $record = json_decode(stream_get_contents($fp) ?: '', true);
    if (!is_array($record)) { flock($fp, LOCK_UN); fclose($fp); cc_send_json(404, ['error' => 'Checkout record not found.']); }
    if (!empty($record['orderId'])) {
        $existing = cc_get_order((string)$record['orderId']);
        flock($fp, LOCK_UN); fclose($fp);
        if ($existing) return cc_public_order($existing);
    }

    $expected = (int)($record['expectedAmount'] ?? cc_quote_amount_cents($record['quote'] ?? []));
    $paid = (int)($session['amount_total'] ?? -1);
    if ($expected <= 0 || $paid !== $expected) {
        flock($fp, LOCK_UN); fclose($fp);
        cc_send_json(409, ['error' => 'Paid amount does not match the checkout total.']);
    }

    $card = cc_stripe_card_summary($session);
    $subscriptionId = is_array($session['subscription'] ?? null)
        ? ($session['subscription']['id'] ?? null)
        : ($session['subscription'] ?? null);
    $payment = [
        'provider' => 'stripe',
        'status' => 'paid',
        'reference' => $sessionId,
        'brand' => $card['brand'],
        'last4' => $card['last4'],
        'subscriptionId' => $subscriptionId,
    ];
    $order = cc_create_order($record['payload'], $payment, $record['quote'] ?? null);
    $record['orderId'] = $order['id'];
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $order;
}

function cc_stripe_verify_webhook(string $payload, string $header, string $secret): bool {
    if ($secret === '' || $payload === '' || $header === '') return false;
    $parts = [];
    foreach (explode(',', $header) as $item) {
        [$k, $v] = array_pad(explode('=', trim($item), 2), 2, '');
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? '';
    if (!preg_match('/^\d+$/', $timestamp)) return false;
    if (abs(time() - (int)$timestamp) > 300) return false;
    $signed = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    foreach ($parts['v1'] ?? [] as $signature) {
        if (hash_equals($expected, $signature)) return true;
    }
    return false;
}

function cc_checkout_session_id_for_subscription(string $subscriptionId): ?string {
    $list = cc_stripe_request('GET', 'checkout/sessions', [
        'subscription' => $subscriptionId,
        'limit' => 1,
    ]);
    $id = $list['data'][0]['id'] ?? '';
    return is_string($id) && $id !== '' ? $id : null;
}

function cc_handle_paid_invoice(array $invoice): void {
    $invoiceId = (string)($invoice['id'] ?? '');
    if ($invoiceId === '' || ($invoice['status'] ?? '') !== 'paid') return;
    if (cc_stripe_invoice_processed($invoiceId)) return;

    $subscriptionId = $invoice['subscription'] ?? null;
    if (is_array($subscriptionId)) $subscriptionId = $subscriptionId['id'] ?? null;
    $subscriptionId = is_string($subscriptionId) ? $subscriptionId : '';
    if ($subscriptionId === '') return;

    $reason = (string)($invoice['billing_reason'] ?? '');
    if ($reason === 'subscription_create') {
        $sessionId = cc_checkout_session_id_for_subscription($subscriptionId);
        if ($sessionId) {
            $order = cc_complete_checkout($sessionId);
            cc_mark_stripe_invoice($invoiceId, (string)$order['id'], 'initial');
        }
        return;
    }

    if ($reason !== 'subscription_cycle' && $reason !== 'subscription_update') return;

    $orderId = cc_order_id_for_subscription($subscriptionId);
    if ($orderId === null) {
        $sessionId = cc_checkout_session_id_for_subscription($subscriptionId);
        if ($sessionId) {
            $order = cc_complete_checkout($sessionId);
            cc_mark_stripe_invoice($invoiceId, (string)$order['id'], 'initial');
        }
        return;
    }

    $order = cc_get_order($orderId);
    if ($order === null) return;

    $subscribeItems = array_values(array_filter($order['items'] ?? [], fn($item) => ($item['purchaseType'] ?? '') === 'subscribe'));
    $amount = cc_round_money(((int)($invoice['amount_paid'] ?? 0)) / 100);
    $renewal = [
        'invoiceId' => $invoiceId,
        'paidAt' => gmdate('c'),
        'amount' => $amount,
        'billingReason' => $reason,
    ];
    $order['renewals'] = array_values(array_merge($order['renewals'] ?? [], [$renewal]));
    foreach ($order['subscriptions'] as $i => $sub) {
        $order['subscriptions'][$i]['status'] = 'active';
        $order['subscriptions'][$i]['lastRenewedAt'] = $renewal['paidAt'];
        $order['subscriptions'][$i]['stripeSubscriptionId'] = $subscriptionId;
    }
    cc_enqueue_fulfillment($order, [
        'kind' => 'renewal',
        'invoiceId' => $invoiceId,
        'createdAt' => $renewal['paidAt'],
        'items' => $subscribeItems,
        'totals' => [
            'subtotal' => $amount,
            'shippingCost' => 0,
            'tax' => 0,
            'total' => $amount,
        ],
    ]);
    cc_save_order($order);
    cc_mark_stripe_invoice($invoiceId, $orderId, 'renewal');
}

function cc_handle_stripe_event(array $event): void {
    $type = (string)($event['type'] ?? '');
    $object = $event['data']['object'] ?? [];
    if (!is_array($object)) return;

    if ($type === 'checkout.session.completed') {
        $sessionId = (string)($object['id'] ?? '');
        if ($sessionId !== '') cc_complete_checkout($sessionId);
        return;
    }

    if ($type === 'invoice.paid') {
        cc_handle_paid_invoice($object);
    }
}
