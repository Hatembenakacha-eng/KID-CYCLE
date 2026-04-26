<?php
/* ============================================================
   auth.php — Connexion, Inscription, Profil utilisateur
   Toutes les routes liées à l'authentification sont ici
   ============================================================ */
require __DIR__ . '/config.php';

/* Envoyer les entêtes JSON */
head();

/* Lire la méthode HTTP (GET, POST, PUT, DELETE) et l'action demandée */
$methode = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';


/* ============================================================
   CONNEXION — POST /api/auth.php?action=login
   Vérifier email + mot de passe et retourner un token
   ============================================================ */
if ($methode === 'POST' && $action === 'login') {

    $donnees    = body();
    $email      = strtolower(trim($donnees['email']    ?? ''));
    $motDePasse = $donnees['password'] ?? '';

    /* Vérifications de base */
    if (!$email || !$motDePasse) {
        out(['ok' => false, 'err' => 'Email et mot de passe requis.'], 400);
    }

    /* filter_var — vérifie que l'email a un format valide (ex: a@b.com) */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(['ok' => false, 'err' => 'Email invalide.'], 400);
    }

    /* Chercher l'utilisateur dans la base de données */
    $req = db()->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1');
    $req->execute([$email]);
    $utilisateur = $req->fetch();

    if (!$utilisateur) {
        out(['ok' => false, 'err' => 'Aucun compte trouvé avec cet email.'], 401);
    }

    /* password_verify — compare le mot de passe avec le hash stocké en base */
    if (!password_verify($motDePasse, $utilisateur['motdepasse'])) {
        out(['ok' => false, 'err' => 'Mot de passe incorrect.'], 401);
    }

    /* Créer le token de session */
    $token = makeToken($utilisateur['id']);

    /* Ne pas envoyer le mot de passe dans la réponse */
    unset($utilisateur['motdepasse']);

    out(['ok' => true, 'token' => $token, 'user' => $utilisateur]);
}


/* ============================================================
   INSCRIPTION — POST /api/auth.php?action=register
   Créer un nouveau compte utilisateur
   ============================================================ */
