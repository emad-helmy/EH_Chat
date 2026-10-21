<?php
/**
 * صفحة محادثة — بتتضمن من usersc/eh_chat.php
 * الشمال: قائمة الجهات (اللي تقدر تكلمهم) | اليمين: المحادثة
 */
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

require_once __DIR__ . '/plugins/eh_chat/functions.php';

$me = (int)$user->data()->id;
$startWith = (int)Input::get('user_id');

/* التحقق لو فتحنا حد بالـid مباشرة — نمایش مش رد (القراية تحددها المصفوفة من أي اتجاه) */
if ($startWith > 0) {
    if (!eh_chat_can_view($me, $startWith)) {
        echo '<div class="alert alert-warning mt-3"><i class="fas fa-lock"></i> ' . eh_lang('EH_CHAT_DENIED') . '</div>';
        return;
    }
}
?>
<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>
<div class="row eh-chat-page" dir="<?= function_exists('eh_chat_dir') ? eh_chat_dir() : 'ltr' ?>">
    <!-- قائمة الجهات -->
    <div class="col-12 col-md-4">
        <div class="card card-outline card-info mb-3 mb-md-0">
            <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_CONTACTS') ?></h3></div>
            <div class="card-body p-0">
                <div class="eh-contacts" id="ehContacts">
                    <div class="p-3 text-muted text-center">…</div>
                </div>
            </div>
        </div>
    </div>

    <!-- المحادثة -->
    <div class="col-12 col-md-8">
        <div class="card card-outline card-success" id="ehChatCard">
            <div class="card-header d-flex align-items-center gap-2" id="ehChatHeader">
                <img id="ehChatAvatar" class="rounded-circle" width="34" height="34" alt="" src="">
                <div>
                    <div class="fw-bold" id="ehChatName">—</div>
                    <small id="ehChatStatus" class="text-muted"></small>
                </div>
            </div>
            <div class="card-body">
                <div class="eh-messages" id="ehMessages">
                    <div class="text-center text-muted py-4"><?= eh_lang('EH_CHAT_PICK') ?></div>
                </div>
            </div>
            <div class="card-footer" id="ehChatFooter" style="display:none;">
                <form id="ehChatForm" class="d-flex gap-2">
                    <input type="hidden" name="csrf" value="<?= Token::generate() ?>">
                    <input type="text" id="ehMsgInput" class="form-control" placeholder="<?= eh_lang('EH_CHAT_PLACEHOLDER') ?>" autocomplete="off">
                    <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> <?= eh_lang('EH_CHAT_SEND') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var startWith = <?= $startWith ?>;
    var current = startWith;
    var box = document.getElementById('ehMessages');
    var footer = document.getElementById('ehChatFooter');
    var input = document.getElementById('ehMsgInput');

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function scrollBottom() { box.scrollTop = box.scrollHeight; }

    /* ---------- قائمة الجهات ---------- */
    function loadContacts() {
        fetch(EH_CHAT.urls.contacts, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.status !== 'success') return;
                document.getElementById('ehContacts').innerHTML = res.html;
                /* لو في محادثة مفتوحة — اعملها active */
                if (current > 0) {
                    var act = document.querySelector('.eh-contact[data-id="' + current + '"]');
                    if (act) act.classList.add('active');
                }
            });
    }

    document.getElementById('ehContacts').addEventListener('click', function (e) {
        var c = e.target.closest('.eh-contact');
        if (!c) return;
        openConversation(parseInt(c.getAttribute('data-id'), 10));
    });

    /* ---------- عرض الرسائل (JSON: html + can_send) ---------- */
    function applyMessages(text) {
        var res = null;
        try { res = JSON.parse(text); } catch (e) {}
        if (res && res.status === 'error') {
            box.innerHTML = '<div class="eh-denied">' + esc(res.message || '') + '</div>';
            footer.style.display = 'none';
            return;
        }
        var html = text, canSend = true;
        if (res && res.status === 'success') { html = res.html; canSend = !!parseInt(res.can_send, 10); }
        box.innerHTML = html;
        footer.style.display = canSend ? '' : 'none';
        scrollBottom();
    }

    /* ---------- فتح محادثة ---------- */
    function openConversation(otherId) {
        current = otherId;
        document.querySelectorAll('.eh-contact').forEach(function (el) {
            el.classList.toggle('active', parseInt(el.getAttribute('data-id'), 10) === otherId);
        });
        fetch(EH_CHAT.urls.messages + '?user_id=' + otherId)
            .then(function (r) { return r.text(); })
            .then(applyMessages);
    }

    /* ---------- الإرسال ---------- */
    document.getElementById('ehChatForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = input.value.trim();
        if (!msg || !current) return;
        input.value = '';
        fetch(EH_CHAT.urls.send, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send&csrf=' + encodeURIComponent(EH_CHAT.csrf) +
                  '&send_type=users&receiver_ids=' + current +
                  '&message=' + encodeURIComponent(msg)
        }).then(function () {
            /* تحديث الرسائل بعد الإرسال */
            fetch(EH_CHAT.urls.messages + '?user_id=' + current).then(function (r) { return r.text(); })
                .then(applyMessages);
        });
    });

    /* ---------- تحديث الحالة + البولينج ---------- */
    function refreshHeader() {
        if (!current) return;
        fetch(EH_CHAT.urls.contacts, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.people) return;
                var p = null;
                res.people.forEach(function (x) { if (x.id === current) p = x; });
                if (!p) return;
                document.getElementById('ehChatAvatar').src = p.avatar;
                document.getElementById('ehChatName').textContent = p.name;
                var st = document.getElementById('ehChatStatus');
                var LG = (window.EH_CHAT && window.EH_CHAT.lang) || {};
                if (p.online) { st.className = 'text-success'; st.innerHTML = esc(LG.EH_CHAT_ONLINE || 'Online'); }
                else { st.className = 'text-muted'; st.innerHTML = esc(LG.EH_CHAT_OFFLINE || 'Offline'); }
            });
    }

    /* ---------- التشغيل ---------- */
    function initPage() {
        loadContacts();
        refreshHeader();
        if (startWith > 0) {
            setTimeout(function () { openConversation(startWith); }, 300);
        }
        setInterval(function () {
            loadContacts();
            refreshHeader();
            if (current) {
                fetch(EH_CHAT.urls.messages + '?user_id=' + current).then(function (r) { return r.text(); })
                    .then(applyMessages);
            }
        }, 3000);
    }

    /* الـconfig بيتحمّل من الفوتر — فلازم نستنى DOMContentLoaded */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPage);
    } else {
        initPage();
    }
})();
</script>

<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
