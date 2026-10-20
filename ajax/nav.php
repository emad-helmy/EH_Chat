<?php
/**
 * JSON للونبار والزر العائم: عداد غير المقروء + آخر المحادثات
 * html      → شكل عناصر الثيم (media) — لدروب داون النافبار
 * panelHtml → شكل واتساب — للزر العائم
 */
require_once __DIR__ . '/../../../../users/init.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if (!isset($user) || !$user->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NOT_LOGGED_IN')]);
    exit;
}

$me = (int)$user->data()->id;
$unread = eh_chat_unread_count($me);

/* آخر محادثة مع كل مستخدم (جديد أولاً) */
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
    if (count($threads) >= 5) break;
}

$html = '';       /* النافبار */
$panelHtml = '';  /* الزر العائم */

if (!$threads) {
    $panelHtml = '<div class="eh-empty">' . htmlspecialchars(eh_lang('EH_CHAT_NO_THREADS')) . '</div>';
    $html      = $panelHtml;
} else {
    foreach ($threads as $row) {
        $uid   = (int)$row->other_id;
        $name  = htmlspecialchars(eh_chat_name($row));
        $msg   = htmlspecialchars(mb_strimwidth((string)$row->message, 0, 70, '...'));
        $time  = eh_chat_time($row->created_at);
        $online = eh_chat_is_online($uid);
        $dot   = $online ? 'on' : '';
        $img   = htmlspecialchars(eh_chat_avatar($uid));
        $url   = $us_url_root . 'usersc/eh_chat.php?user_id=' . $uid;
        $last  = htmlspecialchars((string)$row->message);
        /* زر الرد السريع بنظهره بس لو مسموح يبعت له (القراية متاحة دايمًا) */
        $canMsg1to1 = eh_chat_can_message($me, $uid);
        $quickBtn = '';
        if ($canMsg1to1) {
            $quickBtn = '<button type="button" class="eh-quick" data-quick="' . $uid .
                 '" data-name="' . $name . '" data-last="' . $last . '" title="' . htmlspecialchars(eh_lang('EH_CHAT_QUICK_REPLY')) . '"><i class="fas fa-reply"></i></button>';
        }

        /* شكل الثيم للونبار (نفس ماركب الهيدر: صورة مدورة + الاسم + الوقت + الرسالة) */
        $html .= '<a class="dropdown-item" href="' . $url . '">';
        $html .= '<div class="media">';
        $html .= '<img src="' . $img . '" alt="User Avatar" class="img-size-50 me-3 img-circle">';
        $html .= '<div class="media-body">';
        $html .= '<div class="d-flex justify-content-between">';
        $html .= '<span class="fw-semibold"><span class="eh-dot-img ' . $dot . '"></span>' . $name . '</span>';
        $html .= '<small class="text-muted">' . $time . '</small>';
        $html .= '</div>';
        $html .= '<p class="text-truncate mb-0">' . $msg . '</p>';
        $html .= '</div>';
        $html .= $quickBtn;
        $html .= '</div></a>';
        $html .= '<div class="dropdown-divider"></div>';

        /* شكل واتساب للزر العائم — نفس شكل صفحة الرسائل: التاريخ تحت + بادج مدور */
        $unrN = (int)$db->query(
            "SELECT COUNT(*) AS c FROM eh_chat_messages WHERE receiver_id = ? AND sender_id = ? AND is_read = 0",
            [$me, $uid]
        )->first()->c;
        $panelBadge = $unrN > 0 ? '<span class="badge text-bg-danger eh-thread-badge">' . $unrN . '</span>' : '';

        $panelHtml .= '<a class="eh-thread" href="' . $url . '">';
        $panelHtml .= '<img class="eh-thread-img" src="' . $img . '" alt="">';
        $panelHtml .= '<div class="eh-thread-body">';
        $panelHtml .= '<div class="eh-thread-name"><span class="eh-dot ' . $dot . '"></span>' . $name . '</div>';
        $panelHtml .= '<div class="eh-thread-msg">' . $msg . '</div>';
        $panelHtml .= '<div class="eh-thread-time"><i class="fas fa-clock"></i> ' . $time . '</div>';
        $panelHtml .= '</div>';
        $panelHtml .= $quickBtn;
        $panelHtml .= $panelBadge;
        $panelHtml .= '</a>';
    }
}

echo json_encode([
    'status'     => 'success',
    'unread_count' => $unread,
    'html'       => $html,
    'panelHtml'  => $panelHtml,
]);
