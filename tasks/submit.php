<?php
/** Work submission + proof upload. Only the person who accepted the task can submit. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer']);
$uid = (int)$user['id'];
$taskId = (int)($_GET['task_id'] ?? 0);

$sub = fetch_one('SELECT s.*, t.title, t.description, t.reward FROM task_submissions s JOIN tasks t ON t.id=s.task_id WHERE s.task_id=? AND s.user_id=?', [$taskId, $uid]);
if (!$sub) { flash('error', 'Accept the task first.'); redirect('tasks/details.php?id=' . $taskId); }
if (!in_array($sub['status'], ['accepted', 'rejected'], true)) { flash('info', 'This work was already submitted.'); redirect('tasks/submissions.php'); }
$errors = [];

if (is_post()) {
    csrf_verify();
    $note = clean($_POST['proof_note'] ?? '', 3000);
    $hasFile = isset($_FILES['proof']) && $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($note === '' && !$hasFile && !$sub['proof_file']) { $errors[] = 'Add a note or upload proof (JPG, PNG or PDF).'; }
    $stored = $sub['proof_file']; $orig = $sub['proof_orig'];
    if (!$errors && $hasFile) {
        try { [$stored, $orig] = save_upload($_FILES['proof']); } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    }
    if (!$errors) {
        $st = q("UPDATE task_submissions SET status='submitted', proof_note=?, proof_file=?, proof_orig=?, submitted_at=NOW(), review_note=NULL
                 WHERE id=? AND user_id=? AND status IN ('accepted','rejected')", [$note ?: null, $stored, $orig, $sub['id'], $uid]);
        if ($st->rowCount()) {
            if ($hasFile && $sub['proof_file'] && $sub['proof_file'] !== $stored) { @unlink(ROOT_PATH . '/storage/uploads/' . basename($sub['proof_file'])); }
            audit_log('submission_sent', 'Submission #' . $sub['id'] . ' for task #' . $taskId);
            notify_admins($user['full_name'] . ' submitted proof for "' . $sub['title'] . '".', 'tasks/submissions.php');
            flash('success', 'Proof submitted. You will be notified once it is reviewed.');
        }
        redirect('tasks/submissions.php');
    }
}
$pageTitle = 'Submit proof';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><div><h1>Submit your work</h1><p><?= e($sub['title']) ?> &middot; <span class="reward"><?= e(money($sub['reward'])) ?></span></p></div></div>
  <?php if ($sub['status'] === 'rejected'): ?><div class="alert alert-warning">Rejected: <?= e($sub['review_note'] ?: 'No reason given.') ?> Please fix the issues and resubmit.</div><?php endif; ?>
  <div class="card">
    <h3>Task instructions</h3><p class="pre muted"><?= e($sub['description']) ?></p>
  </div>
  <div class="card">
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
      <div class="form-group"><label for="proof_note">Note / text proof</label><textarea id="proof_note" name="proof_note" maxlength="3000"><?= e($_POST['proof_note'] ?? ($sub['proof_note'] ?? '')) ?></textarea></div>
      <div class="form-group"><label for="proof">Proof file <small>(JPG, PNG or PDF, max 2 MB)</small></label><input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf"><?php if ($sub['proof_orig']): ?><div class="help">Current file: <?= e($sub['proof_orig']) ?> (upload a new one to replace it)</div><?php endif; ?></div>
      <button class="btn btn-primary" type="submit">Submit for review</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
