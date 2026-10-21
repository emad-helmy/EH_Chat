<?php
/**
 * صفحة الرسائل (المحادثات + الإرسال الجماعي) — EH WhatsApp Like
 * ملف كامل زي eh_chat.php — الـdeploy بتنقل الملف الفعلي (من غير view)
 * الشمال: الإرسال (خاص / برمشن / @all) | اليمين: المحادثات (threads)
 */
require_once __DIR__ . '/../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

require_once __DIR__ . '/plugins/eh_chat/functions.php';

$me     = (int)$user->data()->id;
$isAdm  = eh_chat_is_admin($me);
$canBc  = eh_chat_can_broadcast($me);
$errors = [];

$online_users = eh_chat_online_users();
$onlineIds    = array_map(function ($u) { return (int)$u->id; }, $online_users);

$targets = eh_chat_allowed_targets($me);

$perms = $db->query("SELECT id, name, descrip FROM permissions WHERE id NOT IN (1) ORDER BY id ASC")->results();

$bcPerms = [];
if ($canBc && !$isAdm) {
    $s = eh_chat_settings_row();
    foreach (eh_chat_perms_of($me) as $p) {
        if (in_array($p, $s->broadcast_perms)) $bcPerms[] = $p;
    }
}
?>
<style>
  .eh-msg-page { direction: rtl; }
  .eh-thread { display: flex; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eee; text-decoration: none; color: inherit; }
  .eh-thread:hover { background: #f6f6f6; }
  .eh-thread-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
  .eh-thread-body { flex: 1; min-width: 0; }
  .eh-thread-name { font-weight: 600; display: flex; align-items: center; gap: 5px; }
  .eh-thread-msg { font-size: 13px; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  /* التاريخ والساعة — سطر لوحده تحت الرسالة */
  .eh-thread-time { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #888; margin-top: 4px; }
  /* بادج غير المقروء — دائرة صغيرة مدورة */
  .eh-thread-badge {
    display: inline-flex; align-items: center; justify-content: center;
    flex: 0 0 auto; align-self: flex-start;
    min-width: 22px; height: 22px; padding: 0 6px; margin-top: 8px;
    border-radius: 999px; font-size: 11px; font-weight: 700; line-height: 1; text-align: center;
  }
  .eh-dot { width: 8px; height: 8px; border-radius: 50%; background: #bbb; display: inline-block; }
  .eh-dot.on { background: #22c55e; }

  /* الدارك مود — نفس عناصر section الدارك في eh_chat.css (الصفحة دي بتستخدم
     .eh-thread كمان فاللون لازم يتقلب) */
  [data-bs-theme="dark"] .eh-thread { border-bottom-color: #3a4046; color: #dee2e6; }
  [data-bs-theme="dark"] .eh-thread:hover { background: #343a40; }
  [data-bs-theme="dark"] .eh-thread-name { color: #e9ecef; }
  [data-bs-theme="dark"] .eh-thread-time { color: #adb5bd; }
  [data-bs-theme="dark"] .eh-thread-msg { color: #ced4da; }
</style>

<link rel="stylesheet" href="<?= $us_url_root ?>usersc/plugins/eh_chat/assets/plugins/select2/css/select2.min.css">
<script src="<?= $us_url_root ?>usersc/plugins/eh_chat/assets/plugins/select2/js/select2.min.js"></script>
<div class="row">
  <div class="col-md-12">&nbsp;</div>
</div>
<div class="row eh-msg-page" dir="<?= function_exists('eh_chat_dir') ? eh_chat_dir() : 'ltr' ?>">
    <div class="col-12 col-md-5">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_NEW') ?></h3></div>
            <form id="ehCompose" class="card-body">
                <input type="hidden" name="csrf" value="<?= Token::generate() ?>">

                <?php if ($isAdm || $canBc) { ?>
                <div class="mb-3">
                    <label class="form-label"><?= eh_lang('EH_CHAT_TYPE') ?></label>
                    <?php if ($isAdm) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_type" value="users" id="stUsers" checked>
                        <label class="form-check-label" for="stUsers"><?= eh_lang('EH_CHAT_USERS') ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_type" value="permission" id="stPerm">
                        <label class="form-check-label" for="stPerm"><?= eh_lang('EH_CHAT_PERM') ?></label>
                    </div>
                    <?php } ?>
                    <?php if ($isAdm || $canBc) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_type" value="all" id="stAll" <?= !$isAdm ? 'style="display:none"' : '' ?>>
                        <?php if ($isAdm) { ?><label class="form-check-label" for="stAll"><strong>@all</strong> <?= eh_lang('EH_CHAT_ALL') ?></label><?php } ?>
                    </div>
                    <?php } ?>
                    <?php if (!$isAdm && $canBc) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="send_type" value="broadcast" id="stBc">
                        <label class="form-check-label" for="stBc"><strong>@all</strong> <?= eh_lang('EH_CHAT_BC_ALLOWED') ?></label>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>

                <div class="mb-3" id="usersBox">
                    <label class="form-label"><?= eh_lang('EH_CHAT_TARGET') ?></label>
                    <select class="form-select eh-select2" id="ehTarget" multiple
                            data-placeholder="<?= htmlspecialchars(eh_lang('EH_CHAT_PICK_USER')) ?>">
                        <?php foreach ($targets as $t) { ?>
                            <option value="<?= (int)$t->id ?>"><?= htmlspecialchars(eh_chat_name($t)) ?> (<?= htmlspecialchars($t->username) ?>)</option>
                        <?php } ?>
                    </select>
                    <?php if (!$targets) { ?><small class="text-muted"><?= htmlspecialchars(eh_lang('EH_CHAT_NO_TARGETS')) ?></small><?php } ?>
                </div>

                <?php if ($isAdm) { ?>
                <div class="mb-3" id="permBox" style="display:none;">
                    <label class="form-label"><?= eh_lang('EH_CHAT_PERM_LABEL') ?></label>
                    <select class="form-select eh-select2" id="ehPerm" multiple
                            data-placeholder="<?= htmlspecialchars(eh_lang('EH_CHAT_PICK_PERM')) ?>">
                        <?php foreach ($perms as $p) { ?>
                            <?php $pd = trim((string)($p->descrip ?? '')); ?>
                            <option value="<?= (int)$p->id ?>">
                                <?= htmlspecialchars($p->name . ($pd !== '' ? ' — ' . $pd : '')) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <label class="form-label"><?= eh_lang('EH_CHAT_MESSAGE') ?></label>
                    <textarea id="ehMsg" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i> <?= eh_lang('EH_CHAT_SEND') ?></button>
                <div id="ehComposeMsg" class="mt-2"></div>
            </form>
        </div>
    </div>

    <div class="col-12 col-md-7">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_CONTACTS') ?></h3></div>
            <div class="card-body p-0">
                <?php
                $rows = $db->query(
                    "SELECT m.*, u.id AS other_id, u.username, u.fname, u.lname
                     FROM eh_chat_messages m
                     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
                     WHERE m.sender_id = ? OR m.receiver_id = ?
                     ORDER BY m.id DESC LIMIT 500",
                    [$me, $me, $me]
                )->results();
                $threads = [];
                foreach ($rows as $row) {
                    $oid = (int)$row->other_id;
                    if (!isset($threads[$oid])) $threads[$oid] = $row;
                }
                if (!$threads) {
                    echo '<div class="p-3 text-muted text-center">' . eh_lang('EH_CHAT_NO_THREADS') . '</div>';
                }
                foreach ($threads as $row) {
                    $oid = (int)$row->other_id;
                    $unr = (int)$db->query(
                        "SELECT COUNT(*) AS c FROM eh_chat_messages WHERE receiver_id = ? AND sender_id = ? AND is_read = 0",
                        [$me, $oid]
                    )->first()->c;
                    $dot = in_array($oid, $onlineIds) ? 'eh-dot on' : 'eh-dot';
                    ?>
                    <a class="eh-thread" href="<?= $us_url_root ?>usersc/eh_chat.php?user_id=<?= $oid ?>">
                        <img class="eh-thread-img" src="<?= htmlspecialchars(eh_chat_avatar($oid)) ?>" alt="">
                        <div class="eh-thread-body">
                            <div class="eh-thread-name"><i class="<?= $dot ?>"></i><?= htmlspecialchars(eh_chat_name($row)) ?></div>
                            <div class="eh-thread-msg"><?= htmlspecialchars(mb_strimwidth((string)$row->message, 0, 90, '...')) ?></div>
                            <div class="eh-thread-time"><i class="fas fa-clock"></i> <?= eh_chat_time($row->created_at) ?></div>
                        </div>
                        <?php if ($unr > 0) { ?><span class="badge text-bg-danger eh-thread-badge"><?= $unr ?></span><?php } ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('ehCompose');
    var msgBox = document.getElementById('ehComposeMsg');
    var usersBox = document.getElementById('usersBox');
    var permBox = document.getElementById('permBox');
    var target = document.getElementById('ehTarget');
    var permSel = document.getElementById('ehPerm');
    var msgArea = document.getElementById('ehMsg');

    /* لغة الواجهة — بتتقرأ وقت الحاجة (الفوتر بيحمّل window.EH_CHAT.lang بعد السكربت ده) */
    function eh_lang(k) {
        var bag = (window.EH_CHAT && window.EH_CHAT.lang) || {};
        return (bag[k] !== undefined && bag[k] !== '') ? bag[k] : k;
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function sendType() {
        var r = form.querySelector('input[name="send_type"]:checked');
        return r ? r.value : 'users'; /* يوزر عادي مش عنده اختيار — دائمًا users */
    }
    function toggleBoxes() {
        var t = sendType();
        if (usersBox) usersBox.style.display = (t === 'users') ? '' : 'none';
        if (permBox) permBox.style.display = (t === 'permission') ? '' : 'none';
    }
    form.querySelectorAll('input[name="send_type"]').forEach(function (r) {
        r.addEventListener('change', toggleBoxes);
    });
    toggleBoxes();

    /* select2 — بحث بالاسم (fname / lname) أو اليوزرنيم + multi-select
       (اللغة بتتبع لغة الموقع من data-placeholder بتاعة الحقل) */
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.eh-select2').each(function () {
            jQuery(this).select2({
                width: '100%',
                placeholder: jQuery(this).data('placeholder') || '',
                closeOnSelect: false,
                language: {}
            });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var st = sendType();
        var msg = msgArea.value.trim();
        if (!msg) { msgBox.innerHTML = '<div class="alert alert-warning py-1">' + esc(eh_lang('EH_CHAT_WRITE_MSG')) + '</div>'; return; }

        var body = 'action=send&csrf=' + encodeURIComponent(form.csrf.value) +
                   '&send_type=' + st + '&message=' + encodeURIComponent(msg);
        function selIds(sel) {
            return Array.prototype.slice.call(sel.selectedOptions || []).map(function (o) { return o.value; });
        }
        if (st === 'permission') {
            var pids = selIds(permSel);
            if (!pids.length) { msgBox.innerHTML = '<div class="alert alert-warning py-1">' + esc(eh_lang('EH_CHAT_PICK_PERM')) + '</div>'; return; }
            pids.forEach(function (p) { body += '&permission_ids[]=' + encodeURIComponent(p); });
        } else {
            var ids = selIds(target);
            if (!ids.length) { msgBox.innerHTML = '<div class="alert alert-warning py-1">' + esc(eh_lang('EH_CHAT_PICK_USER')) + '</div>'; return; }
            ids.forEach(function (i) { body += '&receiver_ids[]=' + encodeURIComponent(i); });
        }

        msgBox.innerHTML = '<div class="alert alert-info py-1">' + esc(eh_lang('EH_CHAT_SENDING')) + '</div>';
        fetch(EH_CHAT.urls.send, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); })
          .then(function (res) {
              if (res.status === 'success') {
                  msgBox.innerHTML = '<div class="alert alert-success py-1">' + esc(eh_lang('EH_CHAT_SENT_TO').replace('%s', (res.sent_to || 0))) + '</div>';
                  msgArea.value = '';
                  setTimeout(function () { location.reload(); }, 800);
              } else {
                  msgBox.innerHTML = '<div class="alert alert-danger py-1">' + esc(res.message || eh_lang('EH_CHAT_SEND_FAILED')) + '</div>';
              }
          });
    });
})();
</script>

<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php';
