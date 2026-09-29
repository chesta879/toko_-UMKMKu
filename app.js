(function () {
  'use strict';

  // Toast: tampil sebentar lalu hilang
  function showToast() {
    var t = document.getElementById('toast');
    if (!t || !t.classList.contains('show')) return;
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(function () { t.classList.remove('show'); }, 3600);
  }
  showToast();

  // Form ber-class "ajax": kirim di latar belakang lalu perbarui keranjang + toast
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.classList || !f.classList.contains('ajax')) return;
    ev.preventDefault();
    var btns = f.querySelectorAll('button');
    btns.forEach(function (b) { b.disabled = true; });
    fetch(f.action, { method: 'POST', body: new FormData(f), credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var d = new DOMParser().parseFromString(html, 'text/html');
        ['cartwrap', 'toast'].forEach(function (id) {
          var n = d.getElementById(id), o = document.getElementById(id);
          if (n && o) o.replaceWith(n);
        });
        showToast();
      })
      .catch(function () { btns.forEach(function (b) { b.disabled = false; }); f.classList.remove('ajax'); f.submit(); });
  });

  // Buka/tutup keranjang di layar kecil
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-cart-toggle]')) document.body.classList.toggle('cart-open');
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.body.classList.remove('cart-open');
  });

  // Pencarian + filter kategori
  var q = document.getElementById('q'), cat = '';
  function apply() {
    var term = q ? q.value.trim().toLowerCase() : '';
    document.querySelectorAll('.pc').forEach(function (c) {
      c.hidden = !((!cat || c.dataset.cat === cat) && (!term || c.dataset.name.indexOf(term) > -1));
    });
    document.querySelectorAll('.cat-sec').forEach(function (s) { s.hidden = !s.querySelector('.pc:not([hidden])'); });
    var none = document.getElementById('nomatch');
    if (none) none.hidden = !!document.querySelector('.pc:not([hidden])');
  }
  if (q) q.addEventListener('input', apply);
  document.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      cat = chip.dataset.cat || '';
      document.querySelectorAll('.chip').forEach(function (c) { c.classList.toggle('on', c === chip); });
      apply();
    });
  });

  // Muat ulang otomatis (dipakai halaman pesanan staf)
  var r = parseInt(document.body.getAttribute('data-refresh') || '0', 10);
  if (r > 0) setTimeout(function () { if (document.visibilityState === 'visible') location.reload(); }, r * 1000);
})();
