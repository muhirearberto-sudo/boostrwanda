<?php
/**
 * Process 5.0 - Generate Reports & Analytics (DFD data store D4).
 * D4 holds NO copy of the data: every figure is calculated live from the operational tables
 * (users, tasks, transactions, campaigns ...), so reports can never drift from reality.
 *
 * Which report types a role may request is decided HERE (server-side), not by the browser.
 */
const REPORT_TYPES_BY_ROLE = [
    'admin'  => ['users' => 'Users', 'tasks' => 'Tasks', 'earnings' => 'Earnings', 'payments' => 'Payments', 'campaigns' => 'Advertiser campaigns'],
    'school' => ['users' => 'Users', 'tasks' => 'Tasks', 'earnings' => 'Earnings', 'payments' => 'Payments'],   // DFD: earnings, users, tasks, payments
];
const REPORT_STATUSES = [
    'users' => ['active', 'suspended'], 'tasks' => ['open', 'closed'], 'earnings' => [],
    'payments' => ['pending', 'completed', 'failed'], 'campaigns' => ['pending', 'active', 'paused', 'rejected', 'completed'],
];

/** Validate and normalise report filters from $_GET. */
function report_filters(array $src, string $role): array
{
    $types = REPORT_TYPES_BY_ROLE[$role] ?? [];
    $type = isset($types[$src['type'] ?? '']) ? $src['type'] : (array_key_first($types) ?? 'users');
    $from = valid_date((string)($src['from'] ?? '')) ? $src['from'] : '';
    $to   = valid_date((string)($src['to'] ?? '')) ? $src['to'] : '';
    $status = in_array($src['status'] ?? '', REPORT_STATUSES[$type], true) ? $src['status'] : '';
    return ['type' => $type, 'from' => $from, 'to' => $to, 'status' => $status];
}

function rpt_range(string $col, array $f, array &$params): string
{
    $s = '';
    if ($f['from'] !== '') { $s .= " AND $col >= ?"; $params[] = $f['from'] . ' 00:00:00'; }
    if ($f['to'] !== '')   { $s .= " AND $col <= ?"; $params[] = $f['to'] . ' 23:59:59'; }
    return $s;
}
function rpt_pairs(array $rows): array { $o = []; foreach ($rows as $r) { $o[$r['label']] = (float)$r['val']; } return $o; }

/**
 * Build one report.
 * @return array{title:string, summary:array, chart:array, chart_title:string, columns:array, rows:array, money_cols:array}
 */
