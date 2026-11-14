<?php
/**
 * Members/Freelancers: track their own submissions and statuses.
 * Admins: review submissions (approve -> wallet credited; reject -> reason sent to user).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'admin']);
$isAdmin = $user['role'] === 'admin';
$uid = (int)$user['id'];

if (is_post()) {
    csrf_verify();
    require_role(['admin']);                                   // server-side check, regardless of what the form showed
    $sid = (int)($_POST['submission_id'] ?? 0);
    if (($_POST['decision'] ?? '') === 'approve') { [$ok, $msg] = approve_submission($sid, $uid); }
    elseif (($_POST['decision'] ?? '') === 'reject') {
        $note = clean($_POST['note'] ?? '', 255);
        [$ok, $msg] = $note === '' ? [false, 'Please give a reason for rejecting.'] : reject_submission($sid, $uid, $note);
    } else { [$ok, $msg] = [false, 'Unknown action.']; }
    flash($ok ? 'success' : 'error', $msg);
    redirect('tasks/submissions.php' . (isset($_POST['back']) ? '?' . http_build_query(['status' => $_POST['back']]) : ''));
}

$status = $_GET['status'] ?? ($isAdmin ? 'submitted' : '');
$taskId = (int)($_GET['task_id'] ?? 0);
$where = ['1=1']; $params = [];
if (!$isAdmin) { $where[] = 's.user_id=?'; $params[] = $uid; }
if (in_array($status, ['accepted', 'submitted', 'approved', 'rejected'], true)) { $where[] = 's.status=?'; $params[] = $status; } else { $status = ''; }
if ($isAdmin && $taskId) { $where[] = 's.task_id=?'; $params[] = $taskId; }
$w = implode(' AND ', $where);

$total = (int)fetch_val("SELECT COUNT(*) FROM task_submissions s WHERE $w", $params);
$pg = paginate($total, 10);
$rows = fetch_all("SELECT s.*, t.title, t.reward, u.full_name, u.member_id FROM task_submissions s
                   JOIN tasks t ON t.id=s.task_id JOIN users u ON u.id=s.user_id
                   WHERE $w ORDER BY (s.status='submitted') DESC, s.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$pageTitle = $isAdmin ? 'Review submissions' : 'My work';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?php if ($isAdmin) { echo admin_subnav('tasks'); } ?>
  <div class="page-head"><div><h1><?= e($pageTitle) ?></h1><p><?= $isAdmin ? 'Approve to pay the reward into the user\'s wallet, or reject with a reason.' : 'Track the tasks you accepted and their approval status.' ?></p></div></div>
  <form class="filters" method="get" data-no-loading>
    <?php if ($taskId): ?><input type="hidden" name="task_id" value="<?= $taskId ?>"><?php endif; ?>
    <div class="form-group"><label for="status">Status</label><select id="status" name="status" onchange="this.form.submit()">
      <option value="">All</option><?php foreach (['accepted', 'submitted', 'approved', 'rejected'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Task</th><?= $isAdmin ? '<th>User</th>' : '' ?><th class="num">Reward</th><th>Status</th><th>Proof</th><th><?= $isAdmin ? 'Review' : 'Feedback' ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><h3>Nothing here yet</h3><p><?= $isAdmin ? 'No submissions match this filter.' : 'Accept a task to start earning.' ?></p></div></td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('tasks/details.php?id=' . $r['task_id'])) ?>"><?= e($r['title']) ?></a><br><small><?= e(fmt_date($r['submitted_at'] ?: $r['accepted_at'])) ?></small></td>
        <?= $isAdmin ? '<td>' . e($r['full_name']) . '<br><small>' . e($r['member_id']) . '</small></td>' : '' ?>
        <td class="num"><?= e(money($r['reward'])) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td><?php if ($r['proof_note']): ?><div class="pre" style="max-width:260px"><?= e(excerpt($r['proof_note'], 160)) ?></div><?php endif; ?>
            <?php if ($r['proof_file']): ?><a href="<?= e(url('tasks/proof.php?id=' . $r['id'])) ?>" target="_blank" rel="noopener"><?= e($r['proof_orig'] ?: 'View file') ?></a><?php endif; ?>
            <?php if (!$r['proof_note'] && !$r['proof_file']) echo '-'; ?></td>
        <td>
          <?php if ($isAdmin && $r['status'] === 'submitted'): ?>
            <div class="actions">
              <form method="post" data-confirm="Approve and pay <?= e(money($r['reward'])) ?>?"><?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approve"><button class="btn btn-ok btn-sm" type="submit">Approve</button></form>
              <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="reject"><input name="note" placeholder="Reason" required maxlength="255" style="width:130px;padding:.3rem .5rem"><button class="btn btn-danger btn-sm" type="submit">Reject</button></form>
            </div>
          <?php elseif (!$isAdmin && in_array($r['status'], ['accepted', 'rejected'], true)): ?>
            <?= $r['review_note'] ? '<small>' . e($r['review_note']) . '</small><br>' : '' ?><a class="btn btn-primary btn-sm" href="<?= e(url('tasks/submit.php?task_id=' . $r['task_id'])) ?>"><?= $r['status'] === 'rejected' ? 'Resubmit' : 'Submit proof' ?></a>
          <?php else: ?><small><?= e($r['review_note'] ?: ($r['status'] === 'approved' ? 'Paid to wallet' : '-')) ?></small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
