<?php
/**
 * Deposit request. Creates a PENDING payment request only; the wallet is credited later,
 * and only after the payment gateway (sandbox) reports success.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
$min = (float)setting('min_deposit', 1000); $max = (float)setting('max_deposit', 500000);
$enabled = setting('sandbox_gateway_enabled', '1') === '1';
$errors = [];

if (is_post()) {
    csrf_verify();
    $amount = parse_amount($_POST['amount'] ?? '');
    if (!$enabled) { $errors[] = 'Deposits are currently disabled by the administrator.'; }
    elseif ($amount === null) { $errors[] = 'Enter a valid amount (positive, up to 2 decimals).'; }
    elseif ((float)$amount < $min || (float)$amount > $max) { $errors[] = 'Amount must be between ' . money($min) . ' and ' . money($max) . '.'; }
    else { redirect('wallet/sandbox.php?ref=' . urlencode(create_deposit_request($uid, $amount))); }
}
$pending = fetch_all("SELECT gateway_ref, amount, created_at FROM payment_requests WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 5", [$uid]);
$pageTitle = 'Deposit';
$extraJs = ['wallet.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container narrow">
  <div class="page-head"><h1>Deposit</h1></div>
  <div class="demo-banner">SANDBOX / DEMO payment gateway. No real money is charged or moved. A production gateway needs merchant credentials (see README).</div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="form-group"><label for="amount">Amount (<?= e(app_config()['currency']) ?>)</label>
        <input id="amount" name="amount" inputmode="decimal" required data-min="<?= $min ?>" data-max="<?= $max ?>" value="<?= old('amount') ?>"><div id="amount-hint" class="help"></div>
        <div class="help">Min <?= e(money($min)) ?> &middot; Max <?= e(money($max)) ?></div></div>
      <button class="btn btn-primary btn-block" type="submit" <?= $enabled ? '' : 'disabled' ?>>Continue to payment gateway</button>
    </form>
  </div>
  <?php if ($pending): ?><div class="card"><h3>Unfinished payments</h3>
    <?php foreach ($pending as $p): ?><div class="notif"><span><?= e(money($p['amount'])) ?> &middot; <small><?= e($p['gateway_ref']) ?></small></span><a href="<?= e(url('wallet/sandbox.php?ref=' . urlencode($p['gateway_ref']))) ?>">Resume</a></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
