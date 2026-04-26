/* =====================================================
   auth.js — Connexion & Inscription KidCycle
   =====================================================
   Ce fichier gère :
   - S'inscrire (créer un compte)
   - Se connecter (ouvrir une session)
   - Se déconnecter
   - Savoir si l'utilisateur est connecté

   Les données sont stockées dans "localStorage" :
   c'est une petite mémoire du navigateur qui garde
   les informations même si on ferme la page.
   ===================================================== */


/* =====================================================
   PARTIE 1 : Lire et écrire dans la mémoire du navigateur
   ===================================================== */

/* Lire une valeur enregistrée */
function lire(cle) {
  try {
    return JSON.parse(localStorage.getItem(cle));
  } catch (e) {
    return null;
  }
}

/* Sauvegarder une valeur */
function sauvegarder(cle, valeur) {
  try {
    localStorage.setItem(cle, JSON.stringify(valeur));
  } catch (e) {}
}

/* Supprimer une valeur */
function supprimer(cle) {
  localStorage.removeItem(cle);
}


/* =====================================================
   PARTIE 2 : Fonctions de base pour l'authentification
   ===================================================== */

/* Est-ce que l'utilisateur est connecté ? */
function estConnecte() {
  return lire('kc_user') !== null;
}

/* Obtenir les infos de l'utilisateur connecté */
function utilisateurConnecte() {
  return lire('kc_user');
}

/* Obtenir la liste de tous les comptes enregistrés */
function tousLesComptes() {
  return lire('kc_comptes') || [];
}


/* =====================================================
   PARTIE 3 : S'inscrire — créer un nouveau compte
   ===================================================== */
function inscrire(nom, prenom, email, motDePasse) {
  var comptes = tousLesComptes();

  /* Vérifier si l'email est déjà utilisé */
  var emailExiste = comptes.find(function (c) {
    return c.email === email;
  });
  if (emailExiste) {
    return { ok: false, err: 'Cet email est déjà utilisé.' };
  }

  /* Créer le nouveau compte */
  var nouveauCompte = {
    id: Date.now(),
    nom: nom,
    prenom: prenom,
    email: email,
    motDePasse: motDePasse
  };

  /* Ajouter à la liste et sauvegarder */
  comptes.push(nouveauCompte);
  sauvegarder('kc_comptes', comptes);

  /* Connecter automatiquement après l'inscription */
  var session = {
    id: nouveauCompte.id,
    nom: nom,
    prenom: prenom,
    email: email
  };
  sauvegarder('kc_user', session);

  return { ok: true, user: session };
}


/* =====================================================
   PARTIE 4 : Se connecter — entrer email + mot de passe
   ===================================================== */
function connecter(email, motDePasse) {
  var comptes = tousLesComptes();

  /* Chercher le compte avec cet email et ce mot de passe */
  var compte = comptes.find(function (c) {
    return c.email === email && c.motDePasse === motDePasse;
  });

  if (!compte) {
    return { ok: false, err: 'Email ou mot de passe incorrect.' };
  }

  /* Sauvegarder la session */
  var session = {
    id: compte.id,
    nom: compte.nom,
    prenom: compte.prenom,
    email: compte.email
  };
  sauvegarder('kc_user', session);

  return { ok: true, user: session };
}


/* =====================================================
   PARTIE 5 : Se déconnecter — effacer la session
   ===================================================== */
function deconnecter() {
  supprimer('kc_user');
  window.location.href = 'index.html';
}


/* =====================================================
   PARTIE 6 : Mettre à jour le menu de navigation
   ===================================================== */
function mettreAJourNav() {
  var user = utilisateurConnecte();

  /* Bouton déconnexion : affiché seulement si connecté */
  var btnLogout = document.getElementById('nav-logout');
  if (btnLogout) {
    btnLogout.style.display = user ? 'inline-flex' : 'none';
  }

  /* Compteur du panier */
  var panier = lire('kc_cart') || [];
  var compteurs = document.querySelectorAll('.cart-badge, .js-cart-count, #cart-count');
  compteurs.forEach(function (el) {
    el.textContent = panier.reduce(function (total, article) {
      return total + (article.quantite || 1);
    }, 0);
  });

  /* Compteur des favoris */
  var favs = lire('kc_favs') || [];
  var cptFav = document.getElementById('fav-badge');
  if (cptFav) {
    cptFav.textContent = favs.length;
    cptFav.style.display = favs.length > 0 ? 'flex' : 'none';
  }
}

/* Appeler mettreAJourNav quand la page est chargée */
document.addEventListener('DOMContentLoaded', mettreAJourNav);
