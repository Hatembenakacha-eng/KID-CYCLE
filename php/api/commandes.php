<?php
/* ═══════════════════════════════════════════════════════════
   KidCycle — api/commandes.php
   Gestion des commandes : créer, lister, détail, statut
   ═══════════════════════════════════════════════════════════ */

require_once __DIR__ . '/../config.php';
startKcSession();

$action = $_GET['action'] ?? '';
$id     = cleanInt($_GET['id'] ?? 0);

$body = [];
$raw  = file_get_contents('php://input');
if ($raw) $body = json_decode($raw, true) ?? [];
$data = array_merge($_POST, $body);

match ($action) {
    'create'       => handleCreate($data),
    'list'         => handleList(),
    'detail'       => handleDetail($id),
    'update-statut'=> handleUpdateStatut($id, $data),
    // Admin
    'admin-list'   => handleAdminList(),
    'admin-stats'  => handleAdminStats(),
    default        => jsonResponse(['error' => 'Action inconnue.'], 400),
};


/* ── CREATE COMMANDE ──────────────────────────────────────── */
function handleCreate(array $d): void {
    requireLogin();

    $db = getDB();

    // Get panier items
    $stmt = $db->prepare(
        'SELECT pa.id AS panier_id, pa.quantite, pa.taille, p.id AS produit_id, p.titre, p.prix_swaps, p.image_url, p.statut
         FROM panier pa
         JOIN produits p ON p.id = pa.produit_id
         WHERE pa.user_id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        jsonResponse(['error' => 'Votre panier est vide.'], 422);
    }

    // Check all products are still active
    foreach ($items as $item) {
        if ($item['statut'] !== 'actif') {
            jsonResponse(['error' => 'Le produit "' . $item['titre'] . '" n\'est plus disponible.'], 422);
        }
    }

    $totalSwaps = array_sum(array_map(fn($i) => $i['prix_swaps'] * $i['quantite'], $items));

    // Check user has enough swaps
    $stmt = $db->prepare('SELECT swaps FROM utilisateurs WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $userSwaps = (float) $stmt->fetchColumn();

    if ($userSwaps < $totalSwaps) {
        jsonResponse(['error' => 'SWAPS insuffisants. Vous avez ' . $userSwaps . ' SWAPS, besoin de ' . $totalSwaps . '.'], 422);
    }

    $adresse  = clean($d['adresse'] ?? '');
    $tel      = clean($d['telephone'] ?? '');
    $mode     = clean($d['mode_expedition'] ?? 'standard');
    $fraisLivr = (float) ($d['frais_livraison'] ?? 5.90);

    // Generate reference
    $ref = '#P-' . str_pad(rand(1000000, 9999999), 9, '0', STR_PAD_LEFT);

    // Commission (5%)
    $commission  = round($totalSwaps * 0.05, 2);
    $totalEuros  = round($totalSwaps * 0.20, 2); // 1 SWAP = 0.20€

    $db->beginTransaction();
    try {
        // Insert commande
        $stmt = $db->prepare(
            'INSERT INTO commandes (reference, acheteur_id, adresse_livr, telephone, mode_expedition, frais_livr, total_swaps, total_euros, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "en_attente")'
        );
        $stmt->execute([$ref, $_SESSION['user_id'], $adresse, $tel, $mode, $fraisLivr, $totalSwaps, $totalEuros]);
        $cmdId = (int) $db->lastInsertId();

        // Insert lignes
        $stmtLigne = $db->prepare(
            'INSERT INTO commande_lignes (commande_id, produit_id, titre_snap, image_snap, taille, quantite, prix_swaps)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmtLigne->execute([
                $cmdId,
                $item['produit_id'],
                $item['titre'],
                $item['image_url'],
                $item['taille'],
                $item['quantite'],
                $item['prix_swaps'],
            ]);
            // Mark product as sold
            $db->prepare('UPDATE produits SET statut = "vendu" WHERE id = ?')
               ->execute([$item['produit_id']]);
        }

        // Deduct swaps from buyer
        $db->prepare('UPDATE utilisateurs SET swaps = swaps - ? WHERE id = ?')
           ->execute([$totalSwaps + $commission, $_SESSION['user_id']]);

        // Log SWAPS transaction
        $db->prepare(
            'INSERT INTO swaps_transactions (user_id, type, montant, description, commande_id)
             VALUES (?, "achat", ?, ?, ?)'
        )->execute([$_SESSION['user_id'], $totalSwaps, 'Commande ' . $ref, $cmdId]);

        // Credit sellers
        foreach ($items as $item) {
            // Get vendeur_id
            $sv = $db->prepare('SELECT vendeur_id FROM produits WHERE id = ?');
            $sv->execute([$item['produit_id']]);
            $vendeurId = (int) $sv->fetchColumn();
            if ($vendeurId) {
                $vendeurSwaps = $item['prix_swaps'] * $item['quantite'] * 0.85; // 15% commission
                $db->prepare('UPDATE utilisateurs SET swaps = swaps + ? WHERE id = ?')
                   ->execute([$vendeurSwaps, $vendeurId]);
            }
        }

        // Clear cart
        $db->prepare('DELETE FROM panier WHERE user_id = ?')
           ->execute([$_SESSION['user_id']]);

        $db->commit();

        jsonResponse([
            'success'    => true,
            'message'    => 'Commande passée avec succès !',
            'commande_id'=> $cmdId,
            'reference'  => $ref,
            'total_swaps'=> $totalSwaps,
            'redirect'   => '../Commandes.html',
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Erreur lors de la commande: ' . $e->getMessage()], 500);
    }
}


/* ── LIST MES COMMANDES ───────────────────────────────────── */
function handleList(): void {
    requireLogin();

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT c.*, COUNT(cl.id) AS nb_articles
         FROM commandes c
         LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
         WHERE c.acheteur_id = ?
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$_SESSION['user_id']]);
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}


