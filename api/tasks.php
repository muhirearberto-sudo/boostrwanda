<?php
/** JSON task search used by assets/js/tasks.js. Same rules as tasks/index.php. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login_json();
if (!in_array($user['role'], ['member', 'freelancer', 'admin'], true)) { json_out(['ok' => false, 'error' => 'Not allowed'], 403); }
$rows = list_tasks(clean($_GET['q'] ?? '', 80), (int)($_GET['category'] ?? 0), $user['role'] !== 'admin', 30, 0);
json_out(['ok' => true, 'items' => array_map(fn($t) => [
    'id' => (int)$t['id'], 'title' => $t['title'], 'category' => $t['category'], 'excerpt' => excerpt($t['description']),
    'reward' => money($t['reward']), 'slots_left' => max(0, (int)$t['slots'] - (int)$t['taken']),
], $rows)]);
