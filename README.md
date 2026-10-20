# EH WhatsApp Like — UserSpice Chat Plugin

<div dir="rtl">

## العربي

بلجن محادثات داخلي شبيه بواجهة واتساب لأي موقع يعمل بنظام **UserSpice 6.1.7**.
مستقل تمامًا عن أي كود تاني في الموقع (ولا يعتمد على بلجن خارجي ولا الـCRM)، وبيتثبت من غير أي تعديل على ملفات النظام.

| | |
| --- | --- |
| **المؤلف** | Emad Helmy |
| **الإصدار** | 1.0.0 — مختبر مع UserSpice 6.1.7 |
| **الترخيص** | MIT |

### المميزات
- **محادثة واحد-لواحد** بفقاعات رسايل زي واتساب بالظبط.
- **إرسال جماعي (Broadcast):** لبرمشنات معينة، أو لكل الموقع (`@all` — للأدمن بس)، أو لمستخدمين محددين.
- **مصفوفة صلاحيات (Permission Matrix):** الأدمن بيحدد مين يقدر يكلم مين (برمشن × برمشن) من صفحة إعدادات البلجن.
- **القراية مش الرد:** لو الأدمن بعت لمستخدم مش مسموح له يرد عليه — المستخدم **يقدر يفتح ويقرا** الرسالة، ومربع الرد بيختفي مع إشعار واضح، وأي محاولة رد مخبولة بترفض من السيرفر نفسه.
- **عداد رسائل غير مقروءة** على زر عائم أسفل اليمين + دروب داون في الشريط العلوي بستايل AdminLTE 4.
- **مؤشر متصل/غير متصل** عبر عمود `last_watchdog` (ولو بلجن watchdog شغال بيتكامل معاه).
- **قراية الرسالة:** علامة ✓ رمادية (اترسلت) و✓✓ زرق (اتقرت) زي واتساب.
- **دعم كامل للدارك مود** (`data-bs-theme="dark"`) في كل أجزاء البلجن.
- **دعم اللغتين:** كل واجهة البلجن بتتبع لغة الموقع (عربي / إنجليزي) بملفات لغة مستقلة + احتياطي مدمج.
- **SweetAlert2** مرفقة مع البلجن نفسه (من غير أي تثبيت إضافي) — ومفيش استخدم لدير أو alert مش نافة من الإانتربيوي.
- **Select2** مرفقة كمان (بحث + اختيار متعدد) في كل دروب داونس — بتقدر تبعت رسالة واحدة لكذا مستخدم في نفس الوقت، والمستخدمين الكزامة بيتفلتر بسرعة (بحث بالاسم أو اليوزرنيم).

### التثبيت
1. ارفع فولدر `eh_chat` كامل تحت:
   ```
   usersc/plugins/
   ```
2. من لوحة تحكم الأدمن: **Plugins → Informations → Install** — دولًا أول ما البلوغين يتركب، هيُنشئ تلقائيًا:
   - جداول الداتابيز الخاصة بالبلجن
   - صفحات `usersc/eh_chat.php` + `usersc/eh_messages.php`
   - مفاتيح اللغة في `usersc/lang` (عربي + إنجليزي)
   - تسجيل البلجن في نظام بلجنات UserSpice
3. فعّل البلجن (Activate).
4. من زر **EH Chat (Configure)** اضبط مصفوفة الصلاحيات وإعدادات الـBroadcast.
5. **مفيش SQL جديد** — الجداول بتتعمل أوتوماتيك من الـInstall، وتقدر تعيد التثبيت بأمان (Idempotent).

### الصفحات اللي البلجن بيعملها
| الصفحة | وظيفتها |
| --- | --- |
| `usersc/eh_chat.php` | شات عمودين: قايمة جهات (شمال) + مربع المحادثة (يمين)، مع تحديث (polling) كل ٣ ثواني |
| `usersc/eh_messages.php` | آخر المحادثات + شاشة إرسال (خاص / صفحة بروادكاست / @all) |

### هيكل الداتابيز (بيتعمل أتوماتيك من Install)
| الجدول | وظيفته |
| --- | --- |
| `eh_chat_messages` | الرسائل: مرسل / مستلم / النص / حالة القراءة / نوع (خاص أو جماعي) / التوقيت |
| `eh_chat_settings` | إعدادات البلجن (سطر واحد): مصفوفة الصلاحيات + برمشنات ومستخدمين الـBroadcast + حد مؤشر الاتصال (دقائق) |
| `users.last_watchdog` | توقيت آخر نشاط لكل مستخدم (بيستخدمه المؤشر متصل/غير متصل) — عمود يُضاف على جدولUsers |

