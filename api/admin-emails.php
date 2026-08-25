<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
cc_cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') cc_send_json(405, ['error' => 'Method not allowed.']);
cc_require_admin();
$sync = cc_sync_inbound_archive();
$messages = cc_email_archive_list();
usort($messages, fn($a, $b) => strtotime((string)($b['date'] ?? '')) <=> strtotime((string)($a['date'] ?? '')));
cc_send_json(200, ['messages' => $messages, 'sync' => $sync]);
