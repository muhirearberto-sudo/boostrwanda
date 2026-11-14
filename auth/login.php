<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) { redirect(role_home(current_user()['role'])); }

$error = null;
if (is_post()) {
    csrf_verify();
    $error = attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($error === null) {
        $user = fetch_one('SELECT role FROM users WHERE id=?', [$_SESSION['user_id']]);
        redirect(role_home($user['role']));     // fixed server-side destinations only (no open redirect)
    }
}
$pageTitle = 'Log in';
include __DIR__ . '/../includes/header.php';
?>
<div class="container auth-wrap"><div class="card auth-card">
  <h1>Welcome back</h1>
  <p class="muted">Log in to your BoostRwanda account.</p>
  <?php if ($error): ?><div class="error-list" role="alert" style="padding-left:1rem"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= old('email') ?>" required autocomplete="username" autofocus></div>
    <div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
  <p class="muted" style="margin:1rem 0 0">New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
