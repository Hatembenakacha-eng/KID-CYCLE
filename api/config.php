<?php
define('DB_HOST','localhost');
define('DB_NAME','kidcycle');
define('DB_USER','root');
define('DB_PASS','');
define('DB_CHARSET','utf8mb4');
define('SITE_URL','http://localhost/kidcycle');
define('UPLOAD_DIR',__DIR__.'/../uploads/');
define('UPLOAD_URL',SITE_URL.'/uploads/');
define('JWT_SECRET','kidcycle_2025_!@#$%^&*_secret');

function db():PDO{
  static $p=null;
  if(!$p){
    try{$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
    catch(PDOException $e){http_response_code(500);die(json_encode(['ok'=>false,'err'=>'DB error']));}
  }
  return $p;
}
function head():void{
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type,Authorization');
  if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);exit;}
}
function out(array $d,int $c=200):never{http_response_code($c);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function body():array{$r=file_get_contents('php://input');return json_decode($r,true)??[];}
function clean(string $s):string{return htmlspecialchars(strip_tags(trim($s)),ENT_QUOTES,'UTF-8');}
function makeToken(int $id):string{
  $h=base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
  $p=base64_encode(json_encode(['sub'=>$id,'exp'=>time()+86400*30,'iat'=>time()]));
  $s=base64_encode(hash_hmac('sha256',"$h.$p",JWT_SECRET,true));
  return "$h.$p.$s";
}
function auth():?array{
  $hdr=$_SERVER['HTTP_AUTHORIZATION']??'';
  $tok=trim(str_replace('Bearer','',$hdr));
  if(!$tok)return null;
  try{
    $pts=explode('.',$tok);if(count($pts)!==3)return null;
    $pl=json_decode(base64_decode($pts[1]),true);
    if(!$pl||$pl['exp']<time())return null;
    $s=db()->prepare('SELECT * FROM utilisateurs WHERE id=? AND actif=1');
    $s->execute([$pl['sub']]);$u=$s->fetch();
    if($u)unset($u['motdepasse']);
    return $u?:null;
  }catch(Exception){return null;}
}
function authRequired():array{$u=auth();if(!$u)out(['ok'=>false,'err'=>'Authentification requise.'],401);return $u;}
