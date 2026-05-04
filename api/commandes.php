<?php
require __DIR__.'/config.php';
head();
$u = authRequired();
$m = $_SERVER['REQUEST_METHOD'];
$id = (int)($_GET['id'] ?? 0);

/* ── GET liste commandes ───────────────────────────────────── */
if ($m === 'GET' && !$id) {
    $s = db()->prepare(
        'SELECT c.*, COUNT(ca.id) as nb_articles
         FROM commandes c
         LEFT JOIN commande_articles ca ON ca.commande_id = c.id
         WHERE c.utilisateur_id = ?
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
    $s->execute([$u['id']]);
    $cmds = $s->fetchAll();
    foreach ($cmds as &$cmd) {
        $a = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
        $a->execute([$cmd['id']]);
        $cmd['articles'] = $a->fetchAll();
    }
    out(['ok' => true, 'data' => $cmds]);
}

/* ── GET détail commande ───────────────────────────────────── */
if ($m === 'GET' && $id) {
    $s = db()->prepare('SELECT * FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $s->execute([$id, $u['id']]);
    $cmd = $s->fetch();
    if (!$cmd) out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    $a = db()->prepare('SELECT * FROM commande_articles WHERE commande_id = ?');
    $a->execute([$id]);
    $cmd['articles'] = $a->fetchAll();
    out(['ok' => true, 'data' => $cmd]);
}

/* ── POST créer commande ───────────────────────────────────── */
if ($m === 'POST') {
    $b = body();
    $adresse    = clean($b['adresse']    ?? '');
    $ville      = clean($b['ville']      ?? '');
    $cp         = clean($b['code_postal']?? '');
    $pays       = clean($b['pays']       ?? 'Tunisie');
    $tel        = clean($b['tel']        ?? '');
    $modeLiv    = clean($b['mode_livraison'] ?? 'standard');
    $frais      = (float)($b['frais_livraison'] ?? 5.90);
    $modePay    = clean($b['mode_paiement']  ?? 'carte');
    $codePromo  = strtoupper(trim($b['code_promo'] ?? ''));

    if (!$adresse) out(['ok' => false, 'err' => 'L\'adresse de livraison est obligatoire.'], 400);

    // Vérifier panier
    $ps = db()->prepare(
        'SELECT pa.*, p.nom, p.image
         FROM panier pa
         JOIN produits p ON pa.produit_id = p.id
         WHERE pa.utilisateur_id = ?'
    );
    $ps->execute([$u['id']]);
    $panier = $ps->fetchAll();
    if (!$panier) out(['ok' => false, 'err' => 'Votre panier est vide.'], 400);

    $sousTotal = array_sum(array_map(fn($i) => $i['prix'] * $i['quantite'], $panier));

    // Code promo
    $reduction = 0;
    if ($codePromo) {
        $promo = db()->prepare(
            'SELECT * FROM codes_promo WHERE code = ? AND actif = 1
             AND (expiration IS NULL OR expiration >= CURDATE())'
        );
        $promo->execute([$codePromo]);
        $promo = $promo->fetch();
        if ($promo) {
            if ($promo['type'] === 'pourcentage') $reduction = round($sousTotal * $promo['valeur'] / 100, 2);
            elseif ($promo['type'] === 'montant')  $reduction = min($promo['valeur'], $sousTotal);
            elseif ($promo['type'] === 'livraison') $frais = 0;
            db()->prepare('UPDATE codes_promo SET utilisations = utilisations + 1 WHERE id = ?')->execute([$promo['id']]);
        }
    }

    $total = max(0, $sousTotal - $reduction) + $frais;
    $num   = 'KC' . date('Ymd') . strtoupper(substr(uniqid(), -6));

    db()->beginTransaction();
    try {
        db()->prepare(
            'INSERT INTO commandes
             (utilisateur_id,numero,adresse,ville,code_postal,pays,tel,
              mode_livraison,frais_livraison,sous_total,total,mode_paiement,code_promo,reduction)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $u['id'],$num,$adresse,$ville,$cp,$pays,$tel,
            $modeLiv,$frais,$sousTotal,$total,$modePay,$codePromo?:null,$reduction
        ]);
        $cid = (int)db()->lastInsertId();

        foreach ($panier as $item) {
            db()->prepare(
                'INSERT INTO commande_articles(commande_id,produit_id,nom,image,prix,quantite,taille)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([
                $cid,$item['produit_id'],$item['nom'],$item['image'],
                $item['prix'],$item['quantite'],$item['taille']
            ]);
        }
        // Vider panier
        db()->prepare('DELETE FROM panier WHERE utilisateur_id = ?')->execute([$u['id']]);
        db()->commit();
        out(['ok' => true, 'commande_id' => $cid, 'numero' => $num, 'total' => $total], 201);
    } catch (Exception $e) {
        db()->rollBack();
        out(['ok' => false, 'err' => 'Erreur création commande: ' . $e->getMessage()], 500);
    }
}

/* ── PUT mettre à jour statut (côté client : annuler/confirmer réception) */
if ($m === 'PUT' && $id) {
    $b = body();
    $statut = clean($b['statut'] ?? '');
    if (!in_array($statut, ['annulee', 'livree'])) out(['ok' => false, 'err' => 'Statut non autorisé.'], 403);
    $chk = db()->prepare('SELECT statut FROM commandes WHERE id = ? AND utilisateur_id = ?');
    $chk->execute([$id, $u['id']]);
    $cmd = $chk->fetch();
    if (!$cmd) out(['ok' => false, 'err' => 'Commande introuvable.'], 404);
    if ($statut === 'annulee' && !in_array($cmd['statut'], ['en_attente', 'preparation']))
        out(['ok' => false, 'err' => 'Impossible d\'annuler cette commande.'], 400);
    db()->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$statut, $id]);
    out(['ok' => true, 'msg' => 'Commande mise à jour.']);
}

out(['ok' => false, 'err' => 'Route introuvable.'], 404);
