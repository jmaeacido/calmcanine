<?php
declare(strict_types=1);

function cc_root(): string {
    return dirname(__DIR__);
}

function cc_load_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $path = cc_root() . '/.env';
    if (!is_file($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') continue;
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

cc_load_env();

function cc_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('cc_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cc_admin_logged_in(): bool {
    cc_session_start();
    return !empty($_SESSION['cc_admin_authenticated']);
}

function cc_require_admin(): void {
    if (!cc_admin_logged_in()) {
        cc_send_json(401, ['error' => 'Unauthorized.']);
    }
}

function cc_admin_login(string $password): array {
    $expected = (string)(getenv('ADMIN_PASSWORD') ?: '');
    if ($expected === '') {
        return ['ok' => false, 'error' => 'Admin password is not configured. Set ADMIN_PASSWORD in .env.', 'status' => 503];
    }
    if (!hash_equals($expected, $password)) {
        return ['ok' => false, 'error' => 'Invalid password.', 'status' => 401];
    }

    cc_session_start();
    session_regenerate_id(true);
    $_SESSION['cc_admin_authenticated'] = true;
    return ['ok' => true];
}

function cc_clear_session_cookie(): void {
    if (!ini_get('session.use_cookies')) return;
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)$params['secure'],
        'httponly' => (bool)$params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

function cc_admin_logout(): void {
    cc_session_start();
    $_SESSION = [];
    cc_clear_session_cookie();
    session_destroy();
}

function cc_customer_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('cc_customer');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cc_normalize_email(string $email): string {
    return strtolower(trim($email));
}

function cc_users_dir(): string {
    return cc_ensure_dir('users');
}

function cc_user_path(string $userId): string {
    return cc_users_dir() . '/' . $userId . '.json';
}

function cc_user_index_path(): string {
    return cc_users_dir() . '/index.json';
}

function cc_read_user_index(): array {
    $path = cc_user_index_path();
    if (!is_file($path)) return [];
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function cc_write_user_index(array $index): void {
    file_put_contents(
        cc_user_index_path(),
        json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function cc_get_user(string $userId): ?array {
    if (!preg_match('/^CU-[A-Z0-9]+$/', $userId)) return null;
    $path = cc_user_path($userId);
    if (!is_file($path)) return null;
    $user = json_decode((string)file_get_contents($path), true);
    return is_array($user) && !empty($user['id']) ? $user : null;
}

function cc_newsletter_file(): string {
    return cc_ensure_dir('newsletter') . '/subscribers.json';
}

function cc_newsletter_list(): array {
    $path = cc_newsletter_file();
    if (!is_file($path)) return [];
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function cc_newsletter_has(string $email): bool {
    $email = cc_normalize_email($email);
    if ($email === '') return false;
    foreach (cc_newsletter_list() as $row) {
        if (cc_normalize_email((string)($row['email'] ?? '')) === $email) {
            return true;
        }
    }
    return false;
}

function cc_newsletter_subscribe(string $email, string $name = '', string $source = ''): array {
    $email = cc_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.', 'status' => 400];
    }

    $list = cc_newsletter_list();
    $now = gmdate('c');
    $found = false;
    foreach ($list as &$row) {
        if (cc_normalize_email((string)($row['email'] ?? '')) === $email) {
            $row['name'] = $name !== '' ? $name : ($row['name'] ?? '');
            $row['source'] = $source !== '' ? $source : ($row['source'] ?? '');
            $row['updatedAt'] = $now;
            $found = true;
            break;
        }
    }
    unset($row);

    if (!$found) {
        $list[] = [
            'email' => $email,
            'name' => $name,
            'source' => $source,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    file_put_contents(cc_newsletter_file(), json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $userId = cc_find_user_id_by_email($email);
    $sessionUser = cc_customer_user();
    $targetId = $userId ?? ($sessionUser['id'] ?? null);
    if ($targetId) {
        $user = cc_get_user($targetId);
        if ($user !== null) {
            $user['newsletter'] = true;
            $user['updatedAt'] = $now;
            cc_save_user($user);
        }
    }

    return ['ok' => true, 'subscribed' => true];
}

function cc_save_user(array $user): array {
    file_put_contents(
        cc_user_path($user['id']),
        json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $user;
}

function cc_find_user_id_by_email(string $email): ?string {
    $id = cc_read_user_index()[cc_normalize_email($email)] ?? null;
    return is_string($id) && $id !== '' ? $id : null;
}

function cc_public_user(array $user): array {
    return [
        'id' => $user['id'],
        'email' => $user['email'] ?? '',
        'name' => $user['name'] ?? '',
        'phone' => $user['phone'] ?? '',
        'newsletter' => !empty($user['newsletter']),
        'shipping' => [
            'address1' => $user['shipping']['address1'] ?? '',
            'address2' => $user['shipping']['address2'] ?? '',
            'city' => $user['shipping']['city'] ?? '',
            'state' => $user['shipping']['state'] ?? '',
            'zip' => $user['shipping']['zip'] ?? '',
        ],
    ];
}

function cc_customer_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['cc_customer'])) {
        return null;
    }
    cc_customer_session_start();
    $id = (string)($_SESSION['cc_customer_id'] ?? '');
    if ($id === '') return null;
    return cc_get_user($id);
}

function cc_customer_logged_in(): bool {
    return cc_customer_user() !== null;
}

function cc_require_customer(): array {
    $user = cc_customer_user();
    if ($user === null) {
        cc_send_json(401, ['error' => 'Sign in to continue.']);
    }
    return $user;
}

function cc_customer_login_user(array $user): void {
    cc_customer_session_start();
    session_regenerate_id(true);
    $_SESSION['cc_customer_id'] = $user['id'];
}

function cc_customer_logout(): void {
    cc_customer_session_start();
    $_SESSION = [];
    cc_clear_session_cookie();
    session_destroy();
}

function cc_generate_user_id(): string {
    return 'CU-' . strtoupper(bin2hex(random_bytes(6)));
}

function cc_validate_password(string $password): string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    return '';
}

function cc_account_register(array $payload): array {
    $email = cc_normalize_email((string)($payload['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.', 'status' => 400];
    }

    $password = (string)($payload['password'] ?? '');
    $passwordError = cc_validate_password($password);
    if ($passwordError !== '') {
        return ['ok' => false, 'error' => $passwordError, 'status' => 400];
    }

    if (cc_find_user_id_by_email($email) !== null) {
        return ['ok' => false, 'error' => 'An account with that email already exists. Sign in instead.', 'status' => 409];
    }

    $userId = cc_generate_user_id();
    $user = [
        'id' => $userId,
        'email' => $email,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => trim((string)($payload['name'] ?? '')),
        'phone' => trim((string)($payload['phone'] ?? '')),
        'shipping' => [
            'address1' => trim((string)($payload['shipping']['address1'] ?? '')),
            'address2' => trim((string)($payload['shipping']['address2'] ?? '')),
            'city' => trim((string)($payload['shipping']['city'] ?? '')),
            'state' => strtoupper(trim((string)($payload['shipping']['state'] ?? ''))),
            'zip' => trim((string)($payload['shipping']['zip'] ?? '')),
        ],
        'orderIds' => [],
        'newsletter' => false,
        'createdAt' => gmdate('c'),
        'updatedAt' => gmdate('c'),
    ];

    $index = cc_read_user_index();
    if (isset($index[$email])) {
        return ['ok' => false, 'error' => 'An account with that email already exists. Sign in instead.', 'status' => 409];
    }
    $index[$email] = $userId;
    cc_save_user($user);
    cc_write_user_index($index);
    cc_customer_login_user($user);
    if (!empty($payload['newsletter']) || cc_newsletter_has($email)) {
        cc_newsletter_subscribe($email, $user['name'] ?? '', 'register');
        $user = cc_get_user($userId) ?? $user;
    }
    cc_send_welcome_email($user);

    return ['ok' => true, 'user' => cc_public_user($user)];
}

function cc_account_login(string $email, string $password): array {
    $email = cc_normalize_email($email);
    $userId = cc_find_user_id_by_email($email);
    $user = $userId ? cc_get_user($userId) : null;
    if ($user === null || empty($user['passwordHash']) || !password_verify($password, $user['passwordHash'])) {
        return ['ok' => false, 'error' => 'Invalid email or password.', 'status' => 401];
    }

    cc_customer_login_user($user);
    return ['ok' => true, 'user' => cc_public_user($user)];
}

function cc_account_update(array $user, array $payload): array {
    $name = trim((string)($payload['name'] ?? $user['name'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? $user['phone'] ?? ''));
    if ($name !== '' && strlen($name) < 2) {
        return ['ok' => false, 'error' => 'Enter your full name.', 'status' => 400];
    }
    if ($phone !== '' && !preg_match('/^[\d\s().+-]{7,20}$/', $phone)) {
        return ['ok' => false, 'error' => 'Enter a valid phone number.', 'status' => 400];
    }

    $shippingIn = $payload['shipping'] ?? $user['shipping'] ?? [];
    $state = strtoupper(trim((string)($shippingIn['state'] ?? '')));
    if ($state !== '' && strlen($state) !== 2) {
        return ['ok' => false, 'error' => 'Select a valid state.', 'status' => 400];
    }
    $zip = trim((string)($shippingIn['zip'] ?? ''));
    if ($zip !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        return ['ok' => false, 'error' => 'Enter a valid ZIP code.', 'status' => 400];
    }

    $user['name'] = $name;
    $user['phone'] = $phone;
    $user['shipping'] = [
        'address1' => trim((string)($shippingIn['address1'] ?? '')),
        'address2' => trim((string)($shippingIn['address2'] ?? '')),
        'city' => trim((string)($shippingIn['city'] ?? '')),
        'state' => $state,
        'zip' => $zip,
    ];
    $user['updatedAt'] = gmdate('c');
    cc_save_user($user);

    return ['ok' => true, 'user' => cc_public_user($user)];
}

function cc_account_add_order_id(array $user, string $orderId): void {
    $ids = $user['orderIds'] ?? [];
    if (!in_array($orderId, $ids, true)) {
        $ids[] = $orderId;
        $user['orderIds'] = $ids;
        $user['updatedAt'] = gmdate('c');
        cc_save_user($user);
    }
}

function cc_account_touch_from_order(array $user, array $payload): void {
    $user['name'] = trim((string)($payload['customer']['name'] ?? $user['name'] ?? ''));
    $user['phone'] = trim((string)($payload['customer']['phone'] ?? $user['phone'] ?? ''));
    $shipping = $payload['shipping'] ?? [];
    $user['shipping'] = [
        'address1' => trim((string)($shipping['address1'] ?? '')),
        'address2' => trim((string)($shipping['address2'] ?? '')),
        'city' => trim((string)($shipping['city'] ?? '')),
        'state' => strtoupper(trim((string)($shipping['state'] ?? ''))),
        'zip' => trim((string)($shipping['zip'] ?? '')),
    ];
    $user['updatedAt'] = gmdate('c');
    cc_save_user($user);
}

function cc_account_orders(array $user): array {
    $ids = $user['orderIds'] ?? [];
    $email = cc_normalize_email((string)($user['email'] ?? ''));
    $orders = [];
    $seen = [];

    foreach ($ids as $orderId) {
        $order = cc_get_order((string)$orderId);
        if ($order === null) continue;
        $seen[$order['id']] = true;
        $orders[] = cc_public_order($order);
    }

    foreach (cc_list_orders() as $order) {
        $id = (string)($order['id'] ?? '');
        if ($id === '' || isset($seen[$id])) continue;
        $sameAccount = ($order['accountId'] ?? '') === $user['id'];
        $sameEmail = cc_normalize_email((string)($order['customer']['email'] ?? '')) === $email;
        if ($sameAccount || $sameEmail) {
            $orders[] = cc_public_order($order);
        }
    }

    usort($orders, function (array $a, array $b): int {
        return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
    });

    return $orders;
}

/** @return list<string> */
function cc_fulfillment_statuses(): array {
    return ['pending', 'processing', 'shipped', 'cancelled'];
}

function cc_email_kind_suffix(string $kind): ?string {
    return match ($kind) {
        'customer' => '',
        'ops' => '-ops',
        default => null,
    };
}

function cc_email_job_path(string $orderId, string $kind): ?string {
    $suffix = cc_email_kind_suffix($kind);
    if ($suffix === null) return null;
    return cc_ensure_dir('email-queue') . '/' . $orderId . $suffix . '.json';
}

function cc_get_email_job(string $orderId, string $kind): ?array {
    $path = cc_email_job_path($orderId, $kind);
    if ($path === null || !is_file($path)) return null;
    $job = json_decode((string)file_get_contents($path), true);
    return is_array($job) ? $job : null;
}

function cc_list_orders(): array {
    $dir = cc_ensure_dir('orders');
    $files = glob($dir . '/*.json') ?: [];
    $orders = [];
    foreach ($files as $file) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded) && !empty($decoded['id'])) {
            $orders[] = $decoded;
        }
    }
    usort($orders, function (array $a, array $b): int {
        return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
    });
    return $orders;
}

function cc_order_item_summary(array $order): string {
    $parts = [];
    foreach ($order['items'] ?? [] as $item) {
        $qty = (int)($item['quantity'] ?? 1);
        $name = (string)($item['name'] ?? 'Item');
        $type = ($item['purchaseType'] ?? '') === 'subscribe' ? 'sub' : 'one-time';
        $parts[] = "{$qty}× {$name} ({$type})";
    }
    return implode(', ', $parts);
}

function cc_admin_order_summary(array $order): array {
    $email = $order['email'] ?? [];
    return [
        'id' => $order['id'],
        'createdAt' => $order['createdAt'] ?? '',
        'status' => $order['status'] ?? '',
        'customer' => [
            'name' => $order['customer']['name'] ?? '',
            'email' => $order['customer']['email'] ?? '',
        ],
        'guest' => empty($order['accountId']),
        'itemSummary' => cc_order_item_summary($order),
        'total' => $order['total'] ?? 0,
        'fulfillment' => [
            'status' => $order['fulfillment']['status'] ?? 'pending',
        ],
        'email' => [
            'customer' => [
                'sent' => (bool)($email['customer']['sent'] ?? false),
                'queued' => (bool)($email['customer']['queued'] ?? false),
            ],
            'ops' => [
                'sent' => (bool)($email['ops']['sent'] ?? false),
                'queued' => (bool)($email['ops']['queued'] ?? false),
            ],
        ],
    ];
}

function cc_admin_order(array $order): array {
    return [
        'id' => $order['id'],
        'createdAt' => $order['createdAt'] ?? '',
        'status' => $order['status'] ?? '',
        'items' => $order['items'] ?? [],
        'customer' => $order['customer'] ?? [],
        'accountId' => $order['accountId'] ?? null,
        'guest' => empty($order['accountId']),
        'shipping' => $order['shipping'] ?? [],
        'subtotal' => $order['subtotal'] ?? 0,
        'shippingCost' => $order['shippingCost'] ?? 0,
        'tax' => $order['tax'] ?? 0,
        'total' => $order['total'] ?? 0,
        'subscriptions' => $order['subscriptions'] ?? [],
        'payment' => $order['payment'] ?? [],
        'email' => [
            'customer' => [
                'sent' => (bool)($order['email']['customer']['sent'] ?? false),
                'queued' => (bool)($order['email']['customer']['queued'] ?? false),
            ],
            'ops' => [
                'sent' => (bool)($order['email']['ops']['sent'] ?? false),
                'queued' => (bool)($order['email']['ops']['queued'] ?? false),
            ],
        ],
        'fulfillment' => [
            'status' => $order['fulfillment']['status'] ?? 'pending',
        ],
        'emailJobs' => [
            'customer' => cc_get_email_job($order['id'], 'customer'),
            'ops' => cc_get_email_job($order['id'], 'ops'),
        ],
    ];
}

function cc_update_fulfillment_queue_status(string $orderId, string $status): void {
    $file = cc_ensure_dir('fulfillment') . '/queue.jsonl';
    if (!is_file($file)) return;

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return;

    $updated = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $decoded = json_decode($line, true);
        if (is_array($decoded) && ($decoded['orderId'] ?? '') === $orderId) {
            $decoded['status'] = $status;
            $updated[] = json_encode($decoded, JSON_UNESCAPED_SLASHES);
        } else {
            $updated[] = $line;
        }
    }
    file_put_contents($file, implode(PHP_EOL, $updated) . (count($updated) ? PHP_EOL : ''));
}

function cc_update_fulfillment_status(string $orderId, string $status): ?array {
    if (!in_array($status, cc_fulfillment_statuses(), true)) {
        return null;
    }
    $order = cc_get_order($orderId);
    if ($order === null) return null;

    $order['fulfillment'] = $order['fulfillment'] ?? [];
    $order['fulfillment']['status'] = $status;
    cc_save_order($order);
    cc_update_fulfillment_queue_status($orderId, $status);
    return $order;
}

function cc_mark_email_sent(string $orderId, string $kind): ?array {
    if (cc_email_kind_suffix($kind) === null) return null;
    $order = cc_get_order($orderId);
    if ($order === null) return null;

    $order['email'][$kind] = [
        'sent' => true,
        'queued' => (bool)($order['email'][$kind]['queued'] ?? true),
    ];
    cc_save_order($order);

    $path = cc_email_job_path($orderId, $kind);
    $job = cc_get_email_job($orderId, $kind) ?? [
        'type' => $kind === 'ops' ? 'ops_new_order' : 'order_confirmation',
        'to' => $order['customer']['email'] ?? '',
        'orderId' => $orderId,
        'queuedAt' => gmdate('c'),
    ];
    $job['sent'] = true;
    $job['sentAt'] = gmdate('c');
    if ($path !== null) {
        file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    return $order;
}

function cc_requeue_email(string $orderId, string $kind): ?array {
    $suffix = cc_email_kind_suffix($kind);
    if ($suffix === null) return null;
    $order = cc_get_order($orderId);
    if ($order === null) return null;

    $order['email'][$kind] = [
        'sent' => false,
        'queued' => true,
    ];
    cc_save_order($order);
    cc_queue_email($order, $suffix);
    $result = cc_send_order_email($order, $kind);
    return $result['order'] ?? $order;
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
    header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
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
            'sent' => (bool)($order['email']['customer']['sent'] ?? false),
            'queued' => (bool)($order['email']['customer']['queued'] ?? true),
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
    $account = cc_customer_user();
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
        'accountId' => $account['id'] ?? null,
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

    cc_enqueue_fulfillment($order);
    cc_save_order($order);
    $order = cc_dispatch_order_emails($order);

    if ($account !== null) {
        cc_account_touch_from_order($account, $payload);
        cc_account_add_order_id(cc_get_user($account['id']) ?? $account, $orderId);
    }

    return cc_public_order($order);
}

require __DIR__ . '/mail.php';
