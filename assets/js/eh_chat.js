/* ============================================================
   EH WhatsApp Like — الواجهة الأمامية (مستقلة — مفيش CRM)
   - دروب داون في النافبار (أي تمبلت)
   - زر عائم أسفل اليمين + لوحة الرسائل
   - رد سريع من اللوحة
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.EH_CHAT || {
    csrf: '',
    urls: { nav: '', send: '', messages: '', chat: '', inbox: '' }
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ترجمة حسب لغة الموقع */
  function T(key, fallback) {
    return (CFG.lang && CFG.lang[key]) ? CFG.lang[key] : (fallback || key);
  }

  /* ---------- الزر العائم + اللوحة ---------- */
  function initWidget() {
    var fab = document.getElementById('ehFab');
    var panel = document.getElementById('ehPanel');
    if (!fab || !panel) return;

    fab.addEventListener('click', function () {
      panel.hidden = !panel.hidden;
      if (!panel.hidden) loadNav();
    });
    var close = document.getElementById('ehPanelClose');
    if (close) close.addEventListener('click', function () { panel.hidden = true; });

    document.getElementById('ehPanelBody').addEventListener('click', function (e) {
      if (e.target.closest('.eh-quick')) return;
      var a = e.target.closest('.eh-thread');
      if (a) { window.location.href = a.getAttribute('href'); }
    });

    /* ضغط خارج الشات (واتساب-style) بيقفل اللوحة ويرجع الدائرة */
    document.addEventListener('click', function (e) {
      var widget = document.getElementById('ehWidget');
      if (!widget) return;
      if (!widget.contains(e.target)) panel.hidden = true;
    });

    loadNav();
  }

  /* ---------- جلب البيانات (عداد + آخر المحادثات) ---------- */
  function loadNav() {
    if (!CFG.urls.nav) return;
    fetch(CFG.urls.nav, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') return;

        /* عداد الزر العائم */
        var badge = document.getElementById('ehBadge');
        var n = parseInt(res.unread_count || 0, 10);
        if (badge) { badge.hidden = n <= 0; badge.textContent = n > 99 ? '99+' : n; }

        /* عداد النافبار + نص الهيدر */
        var nbadge = document.getElementById('ehNavBadge');
        if (nbadge) { nbadge.hidden = n <= 0; nbadge.textContent = n > 99 ? '99+' : n; }
        var nhead = document.getElementById('ehNavHead');
        if (nhead) { nhead.textContent = n + ' ' + T('EH_CHAT_UNREAD'); }

        /* بديل عام — يشتغل مع أي ثيم: بادج صغير على بند المحادثات في المينيو
           (مشتلزمش أي ids من الثيم — بنلاقي لينك eh_messages.php في المينيو نفسه) */
        var navA = null;
        document.querySelectorAll('a[href*="eh_messages.php"]').forEach(function (a) {
            if (!navA && !a.closest('#ehWidget')) navA = a;
        });
        var mb = document.getElementById('ehNavDropBadge');
        if (navA && n > 0) {
            if (!mb) {
                mb = document.createElement('span');
                mb.id = 'ehNavDropBadge';
                mb.className = 'eh-nav-badge';
                navA.appendChild(mb);
            }
            mb.textContent = n > 99 ? '99+' : n;
        } else if (mb) {
            mb.remove();
        }

        /* المحتوى — عناصر واتساب في الاتنين (النافبار + الزر العائم) */
        var nbody = document.getElementById('ehNavBody');
        if (nbody && res.panelHtml) nbody.innerHTML = res.panelHtml;
        var pbody = document.getElementById('ehPanelBody');
        if (pbody && res.panelHtml) pbody.innerHTML = res.panelHtml;
      })
      .catch(function () { /* مفيش مشكلة — الشبكة */ });
  }

  /* ---------- رد سريع — آخر رسالة فوق والرد تحت ---------- */
  function quickReply(userId, name, lastMsg) {
    var ctx = '';
    if (lastMsg) {
      ctx = '<div class="eh-qr-ctx">' +
              '<div class="eh-qr-ctx-label">' + esc(T('EH_CHAT_LAST_MESSAGE')) + ' ' + esc(name) + ':</div>' +
              '<div class="eh-qr-ctx-msg">' + esc(lastMsg) + '</div>' +
            '</div>';
    }
    var opts = {
      title: esc(T('EH_CHAT_QUICK_REPLY')) + ' — ' + esc(name),
      html: ctx,
      input: 'textarea',
      inputPlaceholder: T('EH_CHAT_WRITE_REPLY'),
      showCancelButton: true,
      confirmButtonText: T('EH_CHAT_SEND')
    };
    function doSend(msg) {
      fetch(CFG.urls.send, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=send&csrf=' + encodeURIComponent(CFG.csrf) +
              '&send_type=users&receiver_ids=' + encodeURIComponent(userId) +
              '&message=' + encodeURIComponent(msg)
      }).then(function (r) { return r.json(); })
        .then(function (res) {
          if (window.Swal) {
            if (res.status === 'success') Swal.fire(T('EH_CHAT_SENT_OK'), (res.sent_to || 0) + ' ' + T('EH_CHAT_SENT_TO'), 'success');
            else Swal.fire(T('EH_CHAT_ERROR'), res.message || T('EH_CHAT_SEND_FAILED'), 'error');
          } else {
            alert(res.status === 'success' ? T('EH_CHAT_SENT_TO') : (res.message || T('EH_CHAT_SEND_FAILED')));
          }
          loadNav();
        });
    }
    /* Swal لسه بيتحمل؟ — نستناه */
    ensureSwal(function () {
      Swal.fire(opts).then(function (r) { if (r.value && r.value.trim()) doSend(r.value.trim()); });
    });
  }

  /* تحميل SweetAlert2 لو مش موجود (نسخة البلجن) */
  function ensureSwal(cb) {
    if (window.Swal) { cb(); return; }
    if (window.SwalLoading) { window.SwalLoading.then(cb); return; }
    var p = new Promise(function (res) {
      var s = document.createElement('script');
      s.src = CFG.assets + '/plugins/sweetalert2/sweetalert2.min.js';
      s.onload = function () {
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = CFG.assets + '/plugins/sweetalert2/sweetalert2.min.css';
        document.head.appendChild(l);
        res();
      };
      s.onerror = function () { res(); };
      document.head.appendChild(s);
    });
    window.SwalLoading = p;
    p.then(cb);
  }

  /* النقر المطوّل على زر الرد السريع */
  function wireQuickReply() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-quick]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      quickReply(btn.getAttribute('data-quick'), btn.getAttribute('data-name') || '', btn.getAttribute('data-last') || '');
    });
  }

  /* ---------- تشغيل ---------- */
  function init() {
    initWidget();
    wireQuickReply();
    setInterval(loadNav, 5000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* إتاحة الرد السريع من أي مكان */
  window.ehChatQuickReply = quickReply;
})();
