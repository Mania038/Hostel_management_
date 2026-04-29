<?php
// ============================================================
//  config/db.php  —  Database connection (MySQLi)
//  Edit DB_HOST / DB_NAME / DB_USER / DB_PASS as needed
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'hostel_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default has no password
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME',    'UniNest HMS');
define('APP_URL',     'http://localhost/uninest');
define('SESSION_TIMEOUT', 3600);    // seconds

// ---- Connect ----
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(503);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset(DB_CHARSET);

// ---- Helper: prepared query returning all rows ----
function db_query($conn, string $sql, string $types = '', ...$params): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ---- Helper: prepared query returning ONE row ----
function db_row($conn, string $sql, string $types = '', ...$params): ?array {
    $rows = db_query($conn, $sql, $types, ...$params);
    return $rows[0] ?? null;
}

// ---- Helper: prepared INSERT/UPDATE/DELETE, returns affected rows ----
function db_exec($conn, string $sql, string $types = '', ...$params): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->affected_rows;
}

// ---- Helper: last insert id ----
function db_insert($conn, string $sql, string $types = '', ...$params): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return (int)$conn->insert_id;
}

// ---- Redirect helper ----
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ---- Flash message ----
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---- Auth guards ----
function require_student(): void {
    session_start_safe();
    if (empty($_SESSION['student_id'])) {
        redirect(APP_URL . '/auth/login.php?role=student');
    }
    // session timeout
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        redirect(APP_URL . '/auth/login.php?timeout=1');
    }
    $_SESSION['last_active'] = time();
}

function require_admin(): void {
    session_start_safe();
    if (empty($_SESSION['admin_id'])) {
        redirect(APP_URL . '/auth/login.php?role=admin');
    }
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        redirect(APP_URL . '/auth/login.php?timeout=1&role=admin');
    }
    $_SESSION['last_active'] = time();
}

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ---- Sanitize input ----
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ---- Generate application code ----
function gen_app_code($conn): string {
    $year = date('Y');
    $row  = db_row($conn, "SELECT COUNT(*) AS cnt FROM applications WHERE YEAR(created_at)=?", 'i', $year);
    $seq  = str_pad(($row['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return "HMS-{$year}-{$seq}";
}
localhost