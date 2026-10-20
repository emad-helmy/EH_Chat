<?php
/**
 * صفحة إعدادات البلجن — admin.php?view=plugins_config&plugin=eh_chat
 * الأدمن يحدد: مين يقدر يكلم مين (مصفوفة) + مين يقدر يعمل broadcast
 */
require_once __DIR__ . '/functions.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
}

include "plugin_info.php";
pluginActive($plugin_name);

$db = DB::getInstance();
$err = '';
$msg = '';

if (!empty($_POST)) {
    $token = $_POST['csrf'] ?? '';
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } elseif (($_POST['eh_menu_action'] ?? '') === 'add') {
        /* تكامل المنيو — إضافة بند المحادثات آخر المنيو المختار
           (نفس نمط منيو UserSpice: MAX(display_order)+1 و parent = 0) */
        $menuId = (int)($_POST['eh_menu_id'] ?? 0);
        $chatLink = 'usersc/eh_messages.php';
        if ($menuId > 0 && $db->query("SELECT id FROM us_menus WHERE id = ?", [$menuId])->count()) {
            if ($db->query("SELECT id FROM us_menu_items WHERE menu = ? AND link = ?", [$menuId, $chatLink])->count()) {
                $msg = eh_lang('EH_CHAT_MENU_ALREADY');
            } else {
                $mx = $db->query("SELECT MAX(display_order) AS mx FROM us_menu_items WHERE menu = ? AND parent = 0", [$menuId])->first();
                $permIds = array_map(function ($p) { return (string)$p->id; }, $db->query("SELECT id FROM permissions")->results());
                $iconClass = trim((string)($_POST['eh_menu_icon'] ?? ''));
                $db->insert('us_menu_items', [
                    'menu'          => $menuId,
                    'type'          => 'link',
                    'label'         => eh_lang('EH_CHAT_MESSAGES'),
                    'link'          => $chatLink,
                    'icon_class'    => $iconClass !== '' ? $iconClass : 'fas fa-comments',
                    'li_class'      => '',
                    'a_class'       => '',
                    'link_target'   => '_self',
                    'parent'        => 0,
                    'display_order' => (int)($mx->mx ?? 0) + 1,
                    'disabled'      => 0,
                    'permissions'   => json_encode($permIds),
                ]);
                $msg = eh_lang('EH_CHAT_MENU_ADDED');
            }
        }
    } elseif (($_POST['eh_menu_action'] ?? '') === 'remove') {
        /* تكامل المنيو — إزالة بند المحادثات من المنيو المختار */
        $menuId = (int)($_POST['eh_menu_id'] ?? 0);
        if ($menuId > 0) {
            $db->query("DELETE FROM us_menu_items WHERE menu = ? AND link = 'usersc/eh_messages.php'", [$menuId]);
            $msg = eh_lang('EH_CHAT_MENU_REMOVED');
        }
    } else {
        /* 1) مصفوفة البرمشن × البرمشن */
        $matrix = [];
        $matrixRaw = $_POST['matrix'] ?? [];
        if (is_array($matrixRaw)) {
            foreach ($matrixRaw as $fromPerm => $targets) {
                $targets = array_map('intval', is_array($targets) ? $targets : [$targets]);
                $targets = array_values(array_filter($targets, function ($t) { return $t > 0; }));
                if ($targets) $matrix[(int)$fromPerm] = $targets;
            }
        }

        /* 2) البرالمشن اللي عندها صلاحية broadcast */
        $broadcastPerms = array_map('intval', (array)($_POST['broadcast_perms'] ?? []));
        $broadcastPerms = array_values(array_filter($broadcastPerms, function ($p) { return $p > 0; }));

        /* 3) مستخدمين محددين عندهم صلاحية broadcast */
        $broadcastUsers = array_map('intval', (array)($_POST['broadcast_users'] ?? []));
        $broadcastUsers = array_values(array_filter($broadcastUsers, function ($u) { return $u > 0; }));

        /* 4) حد الـonline بالدقائق */
        $cutoff = max(1, min(60, (int)($_POST['online_cutoff'] ?? 5)));

        /* الحفظ مضمون: لو صف الإعدادات مش موجود أصلًا بيتعمل INSERT بدل ما
           الـUPDATE يفشل بصمت ويستمر الوضع على الافتراضي */
        $hasRow = $db->query("SELECT id FROM eh_chat_settings WHERE id = 1")->count();
        if ($hasRow) {
            $db->query(
                "UPDATE eh_chat_settings SET perm_matrix = ?, broadcast_perms = ?, broadcast_users = ?, online_cutoff = ? WHERE id = 1",
                [json_encode($matrix), json_encode($broadcastPerms), json_encode($broadcastUsers), $cutoff]
            );
        } else {
            $db->query(
                "INSERT INTO eh_chat_settings (id, perm_matrix, broadcast_perms, broadcast_users, online_cutoff) VALUES (1, ?, ?, ?, ?)",
                [json_encode($matrix), json_encode($broadcastPerms), json_encode($broadcastUsers), $cutoff]
            );
        }
        $msg = eh_lang('EH_CHAT_SAVED');
    }
}

