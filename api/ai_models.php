<?php
// Open in browser:  http://localhost/study-assistant/api/ai_models.php
// Shows which Gemini models your key can really use.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai.php';

if (AI_PROVIDER !== 'gemini') json_out(['ok' => false, 'error' => 'This page is only for Gemini.'], 400);
if (AI_API_KEY === '' || AI_API_KEY === 'YOUR_API_KEY_HERE') json_out(['ok' => false, 'error' => 'API key is not set in includes/config.local.php'], 400);

$url = (defined('AI_MODELS_ENDPOINT') ? AI_MODELS_ENDPOINT : 'https://generativelanguage.googleapis.com/v1beta/models') . '?pageSize=200';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER     => ['x-goog-api-key: ' . AI_API_KEY],
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false) json_out(['ok' => false, 'error' => 'Could not reach Google: ' . $err], 502);
$data = json_decode($raw, true);
if ($code >= 400 || !is_array($data)) json_out(['ok' => false, 'error' => "HTTP $code " . ($data['error']['message'] ?? '')], 502);

$names = [];
foreach (($data['models'] ?? []) as $m) {
    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
        $names[] = preg_replace('#^models/#', '', $m['name']);
    }
}
sort($names);
json_out(['ok' => true, 'count' => count($names), 'models_you_can_use' => $names]);
