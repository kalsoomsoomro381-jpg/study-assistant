<?php
// Login helpers (PHP sessions). This file is loaded by db.php.
//
// RULE: every API file in /api requires a logged-in user automatically
// (db.php calls uid() at the end). Only api/auth.php is public: it says
// define('AUTH_PUBLIC', true) before loading db.php.

const SESSION_DAYS = 7;

function auth_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(SESSION_DAYS * 86400));
    session_name('sa_session');
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,        // JavaScript cannot read the cookie
        'samesite' => 'Lax',       // other websites cannot send it with their forms
        'secure'   => $secure,
    ]);
    session_start();
}

/** A POST must come from this same website (blocks forms on other sites). */
function check_same_origin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return;
    $host = (string)parse_url($origin, PHP_URL_HOST);
    $port = parse_url($origin, PHP_URL_PORT);
    if ($port) $host .= ':' . $port;
    if (strcasecmp($host, $_SERVER['HTTP_HOST'] ?? '') !== 0) {
        json_out(['ok' => false, 'error' => 'Request blocked.'], 403);
    }
}

/** The id of the logged-in user. Stops with a 401 error when nobody is logged in. */
function uid(): int {
    auth_session_start();
    check_same_origin();
    if (empty($_SESSION['uid'])) {
        json_out(['ok' => false, 'login' => true, 'error' => 'Please login first.'], 401);
    }
    return (int)$_SESSION['uid'];
}
