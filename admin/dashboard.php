<?php
/** Administrator overview. All figures are live COUNT/SUM queries on the operational tables. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$n = fn($sql) => (int)fetch_val($sql);
$stats = [
    ['Active users', $n("SELECT COUNT(*) FROM users WHERE status='active'")],
    ['Open tasks', $n("SELECT COUNT(*) FROM tasks WHERE status='open'")],
    ['Submissions to review', $n("SELECT COUNT(*) FROM task_submissions WHERE status='submitted'")],
    ['Pending withdrawals', $n("SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending'")],
    ['Campaigns awaiting approval', $n("SELECT COUNT(*) FROM campaigns WHERE status='pending'")],
    ['Open moderation reports', $n("SELECT COUNT(*) FROM content_reports WHERE status='open'")],
    ['Earnings paid (RWF)', number_format((float)fetch_val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='earning' AND status='completed'"))],
    ['Pending orders', $n("SELECT COUNT(*) FROM orders WHERE status='requested'")],
];
$byRole = array_map('intval', rpt_pairs(fetch_all('SELECT role label, COUNT(*) val FROM users GROUP BY role ORDER BY val DESC')));
$bySub = array_map('intval', rpt_pairs(fetch_all('SELECT status label, COUNT(*) val FROM task_submissions GROUP BY status')));
$pendingSubs = fetch_all("SELECT s.id, t.title, u.full_name, s.submitted_at FROM task_submissions s JOIN tasks t ON t.id=s.task_id JOIN users u ON u.id=s.user_id WHERE s.status='submitted' ORDER BY s.submitted_at LIMIT 5");
$pendingWd = fetch_all("SELECT w.id, w.amount, u.full_name, w.created_at FROM withdrawal_requests w JOIN users u ON u.id=w.user_id WHERE w.status='pending' ORDER BY w.id LIMIT 5");
$pageTitle = 'Admin dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('dashboard') ?>
  <div class="page-head"><div><h1>Administrator dashboard</h1><p>Platform overview &middot; <?= e(date('d M Y')) ?></p></div></div>
  <div class="stats"><?php foreach ($stats as [$label, $val]): ?><div class="stat"><div class="stat-value"><?= e($val) ?></div><div class="stat-label"><?= e($label) ?></div></div><?php endforeach; ?></div>
  <div class="grid grid-2">
    <?php foreach (['Users by role' => $byRole, 'Task submissions by status' => $bySub] as $title => $data): $max = $data ? max($data) : 0; ?>
      <div class="card"><h3><?= e($title) ?></h3><?php if (!$data): ?><div class="empty"><p>No data yet.</p></div><?php endif; ?><div class="bars">
        <?php foreach ($data as $k => $v): ?><div class="bar-row"><span><?= e(ucfirst($k)) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= $max ? round($v / $max * 100) : 0 ?>%"></div></div><span class="val"><?= (int)$v ?></span></div><?php endforeach; ?></div></div>
    <?php endforeach; ?>
    <div class="card"><div class="card-head"><h3>Submissions waiting</h3><a href="<?= e(url('tasks/submissions.php')) ?>">Review</a></div>
      <?php if (!$pendingSubs): ?><div class="empty"><p>Nothing to review.</p></div><?php endif; ?>
      <?php foreach ($pendingSubs as $s): ?><div class="notif"><span><?= e($s['title']) ?><br><small><?= e($s['full_name']) ?></small></span><small><?= e(fmt_date($s['submitted_at'])) ?></small></div><?php endforeach; ?></div>
    <div class="card"><div class="card-head"><h3>Withdrawals waiting</h3><a href="<?= e(url('admin/transactions.php')) ?>">Review</a></div>
      <?php if (!$pendingWd): ?><div class="empty"><p>No pending withdrawals.</p></div><?php endif; ?>
      <?php foreach ($pendingWd as $w): ?><div class="notif"><span><?= e($w['full_name']) ?></span><b><?= e(money($w['amount'])) ?></b></div><?php endforeach; ?></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
