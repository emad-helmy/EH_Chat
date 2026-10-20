<?php
/**
 * footer.php — التضمين الرسمي للبلجن
 * ملف users/includes/html_footer.php بيعمل include للملف ده تلقائيًا
 * في فوتر كل الصفحات (لكل plugins المفعلة) — من غير أي hook.
 *
 * هنا: تحديث last_watchdog + تحميل الأصول + الزر العائم.
 */
if (count(get_included_files()) == 1) die();

if (!isset($user) || !$user->isLoggedIn()) return;

global $db, $lang;
$uid = (int)$user->data()->id;

/* البلجن متفعل؟ */
$active = $db->query("SELECT id FROM us_plugins WHERE plugin = 'eh_chat' AND status = 'active'")->count();
if ($active !== 1) return;

/* تحديث مؤشر الاتصال (real-time — بديل أو مكمل للـwatchdog cron) */
$db->update('users', $uid, ['last_watchdog' => date('Y-m-d H:i:s')]);

$nonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
$root  = $us_url_root;

$ehStrings = function_exists('eh_chat_lang_bag') ? eh_chat_lang_bag() : [];
/* احتياطي: أي مفتاح تواريخ مش موجود ناخده من قاموس البلجن */
foreach (['EH_CHAT_TODAY', 'EH_CHAT_YESTERDAY',
    'EH_CHAT_DAY_SUN', 'EH_CHAT_DAY_MON', 'EH_CHAT_DAY_TUE', 'EH_CHAT_DAY_WED',
    'EH_CHAT_DAY_THU', 'EH_CHAT_DAY_FRI', 'EH_CHAT_DAY_SAT'] as $eh_k) {
    if (empty($ehStrings[$eh_k]) && function_exists('eh_chat_lang_fallback')) {
        $ehStrings[$eh_k] = eh_chat_lang_fallback($eh_k);
    }
}
?>
<link rel="stylesheet" href="<?= $root ?>usersc/plugins/eh_chat/assets/css/eh_chat.css">
<script nonce="<?= $nonce ?>">
    window.EH_CHAT = {
        csrf: '<?= Token::generate() ?>',
        assets: '<?= $root ?>usersc/plugins/eh_chat/assets',
        lang: <?= json_encode($ehStrings, JSON_UNESCAPED_UNICODE) ?>,
        urls: {
            nav:      '<?= $root ?>usersc/plugins/eh_chat/ajax/nav.php',
            send:     '<?= $root ?>usersc/plugins/eh_chat/ajax/send.php',
            messages: '<?= $root ?>usersc/plugins/eh_chat/ajax/messages.php',
            contacts: '<?= $root ?>usersc/plugins/eh_chat/ajax/contacts.php',
            chat:     '<?= $root ?>usersc/eh_chat.php',
            inbox:    '<?= $root ?>usersc/eh_messages.php'
        }
    };
    /* SweetAlert2 — لو مش متحمل من حاجة تانية، البلجن بيحمل نسخته */
    (function () {
        if (typeof window.Swal !== 'undefined') { return; }
        var s = document.createElement('script');
        s.src = window.EH_CHAT.assets + '/plugins/sweetalert2/sweetalert2.min.js';
        s.onload = function () {
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = window.EH_CHAT.assets + '/plugins/sweetalert2/sweetalert2.min.css';
            document.head.appendChild(l);
        };
        document.head.appendChild(s);
    })();
</script>
<script nonce="<?= $nonce ?>" src="<?= $root ?>usersc/plugins/eh_chat/assets/js/eh_chat.js"></script>

<!-- الزر العائم + اللوحة -->
<div class="eh-widget" id="ehWidget" dir="rtl">
    <div class="eh-panel" id="ehPanel" hidden>
        <div class="eh-panel-head">
            <span class="eh-panel-title"><i class="fas fa-comments"></i> <?= htmlspecialchars(eh_lang('EH_CHAT_MESSAGES')) ?></span>
            <a href="<?= $root ?>usersc/eh_messages.php" class="eh-panel-all"><?= htmlspecialchars(eh_lang('EH_CHAT_ALL_MESSAGES')) ?></a>
            <button type="button" class="eh-panel-close" id="ehPanelClose" aria-label="إغلاق"><i class="fas fa-times"></i></button>
        </div>
        <div class="eh-panel-body" id="ehPanelBody">
            <div class="eh-empty"><?= htmlspecialchars(eh_lang('EH_CHAT_NO_MESSAGES')) ?></div>
        </div>
    </div>
    <button type="button" class="eh-fab" id="ehFab" title="المحادثات">
        <i class="fas fa-comment-dots"></i>
        <span class="eh-badge" id="ehBadge" hidden>0</span>
    </button>
</div>
