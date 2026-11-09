<?php
require_once __DIR__ . '/../includes/db.php';

$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare('SELECT id FROM documents WHERE id = ? AND user_id = ?');
$st->execute([$id, uid()]);
if (!$st->fetch()) json_out(['ok' => false, 'error' => 'Document not found.'], 404);

$st = db()->prepare('SELECT page_number, text_content FROM document_pages WHERE document_id = ? ORDER BY page_number LIMIT 3');
$st->execute([$id]);
$pages = [];
foreach ($st->fetchAll() as $r) {
    $pages[] = [
        'page' => (int)$r['page_number'],
        'text' => mb_substr((string)$r['text_content'], 0, 500),
    ];
}
json_out(['ok' => true, 'pages' => $pages]);
