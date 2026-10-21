<?php
/**
 * EH WhatsApp Like — الدوال المشتركة
 * البلجن مستقل تمامًا — مفيش أي اعتماد على كود المشروع غير Userspice نفسه
 */
if (count(get_included_files()) == 1) die();

/* ---------------------------------------------------------------
 * زامنة plugins.ini.php من الداتابيز (نفس ما بيعمل مدير البلجنز)
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_sync_ini')) {
    function eh_chat_sync_ini()
    {
        $db = DB::getInstance();
        $all = $db->query("SELECT plugin, status FROM us_plugins")->results();
        $ini = [];
        foreach ($all as $p) {
            $ini[$p->plugin] = ($p->status === 'active') ? 1 : 2;
        }
        write_php_ini($ini, $abs_us_root . $us_url_root . 'usersc/plugins/plugins.ini.php');
    }
}

/* ---------------------------------------------------------------
 * نشر صفحات البلجن في usersc/ (المصدر جوه assets/pages)
 * — بيتكلم في install / activate / migrate
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_deploy_pages')) {
    function eh_chat_deploy_pages()
    {
        $src = __DIR__ . '/assets/pages';
        $dst = dirname(__DIR__, 2); // .../usersc
        foreach (['eh_chat.php', 'eh_messages.php'] as $f) {
            $from = "$src/$f";
            $to   = "$dst/$f";
            if (file_exists($from) && (!file_exists($to) || filemtime($from) > filemtime($to))) {
                @copy($from, $to);
            }
        }
    }
}

/* ---------------------------------------------------------------
 * الإعدادات
 * ------------------------------------------------------------- */
/* ---------------------------------------------------------------
 * اللغة — ملفات البلجن ← usersc/lang (بيكتبها الـinstall)
 * UserSpice نفسه مش بيقراها (بيصفّر $lang بـ $lang = array() بعد ما يقرا
 * users/lang)، فبنقراها إحنا مرة واحدة لكل طلب في eh_lang()
 * ------------------------------------------------------------- */
