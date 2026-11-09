<?php
require_once __DIR__ . '/db.php';

/**
 * Words that carry no meaning for searching (so "what is the" does not match every page)
 */
function rag_stopwords(): array {
    static $w = null;
    if ($w === null) {
        $w = array_flip(explode(' ',
            'the and for are was were has have had not but you your with from that this these those what which who whom '
          . 'when where why how can could would should will shall may might about into onto than then them they their '
          . 'there here also any all some more most such only own same too very just one two its our out per via '
          . 'explain tell give show please simply simple example examples describe define meaning mean means '
          . 'chapter topic topics page pages pdf document file notes note summary summarize'));
    }
    return $w;
}

/** Meaningful search words of a text */
function rag_terms(string $text): array {
    preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $m);
    $stop = rag_stopwords();
    $terms = [];
    foreach ($m[0] as $t) {
        if (!isset($stop[$t]) && !ctype_digit($t)) $terms[$t] = true;
    }
    return array_keys($terms);
}

/**
 * Pick the pages of a document that best match the question.
 * Small documents are sent completely. Big ones: the best matching pages.
 * Returns [page_number => text] sorted by page number.
 */
function find_relevant_pages(int $docId, string $queryText, int $maxPages = 6, int $maxChars = 24000, int $perPageChars = 6000): array {
    $st = db()->prepare('SELECT page_number, text_content FROM document_pages WHERE document_id = ? ORDER BY page_number');
    $st->execute([$docId]);
    $pages = [];
    $total = 0;
    foreach ($st->fetchAll() as $r) {
        $text = (string)$r['text_content'];
        $pages[(int)$r['page_number']] = $text;
        $total += mb_strlen($text);
    }
    if (!$pages) return [];

    // small PDF: send everything
    if ($total <= $maxChars) {
        return array_filter($pages, fn($t) => trim($t) !== '');
    }

    $terms = rag_terms($queryText);
    $score = array_fill_keys(array_keys($pages), 0.0);
    $lower = array_map('mb_strtolower', $pages);

    // how many pages contain each word (rare words are worth more)
    $n = count($pages);
    foreach ($terms as $t) {
        $has = [];
        foreach ($lower as $num => $txt) {
            $c = substr_count($txt, $t);
            if ($c > 0) $has[$num] = $c;
        }
        if (!$has) continue;
        $idf = log(1 + $n / count($has));
        foreach ($has as $num => $c) $score[$num] += min($c, 5) * $idf;
    }

    // "Chapter 2" -> pages that contain "chapter 2"
    if (preg_match_all('/chapter\s*(\d+)/i', $queryText, $m)) {
        foreach ($m[1] as $ch) {
            foreach ($lower as $num => $txt) {
                if (preg_match('/chapter\s*' . (int)$ch . '\b/', $txt)) $score[$num] += 20;
            }
        }
    }
    // "page 7" -> that page
    if (preg_match_all('/\bpage\s*(\d+)/i', $queryText, $m)) {
        foreach ($m[1] as $p) {
            if (isset($score[(int)$p])) $score[(int)$p] += 50;
        }
    }

    arsort($score);
    $chosen = [];
    foreach ($score as $num => $s) {
        if ($s <= 0 || count($chosen) >= $maxPages) break;
        $chosen[] = $num;
    }
    if (!$chosen) {                       // nothing matched: use the first pages
        $chosen = array_slice(array_keys($pages), 0, min(3, $n));
    }
    sort($chosen);

    $out = [];
    $used = 0;
    foreach ($chosen as $num) {
        $txt = mb_substr($pages[$num], 0, $perPageChars);
        if ($used + mb_strlen($txt) > $maxChars) break;
        $out[$num] = $txt;
        $used += mb_strlen($txt);
    }
    return $out;
}

/** Which pages did the AI mention, like "(Page 4)" or "Pages 4, 5"? Only pages we really sent. */
function extract_cited_pages(string $answer, array $allowedPages): array {
    $found = [];
    if (preg_match_all('/\bPages?\s*((?:\d+\s*(?:,|and|&|-|–)?\s*)+)/i', $answer, $m)) {
        foreach ($m[1] as $chunk) {
            if (preg_match_all('/\d+/', $chunk, $nums)) {
                foreach ($nums[0] as $n) {
                    $n = (int)$n;
                    if (in_array($n, $allowedPages, true)) $found[$n] = true;
                }
            }
        }
    }
    $pages = array_keys($found);
    sort($pages);
    return $pages;
}


/** The PDF must be yours and have readable text. Stops with an error message if not. */
function require_ready_document(int $docId): array {
    $st = db()->prepare('SELECT id, file_name, status FROM documents WHERE id = ? AND user_id = ?');
    $st->execute([$docId, uid()]);
    $doc = $st->fetch();
    if (!$doc) json_out(['ok' => false, 'error' => 'Please choose a PDF first.'], 404);
    if ($doc['status'] !== 'Ready') {
        json_out(['ok' => false, 'error' => 'This PDF has no readable text (status: ' . $doc['status'] . '). Try a PDF where you can select the text.'], 400);
    }
    return $doc;
}

/**
 * The text of the whole PDF as "[Page 1] ... [Page 2] ...", up to $maxChars.
 * Very long PDFs are cut after the last page that fits (truncated = true).
 */
function get_document_text(int $docId, int $maxChars = 120000): array {
    $st = db()->prepare('SELECT page_number, text_content FROM document_pages WHERE document_id = ? ORDER BY page_number');
    $st->execute([$docId]);
    $text = '';
    $used = 0;
    $total = 0;
    $truncated = false;
    foreach ($st->fetchAll() as $r) {
        $total++;
        $t = trim((string)$r['text_content']);
        if ($t === '' || $truncated) continue;
        $block = '[Page ' . (int)$r['page_number'] . "]\n" . $t . "\n\n";
        if (mb_strlen($text) + mb_strlen($block) > $maxChars) { $truncated = true; continue; }
        $text .= $block;
        $used++;
    }
    return ['text' => $text, 'pages_used' => $used, 'total_pages' => $total, 'truncated' => $truncated];
}
