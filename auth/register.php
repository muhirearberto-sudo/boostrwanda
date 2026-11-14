<?php
/** Process 1.0: register. Input = name, student ID, contact. Output = confirmation + Member ID. */
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) { redirect(role_home(current_user()['role'])); }

$errors = [];
if (is_post()) {
    csrf_verify();
    [$errors, $memberId] = register_user($_POST);
    if (!$errors) {
        flash('success', "Account created! Your Member ID is $memberId. You can now log in.");
        redirect('auth/login.php');
    }
}
$pageTitle = 'Create account';
include __DIR__ . '/../includes/header.php';
?>
<div class="container auth-wrap"><div class="card auth-card">
  <h1>Create your account</h1>
  <p class="muted">Join BoostRwanda to work, earn and connect.</p>
  <?php if ($errors): ?><ul class="error-list"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= old('full_name') ?>" required maxlength="100" autocomplete="name"></div>
    <div class="form-row">
      <div class="form-group"><label for="student_id">Student ID <small>(optional)</small></label><input id="student_id" name="student_id" value="<?= old('student_id') ?>" maxlength="30"></div>
      <div class="form-group"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= old('phone') ?>" required inputmode="tel" placeholder="0788123456" autocomplete="tel"></div>
    </div>
    <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= old('email') ?>" required autocomplete="email"></div>
    <div class="form-group"><label for="role">I want to join as</label>
      <select id="role" name="role">
        <?php foreach (['member' => 'Member (earn from tasks, use services)', 'freelancer' => 'Freelancer (offer services, earn)', 'advertiser' => 'Advertiser (run campaigns)'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($_POST['role'] ?? 'member') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-row">
      <div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password"><div class="help">8+ characters, letters and numbers.</div></div>
      <div class="form-group"><label for="password2">Confirm password</label><input id="password2" name="password2" type="password" required autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
  </form>
  <p class="muted" style="margin:1rem 0 0">Already registered? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
