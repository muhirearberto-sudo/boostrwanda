<?php
/** Service catalogue with search + category filter (Process 2.0). */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['member', 'freelancer', 'advertiser', 'admin']);
$isAdmin = $user['role'] === 'admin';
$q = clean($_GET['q'] ?? '', 80); $cat = (int)($_GET['category'] ?? 0);

$w = ['1=1']; $p = [];
if (!$isAdmin) { $w[] = "s.status='active'"; }
if ($q !== '') { $w[] = '(s.title LIKE ? OR s.description LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $p[] = $like; $p[] = $like; }
if ($cat > 0) { $w[] = 's.category_id=?'; $p[] = $cat; }
$where = implode(' AND ', $w);

$total = (int)fetch_val("SELECT COUNT(*) FROM services s WHERE $where", $p);
$pg = paginate($total, 9);
$rows = fetch_all("SELECT s.*, c.name AS category, u.full_name AS provider FROM services s
                   JOIN categories c ON c.id=s.category_id JOIN users u ON u.id=s.provider_id
                   WHERE $where ORDER BY s.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);

$pageTitle = 'Services';
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-head"><div><h1>Services</h1><p>Hire skilled freelancers from the BoostRwanda community.</p></div>
    <div class="actions"><a class="btn btn-outline" href="<?= e(url('services/orders.php')) ?>">Orders</a>
    <?php if (in_array($user['role'], ['freelancer', 'admin'], true)): ?><a class="btn btn-primary" href="<?= e(url('services/create.php')) ?>">+ New service</a><?php endif; ?></div></div>
  <form class="toolbar" method="get" data-no-loading>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search services..." aria-label="Search services">
    <select name="category" aria-label="Category"><option value="0">All categories</option>
      <?php foreach (categories_of('service') as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <span></span><button class="btn btn-navy" type="submit">Search</button>
  </form>
  <div class="grid grid-3">
    <?php if (!$rows): ?><div class="card empty grid-span"><h3>No services found</h3><p>Try another keyword or category.</p></div><?php endif; ?>
    <?php foreach ($rows as $s): ?>
      <article class="item-card">
        <h3><a href="<?= e(url('services/details.php?id=' . $s['id'])) ?>"><?= e($s['title']) ?></a></h3>
        <div class="meta"><span class="badge"><?= e($s['category']) ?></span><span>by <?= e($s['provider']) ?></span><?= $isAdmin ? status_badge($s['status']) : '' ?></div>
        <p class="desc"><?= e(excerpt($s['description'])) ?></p>
        <div class="foot"><span class="price"><?= e(money($s['price'])) ?></span><a class="btn btn-primary btn-sm" href="<?= e(url('services/details.php?id=' . $s['id'])) ?>">View</a></div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
