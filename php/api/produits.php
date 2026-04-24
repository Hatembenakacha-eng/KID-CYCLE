<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — api/produits.php
   CRUD produits + recherche + favoris + panier
   ═══════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config.php';
startKcSession();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = cleanInt($_GET['id'] ?? 0);

$body = [];
$raw  = file_get_contents('php://input');
if ($raw) $body = json_decode($raw, true) ?? [];
$data = array_merge($_POST, $body);

match ($action) {
    'list'         => handleList(),
    'detail'       => handleDetail($id),
    'create'       => handleCreate($data),
    'update'       => handleUpdate($id, $data),
    'delete'       => handleDelete($id),
    'mes-produits' => handleMesProduits(),
    'search'       => handleSearch($_GET['q'] ?? ''),
    // Favoris
    'favs-list'    => handleFavsList(),
    'favs-toggle'  => handleFavsToggle($id),
    // Panier
    'panier-list'  => handlePanierList(),
    'panier-add'   => handlePanierAdd($data),
    'panier-remove'=> handlePanierRemove($id),
    'panier-clear' => handlePanierClear(),
    default        => jsonResponse(['error' => 'Action inconnue.'], 400),
};


/* ── LIST PRODUITS ────────────────────────────────────────── */
function handleList(): void {
    $db = getDB();

    $where   = ['p.statut = "actif"'];
    $params  = [];
    $orderBy = 'p.created_at DESC';

    // Filters
    if (!empty($_GET['categorie'])) {
        $cats   = array_filter(explode(',', $_GET['categorie']));
        $ph     = implode(',', array_fill(0, count($cats), '?'));
        $where[]  = "p.categorie IN ($ph)";
        $params   = array_merge($params, $cats);
    }
    if (!empty($_GET['genre'])) {
        $genres = array_filter(explode(',', $_GET['genre']));
        $ph     = implode(',', array_fill(0, count($genres), '?'));
        $where[]  = "p.genre IN ($ph)";
        $params   = array_merge($params, $genres);
    }
    if (!empty($_GET['taille'])) {
        $tailles = array_filter(explode(',', $_GET['taille']));
        $ph      = implode(',', array_fill(0, count($tailles), '?'));
        $where[]   = "p.taille IN ($ph)";
        $params    = array_merge($params, $tailles);
    }
    if (!empty($_GET['etat'])) {
        $etats  = array_filter(explode(',', $_GET['etat']));
        $ph     = implode(',', array_fill(0, count($etats), '?'));
        $where[]  = "p.etat IN ($ph)";
        $params   = array_merge($params, $etats);
    }
    if (!empty($_GET['prix_max'])) {
        $where[]  = 'p.prix_swaps <= ?';
        $params[] = cleanFloat($_GET['prix_max']);
    }
    if (!empty($_GET['badge'])) {
        $where[]  = 'p.badge IS NOT NULL';
    }

    $page    = max(1, cleanInt($_GET['page'] ?? 1));
    $perPage = max(1, min(50, cleanInt($_GET['per_page'] ?? 6)));
    $offset  = ($page - 1) * $perPage;

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM produits p $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT p.*, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom
         FROM produits p
         JOIN utilisateurs u ON u.id = p.vendeur_id
         $whereSQL
         ORDER BY $orderBy
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    jsonResponse([
        'success'   => true,
        'data'      => $items,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'pages'     => (int) ceil($total / $perPage),
    ]);
}


