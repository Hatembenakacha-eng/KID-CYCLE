<?php
if (!ob_get_level()) {
  ob_start();
}

ini_set('display_errors', '0');

define('DB_HOST', 'localhost');
define('DB_NAME', 'kidcycle');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('JWT_SECRET', 'kidcycle_2025_!@#$%^&*_secret');

function db(): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  try {
    $pdo = new PDO(
      'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
      DB_USER,
      DB_PASS,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
  } catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'err' => 'DB connection error', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  return $pdo;
}

function head(): void {
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type,Authorization');

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
  }
}

function out(array $data, int $code = 200): void {
  if (ob_get_length()) {
    ob_clean();
  }

  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    return [];
  }

  return json_decode($raw, true) ?: [];
}

function clean(string $s): string {
  return trim(strip_tags($s));
}

function makeToken(int $id): string {
  $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
  $payload = base64_encode(json_encode(['sub' => $id, 'iat' => time(), 'exp' => time() + 86400 * 30]));
  $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true));

  return $header . '.' . $payload . '.' . $signature;
}

function bearerToken(): string {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

  if (!$header && !empty($_POST['token'])) {
    return trim((string) $_POST['token']);
  }

  if (!$header) {
    return '';
  }

  if (stripos($header, 'Bearer ') === 0) {
    return trim(substr($header, 7));
  }

  return trim($header);
}

function auth(): ?array {
  $token = bearerToken();
  if (!$token) {
    return null;
  }

  try {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
      return null;
    }

    $payload = json_decode(base64_decode($parts[1]), true);
    if (!$payload || (int)($payload['exp'] ?? 0) < time()) {
      return null;
    }

    $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ? AND actif = 1');
    $stmt->execute([(int) $payload['sub']]);
    $user = $stmt->fetch();

    if (!$user) {
      return null;
    }

    unset($user['motdepasse']);
    return $user;
  } catch (Throwable $e) {
    return null;
  }
}

function authRequired(): array {
  $user = auth();
  if (!$user) {
    out(['ok' => false, 'err' => 'Authentification requise.'], 401);
  }

  return $user;
}
