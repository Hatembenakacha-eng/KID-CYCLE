/* =====================================================
   auth.js — Connexion, Inscription, Session KidCycle
   =====================================================
   Ce fichier fait DEUX choses :
   1. Communiquer avec le serveur PHP (XAMPP) pour
      créer un compte ou se connecter
   2. Garder en mémoire qui est connecté (token + user)

   COMMENT ÇA MARCHE ?
   - Quand on se connecte, le serveur PHP renvoie un "token"
   - Ce token est comme un badge : il prouve qui on est
   - On l'envoie à chaque requête pour s'identifier
   - Il est stocké dans localStorage (mémoire du navigateur)
   ===================================================== */

/* URL de base de l'API (les fichiers PHP sur XAMPP) */
var API_URL = 'api';


/* =====================================================
   PARTIE 1 : Lire/écrire dans la mémoire du navigateur
   ===================================================== */

function lire(cle) {
  try { return JSON.parse(localStorage.getItem(cle)); } catch(e) { return null; }
}
function sauvegarder(cle, valeur) {
  try { localStorage.setItem(cle, JSON.stringify(valeur)); } catch(e) {}
}
function supprimer(cle) {
  localStorage.removeItem(cle);
}


/* =====================================================
   PARTIE 2 : Session — qui est connecté ?
   ===================================================== */

/* Récupérer le token de connexion */
function getToken() {
  return localStorage.getItem('kc_tok') || '';
}

/* Récupérer les infos de l'utilisateur connecté */
function utilisateurConnecte() {
  return lire('kc_user');
}

/* Est-ce que quelqu'un est connecté ? */
function estConnecte() {
  return getToken() !== '' && utilisateurConnecte() !== null;
}

/* Se déconnecter : effacer token et données */
function deconnecter() {
  supprimer('kc_tok');
  supprimer('kc_user');
  supprimer('kc_cart');
  supprimer('kc_favs');
  window.location.href = 'index.html';
}

/* Sauvegarder la session après connexion réussie */
function sauvegarderSession(token, user) {
  localStorage.setItem('kc_tok', token);
  sauvegarder('kc_user', user);
}


/* =====================================================
   PARTIE 3 : Envoyer une requête au serveur PHP
   "fetch" = envoyer une requête HTTP
   "await" = attendre la réponse avant de continuer
   ===================================================== */

/* Envoyer une requête POST avec des données JSON */
function envoyerAuServeur(url, donnees) {
  /*
    fetch() envoie une requête au serveur.
    - method: 'POST' → on envoie des données
    - headers → on dit que c'est du JSON
    - body → les données converties en texte JSON
    La fonction retourne une "promesse" (.then/.catch)
  */
  return fetch(API_URL + url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      /* Envoyer le token si on est connecté */
      'Authorization': 'Bearer ' + getToken()
    },
    body: JSON.stringify(donnees)
  }).then(function(reponse) {
    return reponse.json();
  });
}

/* Envoyer une requête GET (pour récupérer des données) */
function lireServeur(url) {
  return fetch(API_URL + url, {
    method: 'GET',
    headers: {
      'Authorization': 'Bearer ' + getToken()
    }
  }).then(function(reponse) {
    return reponse.json();
  });
}


/* =====================================================
   PARTIE 4 : SE CONNECTER
   Envoie l'email + mot de passe au serveur PHP
   Le serveur vérifie et renvoie un token
   ===================================================== */
function connecter(email, motDePasse) {
  return envoyerAuServeur('/auth.php?action=login', {
    email:    email,
    password: motDePasse
  }).then(function(resultat) {
    if (resultat.ok) {
      /* Connexion réussie : sauvegarder le token et les infos */
      sauvegarderSession(resultat.token, resultat.user);
    }
    return resultat;
  });
  /*
    RETOURNE une promesse. L'appel ressemble à :
    connecter(email, pwd).then(function(r) {
      if (r.ok) { ... succès ... }
      else { afficherErreur(r.err); }
    });
  */
}


/* =====================================================
   PARTIE 5 : S'INSCRIRE
   Envoie les infos du nouveau compte au serveur PHP
   Le serveur crée le compte dans MySQL et renvoie un token
   ===================================================== */
function inscrire(nom, prenom, email, motDePasse, extras) {
  /* extras = objet avec les champs optionnels (tel, pays, etc.) */
  var donnees = Object.assign({
    nom:      nom,
    prenom:   prenom,
    email:    email,
    password: motDePasse
  }, extras || {});

  return envoyerAuServeur('/auth.php?action=register', donnees)
    .then(function(resultat) {
      if (resultat.ok) {
        sauvegarderSession(resultat.token, resultat.user);
      }
      return resultat;
    });
}


/* =====================================================
   PARTIE 6 : Mettre à jour la barre de navigation
   Appelée au chargement de chaque page
   ===================================================== */
function mettreAJourNav() {
  var user = utilisateurConnecte();

  /* Bouton déconnexion : visible seulement si connecté */
  var btnLogout = document.getElementById('nav-logout');
  if (btnLogout) {
    btnLogout.style.display = user ? 'inline-flex' : 'none';
  }

  /* Compteur du panier */
  var panier = lire('kc_cart') || [];
  var totalPanier = panier.reduce(function(t, a) { return t + (a.quantite || 1); }, 0);
  document.querySelectorAll('.cart-badge, .js-cart-count, #cart-count').forEach(function(el) {
    el.textContent = totalPanier;
  });

  /* Compteur des favoris */
  var favs = lire('kc_favs') || [];
  var cptFav = document.getElementById('fav-badge');
  if (cptFav) {
    cptFav.textContent  = favs.length;
    cptFav.style.display = favs.length > 0 ? 'flex' : 'none';
  }

  /* Afficher le nom de l'utilisateur si connecté */
  var elNom = document.getElementById('nav-user-name');
  if (elNom && user) {
    elNom.textContent = user.prenom || user.nom || '';
  }
}

/* Lancer mettreAJourNav quand la page est chargée */
document.addEventListener('DOMContentLoaded', mettreAJourNav);

/* =====================================================
   ALIAS — fonctions compatibles avec les pages existantes
   ===================================================== */
/* Certaines pages utilisent faireDeconnexion() au lieu de deconnecter() */
var faireDeconnexion = deconnecter;

/* Certaines pages utilisent KC.isLogged() */
if (typeof KC !== 'undefined') {
  KC.isLogged = estConnecte;
  KC.user     = utilisateurConnecte;
  KC.token    = getToken;
}
