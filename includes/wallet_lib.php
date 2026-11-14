<?php
/**
 * Process 3.0 - Manage Wallet & Payments (data store D3).
 *
 * SAFETY RULES used everywhere in this file:
 *  - Balances are only changed here, inside a DB transaction, never from a browser value.
 *  - Amounts, reward values and statuses are always read from the database.
 *  - Rows are locked (SELECT ... FOR UPDATE) and status-guarded so nothing is processed twice.
 *  - Debits use "balance >= amount" inside the UPDATE, so a wallet can never go negative.
 */

function new_reference(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(6))); }

/** Lock and return the user's wallet row (call inside a transaction). */
function wallet_for_update(int $userId): array
{
    $w = fetch_one('SELECT * FROM wallets WHERE user_id=? FOR UPDATE', [$userId]);
    if (!$w) {
        q('INSERT INTO wallets (user_id, balance) VALUES (?, 0)', [$userId]);
        $w = fetch_one('SELECT * FROM wallets WHERE user_id=? FOR UPDATE', [$userId]);
    }
    return $w;
}
function wallet_of(int $userId): array
{
    return fetch_one('SELECT * FROM wallets WHERE user_id=?', [$userId]) ?? ['id' => 0, 'balance' => '0.00'];
}
function wallet_balance_now(int $walletId): string { return (string)fetch_val('SELECT balance FROM wallets WHERE id=?', [$walletId]); }

// ------------------- Task earnings (Process 2.0 -> 3.0) -------------------

/** Approve a submitted task and credit the freelancer. @return array [bool ok, string message] */
function approve_submission(int $submissionId, int $adminId): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $s = fetch_one('SELECT s.id, s.user_id, s.status, t.reward, t.title
                        FROM task_submissions s JOIN tasks t ON t.id = s.task_id WHERE s.id=? FOR UPDATE', [$submissionId]);
        if (!$s || $s['status'] !== 'submitted') { $pdo->rollBack(); return [false, 'This submission is not waiting for review.']; }

        $w = wallet_for_update((int)$s['user_id']);
        q('UPDATE wallets SET balance = balance + ? WHERE id=?', [$s['reward'], $w['id']]);
        q('INSERT INTO transactions (wallet_id, user_id, type, amount, status, reference, related_submission_id, balance_after, description)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$w['id'], $s['user_id'], 'earning', $s['reward'], 'completed', new_reference('EARN'), $s['id'],
           wallet_balance_now((int)$w['id']), 'Approved task: ' . mb_substr($s['title'], 0, 150)]);
        q("UPDATE task_submissions SET status='approved', reviewed_by=?, reviewed_at=NOW(), review_note=NULL WHERE id=?", [$adminId, $s['id']]);
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($t instanceof PDOException && $t->getCode() === '23000') { return [false, 'This submission was already paid.']; }
        throw $t;
    }
    audit_log('submission_approved', "Submission #$submissionId approved, " . $s['reward'] . ' credited to user #' . $s['user_id'], $adminId);
    notify((int)$s['user_id'], 'Your submission for "' . $s['title'] . '" was approved. ' . money($s['reward']) . ' added to your wallet.', 'wallet/index.php');
    return [true, 'Approved and ' . money($s['reward']) . ' credited to the user\'s wallet.'];
}

function reject_submission(int $submissionId, int $adminId, string $note): array
{
    $s = fetch_one('SELECT s.user_id, t.title FROM task_submissions s JOIN tasks t ON t.id=s.task_id WHERE s.id=?', [$submissionId]);
    $st = q("UPDATE task_submissions SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=? AND status='submitted'",
            [$adminId, mb_substr($note, 0, 255), $submissionId]);
    if (!$s || $st->rowCount() === 0) { return [false, 'This submission is not waiting for review.']; }
    audit_log('submission_rejected', "Submission #$submissionId rejected", $adminId);
    notify((int)$s['user_id'], 'Your submission for "' . $s['title'] . '" was rejected: ' . mb_substr($note, 0, 120) . ' You may fix it and resubmit.', 'tasks/submissions.php');
    return [true, 'Submission rejected.'];
}

// ------------------- Deposits via payment gateway (SANDBOX) -------------------

/** Create a PENDING deposit + payment request. Money is NOT credited until the gateway confirms. @return string gateway_ref */
function create_deposit_request(int $userId, string $amount): string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $w = wallet_for_update($userId);
        q('INSERT INTO transactions (wallet_id, user_id, type, amount, status, reference, description) VALUES (?,?,?,?,?,?,?)',
          [$w['id'], $userId, 'deposit', $amount, 'pending', new_reference('DEP'), 'Deposit via payment gateway (sandbox)']);
        $txId = (int)$pdo->lastInsertId();
        $ref = new_reference('SBX');
        q('INSERT INTO payment_requests (user_id, transaction_id, amount, provider, gateway_ref) VALUES (?,?,?,?,?)',
          [$userId, $txId, $amount, app_config()['payment']['mode'], $ref]);
        $pdo->commit();
    } catch (Throwable $t) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $t; }
    audit_log('deposit_requested', "Deposit $amount requested, ref $ref", $userId);
    return $ref;
}

