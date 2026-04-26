<?php
/* ============================================================
   produits.php — Gestion des produits KidCycle
   Voir, ajouter, modifier, supprimer des produits
   ============================================================ */
require __DIR__ . '/config.php';
head();

$methode = $_SERVER['REQUEST_METHOD'];
$id      = (int)($_GET['id']     ?? 0);
$action  = $_GET['action'] ?? '';


/* ============================================================
   LISTE DES PRODUITS — GET /api/produits.php
   Retourne la liste filtrée des produits
   Paramètres optionnels : categorie, tri, limit, page, q (recherche)
   ============================================================ */
if ($methode === 'GET' && !$id && $action !== 'vendeur') {

    /* Construire la requête SQL dynamiquement selon les filtres */
    $conditions = ["p.statut = 'actif'"];
    $parametres = [];

    /* Filtre : produits en vente (promotions) */
    if ($action === 'vente') {
        $conditions[] = "v.actif = 1";
    }

    /* Filtre : catégorie (ex: bebe, fille, garcon) */
    if (!empty($_GET['categorie'])) {
        $cats = array_filter(array_map('clean', explode(',', clean($_GET['categorie']))));
        if ($cats) {
            /* implode — joint un tableau en chaîne. array_fill — crée un tableau de '?' */
            $placeholders = implode(',', array_fill(0, count($cats), '?'));
            $conditions[] = "c.slug IN ($placeholders)";
            $parametres   = array_merge($parametres, array_values($cats));
        }
    }

    /* Filtre : état (neuf, bon_etat, usage) */
    if (!empty($_GET['etat'])) {
        $etats = array_filter(array_map('clean', explode(',', clean($_GET['etat']))));
        if ($etats) {
            $placeholders = implode(',', array_fill(0, count($etats), '?'));
            $conditions[] = "p.etat IN ($placeholders)";
            $parametres   = array_merge($parametres, array_values($etats));
        }
    }

    /* Filtre : prix maximum */
    if (!empty($_GET['prix_max'])) {
        $conditions[] = 'p.prix <= ?';
        $parametres[] = (float)$_GET['prix_max'];
    }

    /* Filtre : recherche par mot-clé */
    if (!empty($_GET['q'])) {
        $mot = '%' . clean($_GET['q']) . '%';
        $conditions[] = '(p.nom LIKE ? OR p.description LIKE ?)';
        $parametres[] = $mot;
        $parametres[] = $mot;
    }

    /* Tri */
    $tri = 'p.created_at DESC';
    switch ($_GET['tri'] ?? 'recent') {
        case 'prix_asc':  $tri = 'p.prix ASC';    break;
        case 'prix_desc': $tri = 'p.prix DESC';   break;
        case 'nom':       $tri = 'p.nom ASC';     break;
        case 'popular':   $tri = 'p.vues DESC';   break;
    }

    /* Pagination : page courante et nombre de produits par page */
    $page     = max(1, (int)($_GET['page']  ?? 1));
    $limite   = max(1, min(50, (int)($_GET['limit'] ?? 9)));
    $offset   = ($page - 1) * $limite;  /* offset = combien de produits à sauter */

    /* JOIN différent selon si on cherche les ventes ou pas */
    $joinVente = ($action === 'vente')
        ? 'JOIN ventes v ON v.produit_id = p.id'
        : 'LEFT JOIN ventes v ON v.produit_id = p.id AND v.actif = 1';

    $clauseWhere = 'WHERE ' . implode(' AND ', $conditions);

    /* Requête principale pour récupérer les produits */
    $sql = '
        SELECT p.*, c.slug AS categorie_slug, c.nom AS categorie_nom,
               v.prix_solde, v.reduction,
               u.nom AS vendeur_nom, u.prenom AS vendeur_prenom
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        ' . $joinVente . '
        LEFT JOIN utilisateurs u ON p.vendeur_id = u.id
        ' . $clauseWhere . '
        ORDER BY ' . $tri . '
        LIMIT ' . $limite . ' OFFSET ' . $offset;

    $req = db()->prepare($sql);
    $req->execute($parametres);
    $produits = $req->fetchAll();

    /* Compter le total pour la pagination */
    $reqTotal = db()->prepare('SELECT COUNT(*) FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id ' . $joinVente . ' ' . $clauseWhere);
    $reqTotal->execute($parametres);
    $total = (int)$reqTotal->fetchColumn();

    /* ceil — arrondit au supérieur. Ex: 11/9 = 1.2 → 2 pages */
    out([
        'ok'    => true,
        'data'  => $produits,
        'total' => $total,
        'page'  => $page,
        'pages' => (int)ceil($total / $limite),
        'limit' => $limite
    ]);
}


