<?php
/** One conversation. Only its two participants can open it (checked in conversation_for()). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(MESSAGING_ROLES);
$uid = (int)$user['id'];
$conv = conversation_for((int)($_GET['id'] ?? $_POST['id'] ?? 0), $uid);
if (!$conv) { http_response_code(403); flash('error', 'Conversation not found.'); redirect('messages/index.php'); }
$otherId = (int)($conv['user_a'] === $uid ? $conv['user_b'] : $conv['user_a']);
$other = fetch_one('SELECT id, full_name, member_id, status FROM users WHERE id=?', [$otherId]);

if (is_post()) {                                           // report the other user (moderation, Process 4.0)
    csrf_verify();
    $reason = clean($_POST['reason'] ?? '', 300);
    if (mb_strlen($reason) < 5) { flash('error', 'Please describe the problem (at least 5 characters).'); }
    else { q("INSERT INTO content_reports (reporter_id, target_type, target_id, reason) VALUES (?, 'user', ?, ?)", [$uid, $otherId, $reason]);
           notify_admins('A user was reported from a conversation.', 'admin/moderation.php'); flash('success', 'Report sent to the administrators.'); }
    redirect('messages/conversation.php?id=' . $conv['id']);
}
q('UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id<>? AND is_read=0', [$conv['id'], $uid]);
$msgs = array_reverse(fetch_all('SELECT * FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 100', [$conv['id']]));
$lastId = $msgs ? (int)end($msgs)['id'] : 0;
$pageTitle = 'Chat with ' . $other['full_name'];
$extraJs = ['messaging.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Messages</h1></div><a class="btn btn-outline btn-sm" href="<?= e(url('messages/index.php')) ?>">&larr; All conversations</a></div>
  <div class="chat-box">
    <div class="chat-head"><div><b><?= e($other['full_name']) ?></b> <small><?= e($other['member_id']) ?></small></div>
      <details><summary class="linkbtn">Report</summary>
        <form method="post" class="chat-form" data-no-loading><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$conv['id'] ?>"><input name="reason" placeholder="What is the problem?" required maxlength="300"><button class="btn btn-danger btn-sm" type="submit">Send report</button></form></details></div>
    <div id="chat-msgs" class="chat-msgs" data-conv="<?= (int)$conv['id'] ?>" data-last="<?= $lastId ?>">
      <?php if (!$msgs): ?><div class="empty">Say hello!</div><?php endif; ?>
      <?php foreach ($msgs as $m): ?><div class="bubble <?= (int)$m['sender_id'] === $uid ? 'me' : '' ?>"><?= e($m['body']) ?><small><?= e(fmt_date($m['created_at'])) ?></small></div><?php endforeach; ?>
    </div>
    <?php if ($other['status'] === 'active'): ?>
    <form id="chat-form" class="chat-form" data-no-loading autocomplete="off"><input name="body" placeholder="Type a message..." maxlength="2000" required aria-label="Message"><button class="btn btn-primary" type="submit">Send</button></form>
    <?php else: ?><div class="chat-form"><small>This user is no longer active.</small></div><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