if (!function_exists('eh_lang')) {
    /** مفاتيح احتياطية لو ملف اللغة لسه ما اتحمّلش (مثلاً قبل ما الفوتر يشتغل) */
    function eh_chat_lang_fallback($key)
    {
        $ar = ['EH_CHAT_TODAY' => 'اليوم', 'EH_CHAT_YESTERDAY' => 'أمس',
            'EH_CHAT_READ_ONLY' => 'مسموح تستلم رسايل من المستخدم ده بس — مش مسموح تردها (راجع الأدمن).',
            'EH_CHAT_DAY_SUN' => 'الأحد', 'EH_CHAT_DAY_MON' => 'الاثنين', 'EH_CHAT_DAY_TUE' => 'الثلاثاء',
            'EH_CHAT_DAY_WED' => 'الأربعاء', 'EH_CHAT_DAY_THU' => 'الخميس', 'EH_CHAT_DAY_FRI' => 'الجمعة',
            'EH_CHAT_DAY_SAT' => 'السبت'];
        $en = ['EH_CHAT_TODAY' => 'Today', 'EH_CHAT_YESTERDAY' => 'Yesterday',
            'EH_CHAT_READ_ONLY' => 'You can receive messages from this user, but you cannot reply (check with the admin).',
            'EH_CHAT_DAY_SUN' => 'Sunday', 'EH_CHAT_DAY_MON' => 'Monday', 'EH_CHAT_DAY_TUE' => 'Tuesday',
            'EH_CHAT_DAY_WED' => 'Wednesday', 'EH_CHAT_DAY_THU' => 'Thursday', 'EH_CHAT_DAY_FRI' => 'Friday',
            'EH_CHAT_DAY_SAT' => 'Saturday'];
        $lg  = (string)($_SESSION['us_lang'] ?? 'en-US');
        $bag = (strpos($lg, 'ar') === 0) ? $ar : $en;
        return $bag[$key] ?? '';
    }

    /** ترجمة مفتاح — بتقرأ قاموس لغة الموقع + ملف لغة البلجن في usersc/lang */
    function eh_lang($key, $fallback = '')
    {
        global $lang, $abs_us_root, $us_url_root;

        /* ملف لغة البلجن (مفاتيح EH_CHAT_ فقط) — بيتقرأ مرة واحدة لكل طلب
           (مهم: بعد users/lang اللي بيصفّر $lang بـ $lang = array()) */
        static $ehLoaded = false;
        if (!$ehLoaded) {
            $ehLoaded = true;
            $lg = (string)($_SESSION['us_lang'] ?? 'en-US');
            /* المسار من مجلد البلجن نفسه (__DIR__) — أضمن من $us_url_root
               اللي ممكن يبقى فاضي في سياقات مختلفة فيتحل المسار غلط */
            $lf = dirname(__DIR__, 2) . '/lang/' . $lg . '.php';
            if (!file_exists($lf)) {
                $lf = $abs_us_root . $us_url_root . 'usersc/lang/' . $lg . '.php';
            }
            $bag = [];
            if (file_exists($lf)) {
                $txt = (string) @file_get_contents($lf);
                if (preg_match_all("/['\"](EH_CHAT_[A-Z0-9_]+)['\"]\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/s", $txt, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) { $bag[$m[1]] = stripcslashes($m[2]); }
                }
                if (empty($bag)) {
                    $saved = $lang;
                    include $lf;
                    foreach ((array) $lang as $k => $v) {
                        if (strpos((string)$k, 'EH_CHAT_') === 0) { $bag[$k] = $v; }
                    }
                    $lang = $saved;
                }
            }
            $GLOBALS['EH_CHAT_BAG'] = $bag;
        }
        if (isset($GLOBALS['EH_CHAT_BAG'][$key])) return $GLOBALS['EH_CHAT_BAG'][$key];

        /* قاموس لغة الموقع */
        if (isset($lang[$key]) && eh_chat_lang_ok($lang[$key])) return $lang[$key];

        /* احتياطي مدمج (تواريخ/أيام) */
        $auto = eh_chat_lang_fallback($key);
        if ($auto !== '') return $auto;

        return $fallback !== '' ? $fallback : $key;
    }

    /** كل مفاتيح البلجن — للتمرير للجافاسكربت */
    function eh_chat_lang_bag()
    {
        eh_lang('EH_CHAT_MESSAGES'); /* يضمن تحميل الملف */
        return isset($GLOBALS['EH_CHAT_BAG']) ? $GLOBALS['EH_CHAT_BAG'] : [];
    }

    /** النص ده ترجمة سليمة؟ (مش فاضي ومش رسالة "Missing Text") */
    function eh_chat_lang_ok($t)
    {
        if ($t === '' || $t === null) return false;
        return (strpos((string)$t, 'Missing Text') === false);
    }
}

/* ---------------------------------------------------------------
 * مفاتيح اللغة في usersc/lang — دمج آمن:
 * مفاتيح المستخدم مابتتحذفش أبدًا — إحنا بنضيف/نشيل كتلتنا بس
 * (محدّدة بعلامات BEGIN/END واضحة) وقواعد الملف الباقية زي ما هي.
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_lang_dst_dir')) {
    function eh_chat_lang_dst_dir()
    {
        global $abs_us_root, $us_url_root;
        // الباث ممكن يكون مقدر من المستخدم — نقابلها بنسبة لجذر الموقع
        $d = rtrim($abs_us_root . $us_url_root, '/\\') . '/usersc/lang/';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }
}

if (!function_exists('eh_chat_lang_marks')) {
    /** علامات كتلة مفاتيح البلجلن */
    function eh_chat_lang_marks()
    {
        return ["/* ==== EH-CHAT LANG BEGIN ==== */", "/* ==== EH-CHAT LANG END ==== */"];
    }
}

