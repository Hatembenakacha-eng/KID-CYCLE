-- KidCycle — Base de données complète
SET NAMES utf8mb4;
SET foreign_key_checks=0;

CREATE DATABASE IF NOT EXISTS kidcycle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kidcycle;

-- UTILISATEURS
CREATE TABLE IF NOT EXISTS utilisateurs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin par défaut (mot de passe: admin123)
INSERT IGNORE INTO utilisateurs (nom,prenom,email,motdepasse,role,actif,swaps)
VALUES ('Admin','KidCycle','admin@kidcycle.com',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',1,9999.00);

-- CATÉGORIES
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  nom VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO categories (slug,nom) VALUES
('bebe','Bébé (0-2 ans)'),
('fille','Fille (2-8 ans)'),
('garcon','Garçon (2-8 ans)'),
('junior','Junior (8-14 ans)');

-- PRODUITS (avec les vraies images du ZIP)
CREATE TABLE IF NOT EXISTS produits (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Produits avec vraies images locales
INSERT IGNORE INTO produits (nom,description,prix,image,etat,badge,statut,categorie_id,genre,taille) VALUES
('Combinaison Bébé à Pois','Douce combinaison en tricot certifié OEKO-TEX avec motifs pois colorés. Fermeture boutons-pression pour les changes. Lavable à 30°C.',34.00,'images/cl1.png','neuf','Nouveau','actif',1,'Unisexe','0-24 mois'),
('Ensemble Veste & Pantalon Rose','Veste en velours côtelé rose avec jeans blanc. Boutons dorés, doublure confortable. Parfait pour les sorties.',45.00,'images/cl2.png','neuf','Tendance','actif',2,'Fille','2-8 ans'),
('Veste Imperméable Jaune','Coupe-vent imperméable coloris jaune soleil avec col montant tricoté. Légère et résistante à la pluie.',38.00,'images/cl3.png','excellent',NULL,'actif',3,'Unisexe','3-10 ans'),
('Set Bavoir & Chaussures','Set complet : 2 bavoirs en gaze de coton (beige et moutarde) + une paire de sandales en cuir végétalien. Cadeau idéal.',22.00,'images/cl4.png','neuf',NULL,'actif',1,'Unisexe','0-18 mois'),
('Body Manches Longues Ourson','Body bébé en coton doux avec adorable motif ourson. Col snap-button pour faciliter l\'habillage. Existe en 5 coloris.',18.00,'images/cl5.png','neuf','Coup de cœur','actif',1,'Unisexe','0-24 mois'),
('Set Baby Collection','Set complet 5 pièces : body, pantalon, gilet, bonnet et chaussettes. Coton bio certifié. Idéal pour les premiers mois.',34.00,'images/cl6.png','neuf','Populaire','actif',1,'Unisexe','0-6 mois'),
('Veste Matelassée Légère','Veste matelassée ultra-légère idéale pour les demi-saisons. Coupe ajustée, fermeture éclair YKK.',36.00,'images/Rectangle 9.png','excellent',NULL,'actif',3,'Garçon','2-8 ans'),
('Ensemble 3 Pièces Denim','Veste en jean rose + body gris + pantalon blanc. Boutons dorés, 100% coton. Style casual chic.',42.00,'images/Rectangle 11.png','neuf','Top vente','actif',2,'Fille','2-8 ans'),
('Body Bébé Blanc Ourson','Body bébé manches longues en coton velours, motif ourson brodé. Snap crotch pour les changes.',16.00,'images/Rectangle 10.png','neuf',NULL,'actif',1,'Unisexe','0-18 mois'),
('Combinaison Rayée Colorée','Combinaison entière en coton rayé multicolore. Bretelles réglables, facile à enfiler.',28.00,'images/Rectangle 959.png','excellent','Nouveauté','actif',1,'Unisexe','0-12 mois'),
('Salopette Bébé Étoiles','Salopette en velours côtelé beige avec étoiles brodées. Bretelles à boutons-pression, jambes pratiques.',32.00,'images/Rectangle 10 (1).png','neuf',NULL,'actif',1,'Unisexe','6-18 mois'),
('Ensemble Hiver Junior','Sweat molleton et pantalon assortis. Coton gratté intérieur, capuche zippée. Chaud et confortable.',54.00,'images/Rectangle 10 (2).png','neuf','Hiver 2025','actif',4,'Unisexe','8-14 ans');

-- VENTES / SOLDES
CREATE TABLE IF NOT EXISTS ventes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL UNIQUE,
  prix_solde DECIMAL(10,2) NOT NULL,
  reduction VARCHAR(20) DEFAULT NULL,
  debut DATETIME DEFAULT CURRENT_TIMESTAMP,
  fin DATETIME DEFAULT NULL,
  actif TINYINT(1) DEFAULT 1,
  FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO ventes (produit_id,prix_solde,reduction,actif) VALUES
(3,22.00,'-42%',1),
(4,14.00,'-36%',1),
(7,24.00,'-33%',1),
(8,28.00,'-33%',1),
(9,10.00,'-37%',1),
(11,20.00,'-37%',1);

-- PANIER
CREATE TABLE IF NOT EXISTS panier (
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
) ENGINE=InnoDB;

-- FAVORIS
CREATE TABLE IF NOT EXISTS favoris (
  id INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT NOT NULL,
  produit_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fav (utilisateur_id,produit_id),
  FOREIGN KEY(utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY(produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- COMMANDES
CREATE TABLE IF NOT EXISTS commandes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ARTICLES COMMANDE
CREATE TABLE IF NOT EXISTS commande_articles (
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
) ENGINE=InnoDB;

-- NEWSLETTER
CREATE TABLE IF NOT EXISTS newsletter (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CODES PROMO
CREATE TABLE IF NOT EXISTS codes_promo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  type ENUM('pourcentage','montant','livraison') DEFAULT 'pourcentage',
  valeur DECIMAL(10,2) NOT NULL,
  utilisations INT DEFAULT 0,
  expiration DATE DEFAULT NULL,
  actif TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO codes_promo(code,type,valeur,actif) VALUES
('KIDCYCLE20','pourcentage',20,1),
('WELCOME10','montant',10,1),
('FREESHIP','livraison',0,1);

-- REFS ADMIN
CREATE TABLE IF NOT EXISTS ref_genres (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(80) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT IGNORE INTO ref_genres(nom) VALUES ('Fille'),('Garçon'),('Unisexe'),('Bébé');
CREATE TABLE IF NOT EXISTS ref_categories (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT IGNORE INTO ref_categories(nom) VALUES ('Robes'),('Pantalons'),('T-shirts'),('Vestes'),('Pyjamas'),('Combinaisons'),('Accessoires');
CREATE TABLE IF NOT EXISTS ref_marques (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT IGNORE INTO ref_marques(nom) VALUES ('Zara Kids'),('H&M Kids'),('Jacadi'),('Bonpoint'),('Petit Bateau'),('Benetton Kids');
CREATE TABLE IF NOT EXISTS ref_tailles (id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(50) NOT NULL UNIQUE, ordre INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT IGNORE INTO ref_tailles(nom,ordre) VALUES ('0-3 mois',1),('3-6 mois',2),('6-12 mois',3),('12-18 mois',4),('18-24 mois',5),('2 ans',6),('3 ans',7),('4 ans',8),('5 ans',9),('6 ans',10),('8 ans',11),('10 ans',12),('12 ans',13),('14 ans',14);
CREATE TABLE IF NOT EXISTS frais_livraison (id INT AUTO_INCREMENT PRIMARY KEY, zone VARCHAR(100), mode VARCHAR(80) DEFAULT 'Standard', frais DECIMAL(10,2), poids_max DECIMAL(10,2), gratuit_des DECIMAL(10,2), actif TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT IGNORE INTO frais_livraison(zone,mode,frais,poids_max,gratuit_des) VALUES ('France métropolitaine','Standard',5.90,5,75),('Europe','Express',12.00,10,NULL),('Monde','Standard',25.00,15,NULL),('Point Relais','Standard',2.50,5,75);

SET foreign_key_checks=1;
