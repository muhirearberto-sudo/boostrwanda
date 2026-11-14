<?php
/** Community feed: posts, likes, comments, reporting. Process 4.0 / data store D5. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(MESSAGING_ROLES);
$uid = (int)$user['id'];
$isAdmin = $user['role'] === 'admin';

if (is_post()) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $postId = (int)($_POST['post_id'] ?? 0);
    $post = $postId ? fetch_one("SELECT * FROM posts WHERE id=? AND status='visible'", [$postId]) : null;

    if ($act === 'post') {
        $body = clean($_POST['body'] ?? '', 1000);
        if ($body === '') { flash('error', 'Write something first.'); }
        else { q('INSERT INTO posts (user_id, body) VALUES (?,?)', [$uid, $body]); flash('success', 'Posted.'); }
    } elseif ($post && $act === 'like') {
        if (q('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?,?)', [$postId, $uid])->rowCount() === 0) {
            q('DELETE FROM post_likes WHERE post_id=? AND user_id=?', [$postId, $uid]);     // second click = unlike
        } elseif ((int)$post['user_id'] !== $uid) { notify((int)$post['user_id'], $user['full_name'] . ' liked your post.', 'social/index.php'); }
    } elseif ($post && $act === 'comment') {
        $body = clean($_POST['body'] ?? '', 500);
        if ($body !== '') {
            q('INSERT INTO post_comments (post_id, user_id, body) VALUES (?,?,?)', [$postId, $uid, $body]);
            if ((int)$post['user_id'] !== $uid) { notify((int)$post['user_id'], $user['full_name'] . ' replied to your post.', 'social/index.php'); }   // DFD: replies / notifications
        }
    } elseif ($post && $act === 'report') {
        $reason = clean($_POST['reason'] ?? '', 300);
        if (mb_strlen($reason) < 5) { flash('error', 'Please explain the problem (at least 5 characters).'); }
        elseif (fetch_val("SELECT id FROM content_reports WHERE reporter_id=? AND target_type='post' AND target_id=? AND status='open'", [$uid, $postId])) { flash('info', 'You already reported this post.'); }
        else { q("INSERT INTO content_reports (reporter_id, target_type, target_id, reason) VALUES (?, 'post', ?, ?)", [$uid, $postId, $reason]); notify_admins('A post was reported.', 'admin/moderation.php'); flash('success', 'Thank you. An administrator will review it.'); }
    } elseif ($post && $act === 'hide') {
        if ($isAdmin || (int)$post['user_id'] === $uid) {      // authors may remove their own; admins may remove any
            q("UPDATE posts SET status='hidden' WHERE id=?", [$postId]);
            if ($isAdmin) { audit_log('post_hidden', "Post #$postId hidden"); }
            flash('success', 'Post removed.');
        } else { flash('error', 'You cannot remove this post.'); }
    }
    redirect('social/index.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
}

$total = (int)fetch_val("SELECT COUNT(*) FROM posts WHERE status='visible'");
$pg = paginate($total, 8);
$posts = fetch_all("SELECT p.*, u.full_name, u.role,
        (SELECT COUNT(*) FROM post_likes l WHERE l.post_id=p.id) AS likes,
        (SELECT COUNT(*) FROM post_likes l WHERE l.post_id=p.id AND l.user_id=?) AS liked
      FROM posts p JOIN users u ON u.id=p.user_id WHERE p.status='visible' ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$uid]);
$comments = [];
if ($posts) {
    $ids = array_map(fn($p) => (int)$p['id'], $posts);
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (fetch_all("SELECT c.post_id, c.body, c.created_at, u.full_name FROM post_comments c JOIN users u ON u.id=c.user_id WHERE c.post_id IN ($in) ORDER BY c.id ASC", $ids) as $c) { $comments[$c['post_id']][] = $c; }
}
$pageTitle = 'Community feed';
include __DIR__ . '/../includes/header.php';
?>
<div class="container mid">
  <div class="page-head"><div><h1>Community feed</h1><p>Share updates and connect. Be respectful: posts can be reported and removed.</p></div></div>
  <div class="card"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="post">
    <div class="form-group"><label for="body">What's on your mind?</label><textarea id="body" name="body" maxlength="1000" required style="min-height:80px"></textarea></div>
    <button class="btn btn-primary" type="submit">Post</button></form></div>

  <?php if (!$posts): ?><div class="card empty"><h3>No posts yet</h3><p>Be the first to share something.</p></div><?php endif; ?>
  <?php foreach ($posts as $p): $pc = $comments[$p['id']] ?? []; ?>
    <article class="post">
      <div class="post-head"><div><b><?= e($p['full_name']) ?></b> <span class="badge"><?= e($p['role']) ?></span><small><?= e(fmt_date($p['created_at'])) ?></small></div></div>
      <p class="pre" style="margin:0"><?= e($p['body']) ?></p>
      <div class="post-actions">
        <form method="post" class="inline-form" data-no-loading><?= csrf_field() ?><input type="hidden" name="action" value="like"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><button class="linkbtn" type="submit"><?= $p['liked'] ? '&#9829; Liked' : '&#9825; Like' ?> (<?= (int)$p['likes'] ?>)</button></form>
        <?php if ($isAdmin || (int)$p['user_id'] === $uid): ?><form method="post" class="inline-form" data-confirm="Remove this post?"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><button class="linkbtn danger" type="submit">Remove</button></form><?php endif; ?>
        <?php if ((int)$p['user_id'] !== $uid): ?><details><summary class="linkbtn">Report</summary>
          <form method="post" class="chat-form" data-no-loading><?= csrf_field() ?><input type="hidden" name="action" value="report"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><input name="reason" placeholder="Reason" required maxlength="300"><button class="btn btn-danger btn-sm" type="submit">Send</button></form></details><?php endif; ?>
      </div>
      <div class="comments">
        <?php foreach ($pc as $c): ?><div class="comment"><b><?= e($c['full_name']) ?></b> <?= e($c['body']) ?></div><?php endforeach; ?>
        <form method="post" class="chat-form" style="padding:.5rem 0 0" data-no-loading><?= csrf_field() ?><input type="hidden" name="action" value="comment"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><input name="body" placeholder="Write a reply..." maxlength="500" required aria-label="Reply"><button class="btn btn-light btn-sm" type="submit">Reply</button></form>
      </div>
    </article>
  <?php endforeach; ?>
  <?= pagination_html($pg) ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
