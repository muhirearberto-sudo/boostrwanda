<?php
/** Dashboard for Members and Freelancers. Other roles are sent to their own dashboard. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
if (!in_array($user['role'], ['member', 'freelancer'], true)) { redirect(role_home($user['role'])); }
$uid = (int)$user['id'];

$wallet   = wallet_of($uid);
$inProg   = (int)fetch_val("SELECT COUNT(*) FROM task_submissions WHERE user_id=? AND status IN ('accepted','rejected')", [$uid]);
$pending  = (int)fetch_val("SELECT COUNT(*) FROM task_submissions WHERE user_id=? AND status='submitted'", [$uid]);
$approved = (int)fetch_val("SELECT COUNT(*) FROM task_submissions WHERE user_id=? AND status='approved'", [$uid]);
$earned   = fetch_val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='earning' AND status='completed'", [$uid]);
$tasks    = fetch_all("SELECT t.id, t.title, t.reward, c.name AS category FROM tasks t JOIN categories c ON c.id=t.category_id
                       WHERE t.status='open' AND (t.deadline IS NULL OR t.deadline >= CURDATE())
                         AND t.id NOT IN (SELECT task_id FROM task_submissions WHERE user_id=?) ORDER BY t.id DESC LIMIT 4", [$uid]);
$notifs   = fetch_all('SELECT message, link, is_read, created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 5', [$uid]);

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h1><p>Member ID <b><?= e($user['member_id']) ?></b> &middot; <?= e(ucfirst($user['role'])) ?></p></div>
    <a class="btn btn-primary" href="<?= e(url('tasks/index.php')) ?>">Find tasks</a></div>
  <div class="stats">
    <div class="stat"><div class="stat-value"><?= e(number_format((float)$wallet['balance'], 2)) ?></div><div class="stat-label">Wallet balance (<?= e(app_config()['currency']) ?>)</div></div>
    <div class="stat"><div class="stat-value"><?= $inProg ?></div><div class="stat-label">Tasks in progress</div></div>
    <div class="stat"><div class="stat-value"><?= $pending ?></div><div class="stat-label">Awaiting review</div></div>
    <div class="stat"><div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved &middot; <?= e(money($earned)) ?> earned</div></div>
  </div>
  <div class="grid grid-2">
    <div class="card"><div class="card-head"><h2>New tasks for you</h2><a href="<?= e(url('tasks/index.php')) ?>">See all</a></div>
      <?php if (!$tasks): ?><div class="empty"><p>No new tasks right now. Check back soon.</p></div><?php endif; ?>
      <?php foreach ($tasks as $t): ?>
        <div class="notif"><span><a href="<?= e(url('tasks/details.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a><br><small><?= e($t['category']) ?></small></span><span class="reward"><?= e(money($t['reward'])) ?></span></div>
      <?php endforeach; ?></div>
    <div class="card"><div class="card-head"><h2>Recent notifications</h2><a href="<?= e(url('user/notifications.php')) ?>">See all</a></div>
      <?php if (!$notifs): ?><div class="empty"><p>No notifications yet.</p></div><?php endif; ?>
      <?php foreach ($notifs as $n): ?>
        <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>"><span><?= e($n['message']) ?></span><small><?= e(fmt_date($n['created_at'])) ?></small></div>
      <?php endforeach; ?></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
