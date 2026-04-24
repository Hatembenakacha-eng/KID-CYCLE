<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — api/auth.php
   Endpoints: POST /register, POST /login, POST /logout,
              GET  /me, POST /update-profile, POST /delete-account
   ═══════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config.php';
startKcSession();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Parse JSON body
$body = [];
$raw = file_get_contents('php://input');
if ($raw) {
    $body = json_decode($raw, true) ?? [];
}
$data = array_merge($_POST, $body);

// ── Router ────────────────────────────────────────────────
match ($action) {
    'register'       => handleRegister($data),
    'login'          => handleLogin($data),
    'logout'         => handleLogout(),
    'me'             => handleMe(),
    'update-profile' => handleUpdateProfile($data),
    'delete-account' => handleDeleteAccount($data),
    'change-password'=> handleChangePassword($data),
    default          => jsonResponse(['error' => 'Action inconnue.'], 400),
};


/* ── REGISTER ─────────────────────────────────────────────── */
function handleRegister(array $d): void {
    if ($d['_method'] ?? '' !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Méthode POST requise.'], 405);
    }

    $nom    = trim($d['nom']    ?? '');
    $prenom = trim($d['prenom'] ?? '');
    $email  = strtolower(trim($d['email'] ?? ''));
    $mdp    = $d['motdepasse']  ?? '';
    $conf   = $d['confirmation'] ?? $mdp;

    // Validation
    if (!$nom || !$email || !$mdp) {
        jsonResponse(['error' => 'Nom, email et mot de passe sont obligatoires.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Email invalide.'], 422);
    }
    if (strlen($mdp) < 6) {
        jsonResponse(['error' => 'Le mot de passe doit faire au moins 6 caractères.'], 422);
    }
    if ($mdp !== $conf) {
        jsonResponse(['error' => 'Les mots de passe ne correspondent pas.'], 422);
    }

    $db = getDB();

    // Check email uniqueness
    $stmt = $db->prepare('SELECT id FROM utilisateurs WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Un compte existe déjà avec cet email.'], 409);
    }

    // Insert
    $hash = password_hash($mdp, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        'INSERT INTO utilisateurs (nom, prenom, email, motdepasse, swaps, role)
         VALUES (?, ?, ?, ?, 50.00, "user")'
    );
    $stmt->execute([clean($nom), clean($prenom), $email, $hash]);
    $userId = (int) $db->lastInsertId();

    // Auto-login
    $_SESSION['user_id'] = $userId;
    $_SESSION['email']   = $email;
    $_SESSION['nom']     = clean($nom);
    $_SESSION['prenom']  = clean($prenom);
    $_SESSION['role']    = 'user';

    jsonResponse([
        'success' => true,
        'message' => 'Bienvenue '. clean($prenom ?: $nom) .' !',
        'user'    => currentUser(),
        'redirect'=> '../page acceuil.html',
    ], 201);
}


/* ── LOGIN ───────────────────────────────────────────────── */
function handleLogin(array $d): void {
    $email = strtolower(trim($d['email'] ?? ''));
    $mdp   = $d['motdepasse'] ?? '';

    if (!$email || !$mdp) {
        jsonResponse(['error' => 'Email et mot de passe requis.'], 422);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['error' => 'Aucun compte avec cet email.'], 401);
    }

    // Support legacy plain-text passwords (migration) + bcrypt
    $validPwd = password_verify($mdp, $user['motdepasse'])
             || ($user['motdepasse'] === $mdp); // legacy

    if (!$validPwd) {
        jsonResponse(['error' => 'Mot de passe incorrect.'], 401);
    }

    // Rehash if needed
    if (password_needs_rehash($user['motdepasse'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($mdp, PASSWORD_BCRYPT);
        $db->prepare('UPDATE utilisateurs SET motdepasse = ? WHERE id = ?')
           ->execute([$newHash, $user['id']]);
    }

    // Start session
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['nom']     = $user['nom'];
    $_SESSION['prenom']  = $user['prenom'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['swaps']   = (float) $user['swaps'];

    $redirect = $user['role'] === 'admin' ? '../admin/admin.php' : '../page acceuil.html';

    jsonResponse([
        'success'  => true,
        'message'  => 'Bienvenue ' . ($user['prenom'] ?: $user['nom']) . ' !',
        'user'     => currentUser(),
        'swaps'    => (float) $user['swaps'],
        'redirect' => $redirect,
    ]);
}


/* ── LOGOUT ──────────────────────────────────────────────── */
function handleLogout(): void {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true, 'redirect' => '../page acceuil.html']);
}


