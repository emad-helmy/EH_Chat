<?php
/**
 * تفعيل البلجن — من مدير البلجنز (master account بس)
 */
require_once __DIR__ . '/../../../users/init.php';

if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include __DIR__ . '/plugin_info.php';
    require_once __DIR__ . '/functions.php';

    $checkQ = $db->query("SELECT * FROM us_plugins WHERE plugin = ?", [$plugin_name]);
    if ($checkQ->count() > 0) {
        $check = $checkQ->first();
        $db->update('us_plugins', $check->id, ['status' => 'active']);
        if (!$db->error()) {
            err($plugin_name . ' activated');
            logger($user->data()->id, "USPlugins", $plugin_name . " Activated");
            eh_chat_sync_ini();
            eh_chat_deploy_pages();
        } else {
            err($plugin_name . ' was not activated');
        }
    } else {
        err($plugin_name . ' is not found! Has it been installed?');
    }
}
Redirect::to($us_url_root . 'users/admin.php?view=plugins');
