<?php
// 1) Copy this file and name the copy:  config.local.php   (same folder)
// 2) Put your own database details and API key in the copy.
// config.local.php is ignored by git, so your secrets stay on your computer.

// ---- Database (XAMPP defaults) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'study_assistant');
define('DB_USER', 'root');
define('DB_PASS', '');                       // XAMPP root password is empty by default

// ---- AI ----
define('AI_PROVIDER', 'gemini');             // 'gemini' (Google)  or  'anthropic' (Claude)
define('AI_API_KEY',  'YOUR_API_KEY_HERE');

// Optional: choose another model name
// define('AI_MODEL', 'gemini-3.5-flash');
