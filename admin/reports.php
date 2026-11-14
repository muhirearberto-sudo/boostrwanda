<?php
/** Administrator reports (all types incl. advertiser campaigns). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$f = report_filters($_GET, 'admin');
$csv = ($_GET['export'] ?? '') === 'csv';
$report = report_data($f, 'admin', $csv ? 5000 : 200);
if ($csv) { audit_log('report_export', 'Admin exported ' . $f['type']); report_csv($report, 'admin_' . $f['type']); }
$pageTitle = 'Reports'; $role = 'admin'; $action = 'admin/reports.php';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('reports') ?>
  <div class="page-head"><div><h1>Reports &amp; analytics</h1></div></div>
  <?php include __DIR__ . '/../includes/report_view.php'; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
