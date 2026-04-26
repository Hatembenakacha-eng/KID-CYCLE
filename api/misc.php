<?php
require __DIR__.'/config.php';
head();
$m = $_SERVER['REQUEST_METHOD'];
$a = $_GET['action'] ?? '';

/* ── SEARCH ─────────────────────────────────────────────────── */
if ($a === 'search' && $m === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) out(['ok' => true, 'data' => []]);
    $like = '%' . clean($q) . '%';
    $s = db()->prepare(
        'SELECT p.id, p.nom, p.prix, p.image, c.slug as categorie_slug, v.prix_solde
         FROM produits p
         LEFT JOIN categories c ON p.categorie_id = c.id
         LEFT JOIN ventes v ON v.produit_id = p.id AND v.actif = 1
         WHERE (p.nom LIKE ? OR p.description LIKE ?) AND p.statut = \'actif\'
         LIMIT 8'
    );
    $s->execute([$like, $like]);
    out(['ok' => true, 'data' => $s->fetchAll()]);
}

/* ── NEWSLETTER ──────────────────────────────────────────────── */
if ($a === 'newsletter' && $m === 'POST') {
    $b = body();
    $email = strtolower(trim($b['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        out(['ok' => false, 'err' => 'Email invalide.'], 400);
    try {
        db()->prepare('INSERT INTO newsletter(email) VALUES(?)')->execute([$email]);
        out(['ok' => true, 'msg' => 'Inscription confirmée ! Merci.']);
    } catch (PDOException) {
        out(['ok' => false, 'err' => 'Vous êtes déjà inscrit(e).'], 409);
    }
}

/* ── VÉRIFIER CODE PROMO ────────────────────────────────────── */
if ($a === 'promo' && $m === 'GET') {
    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!$code) out(['ok' => false, 'err' => 'Code manquant.'], 400);
    $s = db()->prepare(
        'SELECT * FROM codes_promo WHERE code = ? AND actif = 1
         AND (expiration IS NULL OR expiration >= CURDATE())'
    );
    $s->execute([$code]);
    $promo = $s->fetch();
    if (!$promo) out(['ok' => false, 'err' => 'Code invalide ou expiré.'], 404);
    out(['ok' => true, 'data' => $promo, 'msg' => 'Code valide !']);
}

/* ── STATS ADMIN ────────────────────────────────────────────── */
if ($a === 'stats' && $m === 'GET') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $stats = [
        'users'             => (int)db()->query('SELECT COUNT(*) FROM utilisateurs WHERE actif=1')->fetchColumn(),
        'produits_actifs'   => (int)db()->query("SELECT COUNT(*) FROM produits WHERE statut='actif'")->fetchColumn(),
        'produits_attente'  => (int)db()->query("SELECT COUNT(*) FROM produits WHERE statut='attente'")->fetchColumn(),
        'commandes_total'   => (int)db()->query('SELECT COUNT(*) FROM commandes')->fetchColumn(),
        'commandes_attente' => (int)db()->query("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'")->fetchColumn(),
        'commandes_livrees' => (int)db()->query("SELECT COUNT(*) FROM commandes WHERE statut='livree'")->fetchColumn(),
        'ca_total'          => (float)db()->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE statut!='annulee'")->fetchColumn(),
    ];
    out(['ok' => true, 'data' => $stats]);
}

/* ── ADMIN — LISTE UTILISATEURS ─────────────────────────────── */
if ($a === 'users' && $m === 'GET') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $pg = max(1, (int)($_GET['page'] ?? 1));
    $lim = 20; $off = ($pg - 1) * $lim;
    $q = trim($_GET['q'] ?? '');
    if ($q) {
        $like = '%' . $q . '%';
        $s = db()->prepare("SELECT id,nom,prenom,email,role,swaps,abonnement,adresse,actif,created_at FROM utilisateurs WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT $lim OFFSET $off");
        $s->execute([$like, $like, $like]);
    } else {
        $s = db()->prepare("SELECT id,nom,prenom,email,role,swaps,abonnement,adresse,actif,created_at FROM utilisateurs ORDER BY created_at DESC LIMIT $lim OFFSET $off");
        $s->execute();
    }
    $tot = (int)db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn();
    out(['ok' => true, 'data' => $s->fetchAll(), 'total' => $tot, 'pages' => (int)ceil($tot / $lim)]);
}

/* ── ADMIN — SUSPENDRE UTILISATEUR ─────────────────────────── */
if ($a === 'suspend' && $m === 'PUT') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $b = body();
    $uid = (int)($b['id'] ?? 0);
    $actif = (int)($b['actif'] ?? 0);
    db()->prepare('UPDATE utilisateurs SET actif = ? WHERE id = ?')->execute([$actif, $uid]);
    out(['ok' => true, 'msg' => 'Utilisateur mis à jour.']);
}

/* ── ADMIN — VALIDER PRODUIT ────────────────────────────────── */
if ($a === 'valider' && $m === 'PUT') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $b = body();
    $pid = (int)($b['id'] ?? 0);
    $st = clean($b['statut'] ?? 'actif');
    if (!in_array($st, ['actif', 'refuse', 'archive'])) out(['ok' => false, 'err' => 'Statut invalide.'], 400);
    db()->prepare('UPDATE produits SET statut = ? WHERE id = ?')->execute([$st, $pid]);
    out(['ok' => true, 'msg' => 'Produit mis à jour.']);
}

