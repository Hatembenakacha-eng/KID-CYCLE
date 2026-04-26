<?php
/* ============================================================
   favoris.php — Gestion des favoris (liste de souhaits)
   Voir, ajouter, retirer des produits des favoris
   ============================================================ */
require __DIR__ . '/config.php';
head();

$utilisateur = authRequired();
$methode     = $_SERVER['REQUEST_METHOD'];


/* ============================================================
   VOIR LES FAVORIS — GET /api/favoris.php
   Retourne tous les produits mis en favoris
   ============================================================ */
if ($methode === 'GET') {

    $req = db()->prepare('
        SELECT f.*, p.nom, p.prix, p.image,
               c.slug AS categorie_slug,
               v.prix_solde
        FROM favoris f
        JOIN produits p ON f.produit_id = p.id
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN ventes v ON v.produit_id = p.id AND v.actif = 1
        WHERE f.utilisateur_id = ?
        ORDER BY f.created_at DESC
    ');
    $req->execute([$utilisateur['id']]);

    out(['ok' => true, 'data' => $req->fetchAll()]);
}


/* ============================================================
   AJOUTER / RETIRER UN FAVORI — POST /api/favoris.php
   Si le produit est déjà en favori → on le retire (toggle)
   Sinon → on l'ajoute
   ============================================================ */
if ($methode === 'POST') {

    $donnees   = body();
    $produitId = (int)($donnees['produit_id'] ?? 0);

    if (!$produitId) {
        out(['ok' => false, 'err' => 'Produit invalide.'], 400);
    }

    /* Vérifier si ce produit est déjà en favori */
    $verif = db()->prepare('SELECT id FROM favoris WHERE utilisateur_id = ? AND produit_id = ?');
    $verif->execute([$utilisateur['id'], $produitId]);

    if ($verif->fetch()) {
        /* Déjà en favori → on retire */
        db()->prepare('DELETE FROM favoris WHERE utilisateur_id = ? AND produit_id = ?')
            ->execute([$utilisateur['id'], $produitId]);
        out(['ok' => true, 'action' => 'removed', 'msg' => 'Retiré des favoris.']);
    }

    /* Pas encore en favori → on ajoute */
    db()->prepare('INSERT INTO favoris (utilisateur_id, produit_id) VALUES (?, ?)')
        ->execute([$utilisateur['id'], $produitId]);

    out(['ok' => true, 'action' => 'added', 'msg' => 'Ajouté aux favoris.']);
}


/* ============================================================
   RETIRER UN FAVORI — DELETE /api/favoris.php?produit_id=5
   ============================================================ */
if ($methode === 'DELETE') {
    $produitId = (int)($_GET['produit_id'] ?? 0);
    db()->prepare('DELETE FROM favoris WHERE utilisateur_id = ? AND produit_id = ?')
        ->execute([$utilisateur['id'], $produitId]);
    out(['ok' => true, 'msg' => 'Retiré des favoris.']);
}


/* Si aucune route ne correspond */
out(['ok' => false, 'err' => 'Méthode non supportée.'], 405);
