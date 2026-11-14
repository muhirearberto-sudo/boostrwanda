<?php
/** Create or edit a task. Admin only (assumption documented in README: tasks are platform-funded). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$id = (int)($_GET['id'] ?? 0);
$task = $id ? fetch_one('SELECT * FROM tasks WHERE id=?', [$id]) : null;
if ($id && !$task) { flash('error', 'Task not found.'); redirect('admin/tasks.php'); }
$errors = [];

if (is_post()) {
    csrf_verify();
    $title = clean($_POST['title'] ?? '', 150);
    $desc  = clean($_POST['description'] ?? '', 4000);
    $cat   = (int)($_POST['category_id'] ?? 0);
    $reward = parse_amount($_POST['reward'] ?? '');
    $slots = (int)($_POST['slots'] ?? 0);
    $deadline = clean($_POST['deadline'] ?? '', 10);
    $status = in_array($_POST['status'] ?? '', ['open', 'closed'], true) ? $_POST['status'] : 'open';

    if (mb_strlen($title) < 5) { $errors[] = 'Title must be at least 5 characters.'; }
    if (mb_strlen($desc) < 20) { $errors[] = 'Description must be at least 20 characters.'; }
    if (!fetch_val("SELECT id FROM categories WHERE id=? AND type='task'", [$cat])) { $errors[] = 'Choose a valid category.'; }
    if ($reward === null || (float)$reward > 1000000) { $errors[] = 'Reward must be a positive amount (max 1,000,000, 2 decimals).'; }
    if ($slots < 1 || $slots > 10000) { $errors[] = 'Slots must be between 1 and 10,000.'; }
    if ($deadline !== '' && !valid_date($deadline)) { $errors[] = 'Deadline must be a valid date.'; }

    if (!$errors) {
        if ($task) {
            q('UPDATE tasks SET title=?, description=?, category_id=?, reward=?, slots=?, deadline=?, status=? WHERE id=?',
              [$title, $desc, $cat, $reward, $slots, $deadline ?: null, $status, $id]);
            audit_log('task_updated', "Task #$id updated (reward $reward)");
            flash('success', 'Task updated.');
        } else {
            q('INSERT INTO tasks (creator_id, category_id, title, description, reward, slots, status, deadline) VALUES (?,?,?,?,?,?,?,?)',
              [$user['id'], $cat, $title, $desc, $reward, $slots, $status, $deadline ?: null]);
            audit_log('task_created', 'Task "' . $title . '" created, reward ' . $reward);
            flash('success', 'Task created.');
        }
        redirect('admin/tasks.php');
    }
}
$v = fn($k, $d = '') => e($_POST[$k] ?? ($task[$k] ?? $d));
$pageTitle = $task ? 'Edit task' : 'New task';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1></div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="form-group"><label for="title">Title</label><input id="title" name="title" value="<?= $v('title') ?>" required maxlength="150"></div>
      <div class="form-group"><label for="description">Description &amp; proof required</label><textarea id="description" name="description" required maxlength="4000"><?= $v('description') ?></textarea></div>
      <div class="form-row">
        <div class="form-group"><label for="category_id">Category</label><select id="category_id" name="category_id">
          <?php foreach (categories_of('task') as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($_POST['category_id'] ?? ($task['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="reward">Reward (<?= e(app_config()['currency']) ?>)</label><input id="reward" name="reward" inputmode="decimal" value="<?= $v('reward') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="slots">Slots (people who may take it)</label><input id="slots" name="slots" type="number" min="1" value="<?= $v('slots', '1') ?>" required></div>
        <div class="form-group"><label for="deadline">Deadline <small>(optional)</small></label><input id="deadline" name="deadline" type="date" value="<?= $v('deadline') ?>"></div>
      </div>
      <div class="form-group"><label for="status">Status</label><select id="status" name="status">
        <?php foreach (['open', 'closed'] as $s): ?><option value="<?= $s ?>" <?= ($_POST['status'] ?? ($task['status'] ?? 'open')) === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary" type="submit">Save task</button> <a class="btn btn-light" href="<?= e(url('admin/tasks.php')) ?>">Cancel</a>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
