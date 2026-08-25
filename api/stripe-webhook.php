<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/stripe.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cc_send_json(405, ['error' => 'Method not allowed']);
}

$secret = (string)(getenv('STRIPE_WEBHOOK_SECRET') ?: '');
if ($secret === '' || !str_starts_with($secret, 'whsec_')) {
    cc_send_json(503, ['error' => 'Stripe webhook secret is not configured.']);
}

$payload = file_get_contents('php://input') ?: '';
$header = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (!cc_stripe_verify_webhook($payload, $header, $secret)) {
    cc_send_json(400, ['error' => 'Invalid Stripe signature.']);
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    cc_send_json(400, ['error' => 'Invalid Stripe event.']);
}

$eventId = (string)$event['id'];
if (cc_stripe_event_seen($eventId)) {
    cc_send_json(200, ['received' => true, 'duplicate' => true]);
}

try {
    cc_handle_stripe_event($event);
    cc_mark_stripe_event($eventId, (string)$event['type']);
} catch (Throwable $e) {
    cc_send_json(500, ['error' => $e->getMessage()]);
}

cc_send_json(200, ['received' => true]);
