# EH WhatsApp Like — UserSpice Chat Plugin

## English

A WhatsApp-style internal chat plugin for any site running **UserSpice 6.1.6 and newer**.
It is fully independent from the rest of the site (no external plugin) and installs without touching any core file.

| | |
| --- | --- |
| **Author** | Emad Helmy |
| **Version** | 1.0.0 — tested with UserSpice 6.1.6 / 6.1.7 |
| **License** | MIT |

### Features
- **One-to-one chat** with WhatsApp-style message bubbles.
- **Broadcast messaging:** to specific permissions, to the whole site (`@all` — admin only), or to selected users or permission.
- **Permission matrix:** the admin defines exactly who can message whom (permission × permission) from the plugin's configure page.
- **Read ≠ Reply:** if the admin messages a user who is not allowed to message back, that user **can still open and read** the conversation — the reply box is hidden with a clear notice, and any forged reply attempt is rejected on the server.
- **Unread counter** on a floating button (bottom-right) plus a dropdown in the top navbar in AdminLTE 4 style.
- **Online / offline indicator** via the `last_watchdog` column (integrates with the watchdog plugin if enabled).
- **Read receipts:** a single gray ✓ = sent, and a double blue ✓✓ = read (WhatsApp style).
- **Full dark mode support** (`data-bs-theme="dark"`) across every plugin surface.
- **Bilingual:** the whole UI follows the site language (Arabic / English) with dedicated language files and a built-in fallback.
- **SweetAlert2** is bundled inside the plugin (zero extra install); no native browser `alert()` dialogs are used.
- **Select2** is bundled too (searchable + multi-select) across every dropdown — send one message to several users at once, and big user lists are easily filtered by name or username.
- **Menu item for other themes:** add a "Messages" item as the last entry of any menu straight from the Configure page — rendered in the theme's own style with a live unread-count bubble. **UltraMenu required.**

### Installation
1. Upload the whole `eh_chat` folder into:
   ```
   usersc/plugins/
   ```
2. From the UserSpice admin Dashboard: **Plugin Manager → Install** — the installer automatically:
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

### Menu integration (UltraMenu)
For themes without a chat dropdown in the navbar, the plugin's **Configure** page has a dedicated card that adds a **"Messages" item as the LAST top-level entry of any menu**:

- Choose the menu, optionally set the icon class (e.g. `fas fa-comments`) — the item is stored in `us_menu_items`, so it is rendered **by the theme's own style**, exactly like any other menu item.
- Afterwards you can drag, re-position, rename or re-permission it freely from the UserSpice menu builder like any other item.
- Once the item exists, the unread counter appears as a small red bubble next to it — refreshed every 5 seconds by the plugin's footer poll (works on any theme, no theme-specific markup needed).
- Removing is one click (Add unchanged only once per menu — safe to re-run).
- **UltraMenu only:** requires the standard UserSpice menu system (`us_menus` / `us_menu_items`). Themes that do not read UltraMenu will not show the item.

### AJAX API
All responses share one JSON shape: `{"status": "success|error", "message": ...}`

| File | Purpose |
| --- | --- |
| `ajax/nav.php` | Unread counter + latest threads (navbar dropdown + floating widget) |
| `ajax/contacts.php` | Contacts list for the chat page |
| `ajax/send.php` | Sending (private / by permission / @all / broadcast) with full permission checks |
| `ajax/messages.php` | One conversation's messages + mark-as-read (returns `can_send` so the UI can hide the reply box) |

### Language files
- The plugin detects the site language from `$_SESSION['us_lang']`, loads its own language keys, and also writes the keys into `usersc/lang/<code>.php` during install.
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

**License:** MIT - free to use and modify; keep the author credit.
