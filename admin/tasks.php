<?php
/** Admin task management: list, open/close, jump to edit/review. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['task_id'] ?? 0); $to = ($_POST['status'] ?? '') === 'open' ? 'open' : 'closed';
    q('UPDATE tasks SET status=? WHERE id=?', [$to, $id]);
    audit_log('task_status', "Task #$id set to $to");
    flash('success', "Task #$id is now $to.");
    redirect('admin/tasks.php');
}
$pg = paginate((int)fetch_val('SELECT COUNT(*) FROM tasks'), 12);
$rows = fetch_all("SELECT t.*, c.name AS category, (SELECT COUNT(*) FROM task_submissions s WHERE s.task_id=t.id) taken,
                   (SELECT COUNT(*) FROM task_submissions s WHERE s.task_id=t.id AND s.status='submitted') pending
                   FROM tasks t JOIN categories c ON c.id=t.category_id ORDER BY t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$pageTitle = 'Manage tasks';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('tasks') ?>
  <div class="page-head"><div><h1>Tasks</h1></div><div class="actions"><a class="btn btn-outline" href="<?= e(url('tasks/submissions.php')) ?>">Review submissions</a><a class="btn btn-primary" href="<?= e(url('tasks/create.php')) ?>">+ New task</a></div></div>
  <div class="table-wrap"><table><thead><tr><th>#</th><th>Task</th><th>Category</th><th class="num">Reward</th><th>Slots</th><th>Pending review</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="empty"><h3>No tasks yet</h3><p>Create the first task.</p></div></td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?><tr><td><?= (int)$t['id'] ?></td><td><a href="<?= e(url('tasks/details.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a></td><td><?= e($t['category']) ?></td>
      <td class="num"><?= e(number_format((float)$t['reward'], 2)) ?></td><td><?= (int)$t['taken'] ?>/<?= (int)$t['slots'] ?></td><td><?= (int)$t['pending'] ?></td><td><?= status_badge($t['status']) ?></td>
      <td><div class="actions"><a class="btn btn-outline btn-sm" href="<?= e(url('tasks/create.php?id=' . $t['id'])) ?>">Edit</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="<?= $t['status'] === 'open' ? 'closed' : 'open' ?>"><button class="btn btn-light btn-sm" type="submit"><?= $t['status'] === 'open' ? 'Close' : 'Reopen' ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
