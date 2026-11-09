<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/extract.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST only'], 405);
}

if (!isset($_FILES['pdf'])) {
    json_out(['ok' => false, 'error' => 'No file received. The file may be bigger than the PHP limit (see php.ini upload_max_filesize).'], 400);
}

$f = $_FILES['pdf'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])
        ? 'File is too big for the server limit.' : 'Upload failed (code ' . $f['error'] . ').';
    json_out(['ok' => false, 'error' => $msg], 400);
}

if ($f['size'] > MAX_UPLOAD_BYTES) {
    json_out(['ok' => false, 'error' => 'File is too big. Maximum size is 10 MB.'], 400);
}

// 1) extension must be .pdf
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    json_out(['ok' => false, 'error' => 'Only PDF files are allowed.'], 400);
}

// 2) the real content must be a PDF (file starts with %PDF-), not just a renamed file
$fh = fopen($f['tmp_name'], 'rb');
$head = fread($fh, 5);
fclose($fh);
if ($head !== '%PDF-') {
    json_out(['ok' => false, 'error' => 'This file is not a real PDF.'], 400);
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// random safe name: never trust the user's file name for the path
$stored = bin2hex(random_bytes(16)) . '.pdf';
if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $stored)) {
    json_out(['ok' => false, 'error' => 'Could not save the file on the server.'], 500);
}

// clean display name
$display = mb_substr(preg_replace('/[^\p{L}\p{N}\s._()-]/u', '', basename($f['name'])), 0, 200);
if ($display === '') $display = 'document.pdf';

$st = db()->prepare('INSERT INTO documents (user_id, file_name, stored_name, file_size, status) VALUES (?, ?, ?, ?, ?)');
$st->execute([uid(), $display, $stored, (int)$f['size'], 'Uploaded']);

$id = (int)db()->lastInsertId();

// Step 4: read the PDF text and save it page by page
process_document($id, UPLOAD_DIR . $stored);

$row = db()->prepare('SELECT * FROM documents WHERE id = ?');
$row->execute([$id]);
json_out(['ok' => true, 'document' => doc_to_json($row->fetch())]);
