<?php
/**
 * SANDBOX PAYMENT GATEWAY SIMULATOR (stands in for the DFD "Payment Gateway" external entity).
 * The tester chooses the outcome; the server then applies it with the same idempotent function a real
 * gateway webhook would use (api/payment_webhook.php). Nothing here is real money.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
if (app_config()['payment']['mode'] !== 'sandbox' || setting('sandbox_gateway_enabled', '1') !== '1') { http_response_code(404); exit('Sandbox gateway disabled.'); }

$ref = (string)($_GET['ref'] ?? $_POST['ref'] ?? '');
$pr = fetch_one('SELECT * FROM payment_requests WHERE gateway_ref=? AND user_id=?', [$ref, $uid]);   // must belong to this user
if (!$pr) { http_response_code(404); flash('error', 'Payment request not found.'); redirect('wallet/deposit.php'); }

if (is_post()) {
    csrf_verify();
    $result = ($_POST['result'] ?? '') === 'success' ? 'success' : 'failed';
    [$ok, $msg] = process_payment_result($ref, $result);          // idempotent: a second click does nothing
    flash($ok ? ($result === 'success' ? 'success' : 'warning') : 'error', $msg);
    redirect('wallet/index.php');
}
$pageTitle = 'Sandbox payment';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="gateway-box">
    <header><b>Sandbox Payment Gateway</b><br><small style="color:#b7c8ea">TEST MODE - no real money</small></header>
    <div class="body">
      <dl class="kv"><dt>Merchant</dt><dd>BoostRwanda</dd><dt>Reference</dt><dd><?= e($pr['gateway_ref']) ?></dd>
        <dt>Amount</dt><dd><?= e(money($pr['amount'])) ?></dd><dt>Status</dt><dd><?= status_badge($pr['status']) ?></dd></dl>
      <?php if ($pr['status'] === 'pending'): ?>
        <div class="demo-banner" style="margin-top:1rem">Choose the outcome to simulate. The wallet is only credited on success.</div>
        <form method="post" style="margin-bottom:.6rem"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($ref) ?>"><input type="hidden" name="result" value="success"><button class="btn btn-ok btn-block" type="submit">Simulate successful payment</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($ref) ?>"><input type="hidden" name="result" value="failed"><button class="btn btn-danger btn-block" type="submit">Simulate failed payment</button></form>
      <?php else: ?><p style="margin-top:1rem">This payment was already processed.</p><a class="btn btn-primary btn-block" href="<?= e(url('wallet/index.php')) ?>">Back to wallet</a><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
