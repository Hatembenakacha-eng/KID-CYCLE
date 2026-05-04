<?php
require __DIR__.'/config.php'; head();
$u=authRequired(); $m=$_SERVER['REQUEST_METHOD'];
if($m==='GET'){$s=db()->prepare('SELECT f.*,p.nom,p.prix,p.image,c.slug as categorie_slug,v.prix_solde FROM favoris f JOIN produits p ON f.produit_id=p.id LEFT JOIN categories c ON p.categorie_id=c.id LEFT JOIN ventes v ON v.produit_id=p.id AND v.actif=1 WHERE f.utilisateur_id=? ORDER BY f.created_at DESC');$s->execute([$u['id']]);out(['ok'=>true,'data'=>$s->fetchAll()]);}
if($m==='POST'){$b=body();$pid=(int)($b['produit_id']??0);if(!$pid)out(['ok'=>false,'err'=>'Produit invalide.'],400);$ex=db()->prepare('SELECT id FROM favoris WHERE utilisateur_id=? AND produit_id=?');$ex->execute([$u['id'],$pid]);if($ex->fetch()){db()->prepare('DELETE FROM favoris WHERE utilisateur_id=? AND produit_id=?')->execute([$u['id'],$pid]);out(['ok'=>true,'action'=>'removed','msg'=>'Retiré des favoris.']);}db()->prepare('INSERT INTO favoris(utilisateur_id,produit_id)VALUES(?,?)')->execute([$u['id'],$pid]);out(['ok'=>true,'action'=>'added','msg'=>'Ajouté aux favoris.']);}
if($m==='DELETE'){$pid=(int)($_GET['produit_id']??0);db()->prepare('DELETE FROM favoris WHERE utilisateur_id=? AND produit_id=?')->execute([$u['id'],$pid]);out(['ok'=>true,'msg'=>'Retiré.']);}
out(['ok'=>false,'err'=>'Méthode non supportée.'],405);
