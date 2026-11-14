/* Wallet helpers: client-side amount hints. The server ALWAYS re-validates; this is convenience only. */
(function () {
  'use strict';
  var amt = document.getElementById('amount');
  if (!amt) return;
  var hint = document.getElementById('amount-hint');
  var min = parseFloat(amt.getAttribute('data-min') || '0'), max = parseFloat(amt.getAttribute('data-max') || '0');
  amt.addEventListener('input', function () {
    var v = parseFloat(amt.value), msg = '';
    if (amt.value !== '' && (isNaN(v) || v <= 0)) msg = 'Enter a positive amount.';
    else if (min && v < min) msg = 'Minimum is ' + min.toLocaleString() + '.';
    else if (max && v > max) msg = 'Maximum is ' + max.toLocaleString() + '.';
    if (hint) { hint.textContent = msg; hint.style.color = msg ? '#d6393b' : ''; }
  });
  var method = document.getElementById('method'), acct = document.getElementById('account-label');
  if (method && acct) method.addEventListener('change', function () {
    acct.textContent = method.value === 'bank' ? 'Bank account number' : 'Mobile money number';
  });
})();
