<?php
/**
 * Payment gateway callback ("payment confirmation / status").
 * A real gateway would POST JSON: {"ref":"SBX-...","result":"success|failed","signature":"<hmac-sha256>"}
 * The signature is HMAC-SHA256 of "ref|result" using config payment.webhook_secret, so a browser user
 * cannot forge a payment result. Processing is idempotent (replays are ignored).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_out(['ok' => false, 'error' => 'POST only'], 405); }
$in = json_decode((string)file_get_contents('php://input'), true);
$ref = is_array($in) ? (string)($in['ref'] ?? '') : '';
$result = is_array($in) ? (string)($in['result'] ?? '') : '';
$sig = is_array($in) ? (string)($in['signature'] ?? '') : '';
if ($ref === '' || !hash_equals(gateway_signature($ref, $result), $sig)) {
    audit_log('webhook_rejected', 'Bad signature for ref ' . mb_substr($ref, 0, 40), null);
    json_out(['ok' => false, 'error' => 'Invalid signature'], 403);
}
[$ok, $msg] = process_payment_result($ref, $result);
json_out(['ok' => $ok, 'message' => $msg], $ok ? 200 : 409);
