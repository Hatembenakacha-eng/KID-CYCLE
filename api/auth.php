<?php
require __DIR__.'/config.php'; head();
$m=$_SERVER['REQUEST_METHOD']; $a=$_GET['action']??'';
if($m==='POST'&&$a==='login'){
  $b=body();$em=strtolower(trim($b['email']??''));$pw=$b['password']??'';
  if(!$em||!$pw)out(['ok'=>false,'err'=>'Email et mot de passe requis.'],400);
  if(!filter_var($em,FILTER_VALIDATE_EMAIL))out(['ok'=>false,'err'=>'Email invalide.'],400);
  $s=db()->prepare('SELECT * FROM utilisateurs WHERE email=? AND actif=1');$s->execute([$em]);$u=$s->fetch();
  if(!$u)out(['ok'=>false,'err'=>'Aucun compte trouvé avec cet email.'],401);
  if(!password_verify($pw,$u['motdepasse']))out(['ok'=>false,'err'=>'Mot de passe incorrect.'],401);
  $tok=makeToken($u['id']);unset($u['motdepasse']);
  out(['ok'=>true,'token'=>$tok,'user'=>$u]);
}
if($m==='POST'&&$a==='register'){
  $b=body();$nom=clean($b['nom']??'');$prenom=clean($b['prenom']??'');$em=strtolower(trim($b['email']??''));$pw=$b['password']??'';
  $genre=clean($b['genre']??'');$tel=clean($b['tel']??'');$pays=clean($b['pays']??'Tunisie');$adresse=clean($b['adresse']??'');$cp=clean($b['code_postal']??'');$ville=clean($b['ville']??'');$nl=(bool)($b['newsletter']??false);
  if(!$nom||!$prenom||!$em||!$pw)out(['ok'=>false,'err'=>'Champs obligatoires manquants.'],400);
  if(!filter_var($em,FILTER_VALIDATE_EMAIL))out(['ok'=>false,'err'=>'Email invalide.'],400);
  if(strlen($pw)<6)out(['ok'=>false,'err'=>'Mot de passe: 6 caractères minimum.'],400);
  $chk=db()->prepare('SELECT id FROM utilisateurs WHERE email=?');$chk->execute([$em]);
  if($chk->fetch())out(['ok'=>false,'err'=>'Un compte existe déjà avec cet email.'],409);
  $hash=password_hash($pw,PASSWORD_BCRYPT,['cost'=>12]);
  db()->prepare('INSERT INTO utilisateurs(nom,prenom,email,motdepasse,genre,tel,pays,adresse,code_postal,ville,newsletter)VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$nom,$prenom,$em,$hash,$genre,$tel,$pays,$adresse,$cp,$ville,$nl?1:0]);
  $id=(int)db()->lastInsertId();
  if($nl){try{db()->prepare('INSERT IGNORE INTO newsletter(email)VALUES(?)')->execute([$em]);}catch(Exception){}}
  $tok=makeToken($id);
  $u=['id'=>$id,'nom'=>$nom,'prenom'=>$prenom,'email'=>$em,'genre'=>$genre,'tel'=>$tel,'pays'=>$pays,'adresse'=>$adresse,'code_postal'=>$cp,'ville'=>$ville,'avatar'=>null,'swaps'=>'500.00','role'=>'client','abonnement'=>'Gratuit'];
  out(['ok'=>true,'token'=>$tok,'user'=>$u],201);
}
if($m==='GET'&&$a==='me'){$u=authRequired();out(['ok'=>true,'user'=>$u]);}
if($m==='PUT'&&$a==='update'){
  $u=authRequired();$b=body();
  $nom=clean($b['nom']??$u['nom']);$prenom=clean($b['prenom']??$u['prenom']);$em=strtolower(trim($b['email']??$u['email']));
  $tel=clean($b['tel']??'');$pays=clean($b['pays']??'Tunisie');$adresse=clean($b['adresse']??'');$cp=clean($b['code_postal']??'');$ville=clean($b['ville']??'');
  if(!filter_var($em,FILTER_VALIDATE_EMAIL))out(['ok'=>false,'err'=>'Email invalide.'],400);
  if($em!==$u['email']){$chk=db()->prepare('SELECT id FROM utilisateurs WHERE email=? AND id!=?');$chk->execute([$em,$u['id']]);if($chk->fetch())out(['ok'=>false,'err'=>'Email déjà utilisé.'],409);}
  if(!empty($b['password'])){
    if(strlen($b['password'])<6)out(['ok'=>false,'err'=>'Mot de passe trop court.'],400);
    $hash=password_hash($b['password'],PASSWORD_BCRYPT);
    db()->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,tel=?,pays=?,adresse=?,code_postal=?,ville=?,motdepasse=? WHERE id=?')->execute([$nom,$prenom,$em,$tel,$pays,$adresse,$cp,$ville,$hash,$u['id']]);
  } else {
    db()->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,tel=?,pays=?,adresse=?,code_postal=?,ville=? WHERE id=?')->execute([$nom,$prenom,$em,$tel,$pays,$adresse,$cp,$ville,$u['id']]);
  }
  if(!empty($b['avatar'])) db()->prepare('UPDATE utilisateurs SET avatar=? WHERE id=?')->execute([clean($b['avatar']),$u['id']]);
  out(['ok'=>true,'msg'=>'Profil mis à jour.']);
}
if($m==='DELETE'&&$a==='delete'){$u=authRequired();db()->prepare('DELETE FROM utilisateurs WHERE id=?')->execute([$u['id']]);out(['ok'=>true,'msg'=>'Compte supprimé.']);}
if($m==='POST'&&$a==='avatar'){
  $u=authRequired();
  if(!isset($_FILES['avatar']))out(['ok'=>false,'err'=>'Aucun fichier.'],400);
  $f=$_FILES['avatar'];if($f['size']>3*1024*1024)out(['ok'=>false,'err'=>'Fichier trop volumineux (max 3Mo).'],400);
  if(!in_array($f['type'],['image/jpeg','image/png','image/webp']))out(['ok'=>false,'err'=>'Format non supporté.'],400);
  if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0755,true);
  $ext=pathinfo($f['name'],PATHINFO_EXTENSION);$fname='avatar_'.$u['id'].'_'.time().'.'.$ext;
  if(!move_uploaded_file($f['tmp_name'],UPLOAD_DIR.$fname))out(['ok'=>false,'err'=>'Erreur upload.'],500);
  $url=UPLOAD_URL.$fname;
  db()->prepare('UPDATE utilisateurs SET avatar=? WHERE id=?')->execute([$url,$u['id']]);
  out(['ok'=>true,'url'=>$url]);
}
out(['ok'=>false,'err'=>'Action non reconnue.'],404);