function report_data(array $f, string $role, int $limit = 200): array
{
    $isAdmin = $role === 'admin';
    $p = []; $type = $f['type'];

    if ($type === 'users') {
        $w = 'WHERE 1=1' . rpt_range('u.created_at', $f, $p);
        $sum = fetch_one("SELECT COUNT(*) total, COALESCE(SUM(u.status='active'),0) active, COALESCE(SUM(u.status='suspended'),0) suspended FROM users u $w", $p);
        if ($f['status'] !== '') { $w .= ' AND u.status=?'; $p[] = $f['status']; }
        $chart = rpt_pairs(fetch_all("SELECT u.role label, COUNT(*) val FROM users u $w GROUP BY u.role ORDER BY val DESC", $p));
        $rows = fetch_all("SELECT u.member_id, u.full_name, u.student_id, u.role, u.status, u.created_at" . ($isAdmin ? ', u.email, u.phone' : '') . " FROM users u $w ORDER BY u.id DESC LIMIT $limit", $p);
        $cols = ['Member ID', 'Name', 'Student ID', 'Role', 'Status', 'Registered'];
        if ($isAdmin) { $cols[] = 'Email'; $cols[] = 'Phone'; }   // contact details are NOT shown to school management
        return ['title' => 'Users report', 'summary' => ['Users registered' => (int)$sum['total'], 'Active' => (int)$sum['active'], 'Suspended' => (int)$sum['suspended']],
                'chart' => array_map('intval', $chart), 'chart_title' => 'Users by role', 'columns' => $cols,
                'rows' => array_map(fn($r) => array_values(array_map(fn($v) => (string)($v ?? '-'), $r)), $rows), 'money_cols' => []];
    }

    if ($type === 'tasks') {
        $w = 'WHERE 1=1' . rpt_range('t.created_at', $f, $p);
        if ($f['status'] !== '') { $w .= ' AND t.status=?'; $p[] = $f['status']; }
        $sum = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(t.status='open'),0) open_n, COALESCE(SUM(t.reward*t.slots),0) pool FROM tasks t $w", $p);
        $subs = rpt_pairs(fetch_all("SELECT s.status label, COUNT(*) val FROM task_submissions s JOIN tasks t ON t.id=s.task_id $w GROUP BY s.status", $p));
        $decided = ($subs['approved'] ?? 0) + ($subs['rejected'] ?? 0);
        $rows = fetch_all("SELECT t.id, t.title, c.name cat, t.reward, t.slots, t.status, COUNT(s.id) taken, COALESCE(SUM(s.status='submitted'),0) pend, COALESCE(SUM(s.status='approved'),0) appr, COALESCE(SUM(s.status='rejected'),0) rej, t.created_at
                          FROM tasks t JOIN categories c ON c.id=t.category_id LEFT JOIN task_submissions s ON s.task_id=t.id $w GROUP BY t.id ORDER BY t.id DESC LIMIT $limit", $p);
        return ['title' => 'Tasks report', 'summary' => ['Tasks created' => (int)$sum['n'], 'Open tasks' => (int)$sum['open_n'], 'Reward pool (reward x slots)' => money($sum['pool']),
                    'Submissions' => (int)array_sum($subs), 'Approval rate' => $decided ? round(($subs['approved'] ?? 0) / $decided * 100) . '%' : 'n/a'],
                'chart' => array_map('intval', $subs), 'chart_title' => 'Submissions by status',
                'columns' => ['#', 'Title', 'Category', 'Reward', 'Slots', 'Status', 'Taken', 'Pending', 'Approved', 'Rejected', 'Created'],
                'rows' => array_map(fn($r) => array_map('strval', array_values($r)), $rows), 'money_cols' => [3]];
    }

    if ($type === 'earnings') {
        $w = "WHERE tr.type='earning' AND tr.status='completed'" . rpt_range('tr.created_at', $f, $p);
        $sum = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(tr.amount),0) total, COALESCE(AVG(tr.amount),0) avg_amt FROM transactions tr $w", $p);
        $chart = rpt_pairs(fetch_all("SELECT c.name label, SUM(tr.amount) val FROM transactions tr JOIN task_submissions s ON s.id=tr.related_submission_id
                                      JOIN tasks t ON t.id=s.task_id JOIN categories c ON c.id=t.category_id $w GROUP BY c.name ORDER BY val DESC", $p));
        $rows = fetch_all("SELECT tr.created_at, u.member_id, u.full_name, tr.description, tr.reference, tr.amount FROM transactions tr JOIN users u ON u.id=tr.user_id $w ORDER BY tr.id DESC LIMIT $limit", $p);
        return ['title' => 'Earnings report', 'summary' => ['Total paid out as earnings' => money($sum['total']), 'Approved payments' => (int)$sum['n'], 'Average earning' => money($sum['avg_amt'])],
                'chart' => $chart, 'chart_title' => 'Earnings by task category', 'columns' => ['Date', 'Member ID', 'Name', 'Description', 'Reference', 'Amount'],
                'rows' => array_map(fn($r) => array_map('strval', array_values($r)), $rows), 'money_cols' => [5]];
    }

    if ($type === 'payments') {
        $w = "WHERE tr.type IN ('deposit','withdrawal')" . rpt_range('tr.created_at', $f, $p);
        if ($f['status'] !== '') { $w .= ' AND tr.status=?'; $p[] = $f['status']; }
        $sum = fetch_one("SELECT COALESCE(SUM(CASE WHEN tr.type='deposit' AND tr.status='completed' THEN tr.amount END),0) dep,
                                 COALESCE(SUM(CASE WHEN tr.type='withdrawal' AND tr.status='completed' THEN tr.amount END),0) wd,
                                 COALESCE(SUM(CASE WHEN tr.type='withdrawal' AND tr.status='pending' THEN tr.amount END),0) wd_pending,
                                 COALESCE(SUM(tr.status='failed'),0) failed FROM transactions tr $w", $p);
        $chart = rpt_pairs(fetch_all("SELECT CONCAT(tr.type,' / ',tr.status) label, SUM(tr.amount) val FROM transactions tr $w GROUP BY tr.type, tr.status ORDER BY val DESC", $p));
        $rows = fetch_all("SELECT tr.created_at, u.member_id, u.full_name, tr.type, tr.status, tr.reference, tr.amount FROM transactions tr JOIN users u ON u.id=tr.user_id $w ORDER BY tr.id DESC LIMIT $limit", $p);
        return ['title' => 'Payments report', 'summary' => ['Deposits completed' => money($sum['dep']), 'Withdrawals completed' => money($sum['wd']), 'Withdrawals pending' => money($sum['wd_pending']), 'Failed payments' => (int)$sum['failed']],
                'chart' => $chart, 'chart_title' => 'Amount by type / status', 'columns' => ['Date', 'Member ID', 'Name', 'Type', 'Status', 'Reference', 'Amount'],
                'rows' => array_map(fn($r) => array_map('strval', array_values($r)), $rows), 'money_cols' => [6]];
    }

    // campaigns (admin only - enforced by REPORT_TYPES_BY_ROLE)
    $w = 'WHERE 1=1' . rpt_range('c.created_at', $f, $p);
    if ($f['status'] !== '') { $w .= ' AND c.status=?'; $p[] = $f['status']; }
    $sum = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(c.budget),0) budget, COALESCE(SUM(c.status='active'),0) active_n FROM campaigns c $w", $p);
    $chart = rpt_pairs(fetch_all("SELECT c.status label, SUM(c.budget) val FROM campaigns c $w GROUP BY c.status", $p));
    $rows = fetch_all("SELECT c.id, c.title, u.full_name, c.budget, CONCAT(c.start_date,' to ',c.end_date) period, c.status FROM campaigns c JOIN users u ON u.id=c.advertiser_id $w ORDER BY c.id DESC LIMIT $limit", $p);
    return ['title' => 'Advertiser campaigns report', 'summary' => ['Campaigns' => (int)$sum['n'], 'Active' => (int)$sum['active_n'], 'Total budget' => money($sum['budget'])],
            'chart' => $chart, 'chart_title' => 'Budget by status', 'columns' => ['#', 'Campaign', 'Advertiser', 'Budget', 'Period', 'Status'],
            'rows' => array_map(fn($r) => array_map('strval', array_values($r)), $rows), 'money_cols' => [3]];
}

/** Stream a report as CSV. Cells that start with = + - @ are prefixed to prevent spreadsheet formula injection. */
function report_csv(array $r, string $name)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_\-]/i', '_', $name) . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $r['columns']);
    foreach ($r['rows'] as $row) {
        fputcsv($out, array_map(fn($c) => preg_match('/^[=+\-@]/', $c) ? "'" . $c : $c, $row));
    }
    fclose($out);
    exit;
}
