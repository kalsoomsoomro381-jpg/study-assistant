<?php
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $title   = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
        $content = trim((string)($_POST['content'] ?? ''));
        $type    = in_array($_POST['type'] ?? '', ['summary', 'notes', 'quiz'], true) ? $_POST['type'] : 'notes';
        $docId   = (int)($_POST['document_id'] ?? 0);

        if ($title === '' || $content === '') json_out(['ok' => false, 'error' => 'Nothing to save.'], 400);
        if (mb_strlen($content) > 100000)      json_out(['ok' => false, 'error' => 'Note is too long.'], 400);

        $ins = $pdo->prepare('INSERT INTO notes (user_id, document_id, title, content, type) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([uid(), $docId ?: null, $title, $content, $type]);
        json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?')->execute([(int)($_POST['id'] ?? 0), uid()]);
        json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

$st = $pdo->prepare('SELECT id, title, content, type, document_id, created_at FROM notes WHERE user_id = ? ORDER BY id DESC');
$st->execute([uid()]);
$notes = [];
foreach ($st->fetchAll() as $n) {
    $notes[] = ['id' => (int)$n['id'], 'title' => $n['title'], 'content' => $n['content'], 'type' => $n['type'], 'date' => $n['created_at']];
}
json_out(['ok' => true, 'notes' => $notes]);
