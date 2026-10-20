<?php
/**
 * جلب رسائل محادثة واحدة + تعليمها كمقروءة (بيتنادى من صفحة المحادثة كل 3 ثواني)
 */
require_once __DIR__ . '/../../../../users/init.php';
require_once __DIR__ . '/../functions.php';

if (!isset($user) || !$user->isLoggedIn()) {
    http_response_code(401);
    exit;
}

$me   = (int)$user->data()->id;
$other = (int)Input::get('user_id');

if ($other <= 0) {
    http_response_code(400);
    exit;
}

/* القراية مش الرد: أفتح المحادثة لو مسموح أقراها (من أي اتجاه) */
$canSend = eh_chat_can_message($me, $other);
if (!eh_chat_can_view($me, $other)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_DENIED')], JSON_UNESCAPED_UNICODE);
    exit;
}

/* يقرا = بتعليم كل رسايل الجانب التاني له كـمقروءة (حتى لو مش مسموح يرد) */
eh_chat_mark_read($me, $other);

$messages = $db->query(
    "SELECT * FROM eh_chat_messages
     WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
       AND type = 0
     ORDER BY id ASC LIMIT 500",
    [$me, $other, $other, $me]
)->results();

/* نجمّع البوبbles ونضيف فوقها إشعار القراءة-بس لو مش مسموح يرد */
ob_start();
eh_chat_render_bubbles($messages, $me);
$html = ob_get_clean();

if (!$canSend) {
    $notice = '<div class="eh-readonly">' . htmlspecialchars(eh_lang('EH_CHAT_READ_ONLY')) . '</div>';
    $html = $notice . $html;
}

header('Content-Type: application/json');
echo json_encode(
    ['status' => 'success', 'can_send' => $canSend ? 1 : 0, 'html' => $html],
    JSON_UNESCAPED_UNICODE
);