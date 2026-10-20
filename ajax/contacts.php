<?php
/**
 * المحادثات الحديثة (آخر رسالة مع كل شخص) — قائمة الشمال في صفحة المحادثة
 */
require_once __DIR__ . '/../../../../users/init.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if (!isset($user) || !$user->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NOT_LOGGED_IN')]);
    exit;
}

$me = (int)$user->data()->id;

/* آخر محادثة مع كل شخص (جديد أولاً) */
$rows = $db->query(
    "SELECT m.*, u.id AS other_id, u.username, u.fname, u.lname
     FROM eh_chat_messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     ORDER BY m.id DESC
     LIMIT 30",
    [$me, $me, $me]
)->results();

$threads = [];
foreach ($rows as $row) {
    $oid = (int)$row->other_id;
    if (!isset($threads[$oid])) $threads[$oid] = $row;
    if (count($threads) >= 30) break;
}

$html = '';
$people = [];
foreach ($threads as $row) {
    $uid = (int)$row->other_id;
    $online = eh_chat_is_online($uid);
    $lastMsg = $row ? mb_strimwidth((string)$row->message, 0, 60, '...') : '—';

    $unr = (int)$db->query(
        "SELECT COUNT(*) AS c FROM eh_chat_messages WHERE receiver_id = ? AND sender_id = ? AND is_read = 0",
        [$me, $uid]
    )->first()->c;

    $dot = $online ? 'eh-dot on' : 'eh-dot';
    $name = htmlspecialchars(eh_chat_name($row));

    $html .= '<a class="eh-contact" href="' . $us_url_root . 'usersc/eh_chat.php?user_id=' . $uid . '" data-id="' . $uid . '">';
    $html .= '<img class="eh-contact-img" src="' . htmlspecialchars(eh_chat_avatar($uid)) . '" alt="">';
    $html .= '<div style="flex:1;min-width:0">';
    $html .= '<div class="eh-contact-name"><i class="' . $dot . '"></i><span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . $name . '</span></div>';
    $html .= '<div class="eh-contact-msg">' . htmlspecialchars($lastMsg) . '</div>';
    $html .= '</div>';
    if ($unr > 0) { $html .= '<span class="badge bg-danger">' . ($unr > 99 ? '99+' : $unr) . '</span>'; }
    $html .= '</a>';

    $people[] = ['id' => $uid, 'name' => $name, 'avatar' => eh_chat_avatar($uid), 'online' => $online];
}

if (!$threads) {
    $html = '<div class="p-3 text-muted text-center">' . htmlspecialchars(eh_lang('EH_CHAT_NO_CONTACTS')) . '</div>';
}

echo json_encode([
    'status' => 'success',
    'html'   => $html,
    'people' => $people,
]);
