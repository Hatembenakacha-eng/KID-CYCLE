<?php
/* ============================================================
   config.php — Paramètres de connexion à la base de données
   À modifier selon votre installation XAMPP
   ============================================================ */

/* Adresse du serveur MySQL (localhost = sur votre propre PC) */
define('DB_HOST', 'localhost');

/* Nom de la base de données dans phpMyAdmin */
define('DB_NAME', 'kidcycle');

/* Nom d'utilisateur MySQL (root par défaut dans XAMPP) */
define('DB_USER', 'root');

/* Mot de passe MySQL (vide par défaut dans XAMPP) */
define('DB_PASS', '');

/* Encodage des caractères */
define('DB_CHARSET', 'utf8mb4');

/* URL de base de votre site sur XAMPP */
define('SITE_URL', 'http://localhost/kidcycle_fixed');

/* Dossier où les images uploadées seront sauvegardées */
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

/* URL publique du dossier uploads */
define('UPLOAD_URL', SITE_URL . '/uploads/');

/* Clé secrète pour les tokens de connexion */
define('JWT_SECRET', 'kidcycle_2025_secret_key');


/* ============================================================
   db() — Connexion à la base de données
   PDO = outil PHP pour parler à MySQL
   static = garde la connexion ouverte (ne se reconnecte pas à chaque appel)
   ============================================================ */
function db() {
    static $connexion = null;

    if ($connexion === null) {
        try {
            $connexion = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'err' => 'Erreur de connexion à la base de données.']));
        }
    }

    return $connexion;
}


/* ============================================================
   head() — Envoie les entêtes HTTP nécessaires pour AJAX
   Content-Type JSON = indique que la réponse est du JSON
   Access-Control = autorise les requêtes depuis le navigateur
   ============================================================ */
function head() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type,Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}


/* ============================================================
   out() — Envoie une réponse JSON et arrête le script
   $data = tableau PHP à convertir en JSON
   $code = code HTTP : 200=succès, 400=erreur client, 401=non connecté, 404=introuvable
   ============================================================ */
function out(array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


/* ============================================================
   body() — Lit les données JSON envoyées dans la requête POST
   file_get_contents('php://input') = lit le corps de la requête
   json_decode(..., true) = convertit JSON en tableau PHP
   ============================================================ */
function body() {
    $texte = file_get_contents('php://input');
    return json_decode($texte, true) ?? [];
}


/* ============================================================
   clean() — Nettoie une valeur pour éviter les attaques XSS
   strip_tags — supprime les balises HTML (<script>, etc.)
   htmlspecialchars — encode les caractères spéciaux
   trim — supprime les espaces inutiles
   ============================================================ */
function clean(string $s) {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}


/* ============================================================
   makeToken() — Crée un token JWT pour identifier l'utilisateur
   Un token = identifiant sécurisé valable 30 jours
   hash_hmac — crée une signature numérique avec la clé secrète
   base64_encode — encode les données en texte transportable
   ============================================================ */
function makeToken(int $userId) {
    $entete    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload   = base64_encode(json_encode([
        'sub' => $userId,
        'exp' => time() + 86400 * 30,
        'iat' => time()
    ]));
    $signature = base64_encode(hash_hmac('sha256', "$entete.$payload", JWT_SECRET, true));
    return "$entete.$payload.$signature";
}


/* ============================================================
   auth() — Vérifie le token et retourne l'utilisateur connecté
   Retourne null si pas connecté ou token invalide/expiré
   ============================================================ */
function auth() {
    $entete = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token  = trim(str_replace('Bearer', '', $entete));

    if (!$token) return null;

    try {
        /* explode('.', $token) — coupe le token en 3 parties */
        $parties = explode('.', $token);
        if (count($parties) !== 3) return null;

        $payload = json_decode(base64_decode($parties[1]), true);

        /* time() — heure actuelle en secondes. Si exp < time() : token expiré */
        if (!$payload || $payload['exp'] < time()) return null;

        $req = db()->prepare('SELECT * FROM utilisateurs WHERE id = ? AND actif = 1');
        $req->execute([$payload['sub']]);
        $utilisateur = $req->fetch();

        if ($utilisateur) unset($utilisateur['motdepasse']);

        return $utilisateur ?: null;

    } catch (Exception $e) {
        return null;
    }
}


/* ============================================================
   authRequired() — Bloque si l'utilisateur n'est pas connecté
   Utilisé en début de chaque route protégée
   ============================================================ */
function authRequired() {
    $utilisateur = auth();
    if (!$utilisateur) {
        out(['ok' => false, 'err' => 'Vous devez être connecté(e).'], 401);
    }
    return $utilisateur;
}
