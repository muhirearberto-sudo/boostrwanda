<?php
/** School management overview: headline figures for the reports they are allowed to receive. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['school']);
$blank = ['from' => '', 'to' => '', 'status' => ''];
$cards = [];
foreach (['users' => 'Users', 'tasks' => 'Tasks', 'earnings' => 'Earnings', 'payments' => 'Payments'] as $t => $label) {
    $cards[$t] = report_data(['type' => $t] + $blank, 'school', 0)['summary'];
}
$pageTitle = 'School dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>School management</h1><p>All-time summary. Use Reports to filter by date range and status.</p></div><a class="btn btn-primary" href="<?= e(url('school/reports.php')) ?>">Open reports</a></div>
  <?php foreach ($cards as $t => $sum): ?>
    <h2 style="margin-top:1.2rem"><a href="<?= e(url('school/reports.php?type=' . $t)) ?>" style="text-decoration:none"><?= e(ucfirst($t)) ?> &rarr;</a></h2>
    <div class="stats"><?php foreach ($sum as $label => $val): ?><div class="stat"><div class="stat-value"><?= e($val) ?></div><div class="stat-label"><?= e($label) ?></div></div><?php endforeach; ?></div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
