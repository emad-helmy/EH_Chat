<?php
/**
 * إرسال رسالة — خاص / جماعي لبرمشن / @all / مستخدمين محددين
 */
require_once __DIR__ . '/../../../../users/init.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

if (!isset($user) || !$user->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NOT_LOGGED_IN')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || Input::get('action') !== 'send') {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_BAD_REQUEST')]);
    exit;
}

if (!Token::check(Input::get('csrf'))) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF Error']);
    exit;
}

$me = (int)$user->data()->id;
$message = trim((string)Input::get('message'));
$sendType = Input::get('send_type') ?: 'users';

if ($message === '') {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_WRITE_MSG_FIRST')]);
    exit;
}

$receiverIds = [];
$type = 0;

if ($sendType === 'all') {
    /* الأدمن بس — لكل المستخدمين بلا استثناء */
    if (!eh_chat_is_admin($me)) {
        echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_ALL_ADMIN_ONLY')]);
        exit;
    }
    $type = 1;
    $rows = $db->query("SELECT id FROM users WHERE active = 1 AND id != ?", [$me])->results();
    foreach ($rows as $r) $receiverIds[] = (int)$r->id;

} elseif ($sendType === 'permission') {
    /* الإرسال لبرمشن — لازم عندك صلاحية الـbroadcast — وممكن تعّي أكتر من برمشن */
    if (!eh_chat_can_broadcast($me)) {
        echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NO_BROADCAST')]);
        exit;
    }
    $permIds = Input::get('permission_ids');
    if (!is_array($permIds)) {
        $legacy = (int)Input::get('permission_id');
        $permIds = $legacy > 0 ? [$legacy] : [];
    }
    $permIds = array_values(array_filter(array_map('intval', (array)$permIds), function ($p) { return $p > 0; }));
    if (!$permIds) {
        echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_PICK_PERM')]);
        exit;
    }
    $type = 1;
    $seen = [];
    foreach ($permIds as $permId) {
        $rows = $db->query(
            "SELECT u.id FROM users u JOIN user_permission_matches m ON m.user_id = u.id
             WHERE m.permission_id = ? AND u.active = 1 AND u.id != ?",
            [$permId, $me]
        )->results();
        foreach ($rows as $r) {
            $rid = (int)$r->id;
            if (!isset($seen[$rid])) { $seen[$rid] = 1; $receiverIds[] = $rid; }
        }
    }

} elseif ($sendType === 'broadcast') {
    /* @all للمستخدم اللي عنده صلاحية — لكل البرمشنز اللي هو متاح يكلمهم */
    if (!eh_chat_can_broadcast($me)) {
        echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NO_BROADCAST')]);
        exit;
    }
    $type = 1;
    $allowed = eh_chat_allowed_targets($me);
    foreach ($allowed as $r) $receiverIds[] = (int)$r->id;

} else {
    /* مستخدمين محددين — يبعت لكذا واحد في المرة الواحدة (multi select)
       والممنوع بمصفوفة الصلاحيات بيتخطى مش بيقطع الإرسال كله */
    $ids = Input::get('receiver_ids');
    if (!is_array($ids)) $ids = [$ids];
    foreach ($ids as $rid) {
        $rid = (int)$rid;
        if ($rid <= 0 || $rid === $me) continue;
        if (!eh_chat_can_message($me, $rid)) continue;
        $receiverIds[] = $rid;
    }
}

if (!$receiverIds) {
    echo json_encode(['status' => 'error', 'message' => eh_lang('EH_CHAT_NO_RECEIVERS')]);
    exit;
}

$sent = eh_chat_send($me, $receiverIds, $message, $type);
echo json_encode(['status' => 'success', 'sent_to' => $sent]);