if ($methode === 'POST' && $action === 'register') {

    $donnees = body();

    /* Récupérer et nettoyer les données */
    $nom      = clean($donnees['nom']      ?? '');
    $prenom   = clean($donnees['prenom']   ?? '');
    $email    = strtolower(trim($donnees['email']    ?? ''));
    $password = $donnees['password'] ?? '';
    $genre    = clean($donnees['genre']    ?? '');
    $tel      = clean($donnees['tel']      ?? '');
    $pays     = clean($donnees['pays']     ?? 'Tunisie');
    $adresse  = clean($donnees['adresse']  ?? '');
    $cp       = clean($donnees['code_postal'] ?? '');
    $ville    = clean($donnees['ville']    ?? '');
    $newsletter = (bool)($donnees['newsletter'] ?? false);

    /* Vérifications obligatoires */
    if (!$nom || !$prenom || !$email || !$password) {
        out(['ok' => false, 'err' => 'Nom, prénom, email et mot de passe sont obligatoires.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(['ok' => false, 'err' => 'Email invalide.'], 400);
    }

    /* strlen — compte le nombre de caractères */
    if (strlen($password) < 6) {
        out(['ok' => false, 'err' => 'Le mot de passe doit avoir au moins 6 caractères.'], 400);
    }

    /* Vérifier si l'email est déjà utilisé */
    $verif = db()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
    $verif->execute([$email]);
    if ($verif->fetch()) {
        out(['ok' => false, 'err' => 'Un compte existe déjà avec cet email.'], 409);
    }

    /* password_hash — transforme le mot de passe en hash sécurisé */
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    /* Insérer le nouveau compte dans la base de données */
    db()->prepare('
        INSERT INTO utilisateurs
            (nom, prenom, email, motdepasse, genre, tel, pays, adresse, code_postal, ville, newsletter)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([$nom, $prenom, $email, $hash, $genre, $tel, $pays, $adresse, $cp, $ville, $newsletter ? 1 : 0]);

    /* lastInsertId — récupère l'ID auto-généré par MySQL */
    $id = (int)db()->lastInsertId();

    /* Ajouter à la newsletter si demandé */
    if ($newsletter) {
        try {
            db()->prepare('INSERT IGNORE INTO newsletter (email) VALUES (?)')->execute([$email]);
        } catch (Exception $e) {}
    }

    $token = makeToken($id);

    $utilisateur = [
        'id'          => $id,
        'nom'         => $nom,
        'prenom'      => $prenom,
        'email'       => $email,
        'genre'       => $genre,
        'tel'         => $tel,
        'pays'        => $pays,
        'adresse'     => $adresse,
        'code_postal' => $cp,
        'ville'       => $ville,
        'avatar'      => null,
        'swaps'       => '500.00',
        'role'        => 'client',
        'abonnement'  => 'Gratuit'
    ];

    /* 201 = "Créé avec succès" */
    out(['ok' => true, 'token' => $token, 'user' => $utilisateur], 201);
}


/* ============================================================
   MON PROFIL — GET /api/auth.php?action=me
   Retourner les infos de l'utilisateur connecté
   ============================================================ */
if ($methode === 'GET' && $action === 'me') {
    $utilisateur = authRequired();
    out(['ok' => true, 'user' => $utilisateur]);
}


/* ============================================================
   MODIFIER PROFIL — PUT /api/auth.php?action=update
   Mettre à jour les données de l'utilisateur connecté
   ============================================================ */
if ($methode === 'PUT' && $action === 'update') {

    $utilisateur = authRequired();
    $donnees     = body();

    $nom     = clean($donnees['nom']          ?? $utilisateur['nom']);
    $prenom  = clean($donnees['prenom']       ?? $utilisateur['prenom']);
    $email   = strtolower(trim($donnees['email'] ?? $utilisateur['email']));
    $tel     = clean($donnees['tel']          ?? '');
    $pays    = clean($donnees['pays']         ?? 'Tunisie');
    $adresse = clean($donnees['adresse']      ?? '');
    $cp      = clean($donnees['code_postal']  ?? '');
    $ville   = clean($donnees['ville']        ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(['ok' => false, 'err' => 'Email invalide.'], 400);
    }

    /* Vérifier que le nouvel email n'appartient pas à quelqu'un d'autre */
    if ($email !== $utilisateur['email']) {
        $verif = db()->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id != ?');
        $verif->execute([$email, $utilisateur['id']]);
        if ($verif->fetch()) {
            out(['ok' => false, 'err' => 'Cet email est déjà utilisé par un autre compte.'], 409);
        }
    }

    /* Mettre à jour avec ou sans changement de mot de passe */
    if (!empty($donnees['password'])) {
        if (strlen($donnees['password']) < 6) {
            out(['ok' => false, 'err' => 'Le nouveau mot de passe est trop court.'], 400);
        }
        $hash = password_hash($donnees['password'], PASSWORD_BCRYPT);
        db()->prepare('
            UPDATE utilisateurs
            SET nom=?, prenom=?, email=?, tel=?, pays=?, adresse=?, code_postal=?, ville=?, motdepasse=?
            WHERE id=?
        ')->execute([$nom, $prenom, $email, $tel, $pays, $adresse, $cp, $ville, $hash, $utilisateur['id']]);
    } else {
        db()->prepare('
            UPDATE utilisateurs
            SET nom=?, prenom=?, email=?, tel=?, pays=?, adresse=?, code_postal=?, ville=?
            WHERE id=?
        ')->execute([$nom, $prenom, $email, $tel, $pays, $adresse, $cp, $ville, $utilisateur['id']]);
    }

    /* Mettre à jour l'avatar si fourni */
    if (!empty($donnees['avatar'])) {
        db()->prepare('UPDATE utilisateurs SET avatar=? WHERE id=?')
            ->execute([clean($donnees['avatar']), $utilisateur['id']]);
    }

    out(['ok' => true, 'msg' => 'Profil mis à jour avec succès.']);
}


/* ============================================================
   SUPPRIMER COMPTE — DELETE /api/auth.php?action=delete
   ============================================================ */
if ($methode === 'DELETE' && $action === 'delete') {
    $utilisateur = authRequired();
    db()->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$utilisateur['id']]);
    out(['ok' => true, 'msg' => 'Compte supprimé.']);
}


/* ============================================================
   UPLOAD AVATAR — POST /api/auth.php?action=avatar
   Télécharger une photo de profil
   ============================================================ */
if ($methode === 'POST' && $action === 'avatar') {

    $utilisateur = authRequired();

    if (!isset($_FILES['avatar'])) {
        out(['ok' => false, 'err' => 'Aucun fichier reçu.'], 400);
    }

    $fichier = $_FILES['avatar'];

    /* Vérifier la taille (max 3 Mo) */
    if ($fichier['size'] > 3 * 1024 * 1024) {
        out(['ok' => false, 'err' => 'Image trop grande (maximum 3 Mo).'], 400);
    }

    /* Vérifier le format de l'image */
    $formatsAcceptes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($fichier['type'], $formatsAcceptes)) {
        out(['ok' => false, 'err' => 'Format non supporté. Utilisez JPG, PNG ou WEBP.'], 400);
    }

    /* Créer le dossier uploads s'il n'existe pas */
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    /* pathinfo — récupère l'extension du fichier (ex: jpg, png) */
    $extension = pathinfo($fichier['name'], PATHINFO_EXTENSION);
    $nomFichier = 'avatar_' . $utilisateur['id'] . '_' . time() . '.' . $extension;

    /* move_uploaded_file — déplace le fichier temporaire vers le bon dossier */
    if (!move_uploaded_file($fichier['tmp_name'], UPLOAD_DIR . $nomFichier)) {
        out(['ok' => false, 'err' => 'Erreur lors du téléchargement.'], 500);
    }

    $url = UPLOAD_URL . $nomFichier;
    db()->prepare('UPDATE utilisateurs SET avatar=? WHERE id=?')->execute([$url, $utilisateur['id']]);

    out(['ok' => true, 'url' => $url]);
}


/* Si aucune route ne correspond */
out(['ok' => false, 'err' => 'Action non reconnue.'], 404);
