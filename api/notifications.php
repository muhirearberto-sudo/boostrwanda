<?php
/** JSON: unread notification count (GET) and mark-as-read (POST). Always scoped to the logged-in user. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login_json();
$uid = (int)$user['id'];
if (is_post()) {
    csrf_verify();
    q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
    json_out(['ok' => true, 'count' => 0]);
}
json_out(['ok' => true, 'count' => unread_notifications($uid)]);
