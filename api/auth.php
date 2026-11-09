<?php
define('AUTH_PUBLIC', true);                 // this is the only API file that works without login
require_once __DIR__ . '/../includes/db.php';

auth_session_start();
check_same_origin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = db();

function user_json(array $u): array {
    return ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
}

function log_in_user(int $id): void {
    session_regenerate_id(true);             // new session id after login (stops session fixation)
    $_SESSION['uid'] = $id;
    unset($_SESSION['fails'], $_SESSION['lock_until']);
}

// ---- who am I? ----
if ($action === 'me') {
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $st = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['uid']]);
        $row = $st->fetch();
        if ($row) $user = user_json($row); else unset($_SESSION['uid']);
    }
    json_out(['ok' => true, 'user' => $user]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);

// ---- sign up ----
if ($action === 'register') {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) json_out(['ok' => false, 'error' => 'Please enter your name (2 to 100 letters).'], 400);
    if (preg_match('/[^\\p{L}\\p{M} .\'\\-]/u', $name)) json_out(['ok' => false, 'error' => 'Name can contain letters only (no numbers).'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) json_out(['ok' => false, 'error' => 'Please enter a valid email.'], 400);
    if (strlen($pass) < 8)  json_out(['ok' => false, 'error' => 'Password must be at least 8 characters.'], 400);
    if (strlen($pass) > 72) json_out(['ok' => false, 'error' => 'Password is too long (max 72 characters).'], 400);

    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) json_out(['ok' => false, 'error' => 'This email is already registered. Please login.'], 409);

    try {
        $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'error' => 'This email is already registered. Please login.'], 409);
    }
    $id = (int)$pdo->lastInsertId();
    log_in_user($id);
    json_out(['ok' => true, 'user' => ['id' => $id, 'name' => $name, 'email' => $email]]);
}

// ---- login ----
if ($action === 'login') {
    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $min = (int)ceil(($_SESSION['lock_until'] - time()) / 60);
        json_out(['ok' => false, 'error' => "Too many wrong attempts. Please wait $min minute(s) and try again."], 429);
    }
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();

    // always run password_verify, so the answer takes the same time for unknown emails
    $hash = $row['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuNEcOGrb6oOq1MYy/3.sXJHQ4TT8dUGO';
    $ok = password_verify($pass, $hash) && $row;

    if (!$ok) {
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) { $_SESSION['lock_until'] = time() + 300; $_SESSION['fails'] = 0; }
        usleep(400000);
        json_out(['ok' => false, 'error' => 'Wrong email or password.'], 401);
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
    }
    log_in_user((int)$row['id']);
    json_out(['ok' => true, 'user' => user_json($row)]);
}

// ---- logout ----
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
