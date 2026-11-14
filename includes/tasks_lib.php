<?php
/** Process 2.0 helpers - Tasks, Services & Orders (data store D2). */

function categories_of(string $type): array
{
    return fetch_all('SELECT id, name FROM categories WHERE type=? ORDER BY name', [$type]);
}

/** Build the WHERE clause for task searching. Search text is bound as a parameter (no SQL injection). */
function task_filters(string $q, int $cat, bool $openOnly): array
{
    $w = ['1=1']; $p = [];
    if ($openOnly) { $w[] = "t.status='open' AND (t.deadline IS NULL OR t.deadline >= CURDATE())"; }
    if ($q !== '') {
        $w[] = '(t.title LIKE ? OR t.description LIKE ?)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $p[] = $like; $p[] = $like;
    }
    if ($cat > 0) { $w[] = 't.category_id=?'; $p[] = $cat; }
    return [implode(' AND ', $w), $p];
}
function count_tasks(string $q, int $cat, bool $openOnly): int
{
    [$w, $p] = task_filters($q, $cat, $openOnly);
    return (int)fetch_val("SELECT COUNT(*) FROM tasks t WHERE $w", $p);
}
function list_tasks(string $q, int $cat, bool $openOnly, int $limit, int $offset): array
{
    [$w, $p] = task_filters($q, $cat, $openOnly);
    return fetch_all("SELECT t.*, c.name AS category,
                        (SELECT COUNT(*) FROM task_submissions s WHERE s.task_id=t.id) AS taken
                      FROM tasks t JOIN categories c ON c.id=t.category_id
                      WHERE $w ORDER BY t.id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset, $p);
}
function excerpt(string $s, int $n = 120): string
{
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}
/** Notify every active administrator. */
function notify_admins(string $message, ?string $link = null): void
{
    foreach (fetch_all("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) { notify((int)$a['id'], $message, $link); }
}

// ---- Messaging helpers (Process 4.0, data store D5) ----
/** One conversation per pair of users. The smaller id is always user_a, so (a,b) and (b,a) are the same row. */
function get_or_create_conversation(int $x, int $y): int
{
    [$a, $b] = $x < $y ? [$x, $y] : [$y, $x];
    q('INSERT IGNORE INTO conversations (user_a, user_b) VALUES (?,?)', [$a, $b]);
    return (int)fetch_val('SELECT id FROM conversations WHERE user_a=? AND user_b=?', [$a, $b]);
}
/** Returns the conversation row only if $userId is one of its two participants, otherwise null. */
function conversation_for(int $convId, int $userId): ?array
{
    return fetch_one('SELECT * FROM conversations WHERE id=? AND (user_a=? OR user_b=?)', [$convId, $userId, $userId]);
}
const MESSAGING_ROLES = ['member', 'freelancer', 'advertiser', 'admin'];
