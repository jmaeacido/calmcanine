<?php
declare(strict_types=1);

function cc_env(string $name, string $default = ''): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        $fallback = $_ENV[$name] ?? $default;
        return is_string($fallback) ? $fallback : $default;
    }
    return $value;
}

function cc_mail_from(): string {
    $from = cc_env('MAIL_FROM', cc_env('SMTP_USERNAME'));
    return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : '';
}

function cc_mail_from_name(): string {
    $name = trim(cc_env('MAIL_FROM_NAME', 'Calm Canine'));
    return $name !== '' ? $name : 'Calm Canine';
}

function cc_mail_ops_to(): string {
    $to = cc_env('MAIL_OPS_TO', cc_mail_from());
    return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
}

function cc_mail_configured(): bool {
    return cc_env('SMTP_USERNAME') !== ''
        && cc_env('SMTP_PASSWORD') !== ''
        && cc_mail_from() !== '';
}

function cc_format_money_mail(float $amount): string {
    return '$' . number_format($amount, 2);
}

function cc_mail_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cc_smtp_read($fp): string {
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function cc_smtp_cmd($fp, string $command, array $okCodes): string {
    fwrite($fp, $command . "\r\n");
    $response = cc_smtp_read($fp);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException(trim($response) !== '' ? trim($response) : "SMTP command failed: {$command}");
    }
    return $response;
}

