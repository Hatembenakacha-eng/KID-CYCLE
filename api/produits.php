<?php
require __DIR__.'/config.php'; head();
$m=$_SERVER['REQUEST_METHOD'];$id=(int)($_GET['id']??0);$a=$_GET['action']??'';
if($m==='GET'&&!$id&&$a!=='vendeur'){
  $w=["p.statut='actif'"];$p=[];
  if($a==='vente'){$w[]="v.actif=1";}
  if(!empty($_GET['categorie'])){$cats=array_filter(array_map('clean',explode(',',clean($_GET['categorie']??''))));if($cats){$ph=implode(',',array_fill(0,count($cats),'?'));$w[]="c.slug IN($ph)";$p=array_merge($p,array_values($cats));}}
  if(!empty($_GET['etat'])){$es=array_filter(array_map('clean',explode(',',clean($_GET['etat']??''))));if($es){$ph=implode(',',array_fill(0,count($es),'?'));$w[]="p.etat IN($ph)";$p=array_merge($p,array_values($es));}}
  if(!empty($_GET['prix_max'])){$w[]='p.prix<=?';$p[]=(float)$_GET['prix_max'];}
  if(!empty($_GET['q'])){$q='%'.clean($_GET['q']).'%';$w[]='(p.nom LIKE ? OR p.description LIKE ?)';$p[]=$q;$p[]=$q;}
  if(!empty($_GET['statut'])){$w[]='p.statut=?';$p[]=clean($_GET['statut']);}
  $tri='p.created_at DESC';
  switch($_GET['tri']??'recent'){case'prix_asc':$tri='p.prix ASC';break;case'prix_desc':$tri='p.prix DESC';break;case'nom':$tri='p.nom ASC';break;case'popular':$tri='p.vues DESC';break;}
  $pg=max(1,(int)($_GET['page']??1));$lim=max(1,min(50,(int)($_GET['limit']??9)));$off=($pg-1)*$lim;
  $jn=$a==='vente'?'JOIN ventes v ON v.produit_id=p.id':'LEFT JOIN ventes v ON v.produit_id=p.id AND v.actif=1';
  $wh='WHERE '.implode(' AND ',$w);
  $sql='SELECT p.*,c.slug as categorie_slug,c.nom as categorie_nom,v.prix_solde,v.reduction,u.nom as vendeur_nom,u.prenom as vendeur_prenom FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id '.$jn.' LEFT JOIN utilisateurs u ON p.vendeur_id=u.id '.$wh.' ORDER BY '.$tri.' LIMIT '.$lim.' OFFSET '.$off;
  $s=db()->prepare($sql);$s->execute($p);$prods=$s->fetchAll();
  $cs=db()->prepare('SELECT COUNT(*) FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id '.$jn.' '.$wh);$cs->execute($p);$tot=(int)$cs->fetchColumn();
  out(['ok'=>true,'data'=>$prods,'total'=>$tot,'page'=>$pg,'pages'=>(int)ceil($tot/$lim),'limit'=>$lim]);
}
if($m==='GET'&&$id){
  $s=db()->prepare('SELECT p.*,c.slug as categorie_slug,c.nom as categorie_nom,v.prix_solde,v.reduction,u.nom as vendeur_nom,u.prenom as vendeur_prenom,u.avatar as vendeur_avatar FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id LEFT JOIN ventes v ON v.produit_id=p.id AND v.actif=1 LEFT JOIN utilisateurs u ON p.vendeur_id=u.id WHERE p.id=? AND p.statut=\'actif\'');
  $s->execute([$id]);$prod=$s->fetch();
  if(!$prod)out(['ok'=>false,'err'=>'Produit introuvable.'],404);
  db()->prepare('UPDATE produits SET vues=vues+1 WHERE id=?')->execute([$id]);
  $sim=db()->prepare('SELECT p.*,v.prix_solde,v.reduction FROM produits p LEFT JOIN ventes v ON v.produit_id=p.id AND v.actif=1 WHERE p.categorie_id=? AND p.id!=? AND p.statut=\'actif\' ORDER BY RAND() LIMIT 5');
  $sim->execute([$prod['categorie_id'],$id]);$prod['similaires']=$sim->fetchAll();
  out(['ok'=>true,'data'=>$prod]);
}
if($m==='GET'&&$a==='vendeur'){$u=authRequired();$s=db()->prepare('SELECT p.*,c.slug as categorie_slug,c.nom as categorie_nom FROM produits p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.vendeur_id=? ORDER BY p.created_at DESC');$s->execute([$u['id']]);out(['ok'=>true,'data'=>$s->fetchAll()]);}
if($m==='POST'){
  $u=authRequired();
  $ct=$_SERVER['CONTENT_TYPE']??'';
  if(str_contains($ct,'multipart')){$nom=clean($_POST['nom']??'');$desc=clean($_POST['description']??'');$prix=(float)($_POST['prix']??0);$cat=clean($_POST['categorie']??'');$etat=clean($_POST['etat']??'neuf');$genre=clean($_POST['genre']??'');$taille=clean($_POST['taille']??'');}
  else{$b=body();$nom=clean($b['nom']??'');$desc=clean($b['description']??'');$prix=(float)($b['prix']??0);$cat=clean($b['categorie']??'');$etat=clean($b['etat']??'neuf');$genre=clean($b['genre']??'');$taille=clean($b['taille']??'');}
  if(!$nom||$prix<=0)out(['ok'=>false,'err'=>'Nom et prix obligatoires.'],400);
  $cs=db()->prepare('SELECT id FROM categories WHERE slug=?');$cs->execute([$cat]);$cid=$cs->fetchColumn()?:null;
  $imgs=[];$mainImg=null;
  if(isset($_FILES['images'])){
    if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0755,true);
    $files=$_FILES['images'];$count=is_array($files['name'])?count($files['name']):1;
    for($i=0;$i<min($count,7);$i++){$tn=is_array($files['tmp_name'])?$files['tmp_name'][$i]:$files['tmp_name'];$fn=is_array($files['name'])?$files['name'][$i]:$files['name'];if(!$tn||!is_uploaded_file($tn))continue;$ext=pathinfo($fn,PATHINFO_EXTENSION);$fname='prod_'.time().'_'.$i.'.'.$ext;if(move_uploaded_file($tn,UPLOAD_DIR.$fname)){$imgs[]=UPLOAD_URL.$fname;if(!$mainImg)$mainImg=UPLOAD_URL.$fname;}}
  }
  if(!$mainImg)$mainImg='images/cl1.png';
  db()->prepare('INSERT INTO produits(vendeur_id,categorie_id,nom,description,prix,image,images,etat,genre,taille,statut)VALUES(?,?,?,?,?,?,?,?,?,?,\'attente\')')->execute([$u['id'],$cid,$nom,$desc,$prix,$mainImg,json_encode($imgs),$etat,$genre,$taille]);
  $pid=(int)db()->lastInsertId();
  out(['ok'=>true,'id'=>$pid,'msg'=>'Produit publié ! En attente de validation.'],201);
}
if($m==='PUT'&&$id){
  $u=authRequired();$chk=db()->prepare('SELECT id FROM produits WHERE id=? AND vendeur_id=?');$chk->execute([$id,$u['id']]);
  if(!$chk->fetch())out(['ok'=>false,'err'=>'Non autorisé.'],403);
  $b=body();$nom=clean($b['nom']??'');$desc=clean($b['description']??'');$prix=(float)($b['prix']??0);$cat=clean($b['categorie']??'');$etat=clean($b['etat']??'neuf');
  $cs=db()->prepare('SELECT id FROM categories WHERE slug=?');$cs->execute([$cat]);$cid=$cs->fetchColumn()?:null;
  db()->prepare('UPDATE produits SET nom=?,description=?,prix=?,categorie_id=?,etat=?,updated_at=NOW() WHERE id=?')->execute([$nom,$desc,$prix,$cid,$etat,$id]);
  out(['ok'=>true,'msg'=>'Produit mis à jour.']);
}
if($m==='DELETE'&&$id){$u=authRequired();$chk=db()->prepare('SELECT id FROM produits WHERE id=? AND vendeur_id=?');$chk->execute([$id,$u['id']]);if(!$chk->fetch())out(['ok'=>false,'err'=>'Non autorisé.'],403);db()->prepare('DELETE FROM produits WHERE id=?')->execute([$id]);out(['ok'=>true,'msg'=>'Produit supprimé.']);}
out(['ok'=>false,'err'=>'Route introuvable.'],404);
