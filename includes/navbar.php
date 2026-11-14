<?php
/** Role-aware responsive navigation. */
$__links = [];
if ($__u) {
    switch ($__u['role']) {
        case 'admin':
            $__links = ['admin/dashboard.php' => 'Admin Panel', 'tasks/index.php' => 'Tasks', 'services/index.php' => 'Services',
                        'social/index.php' => 'Feed', 'messages/index.php' => 'Messages'];
            break;
        case 'school':
            $__links = ['school/dashboard.php' => 'Dashboard', 'school/reports.php' => 'Reports'];
            break;
        case 'advertiser':
            $__links = ['advertiser/dashboard.php' => 'Dashboard', 'advertiser/campaigns.php' => 'Campaigns',
                        'social/index.php' => 'Feed', 'messages/index.php' => 'Messages'];
            break;
        default: // member, freelancer
            $__links = ['user/dashboard.php' => 'Dashboard', 'tasks/index.php' => 'Tasks', 'services/index.php' => 'Services',
                        'tasks/submissions.php' => 'My Work', 'wallet/index.php' => 'Wallet',
                        'social/index.php' => 'Feed', 'messages/index.php' => 'Messages'];
    }
}
$__here = ltrim(str_replace(rtrim(app_config()['base_url'], '/'), '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
// Highlight the exact page if it is in the menu, otherwise the first link in the same folder.
$__active = array_key_exists($__here, $__links) ? $__here : null;
if (!$__active) { foreach (array_keys($__links) as $__p) { if (strpos($__here, dirname($__p) . '/') === 0) { $__active = $__p; break; } } }
?>
<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= e(url($__u ? role_home($__u['role']) : '')) ?>">
      <img src="<?= e(url('assets/images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Boost<b>Rwanda</b></span>
    </a>
    <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false" aria-controls="nav">&#9776;</button>
    <nav id="nav" class="nav">
      <?php foreach ($__links as $path => $label): ?>
        <a class="<?= $path === $__active ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($__u): ?>
        <a class="bell" href="<?= e(url('user/notifications.php')) ?>" title="Notifications">&#128276;<span id="notif-count" class="count" <?= ($n = unread_notifications((int)$__u['id'])) ? '' : 'hidden' ?>><?= (int)$n ?></span></a>
        <a href="<?= e(url('user/profile.php')) ?>" class="nav-user"><?= e(explode(' ', $__u['full_name'])[0]) ?> <small><?= e($__u['role']) ?></small></a>
        <a class="btn btn-light btn-sm" href="<?= e(url('auth/logout.php')) ?>">Log out</a>
      <?php else: ?>
        <a href="<?= e(url('auth/login.php')) ?>">Log in</a>
        <a class="btn btn-gold btn-sm" href="<?= e(url('auth/register.php')) ?>">Join free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
