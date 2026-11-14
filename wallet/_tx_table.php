<?php /** Shared transaction table. Expects $recent (array of transaction rows). */ ?>
if (!isset($recent)) { http_response_code(404); exit; }   // partial: not a standalone page
<div class="table-wrap"><table>
  <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Description</th><th class="num">Amount</th><th>Status</th></tr></thead>
  <tbody>
  <?php if (!$recent): ?><tr><td colspan="6"><div class="empty"><h3>No transactions yet</h3><p>Approved task earnings, deposits and withdrawals will appear here.</p></div></td></tr><?php endif; ?>
  <?php foreach ($recent as $t): $in = in_array($t['type'], ['earning', 'deposit'], true); ?>
    <tr><td><?= e(fmt_date($t['created_at'])) ?></td><td><small><?= e($t['reference']) ?></small></td><td><?= e(ucfirst($t['type'])) ?></td>
      <td><?= e($t['description']) ?></td>
      <td class="num <?= $in ? 'tx-in' : 'tx-out' ?>"><?= $in ? '+' : '-' ?><?= e(number_format((float)$t['amount'], 2)) ?></td><td><?= status_badge($t['status']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