if (!function_exists('eh_chat_lang_install_keys')) {
    /** إضافة/تحديث كتلة مفاتيح البلجن — من غير أي لمس لباقي الملف */
    function eh_chat_lang_install_keys()
    {
        $names = ['en-US.php' => 'English', 'ar-EG.php' => 'Arabic'];
        $dir   = eh_chat_lang_dst_dir();
        list($MB, $ME) = eh_chat_lang_marks();
        $re    = '~' . preg_quote($MB, '~') . '(.*?)' . preg_quote($ME, '~') . '~s';

        foreach ($names as $lg => $lgName) {
            $src = __DIR__ . '/lang/' . $lg;
            if (!is_file($src)) { continue; }
            $srcTxt = (string) file_get_contents($src);
            if (!preg_match('/array_merge\s*\(\s*\$lang\s*,\s*array\s*\((.*?)\)\s*\)\s*;/s', $srcTxt, $m)) { continue; }
            $keys  = trim($m[1]);
            $block = $MB . "\n\$lang = array_merge(\$lang,array(\n\n" . $keys . "\n\n));\n" . $ME . "\n";

            $dst = $dir . $lg;
            if (!is_file($dst)) {
                @file_put_contents($dst, "<?php\n// EH WhatsApp Like — {$lgName} strings (plugin)\n\n" . $block);
                continue;
            }

            $cur = (string) file_get_contents($dst);
            if (preg_match($re, $cur)) {
                // كتلتنا موجودة قبل كده → نحديثها (بنشيل مفاتيح البلجن القديمة بس)
                $cur = preg_replace($re, $block, $cur, 1);
            } else {
                // مفيش كتلة → نضيفها في الآخر (من غير قوس إغلاق كان بيكسر الملف)
                $cur = preg_replace('/\?>\s*$/', '', $cur);
                $cur = rtrim($cur, " \t\r\n") . "\n\n" . $block;
            }
            @file_put_contents($dst, $cur);
        }
    }
}

if (!function_exists('eh_chat_lang_uninstall_keys')) {
    /** شيل مفاتيح البلجن فقط — مفاتيح المستخدم بترسى */
    function eh_chat_lang_uninstall_keys()
    {
        $names = ['en-US.php', 'ar-EG.php'];
        $dir   = eh_chat_lang_dst_dir();
        list($MB, $ME) = eh_chat_lang_marks();
        $re    = '~' . preg_quote($MB, '~') . '(.*?)' . preg_quote($ME, '~') . '~s';

        foreach ($names as $lg) {
            $dst = $dir . $lg;
            if (!is_file($dst)) { continue; }
            $cur = (string) file_get_contents($dst);
            $orig = $cur;

            if (preg_match($re, $cur)) {
                // الكتلة المعلّمة → نشيلها كاملة
                $cur = preg_replace($re, '', $cur, 1);
                $cur = preg_replace('/[ \t]*\n[ \t]*\n[ \t]*\n/s', "\n\n", $cur); // تنظيف سطور فاضية
                // لو الملف كله بتاعنا (تاج الفتح + كومنتات بس) → ننشله
                $codeOnly = trim(preg_replace(array('/^<\?php/', '/^\s*\/\/[^\n]*/m'), '', $cur));
                if ($codeOnly === '') { @unlink($dst); continue; }
                $cur = rtrim($cur, " \t\r\n") . "\n";
            } elseif (preg_match('/EH_CHAT/', $cur)) {
                // شكل قديم من غير علامات — نشيل سطور المفاتيح بس
                $cur = preg_replace_callback(
                    '/\$lang\s*=\s*array_merge\s*\(\s*\$lang\s*,\s*array\s*\((.*?)\)\s*\)\s*;/s',
                    function ($mm) {
                        $inner = $mm[1];
                        if (strpos($inner, 'EH_CHAT') === false) { return $mm[0]; }
                        $lines = preg_split('/\r?\n/', $inner);
                        $lines = array_values(array_filter($lines, function ($l) {
                            return trim($l) === '' || strpos($l, 'EH_CHAT') === false;
                        }));
                        if (trim(implode('', $lines)) === '') { return ''; } // الكتلة كلها لينا
                        return "\$lang = array_merge(\$lang,array(\n" . implode("\n", $lines) . "\n));";
                    },
                    $cur
                );
                // مفاتيح مكتوبة $lang['EH_CHAT_X'] = '...' → نشيلها
                $cur = preg_replace("/^[ \t]*\\\$lang\s*\[\s*['\"]EH_CHAT[^'\"]*['\"]\s*\]\s*=>?[^\n]*\n?/m", '', $cur);
            }

            if ($cur !== $orig) { @file_put_contents($dst, $cur); }
        }
    }
}

