<?php
/* ============================================================
   commandes.php — Gestion des commandes clients
   Voir, créer, annuler des commandes
   ============================================================ */
require __DIR__ . '/config.php';
head();

/* authRequired() — arrête le script si pas connecté */
$utilisateur = authRequired();
$methode     = $_SERVER['REQUEST_METHOD'];
$id          = (int)($_GET['id'] ?? 0);


/* ============================================================
   LISTE DES COMMANDES — GET /api/commandes.php
   Retourne toutes les commandes de l'utilisateur connecté
   ============================================================ */
if ($methode === 'GET' && !$id) {

    /* COUNT — compte le nombre d'articles par commande */
    /* GROUP BY — regroupe les lignes par commande */
    $req = db()->prepare('
        SELECT c.*, COUNT(ca.id) AS nb_articles
        FROM commandes c
        LEFT JOIN commande_articles ca ON ca.commande_id = c.id
        WHERE c.utilisateur_id = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ');
    $req->execute([$utilisateur['id']]);
    $commandes = $req->fetchAll();

    /* Pour chaque commande, ajouter la liste des articles */
    /* foreach — boucle sur chaque élément du tableau */
    /* & = référence : permet de modifier $commande directement */
    foreach ($commandes as &$commande) {
        $reqArticles = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
        $reqArticles->execute([$commande['id']]);
        $commande['articles'] = $reqArticles->fetchAll();
    }

    out(['ok' => true, 'data' => $commandes]);
}


/* ============================================================
   DÉTAIL D'UNE COMMANDE — GET /api/commandes.php?id=5
   ============================================================ */
if ($methode === 'GET' && $id) {

    $req = db()->prepare('SELECT * FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $req->execute([$id, $utilisateur['id']]);
    $commande = $req->fetch();

    if (!$commande) {
        out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    }

    $reqArticles = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
    $reqArticles->execute([$id]);
    $commande['articles'] = $reqArticles->fetchAll();

    out(['ok' => true, 'data' => $commande]);
}


/* ============================================================
   CRÉER UNE COMMANDE — POST /api/commandes.php
   Transforme le panier en commande définitive
   ============================================================ */
if ($methode === 'POST') {

    $donnees   = body();
    $adresse   = clean($donnees['adresse']          ?? '');
    $ville     = clean($donnees['ville']            ?? '');
    $cp        = clean($donnees['code_postal']      ?? '');
    $pays      = clean($donnees['pays']             ?? 'Tunisie');
    $tel       = clean($donnees['tel']              ?? '');
    $modeLiv   = clean($donnees['mode_livraison']   ?? 'standard');
    $frais     = (float)($donnees['frais_livraison'] ?? 5.90);
    $modePay   = clean($donnees['mode_paiement']    ?? 'carte');
    /* strtoupper — met en majuscules (ex: "kc10" → "KC10") */
    $codePromo = strtoupper(trim($donnees['code_promo'] ?? ''));

    if (!$adresse) {
        out(['ok' => false, 'err' => 'L\'adresse de livraison est obligatoire.'], 400);
    }

    /* Lire le panier depuis la base de données */
    $reqPanier = db()->prepare('
        SELECT pa.*, p.nom, p.image
        FROM panier pa
        JOIN produits p ON pa.produit_id = p.id
        WHERE pa.utilisateur_id = ?
    ');
    $reqPanier->execute([$utilisateur['id']]);
    $panier = $reqPanier->fetchAll();

    if (!$panier) {
        out(['ok' => false, 'err' => 'Votre panier est vide.'], 400);
    }

    /* Calculer le sous-total (prix × quantité pour chaque article) */
    $sousTotal = 0;
    foreach ($panier as $article) {
        $sousTotal += $article['prix'] * $article['quantite'];
    }

    /* Vérifier le code promo si fourni */
    $reduction = 0;
    if ($codePromo) {
        /* CURDATE() — date d'aujourd'hui en MySQL */
        $reqPromo = db()->prepare('
            SELECT * FROM codes_promo
            WHERE code = ? AND actif = 1
            AND (expiration IS NULL OR expiration >= CURDATE())
        ');
        $reqPromo->execute([$codePromo]);
        $promo = $reqPromo->fetch();

        if ($promo) {
            if ($promo['type'] === 'pourcentage') {
                /* round — arrondit à 2 décimales */
                $reduction = round($sousTotal * $promo['valeur'] / 100, 2);
            } elseif ($promo['type'] === 'montant') {
                /* min — prend le plus petit des deux (réduction max = sous-total) */
                $reduction = min($promo['valeur'], $sousTotal);
            } elseif ($promo['type'] === 'livraison') {
                $frais = 0;
            }
            /* Incrémenter le compteur d'utilisations du code promo */
            db()->prepare('UPDATE codes_promo SET utilisations = utilisations + 1 WHERE id = ?')
                ->execute([$promo['id']]);
        }
    }

    /* max(0, ...) — le total ne peut pas être négatif */
    $total = max(0, $sousTotal - $reduction) + $frais;

    /* Générer un numéro de commande unique */
    /* date('Ymd') = date du jour (ex: 20250426), uniqid() = identifiant unique */
    $numero = 'KC' . date('Ymd') . strtoupper(substr(uniqid(), -6));

    /* beginTransaction — démarre une transaction : toutes les requêtes réussissent ou toutes échouent */
    db()->beginTransaction();
    try {

        /* Créer la commande */
        db()->prepare('
            INSERT INTO commandes
                (utilisateur_id, numero, adresse, ville, code_postal, pays, tel,
                 mode_livraison, frais_livraison, sous_total, total, mode_paiement, code_promo, reduction)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $utilisateur['id'], $numero, $adresse, $ville, $cp, $pays, $tel,
            $modeLiv, $frais, $sousTotal, $total, $modePay, $codePromo ?: null, $reduction
        ]);

        $commandeId = (int)db()->lastInsertId();

        /* Ajouter chaque article à la commande */
        foreach ($panier as $article) {
            db()->prepare('
                INSERT INTO commande_articles
                    (commande_id, produit_id, nom, image, prix, quantite, taille)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $commandeId, $article['produit_id'], $article['nom'], $article['image'],
                $article['prix'], $article['quantite'], $article['taille']
            ]);
        }

        /* Vider le panier après la commande */
        db()->prepare('DELETE FROM panier WHERE utilisateur_id = ?')->execute([$utilisateur['id']]);

        /* commit() — valide toutes les requêtes de la transaction */
        db()->commit();

        out(['ok' => true, 'commande_id' => $commandeId, 'numero' => $numero, 'total' => $total], 201);

    } catch (Exception $e) {
        /* rollBack() — annule toutes les requêtes si une erreur survient */
        db()->rollBack();
        out(['ok' => false, 'err' => 'Erreur lors de la création de la commande.'], 500);
    }
}


/* ============================================================
   MODIFIER STATUT — PUT /api/commandes.php?id=5
   Le client peut seulement annuler ou confirmer réception
   ============================================================ */
if ($methode === 'PUT' && $id) {

    $donnees = body();
    $statut  = clean($donnees['statut'] ?? '');

    /* in_array — vérifie si la valeur est dans le tableau */
    if (!in_array($statut, ['annulee', 'livree'])) {
        out(['ok' => false, 'err' => 'Action non autorisée.'], 403);
    }

    $verif = db()->prepare('SELECT statut FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $verif->execute([$id, $utilisateur['id']]);
    $commande = $verif->fetch();

    if (!$commande) {
        out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    }

    /* On ne peut annuler que si la commande est en attente ou en préparation */
    if ($statut === 'annulee' && !in_array($commande['statut'], ['en_attente', 'preparation'])) {
        out(['ok' => false, 'err' => 'Cette commande ne peut plus être annulée.'], 400);
    }

    db()->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$statut, $id]);

    out(['ok' => true, 'msg' => 'Commande mise à jour.']);
}


/* Si aucune route ne correspond */
out(['ok' => false, 'err' => 'Route introuvable.'], 404);
