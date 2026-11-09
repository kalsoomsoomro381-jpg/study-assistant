<?php
// Secrets (database password, API key) live in includes/config.local.php (NOT in this file).
// Copy config.example.php to config.local.php and fill in your own values.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---- Database (defaults = XAMPP settings) ----
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'study_assistant');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);   // 10 MB


// ---- AI settings -------------------------------------------------
defined('AI_PROVIDER') || define('AI_PROVIDER', 'anthropic');   // 'anthropic' or 'gemini'
defined('AI_API_KEY')  || define('AI_API_KEY', '');
defined('AI_MODEL')    || define('AI_MODEL', AI_PROVIDER === 'gemini' ? 'gemini-3.5-flash' : 'claude-haiku-5-5');
