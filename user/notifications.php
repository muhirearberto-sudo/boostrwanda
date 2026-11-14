<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];

if (is_post()) {
    csrf_verify();
    q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);   // only the owner's rows
    flash('success', 'All notifications marked as read.');
    redirect('user/notifications.php');
}
$total = (int)fetch_val('SELECT COUNT(*) FROM notifications WHERE user_id=?', [$uid]);
$pg = paginate($total, 15);
$rows = fetch_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], [$uid]);
$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><div><h1>Notifications</h1><p><?= $total ?> total</p></div>
    <form method="post" data-no-loading><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit">Mark all as read</button></form></div>
  <div class="card" style="padding:0">
    <?php if (!$rows): ?><div class="empty"><h3>You are all caught up</h3><p>Task approvals, earnings, payments and messages will appear here.</p></div><?php endif; ?>
    <?php foreach ($rows as $n): ?>
      <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
        <span><?= $n['link'] ? '<a href="' . e(url($n['link'])) . '">' . e($n['message']) . '</a>' : e($n['message']) ?></span>
        <small><?= e(fmt_date($n['created_at'])) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
