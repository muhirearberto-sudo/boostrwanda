<?php
/** Advertiser dashboard: campaign status + budget summary (DFD output "campaign report / status"). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['advertiser']);
$uid = (int)$user['id'];
$by = [];
foreach (fetch_all('SELECT status, COUNT(*) n, COALESCE(SUM(budget),0) b FROM campaigns WHERE advertiser_id=? GROUP BY status', [$uid]) as $r) { $by[$r['status']] = $r; }
$cnt = fn($s) => (int)($by[$s]['n'] ?? 0);
$totalBudget = array_sum(array_map(fn($r) => (float)$r['b'], $by));
$recent = fetch_all('SELECT * FROM campaigns WHERE advertiser_id=? ORDER BY id DESC LIMIT 5', [$uid]);
$pageTitle = 'Advertiser dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Advertiser dashboard</h1><p><?= e($user['full_name']) ?> &middot; <?= e($user['member_id']) ?></p></div><a class="btn btn-primary" href="<?= e(url('advertiser/campaigns.php?new=1')) ?>">+ New campaign</a></div>
  <div class="stats">
    <div class="stat"><div class="stat-value"><?= array_sum(array_map(fn($r) => (int)$r['n'], $by)) ?></div><div class="stat-label">Total campaigns</div></div>
    <div class="stat"><div class="stat-value"><?= $cnt('active') ?></div><div class="stat-label">Active</div></div>
    <div class="stat"><div class="stat-value"><?= $cnt('pending') ?></div><div class="stat-label">Awaiting approval</div></div>
    <div class="stat"><div class="stat-value"><?= e(number_format($totalBudget)) ?></div><div class="stat-label">Total budget (<?= e(app_config()['currency']) ?>)</div></div>
  </div>
  <div class="card"><div class="card-head"><h2>Campaign report</h2><a href="<?= e(url('advertiser/campaigns.php')) ?>">Manage campaigns</a></div>
    <div class="table-wrap"><table><thead><tr><th>Campaign</th><th>Period</th><th class="num">Budget</th><th>Status</th><th>Admin note</th></tr></thead><tbody>
      <?php if (!$recent): ?><tr><td colspan="5"><div class="empty"><h3>No campaigns yet</h3><p>Create your first campaign and submit it for approval.</p></div></td></tr><?php endif; ?>
      <?php foreach ($recent as $c): ?><tr><td><?= e($c['title']) ?></td><td><?= e(fmt_date($c['start_date'], false)) ?> &rarr; <?= e(fmt_date($c['end_date'], false)) ?></td><td class="num"><?= e(money($c['budget'])) ?></td><td><?= status_badge($c['status']) ?></td><td><?= e($c['admin_note'] ?: '-') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="help">Impression and click tracking are not part of this version, so the report shows only status and budget.</p></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
