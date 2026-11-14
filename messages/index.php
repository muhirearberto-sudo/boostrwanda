<?php
/** Conversation list + start a new conversation by Member ID. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(MESSAGING_ROLES);
$uid = (int)$user['id'];

function open_with(int $uid, ?array $other): void
{
    if (!$other || (int)$other['id'] === $uid || $other['status'] !== 'active' || !in_array($other['role'], MESSAGING_ROLES, true)) {
        flash('error', 'You cannot message that user.'); redirect('messages/index.php');
    }
    redirect('messages/conversation.php?id=' . get_or_create_conversation($uid, (int)$other['id']));
}
if (is_post()) {                                           // start by Member ID
    csrf_verify();
    open_with($uid, fetch_one('SELECT id, role, status FROM users WHERE member_id=?', [strtoupper(clean($_POST['member_id'] ?? '', 12))]));
}
if (isset($_GET['with'])) { open_with($uid, fetch_one('SELECT id, role, status FROM users WHERE id=?', [(int)$_GET['with']])); }   // e.g. "Message provider"

$convs = fetch_all("SELECT c.id, u.full_name, u.member_id,
        (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT created_at FROM messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_at,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_id<>? AND m.is_read=0) AS unread
    FROM conversations c JOIN users u ON u.id = IF(c.user_a=?, c.user_b, c.user_a)
    WHERE c.user_a=? OR c.user_b=? ORDER BY COALESCE(last_at, c.created_at) DESC", [$uid, $uid, $uid, $uid]);
$pageTitle = 'Messages';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Messages</h1><p>Private conversations between BoostRwanda members.</p></div></div>
  <div class="chat-layout">
    <div class="conv-list">
      <form method="post" class="chat-form" data-no-loading><?= csrf_field() ?><input name="member_id" placeholder="Member ID e.g. BR000005" required maxlength="12" aria-label="Member ID"><button class="btn btn-primary btn-sm" type="submit">Start</button></form>
      <?php foreach ($convs as $c): ?>
        <a class="conv-item" href="<?= e(url('messages/conversation.php?id=' . $c['id'])) ?>"><?= $c['unread'] ? '<span class="unread-dot">' . (int)$c['unread'] . '</span>' : '' ?><b><?= e($c['full_name']) ?></b><small><?= e($c['member_id']) ?> &middot; <?= e(excerpt((string)$c['last_body'], 40) ?: 'No messages yet') ?></small></a>
      <?php endforeach; ?>
    </div>
    <div class="card empty" style="margin:0"><h3><?= $convs ? 'Select a conversation' : 'No conversations yet' ?></h3><p>Enter a Member ID on the left to start chatting, or use "Message provider" on a service page.</p></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
