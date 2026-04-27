-- =====================================================
-- KidCycle — Base de données pour phpMyAdmin (XAMPP)
-- =====================================================
-- COMMENT UTILISER CE FICHIER :
-- 1. Ouvrir XAMPP → démarrer Apache et MySQL
-- 2. Aller sur http://localhost/phpmyadmin
-- 3. Cliquer sur l'onglet "Importer" (en haut)
-- 4. Choisir ce fichier et cliquer "Importer"
-- La base de données "kidcycle" sera créée automatiquement.
-- =====================================================

SET NAMES utf8mb4;

-- Créer la base de données si elle n'existe pas encore
CREATE DATABASE IF NOT EXISTS kidcycle
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kidcycle;

-- =====================================================
-- TABLE : utilisateurs
-- Stocke les comptes des membres et administrateurs
-- =====================================================
CREATE TABLE IF NOT EXISTS utilisateurs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nom         VARCHAR(100) NOT NULL,
  prenom      VARCHAR(100) NOT NULL,
  email       VARCHAR(255) NOT NULL UNIQUE,
  motdepasse  VARCHAR(255) NOT NULL,         -- Mot de passe haché (bcrypt)
  tel         VARCHAR(30)  DEFAULT NULL,
  adresse     TEXT         DEFAULT NULL,
  ville       VARCHAR(100) DEFAULT NULL,
  code_postal VARCHAR(20)  DEFAULT NULL,
  pays        VARCHAR(100) DEFAULT 'Tunisie',
  avatar      VARCHAR(500) DEFAULT NULL,
  swaps       DECIMAL(10,2) DEFAULT 500.00,  -- Solde en "SWAPS" (monnaie du site)
  role        ENUM('client','vendeur','admin') DEFAULT 'client',
  actif       TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compte administrateur par défaut
-- Email : admin@kidcycle.com | Mot de passe : admin123
INSERT IGNORE INTO utilisateurs (nom, prenom, email, motdepasse, role, swaps)
VALUES (
  'Admin', 'KidCycle', 'admin@kidcycle.com',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin', 9999.00
);

-- =====================================================
-- TABLE : categories
-- Les catégories de produits
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(50)  NOT NULL UNIQUE,
  nom        VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categories (slug, nom) VALUES
  ('bebe',   'Bébé (0-2 ans)'),
  ('fille',  'Fille (2-8 ans)'),
  ('garcon', 'Garçon (2-8 ans)'),
  ('junior', 'Junior (8-14 ans)');

