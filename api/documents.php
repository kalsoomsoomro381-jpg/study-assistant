<?php
require_once __DIR__ . '/../includes/db.php';

$st = db()->prepare('SELECT * FROM documents WHERE user_id = ? ORDER BY id DESC');
$st->execute([uid()]);
json_out(['ok' => true, 'documents' => array_map('doc_to_json', $st->fetchAll())]);
