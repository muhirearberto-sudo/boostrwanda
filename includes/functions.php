<?php
/** General helpers: escaping, URLs, flash messages, settings, audit, notifications, uploads, pagination. */

/** Escape output (prevents XSS). Use on EVERY value printed into HTML. */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url(string $path = ''): string { return rtrim(app_config()['base_url'], '/') . '/' . ltrim($path, '/'); }
function redirect(string $path) { header('Location: ' . url($path)); exit; }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }   // forwarded headers are NOT trusted
function old(string $key): string { return e($_POST[$key] ?? ''); }
function money($n): string { return number_format((float)$n, 2) . ' ' . app_config()['currency']; }
function fmt_date($d, bool $time = true): string { return $d ? date($time ? 'd M Y, H:i' : 'd M Y', strtotime($d)) : '-'; }
function clean(?string $v, int $max = 255): string { return mb_substr(trim((string)$v), 0, $max); }
function status_badge(string $s): string { return '<span class="badge badge-' . e($s) . '">' . e(ucfirst(str_replace('_', ' ', $s))) . '</span>'; }

function json_out(array $data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ---- Flash messages (shown once after a redirect) ----
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- Platform settings (table: settings) ----
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (fetch_all('SELECT setting_key, setting_value FROM settings') as $r) { $cache[$r['setting_key']] = $r['setting_value']; }
    }
    return $cache[$key] ?? $default;
}

// ---- Audit log (important admin + financial actions) ----
function audit_log(string $action, string $details = '', ?int $userId = null): void
{
    $uid = $userId ?? ($_SESSION['user_id'] ?? null);
    q('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)',
      [$uid, $action, mb_substr($details, 0, 500), client_ip()]);
}

// ---- In-app notifications (DFD: "approval / earnings / notifications", "replies / notifications") ----
function notify(int $userId, string $message, ?string $link = null): void
{
    q('INSERT INTO notifications (user_id, message, link) VALUES (?,?,?)', [$userId, mb_substr($message, 0, 255), $link]);
}
function unread_notifications(int $userId): int
{
    return (int)fetch_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$userId]);
}

// ---- Validation ----
function valid_phone(string $p): bool { return (bool)preg_match('/^\+?[0-9]{9,15}$/', $p); }
/** Returns a normalised "1234.50" string, or null if not a positive amount with <=2 decimals. */
function parse_amount($v): ?string
{
    $v = trim((string)$v);
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v) || (float)$v <= 0) { return null; }
    return number_format((float)$v, 2, '.', '');
}
function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

// ---- Pagination ----
function paginate(int $total, int $perPage = 10): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}
function pagination_html(array $p): string
{
    if ($p['pages'] <= 1) { return ''; }
    $qs = $_GET; $out = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $qs['page'] = $i;
        $out .= '<a class="' . ($i === $p['page'] ? 'active' : '') . '" href="?' . e(http_build_query($qs)) . '">' . $i . '</a>';
    }
    return $out . '</nav>';
}

// ---- File upload (task proof). Throws RuntimeException with a user-friendly message. ----
function save_upload(array $file): array
{
    $cfg = app_config();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { throw new RuntimeException('File upload failed. Please try again.'); }
    if ($file['size'] > $cfg['upload_max_bytes']) { throw new RuntimeException('File is too large (max ' . ($cfg['upload_max_bytes'] / 1048576) . ' MB).'); }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($cfg['upload_allowed'][$ext])) { throw new RuntimeException('Only JPG, PNG or PDF files are allowed.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);   // check real content, not the browser's claim
    if ($mime !== $cfg['upload_allowed'][$ext]) { throw new RuntimeException('The file content does not match its type.'); }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;                    // random safe name
    if (!move_uploaded_file($file['tmp_name'], ROOT_PATH . '/storage/uploads/' . $stored)) { throw new RuntimeException('Could not save the file.'); }
    return [$stored, mb_substr(preg_replace('/[^\w.\- ]/u', '_', basename($file['name'])), 0, 100)];
}

// ---- Admin sub-navigation ----
function admin_subnav(string $active): string
{
    $items = ['dashboard' => 'Overview', 'users' => 'Users', 'tasks' => 'Tasks', 'services' => 'Services',
              'transactions' => 'Transactions', 'campaigns' => 'Campaigns', 'moderation' => 'Moderation',
              'reports' => 'Reports', 'settings' => 'Settings'];
    $h = '<div class="subnav">';
    foreach ($items as $k => $label) {
        $h .= '<a class="' . ($k === $active ? 'active' : '') . '" href="' . e(url("admin/$k.php")) . '">' . e($label) . '</a>';
    }
    return $h . '</div>';
}
