<?php
// Open in browser:  http://localhost/study-assistant/api/ai_test.php
// Try another model without editing files:  .../api/ai_test.php?model=MODEL-NAME
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ai.php';

if (isset($_GET['model']) && preg_match('/^[A-Za-z0-9._-]{3,60}$/', $_GET['model'])) {
    $GLOBALS['AI_MODEL_OVERRIDE'] = $_GET['model'];
}

$start = microtime(true);
try {
    $answer = askAI(
        'You are a friendly study assistant. Reply in one short sentence.',
        'Say hello to a student and tell her you are ready to help with her PDF.',
        200
    );
    json_out(['ok' => true, 'provider' => AI_PROVIDER, 'model' => ai_model(), 'model_used' => $GLOBALS['AI_MODEL_USED'] ?? ai_model(),
              'seconds' => round(microtime(true) - $start, 1), 'reply' => $answer,
              'code_version' => AI_CODE_VERSION, 'details' => $GLOBALS['AI_LAST_META'] ?? null]);
} catch (AIException $e) {
    json_out(['ok' => false, 'provider' => AI_PROVIDER, 'model' => ai_model(),
              'seconds' => round(microtime(true) - $start, 1), 'error' => $e->getMessage()], 502);
}
