<?php
/** Withdrawal request + tracking. Funds are held immediately; an administrator approves or rejects. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
$min = (float)setting('min_withdrawal', 5000);
$errors = [];

if (is_post()) {
    csrf_verify();
    $amount = parse_amount($_POST['amount'] ?? '');
    $method = $_POST['method'] ?? '';
    $acct = preg_replace('/[\s-]/', '', clean($_POST['account'] ?? '', 40));
    if ($amount === null) { $errors[] = 'Enter a valid amount.'; }
    elseif ((float)$amount < $min) { $errors[] = 'Minimum withdrawal is ' . money($min) . '.'; }
    if (!in_array($method, ['mobile_money', 'bank'], true)) { $errors[] = 'Choose a withdrawal method.'; }
    elseif ($method === 'mobile_money' && !valid_phone($acct)) { $errors[] = 'Enter a valid mobile money number.'; }
    elseif ($method === 'bank' && !preg_match('/^[0-9]{6,20}$/', $acct)) { $errors[] = 'Enter a valid bank account number (digits only).'; }
    if (!$errors) {
        [$ok, $msg] = request_withdrawal($uid, $amount, $method, $acct);
        if ($ok) { flash('success', $msg); redirect('wallet/withdraw.php'); }
        $errors[] = $msg;
    }
}
$w = wallet_of($uid);
$reqs = fetch_all('SELECT * FROM withdrawal_requests WHERE user_id=? ORDER BY id DESC LIMIT 20', [$uid]);
$pageTitle = 'Withdraw';
$extraJs = ['wallet.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><div><h1>Withdraw</h1><p>Available: <b><?= e(money($w['balance'])) ?></b> &middot; Minimum <?= e(money($min)) ?></p></div></div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" data-confirm="Submit this withdrawal request? The amount will be held until reviewed."><?= csrf_field() ?>
      <div class="form-row">
        <div class="form-group"><label for="amount">Amount (<?= e(app_config()['currency']) ?>)</label><input id="amount" name="amount" inputmode="decimal" required data-min="<?= $min ?>" data-max="<?= e((float)$w['balance']) ?>" value="<?= old('amount') ?>"><div id="amount-hint" class="help"></div></div>
        <div class="form-group"><label for="method">Method</label><select id="method" name="method"><option value="mobile_money" <?= ($_POST['method'] ?? '') !== 'bank' ? 'selected' : '' ?>>Mobile money</option><option value="bank" <?= ($_POST['method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank transfer</option></select></div>
      </div>
      <div class="form-group"><label for="account" id="account-label">Mobile money number</label><input id="account" name="account" value="<?= old('account') ?>" required maxlength="40"><div class="help">Never enter card numbers or PINs. Only a phone or account number is needed.</div></div>
      <button class="btn btn-primary" type="submit">Request withdrawal</button>
    </form>
  </div>
  <div class="card"><h2>My withdrawal requests</h2>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th class="num">Amount</th><th>Method</th><th>Account</th><th>Status</th><th>Admin note</th></tr></thead><tbody>
      <?php if (!$reqs): ?><tr><td colspan="6"><div class="empty"><p>No withdrawal requests yet.</p></div></td></tr><?php endif; ?>
      <?php foreach ($reqs as $r): ?><tr><td><?= e(fmt_date($r['created_at'])) ?></td><td class="num"><?= e(number_format((float)$r['amount'], 2)) ?></td><td><?= e(str_replace('_', ' ', $r['method'])) ?></td>
        <td><?= e(substr($r['account_details'], 0, 3) . str_repeat('*', max(0, strlen($r['account_details']) - 5)) . substr($r['account_details'], -2)) ?></td>
        <td><?= status_badge($r['status']) ?></td><td><?= e($r['admin_note'] ?: '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
