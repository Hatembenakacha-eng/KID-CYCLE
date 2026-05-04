# KidCycle — Guide d'installation XAMPP

## 🚀 Installation en 3 étapes

### Étape 1 — Copier les fichiers
```
Copier le dossier kidcycle/ dans :
→ Windows : C:\xampp\htdocs\kidcycle\
→ Mac     : /Applications/XAMPP/htdocs/kidcycle/
→ Linux   : /opt/lampp/htdocs/kidcycle/
```

### Étape 2 — Démarrer XAMPP
1. Ouvrir XAMPP Control Panel
2. Démarrer **Apache**
3. Démarrer **MySQL**

### Étape 3 — Installer automatiquement
Ouvrir dans le navigateur :
```
http://localhost/kidcycle/install.php
```
Le script crée automatiquement :
- La base de données `kidcycle`
- Toutes les tables
- Le compte admin
- Les données de démonstration

---

## 🔑 Identifiants

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | admin@kidcycle.com | admin123 |
| Client test | S'inscrire sur le site | — |

---

## 🌐 URLs d'accès

| Page | URL |
|------|-----|
| Accueil | http://localhost/kidcycle/index.html |
| Connexion | http://localhost/kidcycle/connexion.html |
| Inscription | http://localhost/kidcycle/inscription.html |
| Admin | http://localhost/kidcycle/admin/login.html |

---

## 📁 Structure du projet

```
kidcycle/
├── index.html              ← Page d'accueil
├── connexion.html          ← Connexion utilisateur
├── inscription.html        ← Inscription
├── nouveautes.html         ← Catalogue nouveautés
├── vente.html              ← Articles en solde
├── detail.html             ← Fiche produit
├── panier.html             ← Mon panier
├── favoris.html            ← Mes favoris
├── profil.html             ← Mon profil
├── modifier-profil.html    ← Modifier profil
├── mes-commandes.html      ← Mes commandes
├── mes-produits.html       ← Mes produits vendeur
├── ajouter-produit.html    ← Ajouter un produit
├── livraison.html          ← Livraison + Paiement
├── global.css              ← CSS global
├── app.js                  ← JS global
├── install.php             ← Installateur auto
│
├── api/                    ← Backend PHP
│   ├── config.php
│   ├── auth.php
│   ├── produits.php
│   ├── panier.php
│   ├── favoris.php
│   ├── commandes.php
│   ├── misc.php
│   └── database.sql
│
├── admin/                  ← Back-office
│   ├── login.html
│   ├── dashboard.html
│   ├── articles.html
│   ├── swaps.html
│   ├── profils.html
│   ├── logistique.html
│   ├── fidelite.html
│   ├── abonnements.html
│   ├── admin.css
│   └── admin.js
│
├── images/                 ← Assets réels (logo, produits)
│   ├── Kidcycle.png        ← Logo réel
│   ├── cl1.png ... cl6.png ← Produits réels
│   ├── Rectangle *.png     ← Autres produits
│   ├── icon-cart.svg
│   ├── icon-heart.svg
│   └── ...
│
└── uploads/                ← Avatars et photos uploadées
```

---

## ⚠️ Après installation

**Supprimer install.php** pour la sécurité :
```
Supprimer le fichier : kidcycle/install.php
```

---

## 🔧 Configuration manuelle (si install.php échoue)

Modifier `api/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // votre user MySQL
define('DB_PASS', '');          // votre mot de passe
define('DB_NAME', 'kidcycle');
```

Puis importer manuellement dans phpMyAdmin :
```
http://localhost/phpmyadmin
→ Créer BDD "kidcycle"
→ Importer api/database.sql
```
