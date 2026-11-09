<?php
require_once __DIR__ . '/config.php';

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        json_out(['ok' => false, 'error' => 'Database not connected. Is MySQL started in XAMPP and database.sql imported?'], 500);
    }
    return $pdo;
}

function format_size(int $bytes): string {
    return $bytes < 1048576 ? round($bytes / 1024, 1) . ' KB' : round($bytes / 1048576, 1) . ' MB';
}

function doc_to_json(array $r): array {
    return [
        'id'     => (int)$r['id'],
        'name'   => $r['file_name'],
        'size'   => format_size((int)$r['file_size']),
        'pages'  => (int)$r['page_count'],
        'status' => $r['status'],
    ];
}

require_once __DIR__ . '/auth.php';
if (!defined('AUTH_PUBLIC')) uid();   // every API needs a logged-in user, unless it says AUTH_PUBLIC
