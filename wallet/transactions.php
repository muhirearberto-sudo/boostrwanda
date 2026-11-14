<?php
/** Full transaction history with filters and pagination. Users only ever see their own rows. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
$type = $_GET['type'] ?? ''; $status = $_GET['status'] ?? ''; $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$w = ['user_id=?']; $p = [$uid];
if (in_array($type, ['earning', 'deposit', 'withdrawal'], true)) { $w[] = 'type=?'; $p[] = $type; } else { $type = ''; }
if (in_array($status, ['pending', 'completed', 'failed'], true)) { $w[] = 'status=?'; $p[] = $status; } else { $status = ''; }
if (valid_date($from)) { $w[] = 'created_at >= ?'; $p[] = $from . ' 00:00:00'; } else { $from = ''; }
if (valid_date($to)) { $w[] = 'created_at <= ?'; $p[] = $to . ' 23:59:59'; } else { $to = ''; }
$where = implode(' AND ', $w);
$total = (int)fetch_val("SELECT COUNT(*) FROM transactions WHERE $where", $p);
$pg = paginate($total, 15);
$recent = fetch_all("SELECT * FROM transactions WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
$pageTitle = 'Transaction history';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Transaction history</h1><p><?= $total ?> record(s)</p></div><a class="btn btn-outline" href="<?= e(url('wallet/index.php')) ?>">&larr; Wallet</a></div>
  <form class="filters" method="get" data-no-loading>
    <div class="form-group"><label for="type">Type</label><select id="type" name="type"><option value="">All</option><?php foreach (['earning', 'deposit', 'withdrawal'] as $o): ?><option <?= $type === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (['pending', 'completed', 'failed'] as $o): ?><option <?= $status === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div>
    <div class="form-group"><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div>
    <button class="btn btn-navy" type="submit">Filter</button>
  </form>
  <?php include __DIR__ . '/_tx_table.php'; ?>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
