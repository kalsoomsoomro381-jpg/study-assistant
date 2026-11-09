<?php
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// ---- POST: delete a chat or clear its messages ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $st = $pdo->prepare('SELECT id FROM chats WHERE id = ? AND user_id = ?');
    $st->execute([$id, uid()]);
    if (!$st->fetch()) json_out(['ok' => false, 'error' => 'Chat not found.'], 404);

    if ($action === 'clear') {
        $pdo->prepare('DELETE FROM messages WHERE chat_id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM messages WHERE chat_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM chats WHERE id = ?')->execute([$id]);
    } else {
        json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
    json_out(['ok' => true]);
}

// ---- GET ?id=5 : one chat with its messages ----
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = $pdo->prepare('SELECT id, document_id, title FROM chats WHERE id = ? AND user_id = ?');
    $st->execute([$id, uid()]);
    $chat = $st->fetch();
    if (!$chat) json_out(['ok' => false, 'error' => 'Chat not found.'], 404);

    $st = $pdo->prepare('SELECT role, content, page_refs FROM messages WHERE chat_id = ? ORDER BY id');
    $st->execute([$id]);
    $messages = [];
    foreach ($st->fetchAll() as $m) {
        $pages = ($m['page_refs'] === null || $m['page_refs'] === '') ? [] : array_map('intval', explode(',', $m['page_refs']));
        $messages[] = ['role' => $m['role'], 'content' => $m['content'], 'pages' => $pages];
    }
    json_out(['ok' => true, 'chat' => ['id' => (int)$chat['id'], 'document_id' => $chat['document_id'] === null ? null : (int)$chat['document_id'], 'title' => $chat['title']], 'messages' => $messages]);
}

// ---- GET : list of chats (history) ----
$st = $pdo->prepare(
    'SELECT c.id, c.title, c.document_id, c.created_at, d.file_name
       FROM chats c LEFT JOIN documents d ON d.id = c.document_id
      WHERE c.user_id = ? ORDER BY c.id DESC LIMIT 100');
$st->execute([uid()]);
$chats = [];
foreach ($st->fetchAll() as $c) {
    $chats[] = [
        'id'          => (int)$c['id'],
        'title'       => $c['title'],
        'document_id' => $c['document_id'] === null ? null : (int)$c['document_id'],
        'pdf'         => $c['file_name'] ?? 'Deleted PDF',
        'date'        => $c['created_at'],
    ];
}
json_out(['ok' => true, 'chats' => $chats]);
