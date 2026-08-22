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

cc_send_json(200, ['authenticated' => cc_admin_logged_in()]);