/* ── DETAIL PRODUIT ───────────────────────────────────────── */
function handleDetail(int $id): void {
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db = getDB();

    // Increment views
    $db->prepare('UPDATE produits SET vues = vues + 1 WHERE id = ?')->execute([$id]);

    $stmt = $db->prepare(
        'SELECT p.*, u.nom AS vendeur_nom, u.prenom AS vendeur_prenom, u.avatar_url AS vendeur_avatar, u.swaps AS vendeur_swaps
         FROM produits p
         JOIN utilisateurs u ON u.id = p.vendeur_id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $produit = $stmt->fetch();

    if (!$produit) jsonResponse(['error' => 'Produit introuvable.'], 404);

    // Get images
    $imgs = $db->prepare('SELECT url FROM produit_images WHERE produit_id = ? ORDER BY ordre');
    $imgs->execute([$id]);
    $produit['images'] = $imgs->fetchAll(PDO::FETCH_COLUMN);
    if (empty($produit['images'])) $produit['images'] = [$produit['image_url']];

    jsonResponse(['success' => true, 'data' => $produit]);
}


/* ── CREATE PRODUIT ───────────────────────────────────────── */
function handleCreate(array $d): void {
    requireLogin();

    $titre  = trim($d['titre'] ?? '');
    $desc   = trim($d['description'] ?? '');
    $cat    = $d['categorie']   ?? 'bebe';
    $genre  = $d['genre']       ?? 'unisexe';
    $taille = $d['taille']      ?? '';
    $etat   = $d['etat']        ?? 'bon';
    $prix   = cleanFloat($d['prix_swaps'] ?? 0);
    $badge  = trim($d['badge']  ?? '');

    if (!$titre || !$taille || $prix <= 0) {
        jsonResponse(['error' => 'Titre, taille et prix sont obligatoires.'], 422);
    }

    $db = getDB();

    // Handle image upload
    $imageUrl = 'cl1.png';
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../uploads/produits/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename  = 'prod_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
            $imageUrl  = 'uploads/produits/' . $filename;
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO produits (vendeur_id, titre, description, categorie, genre, taille, etat, prix_swaps, badge, image_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['user_id'],
        clean($titre), clean($desc),
        $cat, $genre, clean($taille), $etat,
        $prix,
        $badge ? clean($badge) : null,
        $imageUrl,
    ]);
    $newId = (int) $db->lastInsertId();

    jsonResponse(['success' => true, 'message' => 'Produit ajouté !', 'id' => $newId], 201);
}


/* ── UPDATE PRODUIT ───────────────────────────────────────── */
function handleUpdate(int $id, array $d): void {
    requireLogin();
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT vendeur_id FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();

    if (!$row) jsonResponse(['error' => 'Produit introuvable.'], 404);
    if ($row['vendeur_id'] != $_SESSION['user_id'] && !isAdmin()) {
        jsonResponse(['error' => 'Non autorisé.'], 403);
    }

    $fields = [];
    $params = [];

    $map = ['titre'=>'titre','description'=>'description','categorie'=>'categorie',
            'genre'=>'genre','taille'=>'taille','etat'=>'etat',
            'prix_swaps'=>'prix_swaps','badge'=>'badge','statut'=>'statut'];

    foreach ($map as $key => $col) {
        if (isset($d[$key])) {
            $fields[] = "$col = ?";
            $params[] = in_array($col, ['prix_swaps']) ? cleanFloat($d[$key]) : clean($d[$key]);
        }
    }

    if (!$fields) jsonResponse(['error' => 'Aucune donnée à mettre à jour.'], 422);

    $params[] = $id;
    $db->prepare('UPDATE produits SET ' . implode(', ', $fields) . ', updated_at=NOW() WHERE id = ?')
       ->execute($params);

    jsonResponse(['success' => true, 'message' => 'Produit mis à jour !']);
}


/* ── DELETE PRODUIT ───────────────────────────────────────── */
function handleDelete(int $id): void {
    requireLogin();
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT vendeur_id FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();

    if (!$row) jsonResponse(['error' => 'Produit introuvable.'], 404);
    if ($row['vendeur_id'] != $_SESSION['user_id'] && !isAdmin()) {
        jsonResponse(['error' => 'Non autorisé.'], 403);
    }

    $db->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Produit supprimé.']);
}


/* ── MES PRODUITS ────────────────────────────────────────── */
function handleMesProduits(): void {
    requireLogin();

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM produits WHERE vendeur_id = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}


