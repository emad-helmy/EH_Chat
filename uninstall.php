<?php
/**
 * إزالة البلجن — بيتشغّل في وضعين:
 *
 * 1) من مدير البلجنز (include من users/views/_admin_plugins.php):
 *    لازم يبقى صامت تمامًا (مفيش redirect ولا die ولا echo) —
 *    عشان الريكواست يكمل: UserSpice بعد كده بيشيل فولدر البلجن بنفسه.
 *    - Delete  → تنظيف كامل (جداول + صفحات + سجل + لغة + منيو)
 *    - Deactivate → مش بيلمس حاجة (الداتا واللغة تفضل محفوظة)
 *
 * 2) دعوة مباشرة من المتصفح — نفس التنظيف الكامل + redirect بعدها.
 */
if (!isset($db) || !isset($user)) {
    require_once __DIR__ . '/../../../users/init.php';
}

/* وصلنالك إزاي؟ (include من مدير البلجنز ولا دعوة مباشرة) */
$ehIncluded = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) !== realpath(__FILE__);

if (isset($user) && $user->isLoggedIn() !== true) {
    Redirect::to($us_url_root . 'users/login.php');
    die();
}

if ($ehIncluded) {
    /* include جوه مدير البلجنز — نعرف الـmode من البارامترز */
    $ehDeact = (class_exists('Input') && method_exists('Input', 'get')) ? (string)Input::get('deactivate') : '';
    $ehDel   = (class_exists('Input') && method_exists('Input', 'get')) ? (string)Input::get('delete') : '';
    $ehMode  = ($ehDeact !== '' && $ehDel === '') ? 'deactivate' : 'delete';
} else {
    /* دعوة مباشرة — نفس حماية الـinstall (ماستر بس) */
    if (!in_array($user->data()->id, $master_account)) {
        Redirect::to($us_url_root . 'users/admin.php');
        die();
    }
    $ehMode = 'delete';
}

/* الـDeactivate بيسيب الداتا كما هي — والتنظيف بيحصل بس عند Delete/يدوي */
if ($ehMode === 'deactivate') {
    if (!$ehIncluded) {
        Redirect::to($us_url_root . 'users/admin.php?view=plugins');
        die();
    }
    return;
}

require_once __DIR__ . '/plugin_info.php';
require_once __DIR__ . '/functions.php';

$db->query("DROP TABLE IF EXISTS `eh_chat_messages`");
$db->query("DROP TABLE IF EXISTS `eh_chat_settings`");
$db->query("DELETE FROM `pages` WHERE `page` IN ('usersc/eh_chat.php', 'usersc/eh_messages.php')");
$db->query("DELETE FROM `permission_page_matches` WHERE `page_id` NOT IN (SELECT id FROM `pages`)");
$db->query("DELETE FROM `us_plugins` WHERE `plugin` = ?", [$plugin_name]);

/* عناصر المنيو اللي البلجن ضافها */
$db->query("DELETE FROM `us_menu_items` WHERE `link` LIKE '%eh_messages.php%'");

/* الصفحات المنشورة في usersc/ */
foreach (['eh_chat.php', 'eh_messages.php'] as $f) {
    $copied = rtrim($abs_us_root . $us_url_root, '/\\') . '/usersc/' . $f;
    if (file_exists($copied)) { @unlink($copied); }
}

/* مفاتيح اللغة — نشيل كتلتنا بس، مفاتيح المستخدم تفضل زي ما هي */
eh_chat_lang_uninstall_keys();

if (!$ehIncluded) {
    err($plugin_name . ' uninstalled');
    Redirect::to($us_url_root . 'users/admin.php?view=plugins');
}
