<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/stripe.php';
cc_cors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cc_send_json(405, ['error' => 'Method not allowed']);
$body = cc_read_body();
try { cc_send_json(200, ['order' => cc_complete_checkout(trim((string)($body['sessionId'] ?? '')))]); }
catch (Throwable $e) { cc_send_json(502, ['error' => $e->getMessage()]); }
