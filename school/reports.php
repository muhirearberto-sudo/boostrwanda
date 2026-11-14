<?php
/** School management reports: users, tasks, earnings and payments ONLY (see REPORT_TYPES_BY_ROLE). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['school']);
$f = report_filters($_GET, 'school');           // a forged ?type=campaigns is silently replaced by an allowed type
$report = report_data($f, 'school', ($_GET['export'] ?? '') === 'csv' ? 5000 : 200);
if (($_GET['export'] ?? '') === 'csv') { audit_log('report_export', 'School exported ' . $f['type']); report_csv($report, 'school_' . $f['type']); }
$pageTitle = 'School reports'; $role = 'school'; $action = 'school/reports.php';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Reports</h1><p>Request a report by type and date range.</p></div><a class="btn btn-outline" href="<?= e(url('school/dashboard.php')) ?>">&larr; Dashboard</a></div>
  <?php include __DIR__ . '/../includes/report_view.php'; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
