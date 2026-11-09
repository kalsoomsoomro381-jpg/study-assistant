<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}
$id = (int)($_POST['id'] ?? 0);

$st = db()->prepare('SELECT stored_name FROM documents WHERE id = ? AND user_id = ?');
$st->execute([$id, uid()]);
$doc = $st->fetch();
if (!$doc) json_out(['ok' => false, 'error' => 'Document not found.'], 404);

$path = UPLOAD_DIR . $doc['stored_name'];
if (is_file($path)) unlink($path);

db()->prepare('DELETE FROM documents WHERE id = ? AND user_id = ?')->execute([$id, uid()]);
json_out(['ok' => true]);
