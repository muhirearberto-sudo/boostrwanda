<?php
/** Streams a proof file after an access check. Uploaded files are never directly reachable by URL. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$s = fetch_one('SELECT user_id, proof_file, proof_orig FROM task_submissions WHERE id=?', [(int)($_GET['id'] ?? 0)]);
if (!$s || !$s['proof_file'] || ($user['role'] !== 'admin' && (int)$s['user_id'] !== (int)$user['id'])) { http_response_code(404); exit('File not found.'); }

$path = ROOT_PATH . '/storage/uploads/' . basename($s['proof_file']);   // basename() blocks path tricks
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$allowed = app_config()['upload_allowed'];
if (!is_file($path) || !isset($allowed[$ext])) { http_response_code(404); exit('File not found.'); }

header('Content-Type: ' . $allowed[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]/', '_', $s['proof_orig'] ?: 'proof.' . $ext) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
readfile($path);