/** HMAC signature the gateway must send with its callback (used by api/payment_webhook.php). */
function gateway_signature(string $ref, string $result): string
{
    return hash_hmac('sha256', $ref . '|' . $result, app_config()['payment']['webhook_secret']);
}

/**
 * Apply the gateway's answer ("payment confirmation / status"). Idempotent: only a PENDING request can change.
 * @param string $result 'success' | 'failed'
 */
function process_payment_result(string $ref, string $result): array
{
    if (!in_array($result, ['success', 'failed'], true)) { return [false, 'Unknown result.']; }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $p = fetch_one('SELECT * FROM payment_requests WHERE gateway_ref=? FOR UPDATE', [$ref]);
        if (!$p) { $pdo->rollBack(); return [false, 'Unknown payment reference.']; }
        if ($p['status'] !== 'pending') { $pdo->rollBack(); return [false, 'This payment was already processed.']; }

        if ($result === 'success') {
            $w = wallet_for_update((int)$p['user_id']);
            q('UPDATE wallets SET balance = balance + ? WHERE id=?', [$p['amount'], $w['id']]);
            q("UPDATE transactions SET status='completed', balance_after=? WHERE id=? AND status='pending'", [wallet_balance_now((int)$w['id']), $p['transaction_id']]);
            q("UPDATE payment_requests SET status='confirmed' WHERE id=?", [$p['id']]);
        } else {
            q("UPDATE transactions SET status='failed' WHERE id=? AND status='pending'", [$p['transaction_id']]);
            q("UPDATE payment_requests SET status='failed' WHERE id=?", [$p['id']]);
        }
        $pdo->commit();
    } catch (Throwable $t) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $t; }

    audit_log('payment_' . $result, "Payment $ref -> $result", (int)$p['user_id']);
    notify((int)$p['user_id'], $result === 'success' ? 'Your deposit of ' . money($p['amount']) . ' was confirmed.' : 'Your deposit of ' . money($p['amount']) . ' failed. No money was added.', 'wallet/index.php');
    return [true, $result === 'success' ? 'Payment confirmed.' : 'Payment marked as failed.'];
}

// ------------------- Withdrawals -------------------

/** Reserve funds immediately (so they cannot be spent twice) and queue the request for admin review. */
function request_withdrawal(int $userId, string $amount, string $method, string $account): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $w = wallet_for_update($userId);
        $st = q('UPDATE wallets SET balance = balance - ? WHERE id=? AND balance >= ?', [$amount, $w['id'], $amount]);
        if ($st->rowCount() === 0) { $pdo->rollBack(); return [false, 'Insufficient balance.']; }
        q('INSERT INTO transactions (wallet_id, user_id, type, amount, status, reference, balance_after, description) VALUES (?,?,?,?,?,?,?,?)',
          [$w['id'], $userId, 'withdrawal', $amount, 'pending', new_reference('WDR'), wallet_balance_now((int)$w['id']), 'Withdrawal request (awaiting admin review)']);
        $txId = (int)$pdo->lastInsertId();
        q('INSERT INTO withdrawal_requests (user_id, transaction_id, amount, method, account_details) VALUES (?,?,?,?,?)',
          [$userId, $txId, $amount, $method, $account]);
        $pdo->commit();
    } catch (Throwable $t) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $t; }
    audit_log('withdrawal_requested', "Withdrawal $amount requested", $userId);
    return [true, 'Withdrawal request submitted. Funds are held until an administrator reviews it.'];
}

function review_withdrawal(int $id, int $adminId, string $decision, string $note): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $r = fetch_one('SELECT * FROM withdrawal_requests WHERE id=? FOR UPDATE', [$id]);
        if (!$r || $r['status'] !== 'pending') { $pdo->rollBack(); return [false, 'This request was already reviewed.']; }
        if ($decision === 'approve') {
            q("UPDATE transactions SET status='completed', description='Withdrawal approved' WHERE id=?", [$r['transaction_id']]);
            q("UPDATE withdrawal_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), admin_note=? WHERE id=?", [$adminId, $note ?: null, $id]);
        } else {
            $w = wallet_for_update((int)$r['user_id']);
            q('UPDATE wallets SET balance = balance + ? WHERE id=?', [$r['amount'], $w['id']]);   // release held funds
            q("UPDATE transactions SET status='failed', balance_after=?, description='Withdrawal rejected - funds returned' WHERE id=?", [wallet_balance_now((int)$w['id']), $r['transaction_id']]);
            q("UPDATE withdrawal_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), admin_note=? WHERE id=?", [$adminId, $note ?: null, $id]);
        }
        $pdo->commit();
    } catch (Throwable $t) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $t; }
    audit_log('withdrawal_' . $decision, "Withdrawal #$id ($decision)", $adminId);
    notify((int)$r['user_id'], $decision === 'approve'
        ? 'Your withdrawal of ' . money($r['amount']) . ' was approved.'
        : 'Your withdrawal of ' . money($r['amount']) . ' was rejected and the funds were returned to your wallet.', 'wallet/transactions.php');
    return [true, 'Withdrawal ' . ($decision === 'approve' ? 'approved' : 'rejected') . '.'];
}
