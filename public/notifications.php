<?php
// JSON endpoint for the bell popup in the header: GET = list (also used for background refresh), POST = mark one / all as read.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') define('NO_SESSION_TOUCH', true);   // polling must not extend the idle timeout
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$uid = (int)$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    q('UPDATE notifications SET is_read=1 WHERE user_id=? AND (id=? OR ?=0)', [$uid, $id, $id]);
}
$items = [];
foreach (all('SELECT id,message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY is_read, id DESC LIMIT 15', [$uid]) as $n) {
    $items[] = ['id' => (int)$n['id'], 'message' => $n['message'], 'link' => $n['link'], 'is_read' => (int)$n['is_read'], 'when' => fmt_dt($n['created_at'])];
}
echo json_encode(['ok' => true, 'unread' => (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$uid]), 'items' => $items]);