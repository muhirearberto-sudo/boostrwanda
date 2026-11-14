<?php
/** Moderation queue (DFD: "Moderation / reports" from D5 to the administrator). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$me = (int)$user['id'];

if (is_post()) {
    csrf_verify();
    $r = fetch_one("SELECT * FROM content_reports WHERE id=? AND status='open'", [(int)($_POST['report_id'] ?? 0)]);
    $act = $_POST['decision'] ?? '';
    if (!$r) { flash('error', 'Report not found or already handled.'); }
    else {
        $final = 'resolved';
        if ($act === 'hide_post' && $r['target_type'] === 'post') { q("UPDATE posts SET status='hidden' WHERE id=?", [$r['target_id']]); }
        elseif ($act === 'suspend_user') {
            $uid = $r['target_type'] === 'user' ? (int)$r['target_id'] : (int)fetch_val('SELECT user_id FROM posts WHERE id=?', [$r['target_id']]);
            if ($uid && $uid !== $me) { q("UPDATE users SET status='suspended' WHERE id=? AND role<>'admin'", [$uid]); audit_log('user_suspended', "User #$uid suspended via report #{$r['id']}"); }
        } elseif ($act === 'dismiss') { $final = 'dismissed'; }
        else { flash('error', 'Unknown action.'); redirect('admin/moderation.php'); }
        q('UPDATE content_reports SET status=?, handled_by=? WHERE id=?', [$final, $me, $r['id']]);
        audit_log('report_' . $final, "Report #{$r['id']} ($act)");
        notify((int)$r['reporter_id'], 'Your report was reviewed by an administrator. Thank you.', null);
        flash('success', 'Report ' . $final . '.');
    }
    redirect('admin/moderation.php');
}
$status = in_array($_GET['status'] ?? '', ['open', 'resolved', 'dismissed'], true) ? $_GET['status'] : 'open';
$pg = paginate((int)fetch_val('SELECT COUNT(*) FROM content_reports WHERE status=?', [$status]), 10);
$rows = fetch_all("SELECT cr.*, rp.full_name AS reporter FROM content_reports cr JOIN users rp ON rp.id=cr.reporter_id WHERE cr.status=? ORDER BY cr.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$status]);
foreach ($rows as &$r) {
    $r['preview'] = $r['target_type'] === 'post'
        ? (fetch_one('SELECT p.body, p.status, u.full_name FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?', [$r['target_id']]) ?: null)
        : (fetch_one('SELECT full_name, member_id, status FROM users WHERE id=?', [$r['target_id']]) ?: null);
} unset($r);
$pageTitle = 'Moderation';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('moderation') ?>
  <div class="page-head"><div><h1>Moderation</h1><p>Reports of posts and users from the community.</p></div></div>
  <form class="filters" method="get" data-no-loading><div class="form-group"><label for="status">Show</label><select id="status" name="status" onchange="this.form.submit()"><?php foreach (['open', 'resolved', 'dismissed'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div></form>
  <?php if (!$rows): ?><div class="card empty"><h3>Nothing <?= e($status) ?></h3><p>The community is behaving.</p></div><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="card"><div class="card-head"><h3>Report #<?= (int)$r['id'] ?> &middot; <?= e($r['target_type']) ?></h3><?= status_badge($r['status']) ?></div>
      <p><b>Reason:</b> <?= e($r['reason']) ?> <small>&mdash; <?= e($r['reporter']) ?>, <?= e(fmt_date($r['created_at'])) ?></small></p>
      <?php if ($r['preview']): ?><div class="alert alert-info" style="margin:0 0 1rem"><div class="pre">
        <?= $r['target_type'] === 'post' ? '<b>' . e($r['preview']['full_name']) . ':</b> ' . e($r['preview']['body']) . ' ' . status_badge($r['preview']['status']) : '<b>' . e($r['preview']['full_name']) . '</b> (' . e($r['preview']['member_id']) . ') ' . status_badge($r['preview']['status']) ?></div></div>
      <?php else: ?><p class="muted">The reported item no longer exists.</p><?php endif; ?>
      <?php if ($r['status'] === 'open'): ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
        <?php if ($r['target_type'] === 'post'): ?><button class="btn btn-danger btn-sm" name="decision" value="hide_post" type="submit">Hide post</button><?php endif; ?>
        <button class="btn btn-danger btn-sm" name="decision" value="suspend_user" type="submit" onclick="return confirm('Suspend the user involved?')">Suspend user</button>
        <button class="btn btn-light btn-sm" name="decision" value="dismiss" type="submit">Dismiss</button></form><?php endif; ?></div>
  <?php endforeach; ?>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
