<?php
/** Process 1.0 - Manage User Registration: authentication, authorization, registration (data store D1). */

const SELF_REGISTER_ROLES = ['member', 'freelancer', 'advertiser'];   // admin/school accounts are never self-created

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            // Re-read from the database on every request so role/status changes take effect immediately.
            $u = fetch_one('SELECT id, member_id, full_name, email, phone, role, status FROM users WHERE id=?', [$_SESSION['user_id']]);
            if ($u && $u['status'] === 'active') { $user = $u; } else { unset($_SESSION['user_id']); }
        }
    }
    return $user;
}

function role_home(string $role): string
{
    return ['admin' => 'admin/dashboard.php', 'school' => 'school/dashboard.php',
            'advertiser' => 'advertiser/dashboard.php'][$role] ?? 'user/dashboard.php';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) { flash('error', 'Please log in to continue.'); redirect('auth/login.php'); }
    return $u;
}

/** Server-side role check: call at the top of every protected page / action. */
function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        include ROOT_PATH . '/includes/header.php';
        echo '<div class="container narrow"><div class="card empty"><h1>403 - Access denied</h1>'
           . '<p>Your role (' . e($u['role']) . ') is not allowed to open this page.</p>'
           . '<a class="btn btn-primary" href="' . e(url(role_home($u['role']))) . '">Go to my dashboard</a></div></div>';
        include ROOT_PATH . '/includes/footer.php';
        exit;
    }
    return $u;
}

/** Block API callers (JSON) instead of redirecting. */
function require_login_json(): array
{
    $u = current_user();
    if (!$u) { json_out(['ok' => false, 'error' => 'Not logged in'], 401); }
    return $u;
}

// ---- Login with throttling ----
function login_locked(string $email): bool
{
    $cfg = app_config();
    $n = (int)fetch_val('SELECT COUNT(*) FROM login_attempts WHERE (email=? OR ip_address=?) AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
                        [$email, client_ip(), $cfg['lockout_minutes']]);
    return $n >= $cfg['max_login_attempts'];
}

/** @return string|null error message, or null on success */
function attempt_login(string $email, string $password): ?string
{
    $email = strtolower(trim($email));
    if (login_locked($email)) { return 'Too many failed attempts. Please wait ' . app_config()['lockout_minutes'] . ' minutes and try again.'; }
    $u = fetch_one('SELECT * FROM users WHERE email=?', [$email]);
    // password_verify on a dummy hash keeps timing similar when the email does not exist
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltusesomesillystringforsaltuse.u';
    if (!password_verify($password, $hash) || !$u) {
        q('INSERT INTO login_attempts (email, ip_address) VALUES (?,?)', [$email, client_ip()]);
        return 'Invalid email or password.';
    }
    if ($u['status'] !== 'active') { return 'This account is suspended. Contact the administrator.'; }

    q('DELETE FROM login_attempts WHERE email=?', [$email]);
    session_regenerate_id(true);               // prevents session fixation
    unset($_SESSION['csrf']);                  // fresh CSRF token for the new session
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['last_activity'] = time();
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q('UPDATE users SET last_login=NOW() WHERE id=?', [$u['id']]);
    audit_log('login', 'User logged in', (int)$u['id']);
    return null;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Register a new account. DFD: input "registration details (name, student ID, contact)",
 * output "confirmation / Member ID".
 * @return array [errors[], memberId|null]
 */
function register_user(array $in): array
{
    $err = [];
    $name  = clean($in['full_name'] ?? '', 100);
    $sid   = clean($in['student_id'] ?? '', 30);
    $email = strtolower(clean($in['email'] ?? '', 150));
    $phone = preg_replace('/[\s-]/', '', clean($in['phone'] ?? '', 20));
    $pass  = (string)($in['password'] ?? '');
    $role  = (string)($in['role'] ?? 'member');

    if (setting('registration_open', '1') !== '1') { $err[] = 'Registration is currently closed.'; }
    if (mb_strlen($name) < 2) { $err[] = 'Full name must be at least 2 characters.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err[] = 'Enter a valid email address.'; }
    if (!valid_phone($phone)) { $err[] = 'Enter a valid phone number (9-15 digits).'; }
    if ($sid !== '' && !preg_match('/^[A-Za-z0-9\/_-]{3,30}$/', $sid)) { $err[] = 'Student ID may only contain letters, digits, - _ /.'; }
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) { $err[] = 'Password must be 8+ characters with at least one letter and one number.'; }
    if ($pass !== (string)($in['password2'] ?? '')) { $err[] = 'Passwords do not match.'; }
    if (!in_array($role, SELF_REGISTER_ROLES, true)) { $err[] = 'Invalid account type.'; }
    if (!$err && fetch_val('SELECT id FROM users WHERE email=?', [$email])) { $err[] = 'That email is already registered.'; }
    if ($err) { return [$err, null]; }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        q('INSERT INTO users (full_name, student_id, email, phone, password_hash, role) VALUES (?,?,?,?,?,?)',
          [$name, $sid ?: null, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $role]);
        $id = (int)$pdo->lastInsertId();
        $memberId = 'BR' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        q('UPDATE users SET member_id=? WHERE id=?', [$memberId, $id]);
        q('INSERT INTO profiles (user_id) VALUES (?)', [$id]);
        q('INSERT INTO wallets (user_id, balance) VALUES (?, 0)', [$id]);
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($t instanceof PDOException && $t->getCode() === '23000') { return [['That email is already registered.'], null]; }
        throw $t;
    }
    audit_log('register', "New $role account $memberId", $id);
    notify($id, "Welcome to BoostRwanda! Your Member ID is $memberId.", 'user/profile.php');
    return [[], $memberId];
}
