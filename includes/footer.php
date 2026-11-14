</main>
<footer class="footer">
  <div class="container footer-inner">
    <span><b>Boost</b>Rwanda &middot; Work &bull; Earn &bull; Connect</span>
    <span>&copy; <?= date('Y') ?> BoostRwanda &middot; Sandbox/demo payments only</span>
  </div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php foreach (($extraJs ?? []) as $__js): ?><script src="<?= e(url('assets/js/' . $__js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
