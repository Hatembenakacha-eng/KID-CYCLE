<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — config.php
   Configuration base de données + utilitaires globaux
   ═══════════════════════════════════════════════════════════ */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kidcycle_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('KC_VERSION', '1.0.0');
define('KC_BASE_URL', 'http://localhost/kidcycle');

/* ── PDO Connection ─────────────────────────────────────── */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Connexion DB échouée: ' . $e->getMessage()]));
    }
    return $pdo;
}

/* ── JSON Response Helper ───────────────────────────────── */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Session Auth ────────────────────────────────────────── */
function startKcSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('kc_sess');
        session_start();
    }
}

function isLoggedIn(): bool {
    startKcSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Non autorisé. Veuillez vous connecter.'], 401);
    }
}

function isAdmin(): bool {
    startKcSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        jsonResponse(['error' => 'Accès réservé aux administrateurs.'], 403);
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'email'  => $_SESSION['email'],
        'nom'    => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'] ?? '',
        'role'   => $_SESSION['role'] ?? 'user',
    ];
}

/* ── Sanitize ────────────────────────────────────────────── */
function clean(string $s): string {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}

function cleanInt($v): int {
    return (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
}

function cleanFloat($v): float {
    return (float) filter_var($v, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/* ── CORS preflight ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}
