/* BoostRwanda - shared behaviour: nav toggle, alerts, loading buttons, confirm dialogs, PWA, notification badge */
(function () {
  'use strict';
  var BASE = (document.querySelector('meta[name="base-url"]') || {}).content || '/';
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  window.BR = { base: BASE, csrf: CSRF, url: function (p) { return BASE.replace(/\/$/, '') + '/' + p.replace(/^\//, ''); } };

  // Mobile menu
  var toggle = document.querySelector('.nav-toggle'), nav = document.getElementById('nav');
  if (toggle && nav) toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('open'); toggle.setAttribute('aria-expanded', open);
  });

  // Dismiss alerts
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('alert-close')) e.target.closest('.alert').remove();
  });

  // Confirm dialogs: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    var f = e.target, msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Loading indicator + double-submit protection
    var btn = f.querySelector('button[type="submit"], button:not([type])');
    if (btn && !f.hasAttribute('data-no-loading') && !e.defaultPrevented) {
      setTimeout(function () { btn.classList.add('loading'); btn.disabled = true; }, 0);
    }
  });
  // Re-enable buttons if the user comes back with the browser Back button
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('.btn.loading').forEach(function (b) { b.classList.remove('loading'); b.disabled = false; });
  });

  // Notification badge refresh
  var badge = document.getElementById('notif-count');
  if (badge) {
    var refresh = function () {
      fetch(BR.url('api/notifications.php?action=count'), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d && d.ok) { badge.textContent = d.count; badge.hidden = d.count < 1; } })
        .catch(function () {});
    };
    setInterval(refresh, 60000);
  }

  // PWA service worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(BR.url('service-worker.js')).catch(function () {});
    });
  }
})();
