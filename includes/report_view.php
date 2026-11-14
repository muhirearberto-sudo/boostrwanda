<?php
/**
 * Shared report UI (filters + summary + CSS bar chart + table). Expects:
 *   $role (admin|school), $f (filters), $report (from report_data()), $action (form action path)
 */
$types = REPORT_TYPES_BY_ROLE[$role];
$max = $report['chart'] ? max($report['chart']) : 0;
$isMoney = in_array($f['type'], ['earnings'], true) || $f['type'] === 'payments' || $f['type'] === 'campaigns';
?>
<form class="filters card" method="get" action="<?= e(url($action)) ?>" data-no-loading style="padding:1rem">
  <div class="form-group"><label for="type">Report</label><select id="type" name="type"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($f['from']) ?>"></div>
  <div class="form-group"><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($f['to']) ?>"></div>
  <div class="form-group"><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>
    <?php foreach (array_unique(array_merge(...array_values(REPORT_STATUSES))) as $s): ?><option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select>
    <div class="help">Applies where the report has statuses.</div></div>
  <button class="btn btn-navy" type="submit">Generate</button>
  <a class="btn btn-outline" href="?<?= e(http_build_query(array_merge($f, ['export' => 'csv']))) ?>">Download CSV</a>
</form>

<div class="card-head"><h2><?= e($report['title']) ?></h2>
  <small><?= $f['from'] || $f['to'] ? e(($f['from'] ?: 'start') . ' to ' . ($f['to'] ?: 'today')) : 'All dates' ?><?= $f['status'] ? ' &middot; status: ' . e($f['status']) : '' ?></small></div>
<div class="stats">
  <?php foreach ($report['summary'] as $label => $val): ?><div class="stat"><div class="stat-value"><?= e($val) ?></div><div class="stat-label"><?= e($label) ?></div></div><?php endforeach; ?>
</div>
<?php if ($report['chart']): ?>
<div class="card"><h3><?= e($report['chart_title']) ?></h3><div class="bars">
  <?php foreach ($report['chart'] as $label => $val): ?>
    <div class="bar-row"><span><?= e(ucfirst((string)$label)) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= $max > 0 ? round($val / $max * 100) : 0 ?>%"></div></div><span class="val"><?= e(number_format($val, $val == floor($val) ? 0 : 2)) ?></span></div>
  <?php endforeach; ?></div></div>
<?php endif; ?>
<div class="table-wrap"><table>
  <thead><tr><?php foreach ($report['columns'] as $i => $c): ?><th class="<?= in_array($i, $report['money_cols'], true) ? 'num' : '' ?>"><?= e($c) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php if (!$report['rows']): ?><tr><td colspan="<?= count($report['columns']) ?>"><div class="empty"><h3>No records match these filters</h3><p>Try a wider date range or a different status.</p></div></td></tr><?php endif; ?>
  <?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($row as $i => $cell): $m = in_array($i, $report['money_cols'], true); ?>
    <td class="<?= $m ? 'num' : '' ?>"><?= e($m && is_numeric($cell) ? number_format((float)$cell, 2) : $cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
<p class="help">Showing up to 200 rows. Download the CSV for the full list. Every figure is calculated live from the platform's records.</p>
