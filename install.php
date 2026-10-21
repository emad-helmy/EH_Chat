<?php
/**
 * تثبيت البلجن — يتشغّل من مدير البلجنز (أو يدويًا بفتحه في المتصفح)
 * كله idempotent — يشتغل أكثر من مرة من غير مشاكل
 */
require_once __DIR__ . '/../../../users/init.php';

if (!in_array($user->data()->id, $master_account)) {
    Redirect::to($us_url_root . 'users/admin.php');
    die();
}

$db = DB::getInstance();
require_once __DIR__ . '/functions.php';

/* 1) الجداول */
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `eh_chat_messages` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `sender_id` INT UNSIGNED NOT NULL,
          `receiver_id` INT UNSIGNED NOT NULL,
          `message` TEXT NOT NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `read_at` DATETIME DEFAULT NULL,
          `type` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_receiver` (`receiver_id`, `is_read`),
          KEY `idx_sender` (`sender_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS `eh_chat_settings` (
          `id` TINYINT(1) NOT NULL DEFAULT 1,
          `perm_matrix` TEXT,
          `broadcast_perms` TEXT,
          `broadcast_users` TEXT,
          `online_cutoff` INT NOT NULL DEFAULT 5,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_watchdog` DATETIME DEFAULT NULL");
} catch (Throwable $e) {
    die('فشل إنشاء الجداول: ' . $e->getMessage());
}

/* 2) صفحات البلجن */
foreach (['usersc/eh_chat.php', 'usersc/eh_messages.php'] as $pg) {
    $db->query("INSERT INTO `pages` (`page`, `private`) SELECT ?, 1 FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = ?)", [$pg, $pg]);
}
$db->query("
    INSERT INTO `permission_page_matches` (`page_id`, `permission_id`)
    SELECT p.id, per.id
    FROM `pages` p
    CROSS JOIN `permissions` per
    WHERE p.page IN ('usersc/eh_chat.php', 'usersc/eh_messages.php')
      AND NOT EXISTS (
        SELECT 1 FROM `permission_page_matches` m WHERE m.page_id = p.id AND m.permission_id = per.id
      )
");

/* 3) تسجيل البلجن */
$db->query("INSERT INTO `us_plugins` (`plugin`, `status`) SELECT 'eh_chat', 'inactive' FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM `us_plugins` WHERE `plugin` = 'eh_chat')");

/* 4) إعدادات افتراضية — كل برمشن يقدر يكلم نفسه + الأدمن(2) والمدير(5) */
$matrix = [];
foreach ($db->query("SELECT id FROM `permissions`")->results() as $p) {
    $matrix[$p->id] = [(int)$p->id, 2, 5];
}
eh_chat_deploy_pages();
$db->query("
    INSERT INTO `eh_chat_settings` (`id`, `perm_matrix`, `broadcast_perms`, `broadcast_users`, `online_cutoff`)
    SELECT 1, ?, ?, JSON_ARRAY(), 5 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `eh_chat_settings` WHERE `id` = 1)
", [json_encode($matrix), json_encode([2])]);

/* ملفات اللغة في usersc/lang — UserSpice بيقراها تلقائيًا بعد users/lang.
   دمج آمن: ملف المستخدم بيفضل زي ما هو — إحنا بنضيف/نحدّث كتلة مفاتيحنا
   بس (بعلامات BEGIN/END) من غير ما نلمس أي array_merge أو مفاتيح موجودة. */
eh_chat_lang_install_keys();

/* زامنة الـini عشان الفوتر يشتغل فورًا (زي مدير البلجنز بالظبط) */
eh_chat_sync_ini();
eh_chat_deploy_pages();

Redirect::to($us_url_root . 'users/admin.php?view=plugins&err=EH Chat installed — فعّله من زرار Activate');