/* ============================================================
   DÉTAIL D'UN PRODUIT — GET /api/produits.php?id=5
   Retourne un produit complet + produits similaires
   ============================================================ */
if ($methode === 'GET' && $id) {

    $req = db()->prepare('
        SELECT p.*, c.slug AS categorie_slug, c.nom AS categorie_nom,
               v.prix_solde, v.reduction,
               u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.avatar AS vendeur_avatar
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN ventes v ON v.produit_id = p.id AND v.actif = 1
        LEFT JOIN utilisateurs u ON p.vendeur_id = u.id
        WHERE p.id = ? AND p.statut = "actif"
    ');
    $req->execute([$id]);
    $produit = $req->fetch();

    if (!$produit) {
        out(['ok' => false, 'err' => 'Produit introuvable.'], 404);
    }

    /* Incrémenter le compteur de vues */
    db()->prepare('UPDATE produits SET vues = vues + 1 WHERE id = ?')->execute([$id]);

    /* Récupérer des produits similaires (même catégorie) */
    /* RAND() — ordonne aléatoirement pour varier les suggestions */
    $reqSim = db()->prepare('
        SELECT p.*, v.prix_solde, v.reduction
        FROM produits p
        LEFT JOIN ventes v ON v.produit_id = p.id AND v.actif = 1
        WHERE p.categorie_id = ? AND p.id != ? AND p.statut = "actif"
        ORDER BY RAND()
        LIMIT 5
    ');
    $reqSim->execute([$produit['categorie_id'], $id]);
    $produit['similaires'] = $reqSim->fetchAll();

    out(['ok' => true, 'data' => $produit]);
}


/* ============================================================
   MES PRODUITS — GET /api/produits.php?action=vendeur
   Retourne les produits publiés par l'utilisateur connecté
   ============================================================ */
if ($methode === 'GET' && $action === 'vendeur') {
    $utilisateur = authRequired();
    $req = db()->prepare('
        SELECT p.*, c.slug AS categorie_slug, c.nom AS categorie_nom
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.vendeur_id = ?
        ORDER BY p.created_at DESC
    ');
    $req->execute([$utilisateur['id']]);
    out(['ok' => true, 'data' => $req->fetchAll()]);
}


/* ============================================================
   AJOUTER UN PRODUIT — POST /api/produits.php
   Crée un nouveau produit (en attente de validation admin)
   ============================================================ */
if ($methode === 'POST') {
    $utilisateur = authRequired();

    /* Vérifier si les données viennent d'un formulaire avec fichiers (multipart) */
    $typeContenu = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($typeContenu, 'multipart') !== false) {
        /* Données envoyées avec fichiers */
        $nom         = clean($_POST['nom']         ?? '');
        $description = clean($_POST['description'] ?? '');
        $prix        = (float)($_POST['prix']      ?? 0);
        $categorie   = clean($_POST['categorie']   ?? '');
        $etat        = clean($_POST['etat']        ?? 'neuf');
        $genre       = clean($_POST['genre']       ?? '');
        $taille      = clean($_POST['taille']      ?? '');
    } else {
        /* Données envoyées en JSON */
        $donnees     = body();
        $nom         = clean($donnees['nom']         ?? '');
        $description = clean($donnees['description'] ?? '');
        $prix        = (float)($donnees['prix']      ?? 0);
        $categorie   = clean($donnees['categorie']   ?? '');
        $etat        = clean($donnees['etat']        ?? 'neuf');
        $genre       = clean($donnees['genre']       ?? '');
        $taille      = clean($donnees['taille']      ?? '');
    }

    if (!$nom || $prix <= 0) {
        out(['ok' => false, 'err' => 'Le nom et le prix sont obligatoires.'], 400);
    }

    /* Chercher l'ID de la catégorie à partir de son slug (ex: 'bebe' → 3) */
    $reqCat = db()->prepare('SELECT id FROM categories WHERE slug = ?');
    $reqCat->execute([$categorie]);
    $categorieId = $reqCat->fetchColumn() ?: null;

    /* Gérer les images uploadées */
    $images   = [];
    $imageMain = null;

    if (isset($_FILES['images'])) {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        $fichiers = $_FILES['images'];
        /* Compter les fichiers (is_array car plusieurs fichiers possible) */
        $nombre   = is_array($fichiers['name']) ? count($fichiers['name']) : 1;

        for ($i = 0; $i < min($nombre, 7); $i++) {
            $tmp = is_array($fichiers['tmp_name']) ? $fichiers['tmp_name'][$i] : $fichiers['tmp_name'];
            $nom_f = is_array($fichiers['name'])   ? $fichiers['name'][$i]     : $fichiers['name'];

            if (!$tmp || !is_uploaded_file($tmp)) continue;

            $ext       = pathinfo($nom_f, PATHINFO_EXTENSION);
            $nomFichier = 'prod_' . time() . '_' . $i . '.' . $ext;

            if (move_uploaded_file($tmp, UPLOAD_DIR . $nomFichier)) {
                $images[] = UPLOAD_URL . $nomFichier;
                if (!$imageMain) $imageMain = UPLOAD_URL . $nomFichier;
            }
        }
    }

    /* Image par défaut si aucune image uploadée */
    if (!$imageMain) $imageMain = 'images/cl1.png';

    /* json_encode — convertit le tableau d'images en texte JSON pour le stocker en base */
    db()->prepare('
        INSERT INTO produits
            (vendeur_id, categorie_id, nom, description, prix, image, images, etat, genre, taille, statut)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "attente")
    ')->execute([$utilisateur['id'], $categorieId, $nom, $description, $prix, $imageMain, json_encode($images), $etat, $genre, $taille]);

    $produitId = (int)db()->lastInsertId();

    out(['ok' => true, 'id' => $produitId, 'msg' => 'Produit publié ! Il sera visible après validation.'], 201);
}


/* ============================================================
   MODIFIER UN PRODUIT — PUT /api/produits.php?id=5
   Seulement le propriétaire peut modifier son produit
   ============================================================ */
if ($methode === 'PUT' && $id) {
    $utilisateur = authRequired();

    /* Vérifier que ce produit appartient bien à cet utilisateur */
    $verif = db()->prepare('SELECT id FROM produits WHERE id = ? AND vendeur_id = ?');
    $verif->execute([$id, $utilisateur['id']]);
    if (!$verif->fetch()) {
        out(['ok' => false, 'err' => 'Vous n\'êtes pas autorisé à modifier ce produit.'], 403);
    }

    $donnees = body();
    $nom         = clean($donnees['nom']         ?? '');
    $description = clean($donnees['description'] ?? '');
    $prix        = (float)($donnees['prix']      ?? 0);
    $categorie   = clean($donnees['categorie']   ?? '');
    $etat        = clean($donnees['etat']        ?? 'neuf');

    $reqCat = db()->prepare('SELECT id FROM categories WHERE slug = ?');
    $reqCat->execute([$categorie]);
    $categorieId = $reqCat->fetchColumn() ?: null;

    /* NOW() — fonction MySQL qui retourne la date et heure actuelles */
    db()->prepare('
        UPDATE produits
        SET nom = ?, description = ?, prix = ?, categorie_id = ?, etat = ?, updated_at = NOW()
        WHERE id = ?
    ')->execute([$nom, $description, $prix, $categorieId, $etat, $id]);

    out(['ok' => true, 'msg' => 'Produit mis à jour.']);
}


/* ============================================================
   SUPPRIMER UN PRODUIT — DELETE /api/produits.php?id=5
   Seulement le propriétaire peut supprimer son produit
   ============================================================ */
if ($methode === 'DELETE' && $id) {
    $utilisateur = authRequired();

    $verif = db()->prepare('SELECT id FROM produits WHERE id = ? AND vendeur_id = ?');
    $verif->execute([$id, $utilisateur['id']]);
    if (!$verif->fetch()) {
        out(['ok' => false, 'err' => 'Vous n\'êtes pas autorisé à supprimer ce produit.'], 403);
    }

    db()->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);

    out(['ok' => true, 'msg' => 'Produit supprimé.']);
}


/* Si aucune route ne correspond */
out(['ok' => false, 'err' => 'Route introuvable.'], 404);
