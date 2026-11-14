<?php
/** CSRF protection: one token per session, checked on every state-changing request. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
/** Call at the top of any handler that changes data. Accepts form field or X-CSRF-Token header (fetch). */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Security check failed (invalid or expired form). Go back, refresh the page and try again.');
    }
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
