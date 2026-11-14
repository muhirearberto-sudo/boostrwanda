<?php
/** Wallet dashboard: balance + recent transactions. Balance is read-only here; it only changes via includes/wallet_lib.php. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
$w = wallet_of($uid);
$held = fetch_val("SELECT COALESCE(SUM(amount),0) FROM withdrawal_requests WHERE user_id=? AND status='pending'", [$uid]);
$earned = fetch_val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='earning' AND status='completed'", [$uid]);
$recent = fetch_all('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 6', [$uid]);
$pageTitle = 'Wallet';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="wallet-hero">
    <div><small>Available balance</small><div class="bal"><?= e(money($w['balance'])) ?></div>
      <small>Total earned: <?= e(money($earned)) ?> &middot; Held for pending withdrawals: <?= e(money($held)) ?></small></div>
    <div class="actions"><a class="btn btn-gold" href="<?= e(url('wallet/deposit.php')) ?>">Deposit</a><a class="btn btn-light" href="<?= e(url('wallet/withdraw.php')) ?>">Withdraw</a></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Recent transactions</h2><a href="<?= e(url('wallet/transactions.php')) ?>">Full history</a></div>
    <?php include __DIR__ . '/_tx_table.php'; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