/* ── ME (current user info) ──────────────────────────────── */
function handleMe(): void {
    if (!isLoggedIn()) {
        jsonResponse(['logged' => false]);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id,nom,prenom,email,tel,adresse,avatar_url,swaps,role,created_at FROM utilisateurs WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        jsonResponse(['logged' => false]);
    }

    jsonResponse(['logged' => true, 'user' => $user]);
}


/* ── UPDATE PROFILE ──────────────────────────────────────── */
function handleUpdateProfile(array $d): void {
    requireLogin();

    $nom     = trim($d['nom']     ?? '');
    $prenom  = trim($d['prenom']  ?? '');
    $tel     = trim($d['tel']     ?? '');
    $adresse = trim($d['adresse'] ?? '');

    if (!$nom) jsonResponse(['error' => 'Le nom est obligatoire.'], 422);

    $db = getDB();

    // Handle avatar upload
    $avatarUrl = null;
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            jsonResponse(['error' => 'Format image non supporté.'], 422);
        }
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename  = 'av_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename);
        $avatarUrl = 'uploads/avatars/' . $filename;
    }

    $sql    = 'UPDATE utilisateurs SET nom=?, prenom=?, tel=?, adresse=?, updated_at=NOW()';
    $params = [clean($nom), clean($prenom), clean($tel), clean($adresse)];

    if ($avatarUrl) {
        $sql    .= ', avatar_url=?';
        $params[] = $avatarUrl;
    }
    $sql .= ' WHERE id=?';
    $params[] = $_SESSION['user_id'];

    $db->prepare($sql)->execute($params);

    // Update session
    $_SESSION['nom']    = clean($nom);
    $_SESSION['prenom'] = clean($prenom);

    jsonResponse(['success' => true, 'message' => 'Profil mis à jour !', 'avatar_url' => $avatarUrl]);
}


/* ── CHANGE PASSWORD ─────────────────────────────────────── */
function handleChangePassword(array $d): void {
    requireLogin();

    $current = $d['current_pwd'] ?? '';
    $newpwd  = $d['new_pwd']     ?? '';
    $confirm = $d['confirm_pwd'] ?? '';

    if (!$current || !$newpwd) jsonResponse(['error' => 'Champs requis.'], 422);
    if (strlen($newpwd) < 6)   jsonResponse(['error' => 'Minimum 6 caractères.'], 422);
    if ($newpwd !== $confirm)   jsonResponse(['error' => 'Mots de passe différents.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT motdepasse FROM utilisateurs WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();

    if (!password_verify($current, $row['motdepasse']) && $row['motdepasse'] !== $current) {
        jsonResponse(['error' => 'Mot de passe actuel incorrect.'], 401);
    }

    $hash = password_hash($newpwd, PASSWORD_BCRYPT);
    $db->prepare('UPDATE utilisateurs SET motdepasse=? WHERE id=?')
       ->execute([$hash, $_SESSION['user_id']]);

    jsonResponse(['success' => true, 'message' => 'Mot de passe modifié !']);
}


/* ── DELETE ACCOUNT ──────────────────────────────────────── */
function handleDeleteAccount(array $d): void {
    requireLogin();

    $confirm = $d['confirm'] ?? '';
    if ($confirm !== 'SUPPRIMER') {
        jsonResponse(['error' => 'Confirmation requise.'], 422);
    }

    $db = getDB();
    $db->prepare('DELETE FROM utilisateurs WHERE id = ?')
       ->execute([$_SESSION['user_id']]);

    $_SESSION = [];
    session_destroy();

    jsonResponse(['success' => true, 'message' => 'Compte supprimé.', 'redirect' => '../page acceuil.html']);
}
