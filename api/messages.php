<?php
/** JSON API for chat. Every call re-checks login, role and that the user belongs to the conversation. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login_json();
if (!in_array($user['role'], MESSAGING_ROLES, true)) { json_out(['ok' => false, 'error' => 'Not allowed'], 403); }
$uid = (int)$user['id'];
$action = $_REQUEST['action'] ?? '';
$conv = conversation_for((int)($_REQUEST['conversation_id'] ?? 0), $uid);
if (!$conv) { json_out(['ok' => false, 'error' => 'Conversation not found'], 403); }

function msg_json(array $m, int $uid): array
{
    return ['id' => (int)$m['id'], 'mine' => (int)$m['sender_id'] === $uid, 'body' => $m['body'], 'time' => fmt_date($m['created_at'])];
}

if ($action === 'poll') {
    $rows = fetch_all('SELECT * FROM messages WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 100', [$conv['id'], (int)($_GET['after'] ?? 0)]);
    q('UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id<>? AND is_read=0', [$conv['id'], $uid]);
    json_out(['ok' => true, 'messages' => array_map(fn($m) => msg_json($m, $uid), $rows)]);
}
if ($action === 'send' && is_post()) {
    csrf_verify();
    $body = clean($_POST['body'] ?? '', 2000);
    if ($body === '') { json_out(['ok' => false, 'error' => 'Message is empty'], 422); }
    $otherId = (int)($conv['user_a'] === $uid ? $conv['user_b'] : $conv['user_a']);
    if (fetch_val('SELECT status FROM users WHERE id=?', [$otherId]) !== 'active') { json_out(['ok' => false, 'error' => 'User is not active'], 403); }
    q('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?)', [$conv['id'], $uid, $body]);
    $mid = (int)db()->lastInsertId();
    // Notify the recipient, but only once while they still have an unread notification for this chat (avoids spam)
    $link = 'messages/conversation.php?id=' . $conv['id'];
    if (!fetch_val('SELECT id FROM notifications WHERE user_id=? AND link=? AND is_read=0', [$otherId, $link])) {
        notify($otherId, 'New message from ' . $user['full_name'], $link);
    }
    json_out(['ok' => true, 'message' => msg_json(fetch_one('SELECT * FROM messages WHERE id=?', [$mid]), $uid)]);
}
json_out(['ok' => false, 'error' => 'Bad request'], 400);