if (!function_exists('eh_chat_settings_row')) {
    function eh_chat_settings_row()
    {
        $db = DB::getInstance();
        $row = $db->query("SELECT * FROM eh_chat_settings WHERE id = 1")->first();
        if (!$row) {
            return (object)[
                'perm_matrix'     => [],
                'broadcast_perms' => [],
                'broadcast_users' => [],
                'online_cutoff'   => 5,
            ];
        }
        $row->perm_matrix     = json_decode($row->perm_matrix ?: '{}', true) ?: [];
        $row->broadcast_perms = json_decode($row->broadcast_perms ?: '[]', true) ?: [];
        $row->broadcast_users = json_decode($row->broadcast_users ?: '[]', true) ?: [];
        return $row;
    }
}

/* ---------------------------------------------------------------
 * أدوات صغيرة
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_perms_of')) {
    /** مصفوفة أرقام البرمشنز للمستخدم */
    function eh_chat_perms_of($uid)
    {
        $db = DB::getInstance();
        $rows = $db->query("SELECT permission_id FROM user_permission_matches WHERE user_id = ?", [(int)$uid])->results();
        return array_map(function ($r) { return (int)$r->permission_id; }, $rows);
    }
}

if (!function_exists('eh_chat_is_admin')) {
    function eh_chat_is_admin($uid)
    {
        return hasPerm(2, (int)$uid);
    }
}

if (!function_exists('eh_chat_name')) {
    /** اسم المستخدم المعروض */
    function eh_chat_name($row)
    {
        $name = trim((string)(($row->fname ?? '') . ' ' . ($row->lname ?? '')));
        return $name !== '' ? $name : (string)($row->username ?? '');
    }
}

if (!function_exists('eh_chat_avatar')) {
    /**
     * صورة الأفاتار — نفس منطق الثيم بالظبط:
     * 1) روابط كاملة من users.profile_pic (أي بلجن)
     * 2) ملف مرفوع من بلجن profile_pic (امتداد صورة)
     * 3) أفاتار ديسكورد (بدون امتداد + disc_uid)
     * 4) وبالأخير gravatar من الإيميل (d=mm = صورة أفاتار افتراضية)
     */
    function eh_chat_avatar($uid)
    {
        global $us_url_root;
        $db = DB::getInstance();

        $cols = 'email, profile_pic';
        if (method_exists($db, 'tableExists') && method_exists($db, 'columnExists')
            && $db->tableExists('users') && $db->columnExists('users', 'disc_uid')) {
            $cols = 'email, profile_pic, disc_uid';
        }
        $u = $db->query("SELECT $cols FROM users WHERE id = ?", [(int)$uid])->first();

        $mail = ($u && !empty($u->email)) ? strtolower(trim($u->email)) : 'no-reply@example.com';
        $grav = 'https://www.gravatar.com/avatar/' . md5($mail) . '?s=80&d=mm';
        if (!$u) return $grav;

        $pic = trim((string)($u->profile_pic ?? ''));
        if ($pic !== '') {
            if (preg_match('#^(https?:)?//#i', $pic)) {
                /* رابط كامل — نستخدمه زي ما هو */
                return $pic;
            }
            $ext = strtolower(pathinfo($pic, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true)) {
                /* ملف مرفوع من بلجن profile_pic */
                $root = (!empty($us_url_root)) ? $us_url_root : '/';
                return $root . 'usersc/plugins/profile_pic/files/' . $pic;
            }
            if (trim((string)($u->disc_uid ?? '')) !== '') {
                /* أفاتار ديسكورد (hash بدون امتداد) */
                $dext = (strncmp($pic, 'a_', 2) === 0) ? 'gif' : 'png';
                return 'https://cdn.discordapp.com/avatars/' . $u->disc_uid . '/' . $pic . '.' . $dext . '?size=256';
            }
        }
        return $grav;
    }
}

