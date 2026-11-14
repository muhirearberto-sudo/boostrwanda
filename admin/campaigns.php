<?php
/** Review advertiser campaigns: approve, reject (with reason) or mark completed. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['campaign_id'] ?? 0); $act = $_POST['decision'] ?? '';
    $c = fetch_one('SELECT * FROM campaigns WHERE id=?', [$id]);
    $note = clean($_POST['note'] ?? '', 255);
    // [from-statuses => new status]
    $flow = ['approve' => [['pending'], 'active'], 'reject' => [['pending', 'active', 'paused'], 'rejected'], 'complete' => [['active', 'paused'], 'completed']];
    if (!$c || !isset($flow[$act]) || !in_array($c['status'], $flow[$act][0], true)) { flash('error', 'That change is not allowed.'); }
    elseif ($act === 'reject' && $note === '') { flash('error', 'Please give a reason for rejecting.'); }
    else {
        q('UPDATE campaigns SET status=?, admin_note=? WHERE id=?', [$flow[$act][1], $note ?: null, $id]);
        audit_log('campaign_' . $act, "Campaign #$id -> " . $flow[$act][1]);
        notify((int)$c['advertiser_id'], 'Your campaign "' . $c['title'] . '" is now ' . $flow[$act][1] . ($note ? ': ' . $note : '.'), 'advertiser/campaigns.php');
        flash('success', 'Campaign ' . $flow[$act][1] . '.');
    }
    redirect('admin/campaigns.php');
}
$status = in_array($_GET['status'] ?? '', REPORT_STATUSES['campaigns'], true) ? $_GET['status'] : '';
$pg = paginate((int)fetch_val('SELECT COUNT(*) FROM campaigns' . ($status ? ' WHERE status=?' : ''), $status ? [$status] : []), 10);
$rows = fetch_all('SELECT c.*, u.full_name FROM campaigns c JOIN users u ON u.id=c.advertiser_id' . ($status ? ' WHERE c.status=?' : '') . " ORDER BY (c.status='pending') DESC, c.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $status ? [$status] : []);
$pageTitle = 'Campaigns';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('campaigns') ?>
  <div class="page-head"><div><h1>Advertiser campaigns</h1></div></div>
  <form class="filters" method="get" data-no-loading><div class="form-group"><label for="status">Status</label><select id="status" name="status" onchange="this.form.submit()"><option value="">All</option><?php foreach (REPORT_STATUSES['campaigns'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div></form>
  <div class="table-wrap"><table><thead><tr><th>Campaign</th><th>Advertiser</th><th>Period</th><th class="num">Budget</th><th>Status</th><th>Decision</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><p>No campaigns.</p></div></td></tr><?php endif; ?>
    <?php foreach ($rows as $c): ?><tr><td><b><?= e($c['title']) ?></b><br><small class="pre"><?= e(excerpt($c['ad_text'], 140)) ?></small><?= $c['target_url'] ? '<br><small>' . e($c['target_url']) . '</small>' : '' ?></td><td><?= e($c['full_name']) ?></td>
      <td><?= e(fmt_date($c['start_date'], false)) ?> &rarr; <?= e(fmt_date($c['end_date'], false)) ?></td><td class="num"><?= e(number_format((float)$c['budget'], 2)) ?></td><td><?= status_badge($c['status']) ?></td>
      <td><?php if (in_array($c['status'], ['pending', 'active', 'paused'], true)): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>"><input name="note" placeholder="Note" maxlength="255" style="width:120px;padding:.3rem .5rem">
        <?php if ($c['status'] === 'pending'): ?><button class="btn btn-ok btn-sm" name="decision" value="approve" type="submit">Approve</button><?php else: ?><button class="btn btn-light btn-sm" name="decision" value="complete" type="submit">Complete</button><?php endif; ?>
        <button class="btn btn-danger btn-sm" name="decision" value="reject" type="submit">Reject</button></form><?php else: ?><small><?= e($c['admin_note'] ?: '-') ?></small><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
