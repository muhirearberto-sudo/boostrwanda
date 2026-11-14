<?php
/** Page header. Set $pageTitle (and optionally $bodyClass) before including. */
$__u = current_user();
$pageTitle = $pageTitle ?? 'BoostRwanda';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | BoostRwanda</title>
<meta name="theme-color" content="#0a1f4d">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('')) ?>">
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(url('assets/images/logo.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/images/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/responsive.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div id="loader" class="loader" hidden><span></span></div>
<?php include ROOT_PATH . '/includes/navbar.php'; ?>
<main id="main">
<?php foreach (take_flash() as $f): ?>
  <div class="container"><div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?><button class="alert-close" type="button" aria-label="Dismiss">&times;</button></div></div>
<?php endforeach; ?>
