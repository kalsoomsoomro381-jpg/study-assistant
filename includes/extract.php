<?php
require_once __DIR__ . '/db.php';

/**
 * Read a PDF and return its text page by page: [1 => "text...", 2 => "text...", ...]
 */
function extract_pdf_pages(string $path): array {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PDF parser is not installed. Run in the project folder: composer require smalot/pdfparser');
    }
    require_once $autoload;

    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($path);

    $pages = [];
    foreach ($pdf->getPages() as $i => $page) {
        $text = $page->getText();
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');       // remove broken characters
        $text = preg_replace('/[ \t]+/', ' ', $text);               // squeeze spaces
        $text = preg_replace("/\n{3,}/", "\n\n", $text);            // squeeze blank lines
        $pages[$i + 1] = trim($text);
    }
    return $pages;
}

/**
 * Extract the text of one uploaded document and save it in the database.
 * Sets status: Ready / No text found / Failed
 */
function process_document(int $docId, string $path): string {
    @set_time_limit(120);
    $pdo = db();

    try {
        $pages = extract_pdf_pages($path);
    } catch (Throwable $e) {
        error_log('PDF extract failed: ' . $e->getMessage());
        $pdo->prepare("UPDATE documents SET status = 'Failed' WHERE id = ?")->execute([$docId]);
        return 'Failed';
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM document_pages WHERE document_id = ?')->execute([$docId]);
    $ins = $pdo->prepare('INSERT INTO document_pages (document_id, page_number, text_content) VALUES (?, ?, ?)');
    $withText = 0;
    foreach ($pages as $num => $text) {
        $ins->execute([$docId, $num, $text]);
        if (mb_strlen($text) > 20) $withText++;
    }

    // scanned PDFs (only pictures) give no text
    $status = ($withText === 0) ? 'No text found' : 'Ready';
    $pdo->prepare('UPDATE documents SET page_count = ?, status = ? WHERE id = ?')
        ->execute([count($pages), $status, $docId]);
    $pdo->commit();

    return $status;
}