/* ── ADMIN — COMMANDES LISTE ────────────────────────────────── */
if ($a === 'admin_commandes' && $m === 'GET') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $pg = max(1, (int)($_GET['page'] ?? 1));
    $lim = 20; $off = ($pg - 1) * $lim;
    $s = db()->prepare("SELECT c.*, u.nom, u.prenom, u.email FROM commandes c LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id ORDER BY c.created_at DESC LIMIT $lim OFFSET $off");
    $s->execute();
    $tot = (int)db()->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
    out(['ok' => true, 'data' => $s->fetchAll(), 'total' => $tot, 'pages' => (int)ceil($tot / $lim)]);
}

/* ── ADMIN — CHANGER STATUT COMMANDE ───────────────────────── */
if ($a === 'admin_cmd_statut' && $m === 'PUT') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $b = body();
    $cid = (int)($b['id'] ?? 0);
    $st = clean($b['statut'] ?? '');
    $allowed = ['en_attente', 'preparation', 'en_cours', 'livree', 'annulee'];
    if (!in_array($st, $allowed)) out(['ok' => false, 'err' => 'Statut invalide.'], 400);
    db()->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$st, $cid]);
    out(['ok' => true, 'msg' => 'Statut mis à jour.']);
}

/* ── REFS ADMIN (genres, catégories, marques, tailles) ──────── */
if ($a === 'refs' && $m === 'GET') {
    $genres   = db()->query('SELECT * FROM ref_genres ORDER BY nom')->fetchAll();
    $cats     = db()->query('SELECT * FROM ref_categories ORDER BY nom')->fetchAll();
    $marques  = db()->query('SELECT * FROM ref_marques ORDER BY nom')->fetchAll();
    $tailles  = db()->query('SELECT * FROM ref_tailles ORDER BY ordre')->fetchAll();
    $catsProd = db()->query('SELECT * FROM categories ORDER BY nom')->fetchAll();
    out(['ok' => true, 'genres' => $genres, 'categories' => $cats, 'marques' => $marques, 'tailles' => $tailles, 'categories_prod' => $catsProd]);
}

/* ── CRUD REFS ───────────────────────────────────────────────── */
if (in_array($a, ['genre','categorie','marque','taille']) && in_array($m, ['POST','DELETE'])) {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $tables = ['genre'=>'ref_genres','categorie'=>'ref_categories','marque'=>'ref_marques','taille'=>'ref_tailles'];
    $tbl = $tables[$a];
    if ($m === 'POST') {
        $b = body(); $nom = clean($b['nom'] ?? '');
        if (!$nom) out(['ok' => false, 'err' => 'Nom obligatoire.'], 400);
        try {
            db()->prepare("INSERT INTO $tbl(nom) VALUES(?)")->execute([$nom]);
            out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'msg' => 'Ajouté avec succès.'], 201);
        } catch (PDOException) {
            out(['ok' => false, 'err' => 'Élément déjà existant.'], 409);
        }
    }
    if ($m === 'DELETE') {
        $rid = (int)($_GET['id'] ?? 0);
        db()->prepare("DELETE FROM $tbl WHERE id = ?")->execute([$rid]);
        out(['ok' => true, 'msg' => 'Supprimé.']);
    }
}

/* ── SWAPS TARIFS ADMIN ──────────────────────────────────────── */
if ($a === 'swaps_tarifs' && $m === 'GET') {
    $s = db()->query('SELECT * FROM swaps_tarifs ORDER BY montant');
    out(['ok' => true, 'data' => $s ? $s->fetchAll() : []]);
}

/* ── FRAIS LIVRAISON ADMIN ───────────────────────────────────── */
if ($a === 'frais_livraison' && $m === 'GET') {
    $s = db()->query('SELECT * FROM frais_livraison WHERE actif = 1 ORDER BY frais');
    out(['ok' => true, 'data' => $s ? $s->fetchAll() : []]);
}

if ($a === 'frais_livraison' && $m === 'POST') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $b = body();
    $zone = clean($b['zone'] ?? ''); $mode = clean($b['mode'] ?? 'Standard');
    $frais = (float)($b['frais'] ?? 0); $poids = $b['poids_max'] ? (float)$b['poids_max'] : null;
    $gratuit = $b['gratuit_des'] ? (float)$b['gratuit_des'] : null;
    if (!$zone || $frais < 0) out(['ok' => false, 'err' => 'Zone et frais obligatoires.'], 400);
    db()->prepare('INSERT INTO frais_livraison(zone,mode,frais,poids_max,gratuit_des) VALUES(?,?,?,?,?)')->execute([$zone,$mode,$frais,$poids,$gratuit]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'msg' => 'Zone ajoutée.'], 201);
}

if ($a === 'frais_livraison' && $m === 'DELETE') {
    $u = auth();
    if (!$u || $u['role'] !== 'admin') out(['ok' => false, 'err' => 'Accès refusé.'], 403);
    $fid = (int)($_GET['id'] ?? 0);
    db()->prepare('DELETE FROM frais_livraison WHERE id = ?')->execute([$fid]);
    out(['ok' => true, 'msg' => 'Supprimé.']);
}

out(['ok' => false, 'err' => 'Action inconnue.'], 404);