/* ── DETAIL COMMANDE ──────────────────────────────────────── */
function handleDetail(int $id): void {
    requireLogin();
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM commandes WHERE id = ? AND acheteur_id = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    $commande = $stmt->fetch();

    if (!$commande) jsonResponse(['error' => 'Commande introuvable.'], 404);

    $stmt = $db->prepare('SELECT * FROM commande_lignes WHERE commande_id = ?');
    $stmt->execute([$id]);
    $commande['lignes'] = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $commande]);
}


/* ── UPDATE STATUT (admin) ────────────────────────────────── */
function handleUpdateStatut(int $id, array $d): void {
    requireAdmin();
    if (!$id) jsonResponse(['error' => 'ID requis.'], 422);

    $statuts  = ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'];
    $newStatut = $d['statut'] ?? '';

    if (!in_array($newStatut, $statuts)) {
        jsonResponse(['error' => 'Statut invalide.'], 422);
    }

    $db = getDB();
    $db->prepare('UPDATE commandes SET statut = ?, updated_at = NOW() WHERE id = ?')
       ->execute([$newStatut, $id]);

    // Log
    $db->prepare(
        'INSERT INTO admin_logs (admin_id, action, cible_type, cible_id, detail, ip)
         VALUES (?, "update_statut_commande", "commande", ?, ?, ?)'
    )->execute([$_SESSION['user_id'], $id, "Nouveau statut: $newStatut", $_SERVER['REMOTE_ADDR']]);

    jsonResponse(['success' => true, 'message' => 'Statut mis à jour.']);
}


/* ── ADMIN LIST ALL ORDERS ────────────────────────────────── */
function handleAdminList(): void {
    requireAdmin();

    $db = getDB();

    $statut = $_GET['statut'] ?? '';
    $where  = $statut ? 'WHERE c.statut = ?' : '';
    $params = $statut ? [$statut] : [];

    $page    = max(1, cleanInt($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $total = (int) $db->prepare("SELECT COUNT(*) FROM commandes c $where")->execute($params) ? 0 : 0;
    $stmtC = $db->prepare("SELECT COUNT(*) FROM commandes c $where");
    $stmtC->execute($params);
    $total = (int) $stmtC->fetchColumn();

    $stmt = $db->prepare(
        "SELECT c.*, u.nom AS acheteur_nom, u.email AS acheteur_email, COUNT(cl.id) AS nb_articles
         FROM commandes c
         JOIN utilisateurs u ON u.id = c.acheteur_id
         LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
         $where
         GROUP BY c.id
         ORDER BY c.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    jsonResponse([
        'success'  => true,
        'data'     => $stmt->fetchAll(),
        'total'    => $total,
        'page'     => $page,
        'pages'    => (int) ceil($total / $perPage),
    ]);
}


/* ── ADMIN STATS ─────────────────────────────────────────── */
function handleAdminStats(): void {
    requireAdmin();

    $db = getDB();

    $stats = [];

    // Total commandes
    $stats['total_commandes']   = (int) $db->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
    $stats['commandes_pending'] = (int) $db->query('SELECT COUNT(*) FROM commandes WHERE statut="en_attente"')->fetchColumn();
    $stats['commandes_livrees'] = (int) $db->query('SELECT COUNT(*) FROM commandes WHERE statut="livree"')->fetchColumn();

    // Total users
    $stats['total_users']    = (int) $db->query('SELECT COUNT(*) FROM utilisateurs WHERE role="user"')->fetchColumn();
    $stats['total_produits'] = (int) $db->query('SELECT COUNT(*) FROM produits WHERE statut="actif"')->fetchColumn();
    $stats['total_vendus']   = (int) $db->query('SELECT COUNT(*) FROM produits WHERE statut="vendu"')->fetchColumn();

    // Revenue en SWAPS
    $stats['total_swaps']  = (float) $db->query('SELECT COALESCE(SUM(total_swaps),0) FROM commandes WHERE statut != "annulee"')->fetchColumn();
    $stats['total_euros']  = (float) $db->query('SELECT COALESCE(SUM(total_euros),0) FROM commandes WHERE statut != "annulee"')->fetchColumn();

    // Last 7 days commandes
    $stmt = $db->query(
        'SELECT DATE(created_at) AS jour, COUNT(*) AS nb, SUM(total_swaps) AS swaps
         FROM commandes
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY jour ORDER BY jour'
    );
    $stats['last7days'] = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $stats]);
}
