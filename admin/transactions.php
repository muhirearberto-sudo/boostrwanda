<?php
/** Financial oversight: review withdrawal requests, browse all transactions and gateway payment requests. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$me = (int)$user['id'];

if (is_post()) {
    csrf_verify();
    $dec = $_POST['decision'] ?? '';
    if (in_array($dec, ['approve', 'reject'], true)) {
        $note = clean($_POST['note'] ?? '', 255);
        if ($dec === 'reject' && $note === '') { flash('error', 'Please give a reason for rejecting.'); }
        else { [$ok, $msg] = review_withdrawal((int)($_POST['withdrawal_id'] ?? 0), $me, $dec, $note); flash($ok ? 'success' : 'error', $msg); }
    }
    redirect('admin/transactions.php');
}
$pending = fetch_all("SELECT w.*, u.full_name, u.member_id FROM withdrawal_requests w JOIN users u ON u.id=w.user_id WHERE w.status='pending' ORDER BY w.id");
$type = in_array($_GET['type'] ?? '', ['earning', 'deposit', 'withdrawal'], true) ? $_GET['type'] : '';
$status = in_array($_GET['status'] ?? '', ['pending', 'completed', 'failed'], true) ? $_GET['status'] : '';
$w = ['1=1']; $p = [];
if ($type) { $w[] = 'tr.type=?'; $p[] = $type; } if ($status) { $w[] = 'tr.status=?'; $p[] = $status; }
$where = implode(' AND ', $w);
$pg = paginate((int)fetch_val("SELECT COUNT(*) FROM transactions tr WHERE $where", $p), 15);
$tx = fetch_all("SELECT tr.*, u.full_name, u.member_id FROM transactions tr JOIN users u ON u.id=tr.user_id WHERE $where ORDER BY tr.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
$gateway = fetch_all('SELECT pr.*, u.full_name FROM payment_requests pr JOIN users u ON u.id=pr.user_id ORDER BY pr.id DESC LIMIT 10');
$pageTitle = 'Transactions';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('transactions') ?>
  <div class="page-head"><div><h1>Transactions &amp; payments</h1></div></div>
  <div class="card"><h2>Withdrawal requests awaiting review (<?= count($pending) ?>)</h2>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>User</th><th class="num">Amount</th><th>Method</th><th>Account</th><th>Decision</th></tr></thead><tbody>
      <?php if (!$pending): ?><tr><td colspan="6"><div class="empty"><p>No pending withdrawals.</p></div></td></tr><?php endif; ?>
      <?php foreach ($pending as $r): ?><tr><td><?= e(fmt_date($r['created_at'])) ?></td><td><?= e($r['full_name']) ?><br><small><?= e($r['member_id']) ?></small></td><td class="num"><b><?= e(number_format((float)$r['amount'], 2)) ?></b></td>
        <td><?= e(str_replace('_', ' ', $r['method'])) ?></td><td><?= e($r['account_details']) ?></td>
        <td><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="withdrawal_id" value="<?= (int)$r['id'] ?>"><input name="note" placeholder="Note (required to reject)" maxlength="255" style="width:170px;padding:.3rem .5rem">
          <button class="btn btn-ok btn-sm" name="decision" value="approve" type="submit" data-no-loading>Approve</button><button class="btn btn-danger btn-sm" name="decision" value="reject" type="submit" data-no-loading>Reject</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <p class="help">Approving records the payout as completed (the actual mobile-money/bank transfer is made outside this system). Rejecting returns the held funds to the user's wallet.</p></div>

  <div class="card"><h2>All transactions</h2>
    <form class="filters" method="get" data-no-loading>
      <div class="form-group"><label for="type">Type</label><select id="type" name="type"><option value="">All</option><?php foreach (['earning', 'deposit', 'withdrawal'] as $o): ?><option <?= $type === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (['pending', 'completed', 'failed'] as $o): ?><option <?= $status === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-navy" type="submit">Filter</button></form>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>User</th><th>Reference</th><th>Type</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
      <?php if (!$tx): ?><tr><td colspan="6"><div class="empty"><p>No transactions match.</p></div></td></tr><?php endif; ?>
      <?php foreach ($tx as $t): ?><tr><td><?= e(fmt_date($t['created_at'])) ?></td><td><?= e($t['full_name']) ?> <small><?= e($t['member_id']) ?></small></td><td><small><?= e($t['reference']) ?></small></td><td><?= e($t['type']) ?></td><td class="num"><?= e(number_format((float)$t['amount'], 2)) ?></td><td><?= status_badge($t['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?= pagination_html($pg) ?></div>

  <div class="card"><h2>Latest payment gateway requests</h2>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>User</th><th>Gateway ref</th><th>Provider</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
      <?php if (!$gateway): ?><tr><td colspan="6"><div class="empty"><p>No payment requests yet.</p></div></td></tr><?php endif; ?>
      <?php foreach ($gateway as $g): ?><tr><td><?= e(fmt_date($g['created_at'])) ?></td><td><?= e($g['full_name']) ?></td><td><small><?= e($g['gateway_ref']) ?></small></td><td><?= e($g['provider']) ?></td><td class="num"><?= e(number_format((float)$g['amount'], 2)) ?></td><td><?= status_badge($g['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
