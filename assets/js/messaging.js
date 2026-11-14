/* Chat: sends and polls messages through api/messages.php using Fetch. Message text is inserted with textContent (XSS-safe). */
(function () {
  'use strict';
  var box = document.getElementById('chat-msgs'), form = document.getElementById('chat-form');
  if (!box || !form) return;
  var conv = box.getAttribute('data-conv'), last = parseInt(box.getAttribute('data-last') || '0', 10), sending = false;
  var input = form.querySelector('input[name="body"]');

  function add(m) {
    var b = document.createElement('div'); b.className = 'bubble' + (m.mine ? ' me' : '');
    b.appendChild(document.createTextNode(m.body));
    var s = document.createElement('small'); s.textContent = m.time; b.appendChild(s);
    box.appendChild(b); last = Math.max(last, m.id);
  }
  function scroll() { box.scrollTop = box.scrollHeight; }
  scroll();

  function poll() {
    fetch(BR.url('api/messages.php?action=poll&conversation_id=' + conv + '&after=' + last), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.ok && d.messages.length) { d.messages.forEach(add); scroll(); } })
      .catch(function () {});
  }
  setInterval(poll, 4000);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(); if (!text || sending) return;
    sending = true;
    var fd = new FormData(); fd.append('action', 'send'); fd.append('conversation_id', conv); fd.append('body', text);
    fetch(BR.url('api/messages.php'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': BR.csrf } })
      .then(function (r) { return r.json(); })
      .then(function (d) { sending = false; if (d.ok) { input.value = ''; add(d.message); scroll(); } else { alert(d.error || 'Could not send.'); } })
      .catch(function () { sending = false; alert('Network error. Message not sent.'); });
  });
})();
