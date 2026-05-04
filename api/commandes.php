<?php
require __DIR__ . '/config.php';
head();

$user = authRequired();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int) ($_GET['id'] ?? 0);

if ($method === 'GET' && !$id) {
    $stmt = db()->prepare(
        'SELECT c.*, COUNT(ca.id) AS nb_articles
         FROM commandes c
         LEFT JOIN commande_articles ca ON ca.commande_id = c.id
         WHERE c.utilisateur_id = ?
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $commandes = $stmt->fetchAll();

    foreach ($commandes as &$commande) {
        $items = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
        $items->execute([$commande['id']]);
        $commande['articles'] = $items->fetchAll();
    }

    out(['ok' => true, 'data' => $commandes]);
}

if ($method === 'GET' && $id) {
    $stmt = db()->prepare('SELECT * FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $user['id']]);
    $commande = $stmt->fetch();

    if (!$commande) {
        out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    }

    $items = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
    $items->execute([$id]);
    $commande['articles'] = $items->fetchAll();

    out(['ok' => true, 'data' => $commande]);
}

if ($method === 'POST') {
    $data = body();
    $adresse = clean($data['adresse'] ?? '');
    $ville = clean($data['ville'] ?? '');
    $codePostal = clean($data['code_postal'] ?? '');
    $pays = clean($data['pays'] ?? 'Tunisie');
    $tel = clean($data['tel'] ?? '');
    $modeLivraison = clean($data['mode_livraison'] ?? 'standard');
    $fraisLivraison = (float) ($data['frais_livraison'] ?? 5.90);
    $modePaiement = clean($data['mode_paiement'] ?? 'carte');
    $codePromo = strtoupper(trim($data['code_promo'] ?? ''));

    if (!$adresse) {
        out(['ok' => false, 'err' => 'L\'adresse de livraison est obligatoire.'], 400);
    }

    $stmt = db()->prepare(
        'SELECT pa.*, p.nom, p.image
         FROM panier pa
         JOIN produits p ON pa.produit_id = p.id
         WHERE pa.utilisateur_id = ?'
    );
    $stmt->execute([$user['id']]);
    $panier = $stmt->fetchAll();

    if (!$panier) {
        out(['ok' => false, 'err' => 'Votre panier est vide.'], 400);
    }

    $sousTotal = 0;
    foreach ($panier as $item) {
        $sousTotal += (float) $item['prix'] * (int) $item['quantite'];
    }

    $reduction = 0;
    if ($codePromo) {
        $promoStmt = db()->prepare(
            'SELECT * FROM codes_promo WHERE code = ? AND actif = 1 AND (expiration IS NULL OR expiration >= CURDATE())'
        );
        $promoStmt->execute([$codePromo]);
        $promo = $promoStmt->fetch();

        if ($promo) {
            if ($promo['type'] === 'pourcentage') {
                $reduction = round($sousTotal * $promo['valeur'] / 100, 2);
            } elseif ($promo['type'] === 'montant') {
                $reduction = min((float) $promo['valeur'], $sousTotal);
            } elseif ($promo['type'] === 'livraison') {
                $fraisLivraison = 0;
            }

            db()->prepare('UPDATE codes_promo SET utilisations = utilisations + 1 WHERE id = ?')->execute([$promo['id']]);
        }
    }

    $total = max(0, $sousTotal - $reduction) + $fraisLivraison;
    $numero = 'KC' . date('Ymd') . strtoupper(substr(uniqid(), -6));

    db()->beginTransaction();
    try {
        db()->prepare(
            'INSERT INTO commandes
             (utilisateur_id, numero, adresse, ville, code_postal, pays, tel, mode_livraison, frais_livraison, sous_total, total, mode_paiement, code_promo, reduction)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $user['id'], $numero, $adresse, $ville, $codePostal, $pays, $tel, $modeLivraison,
            $fraisLivraison, $sousTotal, $total, $modePaiement, $codePromo ?: null, $reduction
        ]);

        $commandeId = (int) db()->lastInsertId();

        foreach ($panier as $item) {
            db()->prepare(
                'INSERT INTO commande_articles(commande_id, produit_id, nom, image, prix, quantite, taille)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([
                $commandeId,
                $item['produit_id'],
                $item['nom'],
                $item['image'],
                $item['prix'],
                $item['quantite'],
                $item['taille']
            ]);
        }

        db()->prepare('DELETE FROM panier WHERE utilisateur_id = ?')->execute([$user['id']]);
        db()->commit();

        out(['ok' => true, 'commande_id' => $commandeId, 'numero' => $numero, 'total' => $total], 201);
    } catch (Throwable $e) {
        db()->rollBack();
        out(['ok' => false, 'err' => 'Erreur création commande: ' . $e->getMessage()], 500);
    }
}

if ($method === 'PUT' && $id) {
    $data = body();
    $statut = clean($data['statut'] ?? '');

    if (!in_array($statut, ['annulee', 'livree'], true)) {
        out(['ok' => false, 'err' => 'Statut non autorisé.'], 403);
    }

    $check = db()->prepare('SELECT statut FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $check->execute([$id, $user['id']]);
    $commande = $check->fetch();

    if (!$commande) {
        out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    }

    if ($statut === 'annulee' && !in_array($commande['statut'], ['en_attente', 'preparation'], true)) {
        out(['ok' => false, 'err' => 'Impossible d\'annuler cette commande.'], 400);
    }

    db()->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$statut, $id]);
    out(['ok' => true, 'msg' => 'Commande mise à jour.']);
}

out(['ok' => false, 'err' => 'Route introuvable.'], 404);
