-- ═══════════════════════════════════════════════════════════
--  KidCycle — database.sql
--  Schéma complet de la base de données
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS kidcycle_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kidcycle_db;

-- ── Utilisateurs ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS utilisateurs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(80)  NOT NULL,
    prenom      VARCHAR(80)  NOT NULL DEFAULT '',
    email       VARCHAR(160) NOT NULL UNIQUE,
    motdepasse  VARCHAR(255) NOT NULL,              -- bcrypt hash
    tel         VARCHAR(30)  DEFAULT NULL,
    adresse     TEXT         DEFAULT NULL,
    avatar_url  VARCHAR(500) DEFAULT NULL,
    swaps       DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Produits ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendeur_id  INT UNSIGNED NOT NULL,
    titre       VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    categorie   ENUM('bebe','fille','garcon','junior') NOT NULL,
    genre       ENUM('fille','garcon','unisexe')       NOT NULL DEFAULT 'unisexe',
    taille      VARCHAR(30)  NOT NULL,
    etat        ENUM('neuf','tres-bon','bon','acceptable') NOT NULL DEFAULT 'bon',
    prix_swaps  DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    badge       VARCHAR(50)  DEFAULT NULL,
    image_url   VARCHAR(500) DEFAULT 'cl1.png',
    statut      ENUM('actif','vendu','archive') NOT NULL DEFAULT 'actif',
    vues        INT UNSIGNED DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_categorie (categorie),
    INDEX idx_statut    (statut),
    INDEX idx_vendeur   (vendeur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Images produit ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produit_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produit_id  INT UNSIGNED NOT NULL,
    url         VARCHAR(500) NOT NULL,
    ordre       TINYINT UNSIGNED DEFAULT 0,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Favoris ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favoris (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    produit_id  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (user_id, produit_id),
    FOREIGN KEY (user_id)    REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Panier ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS panier (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    produit_id  INT UNSIGNED NOT NULL,
    quantite    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    taille      VARCHAR(30)  DEFAULT NULL,
    added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Commandes ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS commandes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20)  NOT NULL UNIQUE,             -- #P-024688xxx
    acheteur_id     INT UNSIGNED NOT NULL,
    adresse_livr    TEXT         DEFAULT NULL,
    telephone       VARCHAR(30)  DEFAULT NULL,
    mode_expedition VARCHAR(60)  DEFAULT NULL,
    frais_livr      DECIMAL(8,2) DEFAULT 0.00,
    total_swaps     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_euros     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    statut          ENUM('en_attente','confirmee','expediee','livree','annulee')
                    NOT NULL DEFAULT 'en_attente',
    note            TEXT         DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (acheteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_acheteur (acheteur_id),
    INDEX idx_statut   (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Lignes de commande ────────────────────────────────────
CREATE TABLE IF NOT EXISTS commande_lignes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commande_id INT UNSIGNED NOT NULL,
    produit_id  INT UNSIGNED NOT NULL,
    titre_snap  VARCHAR(200) NOT NULL,
    image_snap  VARCHAR(500) DEFAULT NULL,
    taille      VARCHAR(30)  DEFAULT NULL,
    quantite    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    prix_swaps  DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id)  ON DELETE CASCADE,
    FOREIGN KEY (produit_id)  REFERENCES produits(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SWAPS Transactions ────────────────────────────────────
CREATE TABLE IF NOT EXISTS swaps_transactions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('achat','vente','depot','retrait','commission') NOT NULL,
    montant     DECIMAL(10,2) NOT NULL,
    description VARCHAR(200)  DEFAULT NULL,
    commande_id INT UNSIGNED  DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (commande_id) REFERENCES commandes(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Admin Logs ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    cible_type  VARCHAR(50)  DEFAULT NULL,
    cible_id    INT UNSIGNED DEFAULT NULL,
    detail      TEXT         DEFAULT NULL,
    ip          VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════
-- Données de test
-- ═══════════════════════════════════════════════════════════

INSERT IGNORE INTO utilisateurs (nom, prenom, email, motdepasse, swaps, role) VALUES
('Admin', 'KidCycle',  'admin@kidcycle.com',  '$2y$12$placeholder_admin_hash',  500.00, 'admin'),
('Dupont', 'Marie',    'marie@example.com',   '$2y$12$placeholder_user_hash1',   120.00, 'user'),
('Martin', 'Luc',      'luc@example.com',     '$2y$12$placeholder_user_hash2',    80.00, 'user');
-- ⚠ En production, générer les hash avec: password_hash('motdepasse', PASSWORD_BCRYPT)

INSERT IGNORE INTO produits (vendeur_id, titre, description, categorie, genre, taille, etat, prix_swaps, badge, image_url) VALUES
(2, 'Combinaison Velours Bébé',    'Velours doux certifié OEKO-TEX · 0–24 mois',           'bebe',   'unisexe', '0-1',  'tres-bon', 34,  'Nouveau',      'cl1.png'),
(2, 'Robe Fleurie Fille',           'Coton biologique · imprimé fleuri · 2–8 ans',           'fille',  'fille',   '2-3',  'bon',      28,  'Tendance',     'cl2.png'),
(3, 'Veste Matelassée Garçon',      'Doublure chaude · coupe ajustée · 3–10 ans',            'garcon', 'garcon',  '3-4',  'tres-bon', 45,  NULL,           'cl3.png'),
(2, 'Pyjama 2 Pièces Étoiles',      'Coton doux certifié OEKO-TEX · 6 mois–4 ans',           'bebe',   'unisexe', '1-2',  'tres-bon', 22,  'Populaire',    'cl4.png'),
(3, 'Ensemble Jogger Enfant',        'Sweat zippé + pantalon coordonné · 4–12 ans',           'junior', 'unisexe', '3-4',  'bon',      38,  'Coup de cœur', 'cl5.png'),
(2, 'Manteau Capuche Enfant',        'Imperméable déperlant · capuche amovible · 2–10 ans',   'fille',  'fille',   '2-3',  'tres-bon', 52,  NULL,           'cl6.png');