### نظام الصلاحيات
- **مصفوفة البرمشنات:** الصفوف = المرسل، الأعمدة = المستقبل. الأدمن (برمشن 2) يقدر يكلم أي حد دايمًا.
- **الإرسال الجماعي:** بيمر من فحص صلاحية الـBroadcast — إما برمشن معتمدة أو مستخدمين محددين في الإعدادات.
- **فتح المحادثة (القراية) مش الرد:** أقدر أقرا لو أنا مسموح أكلمه *أو* هو مسموح يكلمني *أو* في فواتير (رسايل قديمة) بيننا؛ لكن الرد بيتحدد بس من مصفوفة المرسل أنا.
- **حارس مركزي:** كل إدراج رسالة بيعدي من `eh_chat_send()` اللي بترفض المستلم غير المسموح — يعني حتى لو مسار قديم سب الفحص، بالكتابة المضمونة مافيش حالة خدش صلاحية.
- **CSRF Token** مطلوب في كل عملية إرسال، والصفحات بتستخدم `securePage()` العادية زي أي صفحة UserSpice.
- لو سطر إعدادات إعدادات البلجن مفقود من أي سبب، صفحة الإعدادات بتُنشئه قبل الحفظ الأول (بدل فشل صامت).

### الـAJAX API
كل الردود JSON موحد نفس الشكل: `{"status": "success|error", "message": ...}`

| الملف | الوظيفة |
| --- | --- |
| `ajax/nav.php` | عداد غير المقروء + آخر محادثات (دروب داون النافبار + الزر العائم) |
| `ajax/contacts.php` | قايمة المحادثات لصفحة اShate |
| `ajax/send.php` | إرسال (خاص / لبرمشن / @all / بروادكاست) بفحص صلاحيات كامل |
| `ajax/messages.php` | رسائل محادثة واحدة + تعليمها كمقروءة (بيرجّع `can_send` عشان الواجهة تعرف تخفي مربع الرد ولا لأ) |

### ملفات اللغة
- البلجن بيكتشف لغة الموقع من `$_SESSION['us_lang']` ويحمّل مقاديره المشففة، وبيكتبها كمان في `usersc/lang/<code>.php` وقت التثبيت.
- أي مفتاح مش موجود بيرجع لترجمة احتياطية مدمجة `eh_chat_lang_fallback()` (تواريخ / أيام الأسبوع / إشعارات).

### شجرة الملفات
```
usersc/plugins/eh_chat/
├── activate.php / install.php / uninstall.php / update.php / migrate.php
├── configure.php          ← صفحة إعدادات الأدمن (المصفوفة + Broadcast + حد الاتصال)
├── functions.php          ← المنطق كله (الصلاحيات / التوقيت / الإرسال / الأفاتار)
├── footer.php             ← التضمين الرسمي: الزر العائم + الأصول + مؤشر الاتصال
├── logo.png  plugin_info.php  info.xml
├── ajax/                  (nav, contacts, send, messages)
├── assets/                (css, js, SweetAlert2 + Select2, صفحات مصدرية عند التثبيت)
├── lang/                  (ar-EG.php, en-US.php)
├── sql/001_eh_chat_schema.sql
└── assets/pages/           ← الصفحات اللي بتتنقل لـusersc (eh_chat.php, eh_messages.php) — كاملة بغير view
```

### ملاحظة Git
ملفات اللغة المكتوبة على السيرفر بتظهر مرة ثانية من التثبيت، فمتضفَّتش للـGit:
```
# بتتعمل أوتوماتيك من الـInstall — مش ملفات مصدر
usersc/lang/en-US.php
usersc/lang/ar-EG.php
```

---

</div>

## English

A WhatsApp-style internal chat plugin for any site running **UserSpice 6.1.7**.
It is fully independent from the rest of the site (no external plugin or CRM dependency) and installs without touching any core file.

| | |
| --- | --- |
| **Author** | Emad Helmy |
| **Version** | 1.0.0 — tested with UserSpice 6.1.7 |
| **License** | MIT |