if (!function_exists('eh_chat_time')) {
    /**
     * تاريخ/وقت بصيغة elite-flame حسب لغة الموقع:
     *   النهاردة → "اليوم 7:00 ص" / "Today 7:00 AM"
     *   أمبارح   → "أمس 7:00 ص" / "Yesterday 7:00 AM"
     *   قبل كده → "الاثنين 7:00 ص 15/10/2026" / "Monday 7:00 AM 15/10/2026"
     */
    function eh_chat_time($dt)
    {
        if (empty($dt)) return '';
        $ts = strtotime($dt);
        if (!$ts) return '';

        $lg = $_SESSION['us_lang'] ?? 'ar';
        $isAr = (strpos($lg, 'ar') === 0);

        $hour = (int) date('h', $ts);
        $minute = date('i', $ts);
        $period = date('a', $ts) === 'am' ? ($isAr ? 'ص' : 'AM') : ($isAr ? 'م' : 'PM');
        $time = $hour . ':' . $minute . ' ' . $period;

        $today = date('Y-m-d');
        $d = date('Y-m-d', $ts);
        if ($d === $today) {
            return eh_lang('EH_CHAT_TODAY', $isAr ? 'اليوم' : 'Today') . ' ' . $time;
        }
        if ($d === date('Y-m-d', strtotime('-1 day', strtotime($today)))) {
            return eh_lang('EH_CHAT_YESTERDAY', $isAr ? 'أمس' : 'Yesterday') . ' ' . $time;
        }

        $dayNames = $isAr
            ? ['Sun' => 'الأحد', 'Mon' => 'الاثنين', 'Tue' => 'الثلاثاء', 'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت']
            : ['Sun' => 'Sunday', 'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday'];
        $dayKey = 'EH_CHAT_DAY_' . strtoupper(date('D', $ts));
        $dayName = eh_lang($dayKey, $dayNames[date('D', $ts)] ?? '');
        return $dayName . ' ' . $time . ' ' . date('d/m/Y', $ts);
    }
}

/* ---------------------------------------------------------------
 * قواعد الصلاحيات (من صفحة إعدادات البلجن)
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_can_message')) {
    /** هل المرسل يقدر يبعت رسالة خاصة للمستقبل؟ */
    function eh_chat_can_message($senderId, $receiverId)
    {
        $senderId   = (int)$senderId;
        $receiverId = (int)$receiverId;
        if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) return false;
        if (eh_chat_is_admin($senderId)) return true;

        $settings = eh_chat_settings_row();
        $sPerms = eh_chat_perms_of($senderId);
        $rPerms = eh_chat_perms_of($receiverId);
        if (!$sPerms || !$rPerms) return false;

        foreach ($sPerms as $sp) {
            $allowed = isset($settings->perm_matrix[$sp]) ? $settings->perm_matrix[$sp] : [];
            if (array_intersect($allowed, $rPerms)) return true;
        }
        return false;
    }
}

