<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/stripe.php';
cc_cors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') cc_send_json(405, ['error' => 'Method not allowed']);
try { cc_send_json(201, cc_checkout_session(cc_read_body())); }
catch (Throwable $e) { cc_send_json(502, ['error' => $e->getMessage()]); }
