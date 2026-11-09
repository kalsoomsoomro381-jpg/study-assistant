<?php
require_once __DIR__ . '/config.php';

define('AI_CODE_VERSION', '7b');

class AIException extends RuntimeException {}

// The model name to use (a test page may override it with ?model=...)
function ai_model(): string {
    return $GLOBALS['AI_MODEL_OVERRIDE'] ?? AI_MODEL;
}

/**
 * Send a prompt to the AI and get the answer text back.
 *
 * $system   = instructions for the AI ("Answer only from the PDF...")
 * $json     = true when the answer must be pure JSON (used for quizzes)
 * $messages = the user's question as a string, or a list like
 *             [['role' => 'user', 'content' => '...'], ['role' => 'assistant', 'content' => '...']]
 */
function askAI(string $system, array|string $messages, int $maxTokens = 1000, bool $json = false): string {
    if (AI_API_KEY === '' || AI_API_KEY === 'YOUR_API_KEY_HERE') {
        throw new AIException('API key is not set. Copy includes/config.example.php to includes/config.local.php and paste your key.');
    }
    if (is_string($messages)) {
        $messages = [['role' => 'user', 'content' => $messages]];
    }
    return AI_PROVIDER === 'gemini'
        ? ask_gemini_resilient($system, $messages, $maxTokens, $json)
        : ask_anthropic($system, $messages, $maxTokens);
}

/* ---------------- Claude (Anthropic) ---------------- */
function ask_anthropic(string $system, array $messages, int $maxTokens): string {
    $url = defined('AI_ENDPOINT') ? AI_ENDPOINT : 'https://api.anthropic.com/v1/messages';
    $data = http_post_json($url, [
        'x-api-key: ' . AI_API_KEY,
        'anthropic-version: 2023-06-01',
    ], [
        'model'      => ai_model(),
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => $messages,
    ]);

    $text = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    if ($text === '') throw new AIException('The AI returned an empty answer.');
    return trim($text);
}

/* ---------------- Gemini (Google) ---------------- */
/**
 * Gemini can say "busy" (503) or "limit reached" (429) for a short time.
 * So: try again once after 2 seconds, and if it still fails, try a second (lighter) model.
 * Wrong key / wrong model name errors are NOT retried.
 */
function ask_gemini_resilient(string $system, array $messages, int $maxTokens, bool $json): string {
    $primary  = ai_model();
    $fallback = defined('AI_FALLBACK_MODEL') ? AI_FALLBACK_MODEL : 'gemini-3.1-flash-lite';

    $models = [$primary];
    if ($fallback !== '' && $fallback !== $primary && !isset($GLOBALS['AI_MODEL_OVERRIDE'])) $models[] = $fallback;

    $firstError = null;
    foreach ($models as $i => $model) {
        $attempts = ($i === 0) ? 2 : 1;
        for ($a = 1; $a <= $attempts; $a++) {
            $GLOBALS['AI_MODEL_USED'] = $model;
            try {
                return ask_gemini($system, $messages, $maxTokens, $json, $model);
            } catch (AIException $e) {
                $firstError ??= $e;
                if (!in_array($e->getCode(), [429, 500, 502, 503, 504], true)) {
                    throw $firstError;                 // not a "busy" problem: do not retry
                }
                if ($a < $attempts) sleep(2);          // wait a little, same model again
            }
        }
    }
    throw $firstError;
}

function ask_gemini(string $system, array $messages, int $maxTokens, bool $json = false, ?string $model = null): string {
    $model ??= ai_model();
    $base = defined('AI_ENDPOINT') ? AI_ENDPOINT . (str_contains(AI_ENDPOINT, '?') ? '&' : '?') . 'm=' . rawurlencode($model)
          : 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

    $contents = [];
    foreach ($messages as $m) {
        $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $data = http_post_json($base, ['x-goog-api-key: ' . AI_API_KEY], [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => $contents,
        // newer Gemini models "think" first and the thinking uses part of this limit,
        // so give them plenty of room or the answer gets cut off
        'generationConfig'  => ['maxOutputTokens' => max($maxTokens, 2048)] + ($json ? ['responseMimeType' => 'application/json'] : []),
    ]);

    // keep a few facts about the last answer (shown on the test page)
    $GLOBALS['AI_LAST_META'] = [
        'finishReason' => $data['candidates'][0]['finishReason'] ?? null,
        'tokens'       => $data['usageMetadata'] ?? null,
    ];

    $text = '';
    foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (!empty($part['thought'])) continue;      // skip the model's private thinking
        $text .= $part['text'] ?? '';
    }
    if (($data['candidates'][0]['finishReason'] ?? '') === 'MAX_TOKENS' && $text !== '') {
        $text .= "\n\n(The answer was cut off because it was too long.)";
    }
    if ($text === '') throw new AIException('The AI returned an empty answer (it may have blocked the request).');
    return trim($text);
}

/* ---------------- shared HTTP helper ---------------- */
function http_post_json(string $url, array $headers, array $body): array {
    if (!function_exists('curl_init')) {
        throw new AIException('PHP cURL extension is off. In php.ini enable: extension=curl');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        if ($errno === 60 || $errno === 77) {
            throw new AIException('SSL certificate problem (common on XAMPP/Windows). Fix: download cacert.pem from curl.se/docs/caextract.html, save it as D:\\xampp\\php\\extras\\ssl\\cacert.pem, then in php.ini set curl.cainfo="D:\\xampp\\php\\extras\\ssl\\cacert.pem" and restart Apache.');
        }
        if ($errno === 6 || $errno === 7 || $errno === 28) {
            throw new AIException('Could not reach the AI server. Check your internet connection. (' . $err . ')');
        }
        throw new AIException('Network error: ' . $err);
    }

    $data = json_decode((string)$raw, true);
    if ($code >= 400 || !is_array($data)) {
        $apiMsg = is_array($data) ? ($data['error']['message'] ?? '') : '';
        $hint = match (true) {
            $code === 401, $code === 403 => 'The API key is wrong or not allowed.',
            $code === 404                => 'Model name not found. Check AI_MODEL.',
            $code === 429                => 'Too many requests or no credit left. Wait a minute or check your account.',
            $code >= 500                 => 'Google\'s AI is very busy right now (this is temporary). Wait a few seconds and try again.',
            default                      => 'Request failed.',
        };
        throw new AIException("AI error (HTTP $code). $hint " . $apiMsg, $code);
    }
    return $data;
}


/** Turn the AI's text into a PHP array (removes ```json fences and any words around the JSON). */
function parse_json_answer(string $text): ?array {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
    $start = strcspn($text, '[{');
    $endA = strrpos($text, ']');
    $endB = strrpos($text, '}');
    $end = max($endA === false ? -1 : $endA, $endB === false ? -1 : $endB);
    if ($start >= strlen($text) || $end < $start) return null;
    $data = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($data) ? $data : null;
}