### Features
- **One-to-one chat** with WhatsApp-style message bubbles.
- **Broadcast messaging:** to specific permissions, to the whole site (`@all` — admin only), or to selected users.
- **Permission matrix:** the admin defines exactly who can message whom (permission × permission) from the plugin's configure page.
- **Read ≠ Reply:** if the admin messages a user who is not allowed to message back, that user **can still open and read** the conversation — the reply box is hidden with a clear notice, and any forged reply attempt is rejected on the server.
- **Unread counter** on a floating button (bottom-right) plus a dropdown in the top navbar in AdminLTE 4 style.
- **Online / offline indicator** via the `last_watchdog` column (integrates with the watchdog plugin if enabled).
- **Read receipts:** a single gray ✓ = sent, and a double blue ✓✓ = read (WhatsApp style).
- **Full dark mode support** (`data-bs-theme="dark"`) across every plugin surface.
- **Bilingual:** the whole UI follows the site language (Arabic / English) with dedicated language files and a built-in fallback.
- **SweetAlert2** is bundled inside the plugin (zero extra install); no native browser `alert()` dialogs are used.
- **Select2** is bundled too (searchable + multi-select) across every dropdown — send one message to several users at once, and big user lists are easily filtered by name or username.

### Installation
1. Upload the whole `eh_chat` folder into:
   ```
   usersc/plugins/
   ```
2. From the admin panel: **Plugins → Informations → Install** — the installer automatically:
   - creates the plugin's database tables,
   - registers `usersc/eh_chat.php` + `usersc/eh_messages.php` as pages,
   - writes the language keys into `usersc/lang` (Arabic + English),
   - registers the plugin inside the UserSpice plugin system.
3. Activate the plugin.
4. From the **EH Chat → Configure** button, set the permission matrix and broadcast rules.
5. **No separate SQL** — tables are created by the installer, and it is idempotent (safe to re-run).

### Pages created by the plugin
| Page | Purpose |
| --- | --- |
| `usersc/eh_chat.php` | Two-column chat: contacts list (left) + conversation area (right), with 3-second polling |
| `usersc/eh_messages.php` | Recent conversations + composer (private / broadcast / @all) |

### Database structure (created automatically by Install)
| Table | Purpose |
| --- | --- |
| `eh_chat_messages` | Messages: sender / receiver / body / read state / type (private or broadcast) / timestamp |
| `eh_chat_settings` | Single settings row: permission matrix + broadcast permissions & users + online cutoff (minutes) |
| `users.last_watchdog` | Timestamp of each user's last activity — powers the online indicator (column added to the users table) |

### Permissions model
- **Permission matrix:** rows = senders, columns = receivers. The admin (permission 2) can always message anyone.
- **Broadcast:** passes through a broadcast-permission check — either an approved permission or specific users from the settings page.
- **Opening a conversation (read) is not the same as replying:** you can read if *you* are allowed to message them, *they* are allowed to message you, or if you already share message history; replying is always governed by the sender's own matrix.
- **Central gate:** every message insert goes through `eh_chat_send()`, which refuses unauthorized receivers — so even a code path that skips checks cannot break the rules.
- **CSRF tokens** are required for every send operation; pages use the standard `securePage()` like any UserSpice page.
- If the settings row is missing for any reason, the configure page creates it before the first save (no silent failures).

### AJAX API
All responses share one JSON shape: `{"status": "success|error", "message": ...}`

| File | Purpose |
| --- | --- |
| `ajax/nav.php` | Unread counter + latest threads (navbar dropdown + floating widget) |
| `ajax/contacts.php` | Contacts list for the chat page |
| `ajax/send.php` | Sending (private / by permission / @all / broadcast) with full permission checks |
| `ajax/messages.php` | One conversation's messages + mark-as-read (returns `can_send` so the UI can hide the reply box) |

### Language files
- The plugin detects the site language from `$_SESSION['us_lang']`, loads its own language bag, and also writes the keys into `usersc/lang/<code>.php` during install.
- Missing keys fall back to the built-in `eh_chat_lang_fallback()` map (dates, weekdays, notices).

### Folder structure
```
usersc/plugins/eh_chat/
├── activate.php / install.php / uninstall.php / update.php / migrate.php
├── configure.php          ← Admin settings page (matrix + broadcast + online cutoff)
├── functions.php          ← All logic (permissions, time, sending, avatars)
├── footer.php             ← Official include: floating widget + assets + watchdog
├── logo.png  plugin_info.php  info.xml
├── ajax/                  (nav, contacts, send, messages)
├── assets/                (css, js, SweetAlert2 + Select2, page templates copied at install)
├── lang/                  (ar-EG.php, en-US.php)
├── sql/001_eh_chat_schema.sql
└── assets/pages/           ← pages copied to usersc (eh_chat.php, eh_messages.php) — fully self-contained
```

### Git note
The language files written to the server are generated by the installer — do not commit them:
```
# generated by the installer, not source files
usersc/lang/en-US.php
usersc/lang/ar-EG.php
```

---

**License:** MIT — free to use and modify; keep the author credit.
