<?php
/** Manage users: search, change role and account status. Admins cannot change their own role/status. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin']);
$me = (int)$user['id'];
const ALL_ROLES = ['member', 'freelancer', 'advertiser', 'admin', 'school'];

if (is_post()) {
    csrf_verify();
    $id = (int)($_POST['user_id'] ?? 0); $role = $_POST['role'] ?? ''; $status = $_POST['status'] ?? '';
    $t = fetch_one('SELECT id, role, status, full_name FROM users WHERE id=?', [$id]);
    if (!$t || $id === $me) { flash('error', 'You cannot change this account.'); }
    elseif (!in_array($role, ALL_ROLES, true) || !in_array($status, ['active', 'suspended'], true)) { flash('error', 'Invalid role or status.'); }
    else {
        q('UPDATE users SET role=?, status=? WHERE id=?', [$role, $status, $id]);
        audit_log('user_updated', "User #$id: role {$t['role']}->$role, status {$t['status']}->$status");
        if ($role !== $t['role'] || $status !== $t['status']) { notify($id, 'Your account was updated by an administrator (role: ' . $role . ', status: ' . $status . ').', 'user/profile.php'); }
        flash('success', 'User updated.');
    }
    redirect('admin/users.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'role' => $_POST['f_role'] ?? '', 'page' => $_POST['page'] ?? ''])));
}
$q = clean($_GET['q'] ?? '', 60); $fr = in_array($_GET['role'] ?? '', ALL_ROLES, true) ? $_GET['role'] : '';
$w = ['1=1']; $p = [];
if ($q !== '') { $w[] = '(full_name LIKE ? OR email LIKE ? OR member_id LIKE ? OR student_id LIKE ?)'; $l = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $l, $l, $l, $l); }
if ($fr !== '') { $w[] = 'role=?'; $p[] = $fr; }
$where = implode(' AND ', $w);
$pg = paginate((int)fetch_val("SELECT COUNT(*) FROM users WHERE $where", $p), 12);
$rows = fetch_all("SELECT * FROM users WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
$pageTitle = 'Manage users';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <?= admin_subnav('users') ?>
  <div class="page-head"><div><h1>Users</h1><p><?= $pg['total'] ?> account(s)</p></div></div>
  <form class="filters" method="get" data-no-loading>
    <div class="form-group"><label for="q">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, Member ID, student ID"></div>
    <div class="form-group"><label for="role">Role</label><select id="role" name="role"><option value="">All</option><?php foreach (ALL_ROLES as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-navy" type="submit">Filter</button></form>
  <div class="table-wrap"><table><thead><tr><th>Member</th><th>Contact</th><th>Joined</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><p>No users found.</p></div></td></tr><?php endif; ?>
    <?php foreach ($rows as $u): $self = (int)$u['id'] === $me; ?>
      <?php $fid = 'uf' . (int)$u['id']; ?>
      <tr>
        <td><form id="<?= $fid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="f_role" value="<?= e($fr) ?>"><input type="hidden" name="page" value="<?= (int)$pg['page'] ?>"></form>
          <b><?= e($u['full_name']) ?></b><br><small><?= e($u['member_id']) ?><?= $u['student_id'] ? ' &middot; ' . e($u['student_id']) : '' ?></small></td>
        <td><?= e($u['email']) ?><br><small><?= e($u['phone']) ?></small></td><td><small><?= e(fmt_date($u['created_at'], false)) ?></small></td>
        <td><select name="role" form="<?= $fid ?>" <?= $self ? 'disabled' : '' ?> style="padding:.3rem"><?php foreach (ALL_ROLES as $r): ?><option <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></td>
        <td><select name="status" form="<?= $fid ?>" <?= $self ? 'disabled' : '' ?> style="padding:.3rem"><?php foreach (['active', 'suspended'] as $s): ?><option <?= $u['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></td>
        <td><?= $self ? '<small>(you)</small>' : '<button class="btn btn-primary btn-sm" type="submit" form="' . $fid . '">Save</button>' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