function cc_mail_send(string $to, string $subject, string $text, string $html): array {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient address.'];
    }
    if (!cc_mail_configured()) {
        return ['ok' => false, 'error' => 'Gmail SMTP is not configured. Set SMTP_USERNAME, SMTP_PASSWORD, and MAIL_FROM in .env.'];
    }
    if (!function_exists('openssl_encrypt')) {
        return ['ok' => false, 'error' => 'PHP OpenSSL is required to send mail through Gmail.'];
    }

    $host = cc_env('SMTP_HOST', 'smtp.gmail.com');
    $port = (int)cc_env('SMTP_PORT', '587');
    $encryption = strtolower(cc_env('SMTP_ENCRYPTION', 'tls'));
    $username = cc_env('SMTP_USERNAME');
    $password = cc_env('SMTP_PASSWORD');
    $from = cc_mail_from();
    $fromName = cc_mail_from_name();
    $timeout = 25;

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT]])
    );
    if ($fp === false) {
        return ['ok' => false, 'error' => "Could not connect to SMTP ({$errstr})."];
    }

    stream_set_timeout($fp, $timeout);

    try {
        $greeting = cc_smtp_read($fp);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException(trim($greeting) ?: 'SMTP greeting failed.');
        }

        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        cc_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);

        if ($encryption === 'tls') {
            cc_smtp_cmd($fp, 'STARTTLS', [220]);
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            if ($crypto !== true) {
                throw new RuntimeException('STARTTLS failed.');
            }
            cc_smtp_cmd($fp, 'EHLO ' . $ehloHost, [250]);
        }

        cc_smtp_cmd($fp, 'AUTH LOGIN', [334]);
        cc_smtp_cmd($fp, base64_encode($username), [334]);
        cc_smtp_cmd($fp, base64_encode($password), [235]);
        cc_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        cc_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        cc_smtp_cmd($fp, 'DATA', [354]);

        $boundary = 'cc_' . bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $date = date('r');
        $messageId = '<cc-' . bin2hex(random_bytes(8)) . '@' . $ehloHost . '>';

        $dotText = preg_replace('/^\./m', '..', $text) ?? $text;
        $dotHtml = preg_replace('/^\./m', '..', $html) ?? $html;

        $body = implode("\r\n", [
            'Date: ' . $date,
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Reply-To: ' . $fromHeader,
            'Message-ID: ' . $messageId,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $dotText,
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $dotHtml,
            '',
            '--' . $boundary . '--',
            '.',
        ]);

        fwrite($fp, $body . "\r\n");
        $dataResponse = cc_smtp_read($fp);
        if ((int)substr($dataResponse, 0, 3) !== 250) {
            throw new RuntimeException(trim($dataResponse) ?: 'SMTP DATA failed.');
        }

        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return ['ok' => true];
    } catch (Throwable $e) {
        if (is_resource($fp)) {
            fclose($fp);
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function cc_mail_wrap_html(string $title, string $inner): string {
    $safeTitle = cc_mail_escape($title);
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $safeTitle . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#eee7e7;font-family:Georgia,serif;color:#1f2a24;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:20px;padding:28px 24px;">'
        . '<p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:11px;letter-spacing:.12em;font-weight:700;color:#2a3f34;">CALM CANINE</p>'
        . '<h1 style="margin:0 0 16px;font-size:28px;font-weight:600;">' . $safeTitle . '</h1>'
        . $inner
        . '<p style="margin:24px 0 0;font-size:12px;color:#63735c;">This product is not intended to diagnose, treat, cure, or prevent any disease.</p>'
        . '</div></body></html>';
}

function cc_order_items_text(array $order): string {
    $lines = [];
    foreach ($order['items'] ?? [] as $item) {
        $qty = (int)($item['quantity'] ?? 1);
        $name = (string)($item['name'] ?? 'Item');
        $type = ($item['purchaseType'] ?? '') === 'subscribe' ? 'subscribe' : 'one-time';
        $total = cc_format_money_mail((float)($item['lineTotal'] ?? 0));
        $lines[] = "{$qty} × {$name} ({$type}) — {$total}";
    }
    return implode("\n", $lines);
}

function cc_order_items_html(array $order): string {
    $rows = '';
    foreach ($order['items'] ?? [] as $item) {
        $qty = (int)($item['quantity'] ?? 1);
        $name = cc_mail_escape((string)($item['name'] ?? 'Item'));
        $type = ($item['purchaseType'] ?? '') === 'subscribe' ? 'subscribe' : 'one-time';
        $total = cc_mail_escape(cc_format_money_mail((float)($item['lineTotal'] ?? 0)));
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">' . $qty . ' × ' . $name . ' <span style="color:#63735c;">(' . $type . ')</span></td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">' . $total . '</td></tr>';
    }
    return $rows;
}

function cc_shipping_text(array $order): string {
    $s = $order['shipping'] ?? [];
    $parts = [
        (string)($s['name'] ?? ''),
        (string)($s['address1'] ?? ''),
        (string)($s['address2'] ?? ''),
        trim(($s['city'] ?? '') . ', ' . ($s['state'] ?? '') . ' ' . ($s['zip'] ?? '')),
    ];
    return implode("\n", array_values(array_filter($parts, fn($line) => trim($line) !== '' && trim($line) !== ',')));
}

function cc_build_order_confirmation(array $order): array {
    $id = (string)$order['id'];
    $name = trim((string)($order['customer']['name'] ?? ''));
    $hello = $name !== '' ? "Hi {$name}," : 'Hi,';
    $subject = "Your Calm Canine order {$id}";
    $text = $hello . "\n\nThank you for your order {$id}. We'll get it ready to ship.\n\n"
        . cc_order_items_text($order) . "\n\n"
        . 'Subtotal: ' . cc_format_money_mail((float)$order['subtotal']) . "\n"
        . 'Shipping: ' . (((float)$order['shippingCost'] === 0.0) ? 'Free' : cc_format_money_mail((float)$order['shippingCost'])) . "\n"
        . 'Tax: ' . cc_format_money_mail((float)$order['tax']) . "\n"
        . 'Total: ' . cc_format_money_mail((float)$order['total']) . "\n\n"
        . "Ship to:\n" . cc_shipping_text($order) . "\n\n"
        . "Calm Canine\n";

    $html = cc_mail_wrap_html('Order confirmed', '<p style="margin:0 0 16px;line-height:1.6;">' . cc_mail_escape($hello) . '</p>'
        . '<p style="margin:0 0 16px;line-height:1.6;">Thank you for your order <strong>' . cc_mail_escape($id) . '</strong>. We\'ll get it ready to ship.</p>'
        . '<table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">' . cc_order_items_html($order) . '</table>'
        . '<p style="margin:16px 0 0;font-family:Arial,sans-serif;font-size:14px;">Total: <strong>' . cc_mail_escape(cc_format_money_mail((float)$order['total'])) . '</strong></p>'
        . '<p style="margin:16px 0 0;white-space:pre-line;line-height:1.6;">Ship to:<br>' . nl2br(cc_mail_escape(cc_shipping_text($order))) . '</p>');

    return compact('subject', 'text', 'html');
}

function cc_build_ops_new_order(array $order): array {
    $id = (string)$order['id'];
    $subject = "New Calm Canine order {$id}";
    $customer = (string)($order['customer']['name'] ?? '') . ' <' . (string)($order['customer']['email'] ?? '') . '>';
    $text = "A new order was placed.\n\nOrder: {$id}\nCustomer: {$customer}\nPhone: "
        . ($order['customer']['phone'] ?? '') . "\nTotal: " . cc_format_money_mail((float)$order['total']) . "\n\n"
        . cc_order_items_text($order) . "\n\n"
        . "Ship to:\n" . cc_shipping_text($order) . "\n";

    $html = cc_mail_wrap_html('New order', '<p style="margin:0 0 12px;line-height:1.6;">Order <strong>' . cc_mail_escape($id) . '</strong></p>'
        . '<p style="margin:0 0 12px;line-height:1.6;">' . cc_mail_escape($customer) . '<br>' . cc_mail_escape((string)($order['customer']['phone'] ?? '')) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">' . cc_order_items_html($order) . '</table>'
        . '<p style="margin:16px 0 0;font-family:Arial,sans-serif;">Total: <strong>' . cc_mail_escape(cc_format_money_mail((float)$order['total'])) . '</strong></p>'
        . '<p style="margin:16px 0 0;white-space:pre-line;">' . nl2br(cc_mail_escape(cc_shipping_text($order))) . '</p>');

    return compact('subject', 'text', 'html');
}

function cc_build_welcome(array $user): array {
    $name = trim((string)($user['name'] ?? ''));
    $hello = $name !== '' ? "Hi {$name}," : 'Hi,';
    $subject = 'Welcome to Calm Canine';
    $text = $hello . "\n\nYour Calm Canine account is ready. You can sign in anytime to review saved details and orders. Guest checkout is still available whenever you shop.\n\nCalm Canine\n";
    $html = cc_mail_wrap_html('Welcome', '<p style="margin:0 0 16px;line-height:1.6;">' . cc_mail_escape($hello) . '</p>'
        . '<p style="margin:0;line-height:1.6;">Your Calm Canine account is ready. You can sign in anytime to review saved details and orders. Guest checkout is still available whenever you shop.</p>');
    return compact('subject', 'text', 'html');
}

function cc_write_email_job(string $orderId, string $kind, array $job): void {
    $path = cc_email_job_path($orderId, $kind);
    if ($path === null) return;
    file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cc_send_order_email(array $order, string $kind): array {
    $suffix = cc_email_kind_suffix($kind);
    if ($suffix === null) {
        return ['ok' => false, 'error' => 'Invalid email kind.'];
    }

    $built = $kind === 'ops' ? cc_build_ops_new_order($order) : cc_build_order_confirmation($order);
    $to = $kind === 'ops' ? cc_mail_ops_to() : (string)($order['customer']['email'] ?? '');
    $job = cc_get_email_job($order['id'], $kind) ?? [];
    $job = array_merge($job, [
        'type' => $kind === 'ops' ? 'ops_new_order' : 'order_confirmation',
        'to' => $to,
        'orderId' => $order['id'],
        'subject' => $built['subject'],
        'preview' => substr($built['text'], 0, 240),
        'provider' => 'gmail-smtp',
        'queuedAt' => $job['queuedAt'] ?? gmdate('c'),
        'sent' => false,
    ]);

    if ($kind === 'ops' && $to === '') {
        $job['error'] = 'MAIL_OPS_TO is not set.';
        cc_write_email_job($order['id'], $kind, $job);
        return ['ok' => false, 'error' => $job['error'], 'job' => $job];
    }

    $result = cc_mail_send($to, $built['subject'], $built['text'], $built['html']);
    if ($result['ok']) {
        $job['sent'] = true;
        $job['sentAt'] = gmdate('c');
        unset($job['error']);
    } else {
        $job['error'] = $result['error'] ?? 'Send failed.';
    }
    cc_write_email_job($order['id'], $kind, $job);

    $fresh = cc_get_order($order['id']) ?? $order;
    $fresh['email'][$kind] = [
        'sent' => (bool)$job['sent'],
        'queued' => true,
        'error' => $job['error'] ?? null,
    ];
    cc_save_order($fresh);

    return ['ok' => (bool)$job['sent'], 'error' => $job['error'] ?? null, 'order' => $fresh, 'job' => $job];
}

function cc_dispatch_order_emails(array $order): array {
    cc_queue_email($order);
    cc_queue_email($order, '-ops');
    $customer = cc_send_order_email($order, 'customer');
    $ops = cc_send_order_email($customer['order'] ?? $order, 'ops');
    return $ops['order'] ?? $customer['order'] ?? $order;
}

function cc_send_welcome_email(array $user): void {
    $to = (string)($user['email'] ?? '');
    $built = cc_build_welcome($user);
    cc_mail_send($to, $built['subject'], $built['text'], $built['html']);
}