$perms = $db->query("SELECT id, name, descrip FROM permissions WHERE id NOT IN (1) ORDER BY id ASC")->results();
$allUsers = $db->query("SELECT id, username, fname, lname FROM users WHERE active = 1 ORDER BY COALESCE(NULLIF(fname, ''), username) ASC")->results();
$menus = $db->query("SELECT id, menu_name FROM us_menus ORDER BY id ASC")->results();

$settings = eh_chat_settings_row();
$matrix = $settings->perm_matrix;
$broadcastPerms = $settings->broadcast_perms;
$broadcastUsers = $settings->broadcast_users;

$wdActive = function_exists('isUserOnline');
$csrf = Token::generate();
?>
<div class="content mt-3">
    <div class="row">
        <div class="col-12">
            <h1><?= eh_lang('EH_CHAT_SETTINGS_TITLE') ?></h1>
            <a href="<?= $us_url_root ?>users/admin.php?view=plugins"><?= eh_lang('EH_CHAT_BACK') ?></a>
            <hr>
            <?php if ($msg) { ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php } ?>
            <div class="alert alert-info">
                <i class="fas fa-circle <?= $wdActive ? 'text-success' : 'text-warning' ?>"></i>
                <?= $wdActive
                    ? eh_lang('EH_CHAT_WD_ACTIVE')
                    : eh_lang('EH_CHAT_WD_INACTIVE') ?>
            </div>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">

        <!-- المصفوفة -->
        <div class="card card-outline card-primary mb-3">
            <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_MATRIX_TITLE') ?></h3></div>
            <div class="card-body">
                <p class="text-muted"><?= eh_lang('EH_CHAT_MATRIX_HINT') ?></p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th><?= eh_lang('EH_CHAT_SENDER_TARGET') ?></th>
                                <?php foreach ($perms as $p) { ?>
                                    <th class="text-center" style="min-width:70px"><?= htmlspecialchars($p->name) ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perms as $from) { ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($from->name) ?></strong></td>
                                <?php foreach ($perms as $to) { ?>
                                    <td class="text-center" <?= ((int)$from->id === (int)$to->id) ? 'style="background:#f4f6f8"' : '' ?>>
                                        <input type="checkbox" name="matrix[<?= (int)$from->id ?>][]"
                                               value="<?= (int)$to->id ?>"
                                               title="<?= (int)$from->id === (int)$to->id ? eh_lang('EH_CHAT_SAME_PERM') : '' ?>"
                                            <?= in_array((int)$to->id, (array)($matrix[$from->id] ?? [])) ? 'checked' : '' ?>>
                                    </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- الـbroadcast -->
        <div class="row">
            <div class="col-md-6">
                <div class="card card-outline card-success mb-3">
                    <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_BC_PERMS_TITLE') ?></h3></div>
                    <div class="card-body">
                        <p class="text-muted"><?= eh_lang('EH_CHAT_BC_PERMS_HINT') ?></p>
                        <?php foreach ($perms as $p) { ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="broadcast_perms[]"
                                       value="<?= (int)$p->id ?>" id="bp<?= (int)$p->id ?>"
                                    <?= in_array((int)$p->id, $broadcastPerms) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bp<?= (int)$p->id ?>"><?= htmlspecialchars($p->name) ?></label>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-outline card-warning mb-3">
                    <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_BC_USERS_TITLE') ?></h3></div>
                    <div class="card-body">
                        <p class="text-muted"><?= eh_lang('EH_CHAT_BC_USERS_HINT') ?></p>
                        <select class="form-select eh-select2" name="broadcast_users[]" multiple
                                data-placeholder="<?= htmlspecialchars(eh_lang('EH_CHAT_PICK_USER')) ?>">
                            <?php foreach ($allUsers as $u) { ?>
                                <option value="<?= (int)$u->id ?>"
                                    <?= in_array((int)$u->id, $broadcastUsers) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(eh_chat_name($u)) ?> (<?= htmlspecialchars($u->username) ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_ONLINE_TITLE') ?></h3></div>
                    <div class="card-body">
                        <label class="form-label"><?= eh_lang('EH_CHAT_ONLINE_LABEL') ?></label>
                        <input type="number" class="form-control" name="online_cutoff" min="1" max="60"
                               value="<?= (int)$settings->online_cutoff ?>">
                        <small class="text-muted"><?= eh_lang('EH_CHAT_ONLINE_HINT') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= eh_lang('EH_CHAT_SAVE') ?></button>
    </form>

    <!-- تكامل المنيو: بند المحادثات آخر المنيو للثيمات التانية — فورم مستقل
         عشان ضغط الإضافة/الإزالة مايبعتش الماتريكس فاضية بالغلط -->
    <div class="card card-outline card-info mb-3">
        <div class="card-header"><h3 class="card-title"><?= eh_lang('EH_CHAT_MENU_TITLE') ?></h3></div>
        <div class="card-body">
            <p class="text-muted"><?= eh_lang('EH_CHAT_MENU_HINT') ?></p>
            <?php
            $chatItems = $db->query(
                "SELECT mi.id, mi.menu, mi.display_order, m.menu_name
                 FROM us_menu_items mi
                 JOIN us_menus m ON m.id = mi.menu
                 WHERE mi.link = 'usersc/eh_messages.php'
                 ORDER BY mi.menu ASC"
            )->results();
            ?>
            <?php if ($chatItems) { ?>
                <div class="mb-2">
                    <?php foreach ($chatItems as $ci) { ?>
                    <span class="d-inline-flex align-items-center gap-2 me-3 mb-1">
                        <span class="badge bg-info"><?= htmlspecialchars((string)$ci->menu_name) ?> (#<?= (int)$ci->menu ?>)</span>
                        <span class="text-muted"><?= (int)$ci->display_order ?></span>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="eh_menu_action" value="remove">
                            <input type="hidden" name="eh_menu_id" value="<?= (int)$ci->menu ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> <?= eh_lang('EH_CHAT_MENU_REMOVE') ?></button>
                        </form>
                    </span>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="text-muted"><?= eh_lang('EH_CHAT_MENU_NONE') ?></p>
            <?php } ?>

            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="eh_menu_action" value="add">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ehMenuId"><?= eh_lang('EH_CHAT_MENU_SELECT') ?></label>
                    <select class="form-select" id="ehMenuId" name="eh_menu_id">
                        <?php foreach ($menus as $m) { ?>
                            <option value="<?= (int)$m->id ?>"><?= htmlspecialchars((string)$m->menu_name) ?> — #<?= (int)$m->id ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ehMenuIcon"><?= eh_lang('EH_CHAT_MENU_ICON') ?></label>
                    <input class="form-control" id="ehMenuIcon" name="eh_menu_icon" value="fas fa-comments">
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-info w-100"><i class="fas fa-bars"></i> <?= eh_lang('EH_CHAT_MENU_ADD') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نسخة select2 المستقلة الخاصة بالبلجن -->
<link rel="stylesheet" href="<?= $us_url_root ?>usersc/plugins/eh_chat/assets/plugins/select2/css/select2.min.css">
<script src="<?= $us_url_root ?>usersc/plugins/eh_chat/assets/plugins/select2/js/select2.min.js"></script>
<script>
if (window.jQuery && jQuery.fn.select2) {
    jQuery('.eh-select2').select2({ width: '100%', placeholder: jQuery('.eh-select2').data('placeholder') || '', closeOnSelect: false });
}
</script>