/* ── SEARCH ──────────────────────────────────────────────── */
function handleSearch(string $q): void {
    $q = trim($q);
    if (strlen($q) < 2) jsonResponse(['success' => true, 'data' => []]);

    $db   = getDB();
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        'SELECT id, titre, description, prix_swaps, image_url, categorie
         FROM produits
         WHERE statut = "actif" AND (titre LIKE ? OR description LIKE ?)
         ORDER BY titre LIMIT 12'
    );
    $stmt->execute([$like, $like]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}


/* ══════════════════════════════════════════════════════════
   FAVORIS
═══════════════════════════════════════════════════════════ */

function handleFavsList(): void {
    requireLogin();
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.id, p.titre, p.prix_swaps, p.image_url
         FROM favoris f
         JOIN produits p ON p.id = f.produit_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleFavsToggle(int $produitId): void {
    requireLogin();
    if (!$produitId) jsonResponse(['error' => 'ID requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM favoris WHERE user_id = ? AND produit_id = ?');
    $stmt->execute([$_SESSION['user_id'], $produitId]);

    if ($stmt->fetch()) {
        $db->prepare('DELETE FROM favoris WHERE user_id = ? AND produit_id = ?')
           ->execute([$_SESSION['user_id'], $produitId]);
        jsonResponse(['success' => true, 'action' => 'removed', 'message' => 'Retiré des favoris.']);
    } else {
        $db->prepare('INSERT INTO favoris (user_id, produit_id) VALUES (?, ?)')
           ->execute([$_SESSION['user_id'], $produitId]);
        jsonResponse(['success' => true, 'action' => 'added', 'message' => 'Ajouté aux favoris !']);
    }
}


/* ══════════════════════════════════════════════════════════
   PANIER
═══════════════════════════════════════════════════════════ */

function handlePanierList(): void {
    requireLogin();
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT pa.id, pa.quantite, pa.taille, p.id AS produit_id, p.titre, p.prix_swaps, p.image_url, p.statut
         FROM panier pa
         JOIN produits p ON p.id = pa.produit_id
         WHERE pa.user_id = ?
         ORDER BY pa.added_at DESC'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll();

    $total = array_sum(array_map(fn($i) => $i['prix_swaps'] * $i['quantite'], $items));
    jsonResponse(['success' => true, 'data' => $items, 'total' => $total, 'count' => count($items)]);
}

function handlePanierAdd(array $d): void {
    requireLogin();
    $produitId = cleanInt($d['produit_id'] ?? 0);
    $taille    = clean($d['taille'] ?? '');
    $qte       = max(1, cleanInt($d['quantite'] ?? 1));

    if (!$produitId) jsonResponse(['error' => 'ID produit requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, statut FROM produits WHERE id = ?');
    $stmt->execute([$produitId]);
    $prod = $stmt->fetch();
    if (!$prod || $prod['statut'] !== 'actif') {
        jsonResponse(['error' => 'Produit indisponible.'], 422);
    }

    // Check if already in cart
    $stmt = $db->prepare('SELECT id, quantite FROM panier WHERE user_id = ? AND produit_id = ?');
    $stmt->execute([$_SESSION['user_id'], $produitId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare('UPDATE panier SET quantite = quantite + ? WHERE id = ?')
           ->execute([$qte, $existing['id']]);
    } else {
        $db->prepare('INSERT INTO panier (user_id, produit_id, quantite, taille) VALUES (?, ?, ?, ?)')
           ->execute([$_SESSION['user_id'], $produitId, $qte, $taille]);
    }

    jsonResponse(['success' => true, 'message' => 'Ajouté au panier !']);
}

function handlePanierRemove(int $id): void {
    requireLogin();
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db = getDB();
    $db->prepare('DELETE FROM panier WHERE id = ? AND user_id = ?')
       ->execute([$id, $_SESSION['user_id']]);

    jsonResponse(['success' => true, 'message' => 'Article retiré du panier.']);
}

function handlePanierClear(): void {
    requireLogin();
    $db = getDB();
    $db->prepare('DELETE FROM panier WHERE user_id = ?')->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'message' => 'Panier vidé.']);
}
