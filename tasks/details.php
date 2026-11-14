<?php
/** Task details + "accept task". Reward/slots/status always come from the database. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'admin']);
$uid = (int)$user['id'];
$id = (int)($_GET['id'] ?? 0);

$task = fetch_one('SELECT t.*, c.name AS category, u.full_name AS creator,
                     (SELECT COUNT(*) FROM task_submissions s WHERE s.task_id=t.id) AS taken
                   FROM tasks t JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.creator_id WHERE t.id=?', [$id]);
if (!$task) { http_response_code(404); flash('error', 'Task not found.'); redirect('tasks/index.php'); }

if (is_post()) {
    csrf_verify();
    require_role(['member', 'freelancer']);                 // only earners can accept tasks
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $t = fetch_one('SELECT * FROM tasks WHERE id=? FOR UPDATE', [$id]);   // lock so slots cannot be over-filled
        $taken = (int)fetch_val('SELECT COUNT(*) FROM task_submissions WHERE task_id=?', [$id]);
        $already = fetch_val('SELECT id FROM task_submissions WHERE task_id=? AND user_id=?', [$id, $uid]);
        if ($already) { $msg = ['info', 'You already accepted this task.']; }
        elseif ($t['status'] !== 'open' || ($t['deadline'] && $t['deadline'] < date('Y-m-d'))) { $msg = ['error', 'This task is no longer open.']; }
        elseif ($taken >= (int)$t['slots']) { $msg = ['error', 'Sorry, all slots for this task are taken.']; }
        else {
            q("INSERT INTO task_submissions (task_id, user_id, status) VALUES (?,?, 'accepted')", [$id, $uid]);
            $msg = ['success', 'Task accepted! Complete the work and submit your proof.'];
        }
        $pdo->commit();
    } catch (Throwable $ex) { $pdo->rollBack(); throw $ex; }
    flash($msg[0], $msg[1]);
    redirect($msg[0] === 'success' ? 'tasks/submit.php?task_id=' . $id : 'tasks/details.php?id=' . $id);
}

$mine = in_array($user['role'], ['member', 'freelancer'], true)
    ? fetch_one('SELECT * FROM task_submissions WHERE task_id=? AND user_id=?', [$id, $uid]) : null;
$left = max(0, (int)$task['slots'] - (int)$task['taken']);
$expired = $task['deadline'] && $task['deadline'] < date('Y-m-d');

$pageTitle = $task['title'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <p style="margin-top:1.2rem"><a href="<?= e(url('tasks/index.php')) ?>">&larr; All tasks</a></p>
  <div class="detail-grid">
    <div class="card">
      <div class="meta" style="margin-bottom:.6rem"><span class="badge"><?= e($task['category']) ?></span><?= status_badge($task['status']) ?></div>
      <h1><?= e($task['title']) ?></h1>
      <p class="pre"><?= e($task['description']) ?></p>
    </div>
    <aside>
      <div class="card">
        <div class="reward" style="font-size:1.8rem"><?= e(money($task['reward'])) ?></div>
        <dl class="kv" style="margin:1rem 0">
          <dt>Slots left</dt><dd><?= $left ?> of <?= (int)$task['slots'] ?></dd>
          <dt>Deadline</dt><dd><?= e($task['deadline'] ? fmt_date($task['deadline'], false) : 'None') ?></dd>
          <dt>Posted by</dt><dd><?= e($task['creator']) ?></dd>
        </dl>
        <?php if ($user['role'] === 'admin'): ?>
          <a class="btn btn-outline btn-block" href="<?= e(url('tasks/create.php?id=' . $id)) ?>">Edit task</a>
          <a class="btn btn-navy btn-block" style="margin-top:.5rem" href="<?= e(url('tasks/submissions.php?task_id=' . $id)) ?>">Review submissions</a>
        <?php elseif ($mine): ?>
          <p>Your status: <?= status_badge($mine['status']) ?></p>
          <?php if (in_array($mine['status'], ['accepted', 'rejected'], true)): ?>
            <a class="btn btn-primary btn-block" href="<?= e(url('tasks/submit.php?task_id=' . $id)) ?>"><?= $mine['status'] === 'rejected' ? 'Fix &amp; resubmit' : 'Submit proof' ?></a>
          <?php else: ?><a class="btn btn-outline btn-block" href="<?= e(url('tasks/submissions.php')) ?>">View my work</a><?php endif; ?>
        <?php elseif ($task['status'] === 'open' && $left > 0 && !$expired): ?>
          <form method="post"><?= csrf_field() ?><button class="btn btn-primary btn-block" type="submit">Accept this task</button></form>
        <?php else: ?><div class="alert alert-warning" style="margin:0">This task is not available.</div><?php endif; ?>
      </div>
    </aside>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