if (!function_exists('eh_chat_can_view')) {
    /**
     * هل أقدر أفتح/أقرا المحادثة مع المستخدم ده؟
     * القراية مش الرد: لو الطرف التاني (الأدمن مثلًا) مسموح له يبعتلي
     * أقدر أقرا رسايله عادي، لكن الرد بيتحدد بالمصفوفة من جهتي أنا.
     * وكمان فواتير قديمة (فيها رسائل بين الطرفين) بتفضل مقروءة زي واتساب.
     */
    function eh_chat_can_view($uid, $otherId)
    {
        $uid     = (int)$uid;
        $otherId = (int)$otherId;
        if ($uid <= 0 || $otherId <= 0) return false;

        /* إما إن إنا أقدر أكلمه — أو إن هو مسموح له يكلمني */
        if (eh_chat_can_message($uid, $otherId)) return true;
        if (eh_chat_can_message($otherId, $uid)) return true;

        /* في رسائل بينهم أصلًا؟ يقدر يقرا التاريخ (من غير رد) */
        $db = DB::getInstance();
        $r = $db->query(
            "SELECT COUNT(*) AS c FROM eh_chat_messages
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
            [$uid, $otherId, $otherId, $uid]
        )->first();
        return $r && (int)$r->c > 0;
    }
}

if (!function_exists('eh_chat_can_broadcast')) {
    /** هل المستخدم يقدر يعمل إرسال جماعي؟ */
    function eh_chat_can_broadcast($uid)
    {
        $uid = (int)$uid;
        if ($uid <= 0) return false;
        if (eh_chat_is_admin($uid)) return true;
        $settings = eh_chat_settings_row();
        if (array_intersect(eh_chat_perms_of($uid), $settings->broadcast_perms)) return true;
        if (in_array($uid, $settings->broadcast_users)) return true;
        return false;
    }
}

if (!function_exists('eh_chat_allowed_targets')) {
    /** المستخدمين اللي المرسل يقدر يكلمهم (لليست في صفحة الإرسال) */
    function eh_chat_allowed_targets($uid)
    {
        $uid = (int)$uid;
        $db = DB::getInstance();
        $users = $db->query(
            "SELECT id, username, fname, lname FROM users WHERE active = 1 AND id != ? ORDER BY COALESCE(NULLIF(fname, ''), username) ASC",
            [$uid]
        )->results();
        if (eh_chat_is_admin($uid)) return $users;

        $settings = eh_chat_settings_row();
        $perms = eh_chat_perms_of($uid);
        $allowedPerms = [];
        foreach ($perms as $p) {
            $allowedPerms = array_merge($allowedPerms, isset($settings->perm_matrix[$p]) ? $settings->perm_matrix[$p] : []);
        }
        $allowedPerms = array_unique($allowedPerms);
        if (!$allowedPerms) return [];

        $out = [];
        foreach ($users as $u) {
            if (array_intersect($allowedPerms, eh_chat_perms_of((int)$u->id))) $out[] = $u;
        }
        return $out;
    }
}

/* ---------------------------------------------------------------
 * مستصل/غير متصل — watchdog لو شغال، غير كده قراءة last_watchdog
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_online_users')) {
    function eh_chat_online_users()
    {
        // لو بلجن الـwatchdog متفعل — استخدم دواله (فيها الـcutoff بتاعها)
        if (function_exists('fetchOnlineUsers')) return fetchOnlineUsers();

        $settings = eh_chat_settings_row();
        $cutoff = max(1, (int)$settings->online_cutoff) * 60;
        $db = DB::getInstance();
        $date = date("Y-m-d H:i:s", strtotime("-$cutoff seconds"));
        return $db->query(
            "SELECT id, username, fname, lname, last_watchdog FROM users WHERE last_watchdog >= ? AND active = 1 ORDER BY last_watchdog DESC",
            [$date]
        )->results();
    }
}

if (!function_exists('eh_chat_is_online')) {
    function eh_chat_is_online($uid)
    {
        $uid = (int)$uid;
        if ($uid <= 0) return false;
        if (function_exists('isUserOnline')) return isUserOnline($uid);

        $settings = eh_chat_settings_row();
        $cutoff = max(1, (int)$settings->online_cutoff) * 60;
        $db = DB::getInstance();
        $row = $db->query("SELECT last_watchdog FROM users WHERE id = ?", [$uid])->first();
        if (!$row || empty($row->last_watchdog)) return false;
        return strtotime($row->last_watchdog) >= strtotime("-$cutoff seconds");
    }
}

/* ---------------------------------------------------------------
 * الرسائل
 * ------------------------------------------------------------- */
