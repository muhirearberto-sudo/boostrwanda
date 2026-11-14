<?php
/** Task listing with search, category filter and pagination (Process 2.0). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'admin']);
$isAdmin = $user['role'] === 'admin';

$q   = clean($_GET['q'] ?? '', 80);
$cat = (int)($_GET['category'] ?? 0);
$total = count_tasks($q, $cat, !$isAdmin);
$pg = paginate($total, 9);
$tasks = list_tasks($q, $cat, !$isAdmin, $pg['per'], $pg['offset']);

$pageTitle = 'Tasks';
$extraJs = ['tasks.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Tasks</h1><p>Complete tasks, submit proof and earn into your wallet.</p></div>
    <?php if ($isAdmin): ?><a class="btn btn-primary" href="<?= e(url('tasks/create.php')) ?>">+ New task</a><?php endif; ?></div>

  <form id="task-filter" class="toolbar" method="get" data-no-loading>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search tasks..." aria-label="Search tasks">
    <select name="category" aria-label="Category"><option value="0">All categories</option>
      <?php foreach (categories_of('task') as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <span></span>
    <button class="btn btn-navy" type="submit">Search</button>
  </form>

  <div id="task-grid" class="grid grid-3">
    <?php if (!$tasks): ?>
      <div class="card empty grid-span"><h3>No tasks found</h3><p>Try a different keyword or category, or check back later.</p></div>
    <?php endif; ?>
    <?php foreach ($tasks as $t): $left = max(0, (int)$t['slots'] - (int)$t['taken']); ?>
      <article class="item-card">
        <h3><a href="<?= e(url('tasks/details.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a></h3>
        <div class="meta"><span class="badge"><?= e($t['category']) ?></span><span><?= $left ?> slot(s) left</span><?= $isAdmin ? status_badge($t['status']) : '' ?></div>
        <p class="desc"><?= e(excerpt($t['description'])) ?></p>
        <div class="foot"><span class="reward"><?= e(money($t['reward'])) ?></span><a class="btn btn-primary btn-sm" href="<?= e(url('tasks/details.php?id=' . $t['id'])) ?>">View task</a></div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
