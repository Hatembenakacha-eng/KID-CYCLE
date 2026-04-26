<?php
/**
 * KidCycle — install.php
 * Script d'installation automatique
 * Accès : http://localhost/kidcycle/install.php
 */

define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','kidcycle');

$errors = [];
$success = [];
$step = 0;

// ── Tentative de connexion ──────────────────────────────────
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $success[] = "✅ Connexion MySQL réussie";
    $step = 1;
} catch(PDOException $e) {
    $errors[] = "❌ Connexion MySQL échouée : ".$e->getMessage();
}

if($step >= 1) {
    // ── Créer la base de données ────────────────────────────
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `".DB_NAME."`");
        $success[] = "✅ Base de données '".DB_NAME."' créée";
        $step = 2;
    } catch(PDOException $e) {
        $errors[] = "❌ Création BDD échouée : ".$e->getMessage();
    }
}

if($step >= 2) {
    // ── Créer toutes les tables ─────────────────────────────
    $tables = [];

    $tables['utilisateurs'] = "CREATE TABLE IF NOT EXISTS utilisateurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        motdepasse VARCHAR(255) NOT NULL,
        genre VARCHAR(30) DEFAULT NULL,
        tel VARCHAR(30) DEFAULT NULL,
        pays VARCHAR(100) DEFAULT 'Tunisie',
        adresse TEXT DEFAULT NULL,
        code_postal VARCHAR(20) DEFAULT NULL,
        ville VARCHAR(100) DEFAULT NULL,
        avatar VARCHAR(500) DEFAULT NULL,
        swaps DECIMAL(10,2) DEFAULT 500.00,
        role ENUM('client','vendeur','admin') DEFAULT 'client',
        abonnement VARCHAR(50) DEFAULT 'Gratuit',
        newsletter TINYINT(1) DEFAULT 0,
        actif TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['categories'] = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(50) NOT NULL UNIQUE,
        nom VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['produits'] = "CREATE TABLE IF NOT EXISTS produits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendeur_id INT DEFAULT NULL,
        categorie_id INT DEFAULT NULL,
        nom VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        prix DECIMAL(10,2) NOT NULL,
        images JSON DEFAULT NULL,
        image VARCHAR(500) DEFAULT NULL,
        etat ENUM('neuf','excellent','bon','correct') DEFAULT 'neuf',
        genre VARCHAR(50) DEFAULT NULL,
        taille VARCHAR(50) DEFAULT NULL,
        badge VARCHAR(50) DEFAULT NULL,
        statut ENUM('actif','attente','archive','refuse') DEFAULT 'attente',
        vues INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(vendeur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
        FOREIGN KEY(categorie_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['ventes'] = "CREATE TABLE IF NOT EXISTS ventes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produit_id INT NOT NULL UNIQUE,
        prix_solde DECIMAL(10,2) NOT NULL,
        reduction VARCHAR(20) DEFAULT NULL,
        debut DATETIME DEFAULT CURRENT_TIMESTAMP,
        fin DATETIME DEFAULT NULL,
        actif TINYINT(1) DEFAULT 1,
        FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['panier'] = "CREATE TABLE IF NOT EXISTS panier (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT NOT NULL,
        produit_id INT NOT NULL,
        prix DECIMAL(10,2) NOT NULL,
        quantite INT DEFAULT 1,
        taille VARCHAR(30) DEFAULT 'M',
        couleur VARCHAR(50) DEFAULT 'Standard',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_item (utilisateur_id,produit_id,taille),
        FOREIGN KEY(utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['favoris'] = "CREATE TABLE IF NOT EXISTS favoris (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT NOT NULL,
        produit_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fav (utilisateur_id,produit_id),
        FOREIGN KEY(utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['commandes'] = "CREATE TABLE IF NOT EXISTS commandes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        utilisateur_id INT DEFAULT NULL,
        numero VARCHAR(30) NOT NULL UNIQUE,
        statut ENUM('en_attente','preparation','en_cours','livree','annulee') DEFAULT 'en_attente',
        adresse TEXT NOT NULL,
        ville VARCHAR(100) DEFAULT NULL,
        code_postal VARCHAR(20) DEFAULT NULL,
        pays VARCHAR(100) DEFAULT 'Tunisie',
        tel VARCHAR(30) DEFAULT NULL,
        mode_livraison VARCHAR(80) DEFAULT 'standard',
        frais_livraison DECIMAL(10,2) DEFAULT 5.90,
        sous_total DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        mode_paiement VARCHAR(50) DEFAULT 'carte',
        code_promo VARCHAR(50) DEFAULT NULL,
        reduction DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['commande_articles'] = "CREATE TABLE IF NOT EXISTS commande_articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        commande_id INT NOT NULL,
        produit_id INT DEFAULT NULL,
        nom VARCHAR(255) NOT NULL,
        image VARCHAR(500) DEFAULT NULL,
        prix DECIMAL(10,2) NOT NULL,
        quantite INT DEFAULT 1,
        taille VARCHAR(30) DEFAULT NULL,
        FOREIGN KEY(commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
        FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['newsletter'] = "CREATE TABLE IF NOT EXISTS newsletter (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['codes_promo'] = "CREATE TABLE IF NOT EXISTS codes_promo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        type ENUM('pourcentage','montant','livraison') DEFAULT 'pourcentage',
        valeur DECIMAL(10,2) NOT NULL,
        utilisations INT DEFAULT 0,
        expiration DATE DEFAULT NULL,
        actif TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['ref_genres'] = "CREATE TABLE IF NOT EXISTS ref_genres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(80) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['ref_categories'] = "CREATE TABLE IF NOT EXISTS ref_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['ref_marques'] = "CREATE TABLE IF NOT EXISTS ref_marques (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['ref_tailles'] = "CREATE TABLE IF NOT EXISTS ref_tailles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(50) NOT NULL UNIQUE,
        ordre INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['frais_livraison'] = "CREATE TABLE IF NOT EXISTS frais_livraison (
        id INT AUTO_INCREMENT PRIMARY KEY,
        zone VARCHAR(100) NOT NULL,
        mode VARCHAR(80) DEFAULT 'Standard',
        frais DECIMAL(10,2) NOT NULL,
        poids_max DECIMAL(10,2) DEFAULT NULL,
        gratuit_des DECIMAL(10,2) DEFAULT NULL,
        actif TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            $success[] = "✅ Table '$name' créée";
        } catch(PDOException $e) {
            $errors[] = "⚠️ Table '$name' : ".$e->getMessage();
        }
    }
    $step = 3;
}

if($step >= 3) {
    // ── Insérer données initiales ───────────────────────────

    // Admin
    try {
        $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12]);
        $pdo->prepare("INSERT IGNORE INTO utilisateurs (nom,prenom,email,motdepasse,role,actif,swaps,abonnement) VALUES (?,?,?,?,?,?,?,?)")
            ->execute(['Admin','KidCycle','admin@kidcycle.com',$hash,'admin',1,9999.00,'Pro']);
        $success[] = "✅ Compte admin créé (admin@kidcycle.com / admin123)";
    } catch(PDOException $e) { $errors[] = "⚠️ Admin : ".$e->getMessage(); }

    // Catégories
    $cats = [['bebe','Bébé (0-2 ans)'],['fille','Fille (2-8 ans)'],['garcon','Garçon (2-8 ans)'],['junior','Junior (8-14 ans)']];
    foreach($cats as $c) {
        try { $pdo->prepare("INSERT IGNORE INTO categories (slug,nom) VALUES (?,?)")->execute($c); } catch(PDOException $e){}
    }
    $success[] = "✅ Catégories insérées (bébé, fille, garçon, junior)";

    // Codes promo
    $promos = [['KIDCYCLE20','pourcentage',20],['WELCOME10','montant',10],['FREESHIP','livraison',0]];
    foreach($promos as $p) {
        try { $pdo->prepare("INSERT IGNORE INTO codes_promo (code,type,valeur,actif) VALUES (?,?,?,1)")->execute($p); } catch(PDOException $e){}
    }
    $success[] = "✅ Codes promo créés (KIDCYCLE20, WELCOME10, FREESHIP)";

    // Refs genres
    $genres = ['Fille','Garçon','Unisexe','Bébé','Homme','Femme'];
    foreach($genres as $g) {
        try { $pdo->prepare("INSERT IGNORE INTO ref_genres (nom) VALUES (?)")->execute([$g]); } catch(PDOException $e){}
    }
    $success[] = "✅ Genres de référence insérés";

    // Refs catégories
    $refcats = ['Robes','Pantalons','T-shirts','Vestes','Pyjamas','Combinaisons','Accessoires','Chaussures','Manteaux','Pulls'];
    foreach($refcats as $rc) {
        try { $pdo->prepare("INSERT IGNORE INTO ref_categories (nom) VALUES (?)")->execute([$rc]); } catch(PDOException $e){}
    }
    $success[] = "✅ Catégories de référence insérées";

    // Refs marques
    $marques = ['Zara Kids','H&M Kids','Jacadi','Bonpoint','Petit Bateau','Benetton Kids','Sergent Major','Dpam'];
    foreach($marques as $m) {
        try { $pdo->prepare("INSERT IGNORE INTO ref_marques (nom) VALUES (?)")->execute([$m]); } catch(PDOException $e){}
    }
    $success[] = "✅ Marques de référence insérées";

    // Refs tailles
    $tailles = [['0-3 mois',1],['3-6 mois',2],['6-12 mois',3],['12-18 mois',4],['18-24 mois',5],['2 ans',6],['3 ans',7],['4 ans',8],['5 ans',9],['6 ans',10],['8 ans',11],['10 ans',12],['12 ans',13],['14 ans',14]];
    foreach($tailles as $t) {
        try { $pdo->prepare("INSERT IGNORE INTO ref_tailles (nom,ordre) VALUES (?,?)")->execute($t); } catch(PDOException $e){}
    }
    $success[] = "✅ Tailles de référence insérées";

    // Frais livraison
    $frais = [
        ['France métropolitaine','Standard',5.90,5.00,75.00],
        ['Europe','Express',12.00,10.00,null],
        ['Monde','Standard',25.00,15.00,null],
        ['Point Relais France','Standard',2.50,5.00,75.00],
        ['Livraison à domicile','Express',9.90,10.00,75.00]
    ];
    foreach($frais as $f) {
        try { $pdo->prepare("INSERT IGNORE INTO frais_livraison (zone,mode,frais,poids_max,gratuit_des) VALUES (?,?,?,?,?)")->execute($f); } catch(PDOException $e){}
    }
    $success[] = "✅ Frais de livraison insérés";

    // Produits démo avec vraies images ZIP
    $produits = [
        ['Combinaison Bébé à Pois','Douce combinaison en tricot certifié OEKO-TEX avec motifs pois colorés. Fermeture boutons-pression pour les changes faciles.',34.00,'images/cl1.png','neuf','Nouveau','actif',1,'Unisexe','0-24 mois'],
        ['Ensemble Veste & Pantalon Rose','Veste en velours côtelé rose avec jean blanc. Boutons dorés, doublure confortable.',45.00,'images/cl2.png','neuf','Tendance','actif',2,'Fille','2-8 ans'],
        ['Veste Imperméable Jaune','Coupe-vent imperméable jaune soleil avec col montant tricoté. Légère et résistante.',38.00,'images/cl3.png','excellent',null,'actif',3,'Unisexe','3-10 ans'],
        ['Set Bavoir & Chaussures Bébé','Set complet : 2 bavoirs en gaze de coton + sandales en cuir végétalien. Cadeau idéal.',22.00,'images/cl4.png','neuf',null,'actif',1,'Unisexe','0-18 mois'],
        ['Body Manches Longues Ourson','Body bébé en coton doux avec adorable motif ourson. Col snap-button pour faciliter habillage.',18.00,'images/cl5.png','neuf','Coup de cœur','actif',1,'Unisexe','0-24 mois'],
        ['Set Baby Collection Premium','Set 5 pièces : body, pantalon, gilet, bonnet et chaussettes. Coton bio certifié.',34.00,'images/cl6.png','neuf','Populaire','actif',1,'Unisexe','0-6 mois'],
        ['Veste Matelassée Légère','Veste matelassée ultra-légère pour demi-saisons. Coupe ajustée, fermeture éclair.',36.00,'images/Rectangle 9.png','excellent',null,'actif',3,'Garçon','2-8 ans'],
        ['Ensemble 3 Pièces Denim','Veste jean rose + body gris + pantalon blanc. Boutons dorés, 100% coton.',42.00,'images/Rectangle 11.png','neuf','Top vente','actif',2,'Fille','2-8 ans'],
        ['Body Bébé Blanc Ourson','Body manches longues en coton velours, motif ourson brodé.',16.00,'images/Rectangle 10.png','neuf',null,'actif',1,'Unisexe','0-18 mois'],
        ['Combinaison Rayée Colorée','Combinaison entière en coton rayé multicolore. Bretelles réglables.',28.00,'images/Rectangle 959.png','excellent','Nouveauté','actif',1,'Unisexe','0-12 mois'],
        ['Ensemble Hiver Junior','Sweat molleton et pantalon assortis. Coton gratté intérieur, capuche zippée.',54.00,'images/Rectangle 10 (1).png','neuf','Hiver','actif',4,'Unisexe','8-14 ans'],
        ['Salopette Bébé Étoiles','Salopette en velours côtelé beige avec étoiles brodées.',32.00,'images/Rectangle 10 (2).png','neuf',null,'actif',1,'Unisexe','6-18 mois']
    ];
    $inserted = 0;
    foreach($produits as $p) {
        try {
            $pdo->prepare("INSERT IGNORE INTO produits (nom,description,prix,image,etat,badge,statut,categorie_id,genre,taille) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute($p);
            $inserted++;
        } catch(PDOException $e){}
    }
    $success[] = "✅ $inserted produits démo insérés avec les vraies images";

    // Soldes sur certains produits
    $soldes = [[3,22.00,'-42%'],[4,14.00,'-36%'],[7,24.00,'-33%'],[8,28.00,'-33%'],[9,10.00,'-37%'],[11,34.00,'-37%']];
    foreach($soldes as $s) {
        try { $pdo->prepare("INSERT IGNORE INTO ventes (produit_id,prix_solde,reduction,actif) VALUES (?,?,?,1)")->execute($s); } catch(PDOException $e){}
    }
    $success[] = "✅ Produits en solde configurés";

    // Dossier uploads
    $uploadsDir = __DIR__.'/uploads/';
    if(!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        $success[] = "✅ Dossier uploads/ créé";
    } else {
        $success[] = "✅ Dossier uploads/ existe";
    }

    $step = 4;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Installation KidCycle</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Nunito',sans-serif;background:linear-gradient(135deg,#f4f2fa,#ede9f7);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:20px;padding:40px;width:680px;max-width:100%;box-shadow:0 20px 60px rgba(155,142,196,.2)}
.logo{text-align:center;margin-bottom:8px}
.logo img{height:40px;display:inline-block}
.logo-txt{font-size:28px;font-weight:900;color:#9b8ec4;font-style:italic}
h1{text-align:center;font-size:20px;font-weight:800;color:#1a1a2e;margin-bottom:24px}
.step-bar{display:flex;gap:6px;margin-bottom:24px;justify-content:center}
.step-dot{width:28px;height:28px;border-radius:50%;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #e8e4f5;background:#fff;color:#999;transition:all .3s}
.step-dot.done{background:#6bbd8a;border-color:#6bbd8a;color:#fff}
.step-dot.active{background:#9b8ec4;border-color:#9b8ec4;color:#fff}
.log{background:#f8f7fc;border-radius:12px;padding:16px;max-height:320px;overflow-y:auto;margin-bottom:20px}
.log-item{font-size:13px;padding:5px 0;border-bottom:1px solid #f0eef8;line-height:1.5}
.log-item:last-child{border-bottom:none}
.log-item.err{color:#e04040}
.log-item.ok{color:#2d8a4a}
.log-item.warn{color:#e59010}
.summary{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px}
.summary-box{border-radius:10px;padding:14px;text-align:center}
.summary-box.ok-box{background:#e8f8ed;border:1.5px solid #6bbd8a}
.summary-box.err-box{background:#fff0f0;border:1.5px solid #f0c0c0}
.summary-num{font-size:28px;font-weight:900;margin-bottom:4px}
.summary-lbl{font-size:13px;font-weight:700;color:#555}
.btn-go{display:block;width:100%;padding:15px;background:linear-gradient(135deg,#b8a9d4,#9b8ec4);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;text-align:center;text-decoration:none;font-family:'Nunito',sans-serif;transition:all .2s;margin-bottom:10px}
.btn-go:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,142,196,.4)}
.btn-admin{display:block;width:100%;padding:13px;background:#fff;color:#9b8ec4;border:1.5px solid #9b8ec4;border-radius:12px;font-size:14px;font-weight:800;cursor:pointer;text-align:center;text-decoration:none;font-family:'Nunito',sans-serif;transition:all .2s;margin-bottom:10px}
.btn-admin:hover{background:#f4f2fa}
.credentials{background:#fff8e1;border:1.5px solid #c8a951;border-radius:10px;padding:14px;margin-bottom:18px;font-size:13px}
.credentials h3{color:#c8a951;font-weight:800;margin-bottom:8px}
.cred-row{display:flex;gap:8px;align-items:center;margin-bottom:4px}
.cred-label{font-weight:700;color:#555;min-width:80px}
code{background:#fff;border:1px solid #e8e4f5;border-radius:5px;padding:2px 8px;font-family:'Courier New',monospace;font-size:13px;color:#9b8ec4;font-weight:700}
.warn-del{background:#fff0f0;border:1.5px solid #f0c0c0;border-radius:10px;padding:12px 14px;font-size:12px;color:#c00;margin-top:8px;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-txt">KidCycle</div>
  </div>
  <h1>🛠️ Installation automatique</h1>

  <!-- Étapes -->
  <div class="step-bar">
    <div class="step-dot <?= $step>=1?'done':'' ?>">1</div>
    <div style="height:2px;background:#e8e4f5;flex:1;margin-top:13px;border-radius:2px"></div>
    <div class="step-dot <?= $step>=2?'done':($step>=1?'active':'') ?>">2</div>
    <div style="height:2px;background:#e8e4f5;flex:1;margin-top:13px;border-radius:2px"></div>
    <div class="step-dot <?= $step>=3?'done':($step>=2?'active':'') ?>">3</div>
    <div style="height:2px;background:#e8e4f5;flex:1;margin-top:13px;border-radius:2px"></div>
    <div class="step-dot <?= $step>=4?'done':($step>=3?'active':'') ?>">4</div>
  </div>

  <!-- Log -->
  <div class="log">
    <?php foreach($success as $s): ?>
      <div class="log-item ok"><?= htmlspecialchars($s) ?></div>
    <?php endforeach; ?>
    <?php foreach($errors as $e): ?>
      <div class="log-item err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Résumé -->
  <div class="summary">
    <div class="summary-box ok-box">
      <div class="summary-num" style="color:#2d8a4a"><?= count($success) ?></div>
      <div class="summary-lbl">Étapes réussies</div>
    </div>
    <div class="summary-box err-box">
      <div class="summary-num" style="color:#e04040"><?= count($errors) ?></div>
      <div class="summary-lbl">Erreurs</div>
    </div>
  </div>

  <?php if($step >= 4): ?>
  <!-- Identifiants -->
  <div class="credentials">
    <h3>🔑 Identifiants de connexion</h3>
    <div class="cred-row"><span class="cred-label">Admin :</span><code>admin@kidcycle.com</code></div>
    <div class="cred-row"><span class="cred-label">Mot de passe :</span><code>admin123</code></div>
    <div style="margin-top:8px;font-size:12px;color:#888">Changez le mot de passe après la première connexion.</div>
  </div>

  <!-- Boutons -->
  <a href="index.html" class="btn-go">🏠 Accéder au site KidCycle</a>
  <a href="admin/login.html" class="btn-admin">⚙️ Accéder à l'administration</a>
  <div class="warn-del">⚠️ Pour des raisons de sécurité, <strong>supprimez ce fichier install.php</strong> après installation.</div>

  <?php else: ?>
  <div style="text-align:center;padding:20px;color:#e04040;font-weight:700">
    ❌ Installation incomplète. Vérifiez que XAMPP MySQL est démarré.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
