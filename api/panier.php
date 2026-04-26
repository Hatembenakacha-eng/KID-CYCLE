<?php
/* ============================================================
   panier.php — Gestion du panier d'achat
   Voir, ajouter, modifier, supprimer des articles du panier
   ============================================================ */
require __DIR__ . '/config.php';
head();

$utilisateur = authRequired();
$methode     = $_SERVER['REQUEST_METHOD'];


/* ============================================================
   VOIR LE PANIER — GET /api/panier.php
   Retourne tous les articles du panier avec les infos produits
   ============================================================ */
if ($methode === 'GET') {

    /* JOIN — fusionne la table panier avec la table produits */
    $req = db()->prepare('
        SELECT pa.*, p.nom, p.image, p.statut
        FROM panier pa
        JOIN produits p ON pa.produit_id = p.id
        WHERE pa.utilisateur_id = ?
        ORDER BY pa.created_at DESC
    ');
    $req->execute([$utilisateur['id']]);
    $articles = $req->fetchAll();

    /* Calculer le total du panier */
    $total = 0;
    foreach ($articles as $article) {
        $total += $article['prix'] * $article['quantite'];
    }

    /* round — arrondit à 2 décimales (ex: 12.549 → 12.55) */
    out([
        'ok'    => true,
        'data'  => $articles,
        'total' => round($total, 2),
        'count' => count($articles)
    ]);
}


/* ============================================================
   AJOUTER AU PANIER — POST /api/panier.php
   Ajoute un produit ou augmente sa quantité si déjà dans le panier
   ============================================================ */
if ($methode === 'POST') {

    $donnees   = body();
    $produitId = (int)($donnees['produit_id'] ?? 0);
    /* max(1, ...) — la quantité minimum est 1 */
    $quantite  = max(1, (int)($donnees['quantite'] ?? 1));
    $taille    = clean($donnees['taille']  ?? 'M');
    $couleur   = clean($donnees['couleur'] ?? 'Standard');

    if (!$produitId) {
        out(['ok' => false, 'err' => 'Produit invalide.'], 400);
    }

    /* Vérifier que le produit existe et est actif */
    $reqProd = db()->prepare('SELECT prix FROM produits WHERE id = ? AND statut = "actif"');
    $reqProd->execute([$produitId]);
    $prix = $reqProd->fetchColumn();

    if ($prix === false) {
        out(['ok' => false, 'err' => 'Produit introuvable ou indisponible.'], 404);
    }

    /* Appliquer le prix de vente s'il existe */
    $reqVente = db()->prepare('SELECT prix_solde FROM ventes WHERE produit_id = ? AND actif = 1');
    $reqVente->execute([$produitId]);
    $prixSolde = $reqVente->fetchColumn();
    if ($prixSolde) $prix = $prixSolde;

    /* Vérifier si le produit est déjà dans le panier (même taille) */
    $verif = db()->prepare('SELECT id, quantite FROM panier WHERE utilisateur_id = ? AND produit_id = ? AND taille = ?');
    $verif->execute([$utilisateur['id'], $produitId, $taille]);
    $existant = $verif->fetch();

    if ($existant) {
        /* Augmenter la quantité si déjà dans le panier */
        db()->prepare('UPDATE panier SET quantite = ? WHERE id = ?')
            ->execute([$existant['quantite'] + $quantite, $existant['id']]);
    } else {
        /* Ajouter un nouvel article */
        db()->prepare('INSERT INTO panier (utilisateur_id, produit_id, prix, quantite, taille, couleur) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$utilisateur['id'], $produitId, $prix, $quantite, $taille, $couleur]);
    }

    out(['ok' => true, 'msg' => 'Ajouté au panier.']);
}


/* ============================================================
   MODIFIER QUANTITÉ — PUT /api/panier.php
   Change la quantité d'un article existant dans le panier
   ============================================================ */
if ($methode === 'PUT') {

    $donnees  = body();
    $articleId = (int)($donnees['id']       ?? 0);
    $quantite  = max(1, (int)($donnees['quantite'] ?? 1));

    db()->prepare('UPDATE panier SET quantite = ? WHERE id = ? AND utilisateur_id = ?')
        ->execute([$quantite, $articleId, $utilisateur['id']]);

    out(['ok' => true, 'msg' => 'Quantité mise à jour.']);
}


/* ============================================================
   SUPPRIMER DU PANIER — DELETE /api/panier.php
   Supprime un article (si ?id=X) ou vide tout le panier
   ============================================================ */
if ($methode === 'DELETE') {

    $articleId = (int)($_GET['id'] ?? 0);

    if ($articleId) {
        /* Supprimer un seul article */
        db()->prepare('DELETE FROM panier WHERE id = ? AND utilisateur_id = ?')
            ->execute([$articleId, $utilisateur['id']]);
        out(['ok' => true, 'msg' => 'Article supprimé du panier.']);
    }

    /* Vider tout le panier */
    db()->prepare('DELETE FROM panier WHERE utilisateur_id = ?')->execute([$utilisateur['id']]);
    out(['ok' => true, 'msg' => 'Panier vidé.']);
}


/* Si aucune route ne correspond */
out(['ok' => false, 'err' => 'Méthode non supportée.'], 405);