if (!function_exists('eh_chat_unread_count')) {
    function eh_chat_unread_count($uid)
    {
        $db = DB::getInstance();
        return (int)$db->query(
            "SELECT COUNT(*) AS c FROM eh_chat_messages WHERE receiver_id = ? AND is_read = 0",
            [(int)$uid]
        )->first()->c;
    }
}

if (!function_exists('eh_chat_send')) {
    /**
     * إرسال لمستخدم أو أكثر — type=0 خاص (مصفوفة الصلاحيات)، type=1 جماعي
     * الحارس هنا جوه دالة الإدخال نفسها (آخر سطر دفاع) — أي مسار نسي يفحص
     * قبلها مايعديش غير المسموح لهم فعلًا، والدالة بترجع عدد المرسلات الفعلي.
     */
    function eh_chat_send($senderId, array $receiverIds, $message, $type = 0)
    {
        $senderId = (int)$senderId;
        $type     = (int)$type;
        $db  = DB::getInstance();
        $now = date('Y-m-d H:i:s');
        $sent = 0;

        /* الجماعي لازم صاحبه يكون عنده صلاحية broadcast */
        $canBroadcast = ($type === 1) ? eh_chat_can_broadcast($senderId) : false;

        foreach (array_unique(array_filter(array_map('intval', $receiverIds))) as $rid) {
            if ($rid <= 0 || $rid === (int)$senderId) continue;

            /* رسالة خاصة → لازم مصفوفة الصلاحيات تسمح بالظبط */
            if ($type === 0 && !eh_chat_can_message($senderId, $rid)) continue;
            /* جماعي → من غير صلاحية مافيش أي إدخال */
            if ($type === 1 && !$canBroadcast) continue;

            $db->insert('eh_chat_messages', [
                'sender_id'   => $senderId,
                'receiver_id' => $rid,
                'message'     => $message,
                'is_read'     => 0,
                'type'        => $type,
                'created_at'  => $now,
            ]);
            $sent++;
        }
        return $sent;
    }
}

if (!function_exists('eh_chat_mark_read')) {
    /** تعليم كل الرسائل بين مستخدمين كمقروءة */
    function eh_chat_mark_read($uid, $otherId)
    {
        $db = DB::getInstance();
        $db->query(
            "UPDATE eh_chat_messages SET is_read = 1, read_at = ? WHERE receiver_id = ? AND sender_id = ? AND is_read = 0",
            [date('Y-m-d H:i:s'), (int)$uid, (int)$otherId]
        );
    }
}

if (!function_exists('eh_chat_render_bubbles')) {
    /** رسم فقاعات المحادثة (مشتركة بين الصفحة والـpolling) */
    function eh_chat_render_bubbles($messages, $meId)
    {
        foreach ($messages as $m) {
            $isMe = (int)$m->sender_id === (int)$meId;
            $cls  = $isMe ? 'eh-bubble-me' : 'eh-bubble-other';
            ?>
            <div class="eh-bubble <?= $cls ?>">
                <div class="eh-bubble-text"><?= nl2br(htmlspecialchars($m->message)) ?></div>
                <div class="eh-bubble-meta">
                    <span><?= eh_chat_time($m->created_at) ?></span>
                    <?php if ($isMe) {
                        $isRead = ((int)$m->is_read === 1);
                        $tick   = $isRead ? 'read' : '';
                        $tickIc = $isRead ? 'fa-check-double' : 'fa-check';
                    ?>
                        <i class="fas <?= $tickIc ?> eh-tick <?= $tick ?>"
                           title="<?= eh_lang($isRead ? 'EH_CHAT_READ' : 'EH_CHAT_SENT') ?>"></i>
                    <?php } ?>
                </div>
            </div>
            <?php
        }
    }
}
