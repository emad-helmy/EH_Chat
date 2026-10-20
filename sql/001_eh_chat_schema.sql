-- ============================================================
-- EH WhatsApp Like — schema (يشتغل من phpMyAdmin يدويًا)
-- الجداول: eh_chat_messages + eh_chat_settings
-- ============================================================

CREATE TABLE IF NOT EXISTS `eh_chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `type` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = خاص، 1 = جماعي',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receiver` (`receiver_id`, `is_read`),
  KEY `idx_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `eh_chat_settings` (
  `id` TINYINT(1) NOT NULL DEFAULT 1,
  `perm_matrix` TEXT COMMENT 'JSON: البرمشن المرسل -> مصفوفة البرالمشن اللي يقدر يكلمهم',
  `broadcast_perms` TEXT COMMENT 'JSON: البرالمشن اللي عندها صلاحية الـbroadcast',
  `broadcast_users` TEXT COMMENT 'JSON: يوزرز محددين عندهم صلاحية الـbroadcast',
  `online_cutoff` INT NOT NULL DEFAULT 5 COMMENT 'دقائق — بعدها المستخدم يبقى offline',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- عمود آخر ظهور (نفس عمود الـwatchdog — لو مش موجود يتضاف)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_watchdog` DATETIME DEFAULT NULL;

-- صفحات البلجن (usersc/eh_chat.php + usersc/eh_messages.php)
INSERT INTO `pages` (`page`, `private`)
SELECT 'usersc/eh_chat.php', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_chat.php');

INSERT INTO `pages` (`page`, `private`)
SELECT 'usersc/eh_messages.php', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pages` WHERE `page` = 'usersc/eh_messages.php');

-- كل البرالمشن تقدر تفتح الصفحتين (الأدمن بعدها بيقيد الإرسال من صفحة الإعدادات)
INSERT INTO `permission_page_matches` (`page_id`, `permission_id`)
SELECT p.id, per.id
FROM `pages` p
CROSS JOIN `permissions` per
WHERE p.page IN ('usersc/eh_chat.php', 'usersc/eh_messages.php')
  AND NOT EXISTS (
    SELECT 1 FROM `permission_page_matches` m WHERE m.page_id = p.id AND m.permission_id = per.id
  );

-- تسجيل البلجن في مدير البلجنز (inactive — الأدمن بيفعله من لوحة التحكم)
INSERT INTO `us_plugins` (`plugin`, `status`)
SELECT 'eh_chat', 'inactive' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `us_plugins` WHERE `plugin` = 'eh_chat');

-- إعدادات افتراضية: كل برمشن يقدر يكلم الأدمن(2) والمدير(5) + الأدمن يقدر يعمل broadcast
INSERT INTO `eh_chat_settings` (`id`, `perm_matrix`, `broadcast_perms`, `broadcast_users`, `online_cutoff`)
SELECT 1,
       (SELECT JSON_OBJECTAGG(id, JSON_ARRAY(2, 5)) FROM `permissions`),
       JSON_ARRAY(2),
       JSON_ARRAY(),
       5
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eh_chat_settings` WHERE `id` = 1);
