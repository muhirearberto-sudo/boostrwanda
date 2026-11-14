/* Live task search (Fetch API). The normal GET form still works without JavaScript. */
(function () {
  'use strict';
  var form = document.getElementById('task-filter'), grid = document.getElementById('task-grid');
  if (!form || !grid) return;
  var input = form.querySelector('[name="q"]'), cat = form.querySelector('[name="category"]'), timer;

  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }

  function render(items) {
    grid.textContent = '';
    if (!items.length) { var d = el('div', 'card empty grid-span'); d.appendChild(el('h3', '', 'No tasks match your search')); d.appendChild(el('p', '', 'Try a different keyword or category.')); grid.appendChild(d); return; }
    items.forEach(function (t) {
      var c = el('article', 'item-card');
      var h = el('h3'); var a = el('a', '', t.title); a.href = BR.url('tasks/details.php?id=' + t.id); h.appendChild(a);
      c.appendChild(h);
      var m = el('div', 'meta'); m.appendChild(el('span', 'badge', t.category)); m.appendChild(el('span', '', t.slots_left + ' slot(s) left')); c.appendChild(m);
      c.appendChild(el('p', 'desc', t.excerpt));
      var f = el('div', 'foot'); f.appendChild(el('span', 'reward', t.reward));
      var b = el('a', 'btn btn-primary btn-sm', 'View task'); b.href = a.href; f.appendChild(b); c.appendChild(f);
      grid.appendChild(c);
    });
  }

  function search() {
    var url = BR.url('api/tasks.php?q=' + encodeURIComponent(input.value) + '&category=' + encodeURIComponent(cat.value));
    grid.style.opacity = '.5';
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { grid.style.opacity = ''; if (d.ok) { render(d.items); var p = document.querySelector('.pagination'); if (p) p.hidden = true; } })
      .catch(function () { grid.style.opacity = ''; });
  }
  input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 300); });
  cat.addEventListener('change', search);
  form.addEventListener('submit', function (e) { e.preventDefault(); search(); });
})();
