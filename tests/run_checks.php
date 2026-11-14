<?php
/**
 * Command-line integration checks for the money-handling code.
 * Run from the project folder (XAMPP shell or cmd) AFTER importing the database:
 *
 *     C:\xampp\php\php.exe tests\run_checks.php
 *
 * It creates one throw-away test user + task and leaves them (and their transactions) in the database,
 * because financial records are deliberately never deleted. Re-import database/boostrwanda_db.sql to reset.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/bootstrap.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    $ok ? $pass++ : $fail++;
}
$bal = fn(int $uid) => (float)wallet_of($uid)['balance'];

// 1. schema present
$expected = ['users','profiles','login_attempts','categories','tasks','task_submissions','services','orders','wallets','transactions',
             'payment_requests','withdrawal_requests','campaigns','conversations','messages','posts','post_likes','post_comments',
             'content_reports','notifications','audit_logs','settings'];
$have = array_column(fetch_all('SHOW TABLES'), 'Tables_in_' . app_config()['db']['name']);
check('all ' . count($expected) . ' tables exist', !array_diff($expected, $have));

// 2. seed accounts use real password hashes
$admin = fetch_one("SELECT * FROM users WHERE email='admin@boostrwanda.test'");
check('demo admin exists and password verifies', $admin && password_verify('Demo@12345', $admin['password_hash']));
check('no plaintext passwords stored', (int)fetch_val('SELECT COUNT(*) FROM users WHERE password_hash NOT LIKE ?', ['$2y$%']) === 0);

// 3. registration
$email = 'test' . time() . '@boostrwanda.test';
[$errs, $memberId] = register_user(['full_name' => 'Test Person', 'email' => $email, 'phone' => '0788111222', 'password' => 'Secret123', 'password2' => 'Secret123', 'role' => 'freelancer']);
check('registration succeeds and returns a Member ID', !$errs && preg_match('/^BR\d{6}$/', (string)$memberId));
[$errs2] = register_user(['full_name' => 'Dup', 'email' => $email, 'phone' => '0788111222', 'password' => 'Secret123', 'password2' => 'Secret123', 'role' => 'member']);
check('duplicate email is rejected', (bool)$errs2);
[$errs3] = register_user(['full_name' => 'Evil', 'email' => 'evil' . time() . '@x.test', 'phone' => '0788111222', 'password' => 'Secret123', 'password2' => 'Secret123', 'role' => 'admin']);
check('cannot self-register as admin', (bool)$errs3);
$uid = (int)fetch_val('SELECT id FROM users WHERE email=?', [$email]);
check('wallet created with zero balance', $uid && $bal($uid) === 0.0);

// 4. task -> submission -> approval -> wallet
q("INSERT INTO tasks (creator_id, category_id, title, description, reward, slots) VALUES (1, 1, 'TEST task', 'Automated test task description', 2500.00, 5)");
$taskId = (int)db()->lastInsertId();
q("INSERT INTO task_submissions (task_id, user_id, status, proof_note, submitted_at) VALUES (?,?, 'submitted', 'proof', NOW())", [$taskId, $uid]);
$subId = (int)db()->lastInsertId();
[$ok] = approve_submission($subId, 1);
check('approval credits the reward (2500)', $ok && $bal($uid) === 2500.0);
[$ok2] = approve_submission($subId, 1);
check('second approval is refused (no double payment)', !$ok2 && $bal($uid) === 2500.0);
check('exactly one earning transaction exists', (int)fetch_val("SELECT COUNT(*) FROM transactions WHERE related_submission_id=? AND type='earning'", [$subId]) === 1);

// 5. withdrawals
[$ok] = request_withdrawal($uid, '999999.00', 'mobile_money', '0788111222');
check('withdrawal above balance is refused', !$ok && $bal($uid) === 2500.0);
[$ok] = request_withdrawal($uid, '1000.00', 'mobile_money', '0788111222');
check('valid withdrawal holds the funds (balance 1500)', $ok && $bal($uid) === 1500.0);
$wid = (int)fetch_val('SELECT id FROM withdrawal_requests WHERE user_id=? ORDER BY id DESC LIMIT 1', [$uid]);
[$ok] = review_withdrawal($wid, 1, 'reject', 'test');
check('rejected withdrawal returns the funds (balance 2500)', $ok && $bal($uid) === 2500.0);
[$ok] = review_withdrawal($wid, 1, 'approve', '');
check('an already-reviewed withdrawal cannot be reviewed again', !$ok && $bal($uid) === 2500.0);

// 6. payment sandbox
$ref = create_deposit_request($uid, '4000.00');
check('deposit request does NOT credit the wallet', $bal($uid) === 2500.0);
[$ok] = process_payment_result($ref, 'success');
check('gateway success credits the deposit (6500)', $ok && $bal($uid) === 6500.0);
[$ok] = process_payment_result($ref, 'success');
check('replayed gateway callback is ignored', !$ok && $bal($uid) === 6500.0);
$ref2 = create_deposit_request($uid, '1000.00');
process_payment_result($ref2, 'failed');
check('failed payment adds nothing', $bal($uid) === 6500.0);
check('webhook signature differs for different results', gateway_signature($ref, 'success') !== gateway_signature($ref, 'failed'));

// 7. DB-level guard
try { q('UPDATE wallets SET balance = -5 WHERE user_id=?', [$uid]); check('database blocks negative balance', false); }
catch (PDOException $e) { check('database blocks negative balance', true); }

// 8. messaging authorisation
$c = get_or_create_conversation(1, $uid);
check('conversation is unique per pair', $c === get_or_create_conversation($uid, 1));
check('outsider cannot open the conversation', conversation_for($c, 5) === null && conversation_for($c, $uid) !== null);

// 9. report scoping
check('school reports cannot request campaigns', report_filters(['type' => 'campaigns'], 'school')['type'] !== 'campaigns');
check('school users report has no email column', !in_array('Email', report_data(['type' => 'users', 'from' => '', 'to' => '', 'status' => ''], 'school')['columns'], true));

echo PHP_EOL . "$pass passed, $fail failed" . PHP_EOL;
exit($fail ? 1 : 0);
