<?php
/** Create, edit (while pending/rejected), pause/resume and view campaigns. All scoped to the logged-in advertiser. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['advertiser']);
$uid = (int)$user['id'];
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$camp = $editId ? fetch_one("SELECT * FROM campaigns WHERE id=? AND advertiser_id=? AND status IN ('pending','rejected')", [$editId, $uid]) : null;
if ($editId && !$camp) { flash('error', 'That campaign cannot be edited.'); redirect('advertiser/campaigns.php'); }

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? 'save';
    if ($act === 'pause' || $act === 'resume') {
        $from = $act === 'pause' ? 'active' : 'paused'; $to = $act === 'pause' ? 'paused' : 'active';
        $n = q('UPDATE campaigns SET status=? WHERE id=? AND advertiser_id=? AND status=?', [$to, (int)($_POST['id'] ?? 0), $uid, $from])->rowCount();
        flash($n ? 'success' : 'error', $n ? 'Campaign ' . $to . '.' : 'Could not change this campaign.');
        redirect('advertiser/campaigns.php');
    }
    $title = clean($_POST['title'] ?? '', 150); $ad = clean($_POST['ad_text'] ?? '', 600); $link = clean($_POST['target_url'] ?? '', 255);
    $budget = parse_amount($_POST['budget'] ?? ''); $start = clean($_POST['start_date'] ?? '', 10); $end = clean($_POST['end_date'] ?? '', 10);
    if (mb_strlen($title) < 4) { $errors[] = 'Title must be at least 4 characters.'; }
    if (mb_strlen($ad) < 10) { $errors[] = 'Ad text must be at least 10 characters.'; }
    if ($link !== '' && !preg_match('#^https?://#i', $link)) { $errors[] = 'Link must start with http:// or https://'; }
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) { $errors[] = 'Link is not a valid URL.'; }
    if ($budget === null || (float)$budget > 100000000) { $errors[] = 'Enter a valid budget.'; }
    if (!valid_date($start) || !valid_date($end)) { $errors[] = 'Enter valid start and end dates.'; }
    elseif ($end < $start) { $errors[] = 'End date cannot be before the start date.'; }
    if (!$errors) {
        if ($camp) {
            q("UPDATE campaigns SET title=?, ad_text=?, target_url=?, budget=?, start_date=?, end_date=?, status='pending', admin_note=NULL WHERE id=? AND advertiser_id=?",
              [$title, $ad, $link ?: null, $budget, $start, $end, $camp['id'], $uid]);
            audit_log('campaign_updated', 'Campaign #' . $camp['id']);
        } else {
            q('INSERT INTO campaigns (advertiser_id, title, ad_text, target_url, budget, start_date, end_date) VALUES (?,?,?,?,?,?,?)', [$uid, $title, $ad, $link ?: null, $budget, $start, $end]);
            audit_log('campaign_created', $title);
        }
        notify_admins('Campaign "' . $title . '" is waiting for approval.', 'admin/campaigns.php');
        flash('success', 'Campaign submitted for approval.');
        redirect('advertiser/campaigns.php');
    }
}
$list = fetch_all('SELECT * FROM campaigns WHERE advertiser_id=? ORDER BY id DESC', [$uid]);
$showForm = $camp || isset($_GET['new']) || $errors;
$v = fn($k, $d = '') => e($_POST[$k] ?? ($camp[$k] ?? $d));
$pageTitle = 'Campaigns';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Campaigns</h1><p>New and edited campaigns are reviewed by an administrator before going live.</p></div>
    <?php if (!$showForm): ?><a class="btn btn-primary" href="?new=1">+ New campaign</a><?php endif; ?></div>
  <?php if ($showForm): ?>
  <div class="card"><h2><?= $camp ? 'Edit campaign' : 'New campaign' ?></h2>
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="form-group"><label for="title">Campaign title</label><input id="title" name="title" value="<?= $v('title') ?>" required maxlength="150"></div>
      <div class="form-group"><label for="ad_text">Ad text</label><textarea id="ad_text" name="ad_text" required maxlength="600"><?= $v('ad_text') ?></textarea></div>
      <div class="form-group"><label for="target_url">Link <small>(optional)</small></label><input id="target_url" name="target_url" type="url" value="<?= $v('target_url') ?>" placeholder="https://"></div>
      <div class="form-row"><div class="form-group"><label for="start_date">Start date</label><input id="start_date" name="start_date" type="date" value="<?= $v('start_date') ?>" required></div>
        <div class="form-group"><label for="end_date">End date</label><input id="end_date" name="end_date" type="date" value="<?= $v('end_date') ?>" required></div></div>
      <div class="form-group"><label for="budget">Budget (<?= e(app_config()['currency']) ?>)</label><input id="budget" name="budget" inputmode="decimal" value="<?= $v('budget') ?>" required></div>
      <button class="btn btn-primary" type="submit"><?= $camp ? 'Resubmit for approval' : 'Submit campaign' ?></button> <a class="btn btn-light" href="<?= e(url('advertiser/campaigns.php')) ?>">Cancel</a>
    </form></div>
  <?php endif; ?>
  <div class="table-wrap"><table><thead><tr><th>Campaign</th><th>Period</th><th class="num">Budget</th><th>Status</th><th>Admin note</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$list): ?><tr><td colspan="6"><div class="empty"><h3>No campaigns yet</h3><p>Click "New campaign" to get started.</p></div></td></tr><?php endif; ?>
    <?php foreach ($list as $c): ?><tr><td><b><?= e($c['title']) ?></b><br><small><?= e(excerpt($c['ad_text'], 90)) ?></small></td>
      <td><?= e(fmt_date($c['start_date'], false)) ?> &rarr; <?= e(fmt_date($c['end_date'], false)) ?></td><td class="num"><?= e(money($c['budget'])) ?></td><td><?= status_badge($c['status']) ?></td><td><?= e($c['admin_note'] ?: '-') ?></td>
      <td><div class="actions">
        <?php if (in_array($c['status'], ['pending', 'rejected'], true)): ?><a class="btn btn-outline btn-sm" href="?edit=<?= (int)$c['id'] ?>">Edit</a><?php endif; ?>
        <?php if (in_array($c['status'], ['active', 'paused'], true)): $a = $c['status'] === 'active' ? 'pause' : 'resume'; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="<?= $a ?>"><button class="btn btn-light btn-sm" type="submit"><?= ucfirst($a) ?></button></form><?php endif; ?></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