-- =====================================================
-- TABLE : produits
-- Tous les articles vendus sur le site
-- =====================================================
CREATE TABLE IF NOT EXISTS produits (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  vendeur_id   INT DEFAULT NULL,
  categorie_id INT DEFAULT NULL,
  nom          VARCHAR(255) NOT NULL,
  description  TEXT         DEFAULT NULL,
  prix         DECIMAL(10,2) NOT NULL,
  image        VARCHAR(500)  DEFAULT NULL,
  etat         ENUM('neuf','excellent','bon','correct') DEFAULT 'neuf',
  genre        VARCHAR(50)  DEFAULT NULL,
  taille       VARCHAR(50)  DEFAULT NULL,
  badge        VARCHAR(50)  DEFAULT NULL,
  statut       ENUM('actif','attente','archive','refuse') DEFAULT 'attente',
  vues         INT DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vendeur_id)   REFERENCES utilisateurs(id) ON DELETE SET NULL,
  FOREIGN KEY (categorie_id) REFERENCES categories(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Produits de démonstration (avec les images du site)
INSERT IGNORE INTO produits (nom, description, prix, image, etat, badge, statut, categorie_id, genre, taille) VALUES
('Combinaison Bébé à Pois',       'Douce combinaison en tricot certifié OEKO-TEX.',       34.00, 'images/cl1.png',         'neuf',     'Nouveau',     'actif', 1, 'Unisexe', '0-24 mois'),
('Ensemble Veste & Pantalon Rose', 'Veste en velours côtelé rose avec jeans blanc.',        45.00, 'images/cl2.png',         'neuf',     'Tendance',    'actif', 2, 'Fille',   '2-8 ans'),
('Veste Imperméable Jaune',        'Coupe-vent imperméable coloris jaune soleil.',           38.00, 'images/cl3.png',         'excellent', NULL,          'actif', 3, 'Unisexe', '3-10 ans'),
('Set Bavoir & Chaussures',        'Set complet : 2 bavoirs + sandales en cuir.',           22.00, 'images/cl4.png',         'neuf',     NULL,          'actif', 1, 'Unisexe', '0-18 mois'),
('Body Manches Longues Ourson',    'Body bébé en coton doux avec motif ourson.',            18.00, 'images/cl5.png',         'neuf',     'Coup de cœur','actif', 1, 'Unisexe', '0-24 mois'),
('Set Baby Collection',            'Set complet 5 pièces : body, pantalon, gilet...',       34.00, 'images/cl6.png',         'neuf',     'Populaire',   'actif', 1, 'Unisexe', '0-6 mois'),
('Veste Matelassée Légère',        'Veste ultra-légère idéale pour les demi-saisons.',      36.00, 'images/Rectangle 9.png', 'excellent', NULL,          'actif', 3, 'Garçon',  '2-8 ans'),
('Ensemble 3 Pièces Denim',        'Veste en jean rose + body gris + pantalon blanc.',      42.00, 'images/Rectangle 11.png','neuf',     'Top vente',   'actif', 2, 'Fille',   '2-8 ans'),
('Body Bébé Blanc Ourson',         'Body bébé manches longues, motif ourson brodé.',        16.00, 'images/Rectangle 10.png','neuf',     NULL,          'actif', 1, 'Unisexe', '0-18 mois'),
('Combinaison Rayée Colorée',      'Combinaison en coton rayé multicolore.',                28.00, 'images/Rectangle 959.png','excellent','Nouveauté',  'actif', 1, 'Unisexe', '0-12 mois'),
('Salopette Bébé Étoiles',         'Salopette en velours côtelé beige avec étoiles.',       32.00, 'images/Rectangle 10 (1).png','neuf',NULL,           'actif', 1, 'Unisexe', '6-18 mois'),
('Ensemble Hiver Junior',          'Sweat et pantalon assortis, chaud et confortable.',     54.00, 'images/Rectangle 10 (2).png','neuf','Hiver 2025',  'actif', 4, 'Unisexe', '8-14 ans');

-- =====================================================
-- TABLE : ventes (produits en promotion)
-- =====================================================
CREATE TABLE IF NOT EXISTS ventes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  produit_id  INT NOT NULL UNIQUE,
  prix_solde  DECIMAL(10,2) NOT NULL,
  reduction   VARCHAR(20)   DEFAULT NULL,     -- Ex: "-42%"
  actif       TINYINT(1)    DEFAULT 1,
  debut       DATETIME      DEFAULT CURRENT_TIMESTAMP,
  fin         DATETIME      DEFAULT NULL,
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Promotions actives
INSERT IGNORE INTO ventes (produit_id, prix_solde, reduction, actif) VALUES
  (3, 22.00, '-42%', 1),
  (4, 14.00, '-36%', 1),
  (7, 24.00, '-33%', 1),
  (8, 28.00, '-33%', 1),
  (9, 10.00, '-37%', 1),
  (11,20.00, '-37%', 1);

-- =====================================================
-- TABLE : panier
-- Articles dans le panier de chaque utilisateur
-- =====================================================
CREATE TABLE IF NOT EXISTS panier (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT NOT NULL,
  produit_id     INT NOT NULL,
  prix           DECIMAL(10,2) NOT NULL,
  quantite       INT DEFAULT 1,
  taille         VARCHAR(30) DEFAULT 'M',
  couleur        VARCHAR(50) DEFAULT 'Standard',
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY panier_unique (utilisateur_id, produit_id, taille),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id)     REFERENCES produits(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE : favoris
-- Produits mis en favoris par les utilisateurs
-- =====================================================
CREATE TABLE IF NOT EXISTS favoris (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id INT NOT NULL,
  produit_id     INT NOT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY favoris_unique (utilisateur_id, produit_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id)     REFERENCES produits(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE : commandes
-- Historique des commandes passées
-- =====================================================
CREATE TABLE IF NOT EXISTS commandes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT DEFAULT NULL,
  numero          VARCHAR(30) NOT NULL UNIQUE,    -- Ex: "KC-20250426-001"
  statut          ENUM('en_attente','preparation','en_cours','livree','annulee') DEFAULT 'en_attente',
  adresse         TEXT NOT NULL,
  ville           VARCHAR(100) DEFAULT NULL,
  code_postal     VARCHAR(20)  DEFAULT NULL,
  pays            VARCHAR(100) DEFAULT 'Tunisie',
  tel             VARCHAR(30)  DEFAULT NULL,
  mode_livraison  VARCHAR(80)  DEFAULT 'standard',
  frais_livraison DECIMAL(10,2) DEFAULT 5.90,
  sous_total      DECIMAL(10,2) NOT NULL,
  total           DECIMAL(10,2) NOT NULL,
  mode_paiement   VARCHAR(50)  DEFAULT 'carte',
  reduction       DECIMAL(10,2) DEFAULT 0.00,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE : commande_articles
-- Détail des produits dans chaque commande
-- =====================================================
CREATE TABLE IF NOT EXISTS commande_articles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  commande_id  INT NOT NULL,
  produit_id   INT DEFAULT NULL,
  nom          VARCHAR(255) NOT NULL,
  image        VARCHAR(500) DEFAULT NULL,
  prix         DECIMAL(10,2) NOT NULL,
  quantite     INT DEFAULT 1,
  taille       VARCHAR(30) DEFAULT NULL,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id)  REFERENCES produits(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- TABLE : newsletter
-- Emails inscrits à la newsletter
-- =====================================================
CREATE TABLE IF NOT EXISTS newsletter (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE : codes_promo
-- Codes de réduction utilisables lors du paiement
-- =====================================================
CREATE TABLE IF NOT EXISTS codes_promo (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(50) NOT NULL UNIQUE,
  type        ENUM('pourcentage','montant','livraison') DEFAULT 'pourcentage',
  valeur      DECIMAL(10,2) NOT NULL,
  utilisations INT DEFAULT 0,
  expiration  DATE DEFAULT NULL,
  actif       TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Codes promo disponibles
INSERT IGNORE INTO codes_promo (code, type, valeur, actif) VALUES
  ('KIDCYCLE20', 'pourcentage', 20, 1),   -- -20%
  ('WELCOME10',  'montant',     10, 1),   -- -10 DT
  ('FREESHIP',   'livraison',    0, 1);   -- Livraison gratuite
