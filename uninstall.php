<?php
/**
 * إزالة البلجن — يحذف الجداول والصفحات والسجل من us_plugins
 * (عمود last_watchdog مش بيتشال — ممكن الـwatchdog بيستخدمه)
 */
require_once __DIR__ . '/../../../users/init.php';

if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include __DIR__ . '/plugin_info.php';

    $db->query("DROP TABLE IF EXISTS `eh_chat_messages`");
    $db->query("DROP TABLE IF EXISTS `eh_chat_settings`");
    $db->query("DELETE FROM `pages` WHERE `page` IN ('usersc/eh_chat.php', 'usersc/eh_messages.php')");
    $db->query("DELETE FROM `permission_page_matches` WHERE `page_id` NOT IN (SELECT id FROM `pages`)");
    $db->query("DELETE FROM `us_plugins` WHERE `plugin = ?", [$plugin_name]);

    /* حذف الصفحات المنشورة من usersc/ */
    foreach (['eh_chat.php', 'eh_messages.php'] as $f) {
        $copied = $abs_us_root . $us_url_root . 'usersc/' . $f;
        if (file_exists($copied)) { @unlink($copied); }
    }

    err($plugin_name . ' uninstalled');
}
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
