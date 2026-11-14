<?php
/** Profile management (Process 1.0, data store D1). Available to every logged-in role. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];
$errors = []; $pwErrors = [];

if (is_post()) {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'profile') {
        $name  = clean($_POST['full_name'] ?? '', 100);
        $phone = preg_replace('/[\s-]/', '', clean($_POST['phone'] ?? '', 20));
        $sid   = clean($_POST['student_id'] ?? '', 30);
        if (mb_strlen($name) < 2) { $errors[] = 'Full name must be at least 2 characters.'; }
        if (!valid_phone($phone)) { $errors[] = 'Enter a valid phone number.'; }
        if ($sid !== '' && !preg_match('/^[A-Za-z0-9\/_-]{3,30}$/', $sid)) { $errors[] = 'Invalid student ID.'; }
        if (!$errors) {
            // Role, status, email and Member ID are NOT editable here (only an admin may change role/status).
            q('UPDATE users SET full_name=?, phone=?, student_id=? WHERE id=?', [$name, $phone, $sid ?: null, $uid]);
            q('INSERT INTO profiles (user_id, bio, location, skills, school) VALUES (?,?,?,?,?)
               ON DUPLICATE KEY UPDATE bio=VALUES(bio), location=VALUES(location), skills=VALUES(skills), school=VALUES(school)',
              [$uid, clean($_POST['bio'] ?? '', 500) ?: null, clean($_POST['location'] ?? '', 100) ?: null,
               clean($_POST['skills'] ?? '', 255) ?: null, clean($_POST['school'] ?? '', 120) ?: null]);
            audit_log('profile_updated', 'Profile updated');
            flash('success', 'Profile updated.');
            redirect('user/profile.php');
        }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id=?', [$uid]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) { $pwErrors[] = 'Current password is incorrect.'; }
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) { $pwErrors[] = 'New password needs 8+ characters with letters and numbers.'; }
        if ($new !== (string)($_POST['new_password2'] ?? '')) { $pwErrors[] = 'New passwords do not match.'; }
        if (!$pwErrors) {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            audit_log('password_changed', 'Password changed');
            flash('success', 'Password changed.');
            redirect('user/profile.php');
        }
    }
}
$p = fetch_one('SELECT u.*, pr.bio, pr.location, pr.skills, pr.school FROM users u LEFT JOIN profiles pr ON pr.user_id=u.id WHERE u.id=?', [$uid]);
$v = fn($k) => e($_POST[$k] ?? ($p[$k] ?? ''));
$pageTitle = 'My profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><div><h1>My profile</h1><p>Member ID <b><?= e($p['member_id']) ?></b> &middot; <?= e($p['email']) ?> &middot; <?= status_badge($p['status']) ?> <span class="badge"><?= e($p['role']) ?></span></p></div></div>
  <div class="card">
    <h2>Personal details</h2>
    <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="form-row">
        <div class="form-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= $v('full_name') ?>" required></div>
        <div class="form-group"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= $v('phone') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="student_id">Student ID</label><input id="student_id" name="student_id" value="<?= $v('student_id') ?>"></div>
        <div class="form-group"><label for="school">School</label><input id="school" name="school" value="<?= $v('school') ?>" maxlength="120"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="location">Location</label><input id="location" name="location" value="<?= $v('location') ?>" maxlength="100"></div>
        <div class="form-group"><label for="skills">Skills</label><input id="skills" name="skills" value="<?= $v('skills') ?>" maxlength="255" placeholder="e.g. design, tutoring"></div>
      </div>
      <div class="form-group"><label for="bio">About me</label><textarea id="bio" name="bio" maxlength="500"><?= $v('bio') ?></textarea></div>
      <button class="btn btn-primary" type="submit">Save profile</button>
    </form>
  </div>
  <div class="card">
    <h2>Change password</h2>
    <?php if ($pwErrors): ?><ul class="error-list"><?php foreach ($pwErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="form-group"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required></div>
      <div class="form-row">
        <div class="form-group"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" required></div>
        <div class="form-group"><label for="new_password2">Confirm new password</label><input id="new_password2" name="new_password2" type="password" required></div>
      </div>
      <button class="btn btn-navy" type="submit">Change password</button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
