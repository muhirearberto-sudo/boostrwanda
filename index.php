<?php
/** Public landing page. The numbers shown are live counts from the database (not invented). */
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Work. Earn. Connect.';
$members   = (int)fetch_val("SELECT COUNT(*) FROM users WHERE status='active'");
$openTasks = (int)fetch_val("SELECT COUNT(*) FROM tasks WHERE status='open'");
$approved  = (int)fetch_val("SELECT COUNT(*) FROM task_submissions WHERE status='approved'");
$services  = (int)fetch_val("SELECT COUNT(*) FROM services WHERE status='active'");
$u = current_user();
include __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <div>
      <h1>Boost your campus income. <span>Work. Earn. Connect.</span></h1>
      <p class="lead">BoostRwanda brings students, freelancers and advertisers together: complete tasks, offer your skills, get paid into your wallet and connect with the community.</p>
      <div class="hero-actions">
        <?php if ($u): ?>
          <a class="btn btn-gold" href="<?= e(url(role_home($u['role']))) ?>">Go to my dashboard</a>
        <?php else: ?>
          <a class="btn btn-gold" href="<?= e(url('auth/register.php')) ?>">Create free account</a>
          <a class="btn btn-light" href="<?= e(url('auth/login.php')) ?>">Log in</a>
        <?php endif; ?>
      </div>
    </div>
    <aside class="hero-panel" aria-label="Platform activity">
      <h3>Live on the platform</h3>
      <div class="live-stat"><span>Active members</span><b><?= $members ?></b></div>
      <div class="live-stat"><span>Open tasks</span><b><?= $openTasks ?></b></div>
      <div class="live-stat"><span>Active services</span><b><?= $services ?></b></div>
      <div class="live-stat"><span>Approved submissions</span><b><?= $approved ?></b></div>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-title"><h2>How it works</h2><p class="muted">Four simple steps from sign-up to payout.</p></div>
    <div class="grid grid-4">
      <div class="feature"><div class="num">1</div><h3>Register</h3><p class="muted">Sign up with your name, student ID and contact details and receive your Member ID.</p></div>
      <div class="feature"><div class="num">2</div><h3>Pick a task</h3><p class="muted">Browse tasks and services, search by category and accept what suits you.</p></div>
      <div class="feature"><div class="num">3</div><h3>Submit proof</h3><p class="muted">Upload proof of your work. An administrator reviews and approves it.</p></div>
      <div class="feature"><div class="num">4</div><h3>Get paid</h3><p class="muted">Approved earnings land in your wallet. Request a withdrawal when you are ready.</p></div>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="container">
    <div class="section-title"><h2>Built for everyone on campus</h2></div>
    <div class="grid grid-3">
      <div class="feature role-card"><h3>Members &amp; Freelancers</h3><p class="muted">Earn from tasks, sell your skills as services, chat with others and share on the community feed.</p></div>
      <div class="feature role-card r2"><h3>Advertisers</h3><p class="muted">Create campaigns, submit ad details and follow approval status and campaign reports.</p></div>
      <div class="feature role-card r3"><h3>School management</h3><p class="muted">Receive reports on users, tasks, earnings and payments, filtered by date range.</p></div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
