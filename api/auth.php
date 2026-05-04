<?php
require __DIR__ . '/config.php';
head();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
  $data = body();
  $email = strtolower(trim($data['email'] ?? ''));
  $password = $data['password'] ?? '';

  if (!$email || !$password) {
    out(['ok' => false, 'err' => 'Email et mot de passe requis.'], 400);
  }

  $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1');
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if (!$user) {
    out(['ok' => false, 'err' => 'Aucun compte trouvé avec cet email.'], 401);
  }

  if (!password_verify($password, $user['motdepasse'])) {
    out(['ok' => false, 'err' => 'Mot de passe incorrect.'], 401);
  }

  $token = makeToken((int) $user['id']);
  unset($user['motdepasse']);
  out(['ok' => true, 'token' => $token, 'user' => $user]);
}

if ($method === 'POST' && $action === 'register') {
  $data = body();
  $nom = clean($data['nom'] ?? '');
  $prenom = clean($data['prenom'] ?? '');
  $email = strtolower(trim($data['email'] ?? ''));
  $password = $data['password'] ?? '';
  $genre = clean($data['genre'] ?? '');
  $tel = clean($data['tel'] ?? '');
  $pays = clean($data['pays'] ?? 'Tunisie');
  $adresse = clean($data['adresse'] ?? '');
  $codePostal = clean($data['code_postal'] ?? '');
  $ville = clean($data['ville'] ?? '');
  $newsletter = !empty($data['newsletter']) ? 1 : 0;

  if (!$nom || !$prenom || !$email || !$password) {
    out(['ok' => false, 'err' => 'Champs obligatoires manquants.'], 400);
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(['ok' => false, 'err' => 'Email invalide.'], 400);
  }

  if (strlen($password) < 6) {
    out(['ok' => false, 'err' => 'Mot de passe: 6 caractères minimum.'], 400);
  }

  $check = db()->prepare('SELECT id FROM utilisateurs WHERE email = ?');
  $check->execute([$email]);
  if ($check->fetch()) {
    out(['ok' => false, 'err' => 'Un compte existe déjà avec cet email.'], 409);
  }

  $hash = password_hash($password, PASSWORD_BCRYPT);
  db()->prepare('INSERT INTO utilisateurs(nom,prenom,email,motdepasse,genre,tel,pays,adresse,code_postal,ville,newsletter) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$nom, $prenom, $email, $hash, $genre, $tel, $pays, $adresse, $codePostal, $ville, $newsletter]);

  $id = (int) db()->lastInsertId();
  if ($newsletter) {
    try {
      db()->prepare('INSERT IGNORE INTO newsletter(email) VALUES(?)')->execute([$email]);
    } catch (Throwable $e) {
    }
  }

  $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
  $stmt->execute([$id]);
  $user = $stmt->fetch();
  unset($user['motdepasse']);

  out(['ok' => true, 'token' => makeToken($id), 'user' => $user], 201);
}

if ($method === 'GET' && $action === 'me') {
  out(['ok' => true, 'user' => authRequired()]);
}

if ($method === 'PUT' && $action === 'update') {
  $user = authRequired();
  $data = body();

  $nom = clean($data['nom'] ?? $user['nom']);
  $prenom = clean($data['prenom'] ?? $user['prenom']);
  $email = strtolower(trim($data['email'] ?? $user['email']));
  $tel = clean($data['tel'] ?? '');
  $pays = clean($data['pays'] ?? 'Tunisie');
  $adresse = clean($data['adresse'] ?? '');
  $codePostal = clean($data['code_postal'] ?? '');
  $ville = clean($data['ville'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(['ok' => false, 'err' => 'Email invalide.'], 400);
  }

  if ($email !== $user['email']) {
    $check = db()->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id != ?');
    $check->execute([$email, $user['id']]);
    if ($check->fetch()) {
      out(['ok' => false, 'err' => 'Email déjà utilisé.'], 409);
    }
  }

  if (!empty($data['password'])) {
    if (strlen($data['password']) < 6) {
      out(['ok' => false, 'err' => 'Mot de passe trop court.'], 400);
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    db()->prepare('UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, tel = ?, pays = ?, adresse = ?, code_postal = ?, ville = ?, motdepasse = ? WHERE id = ?')
      ->execute([$nom, $prenom, $email, $tel, $pays, $adresse, $codePostal, $ville, $hash, $user['id']]);
  } else {
    db()->prepare('UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, tel = ?, pays = ?, adresse = ?, code_postal = ?, ville = ? WHERE id = ?')
      ->execute([$nom, $prenom, $email, $tel, $pays, $adresse, $codePostal, $ville, $user['id']]);
  }

  if (!empty($data['avatar'])) {
    db()->prepare('UPDATE utilisateurs SET avatar = ? WHERE id = ?')->execute([clean($data['avatar']), $user['id']]);
  }

  out(['ok' => true, 'msg' => 'Profil mis à jour.']);
}

if ($method === 'DELETE' && $action === 'delete') {
  $user = authRequired();
  db()->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$user['id']]);
  out(['ok' => true, 'msg' => 'Compte supprimé.']);
}

if ($method === 'POST' && $action === 'avatar') {
  $user = authRequired();

  if (empty($_FILES['avatar']['tmp_name'])) {
    out(['ok' => false, 'err' => 'Aucun fichier.'], 400);
  }

  $file = $_FILES['avatar'];
  if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
    out(['ok' => false, 'err' => 'Fichier trop volumineux (max 3Mo).'], 400);
  }

  $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    out(['ok' => false, 'err' => 'Format non supporté.'], 400);
  }

  if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
  }

  $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
  if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
    out(['ok' => false, 'err' => 'Erreur upload.'], 500);
  }

  $url = UPLOAD_URL . $filename;
  db()->prepare('UPDATE utilisateurs SET avatar = ? WHERE id = ?')->execute([$url, $user['id']]);
  out(['ok' => true, 'url' => $url]);
}

out(['ok' => false, 'err' => 'Action non reconnue.'], 404);
