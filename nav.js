/* =====================================================
   nav.js — Navigation commune à TOUTES les pages
   =====================================================
   Ce fichier gère la barre de navigation :
   - Bouton déconnexion
   - Compteur panier
   - Compteur favoris
   - Recherche en direct

   COMMENT L'UTILISER ?
   Ajouter dans chaque page HTML :
     <script src="auth.js"></script>
     <script src="app.js"></script>
     <script src="nav.js"></script>
   Puis appeler KC.initNav() dans le script de la page.
   ===================================================== */

document.addEventListener('DOMContentLoaded', function () {

  /* ─── 1. Bouton déconnexion ─────────────────────── */
  var btnLogout = document.getElementById('nav-logout');
  if (btnLogout) {
    /* Afficher le bouton seulement si connecté */
    btnLogout.style.display = estConnecte() ? 'inline-flex' : 'none';

    btnLogout.addEventListener('click', function (e) {
      e.preventDefault();
      deconnecter();
    });
  }

  /* ─── 2. Lien profil → connexion si pas connecté ─── */
  var lienProfil = document.getElementById('nav-profil');
  if (lienProfil) {
    lienProfil.href = estConnecte() ? 'profil.html' : 'Connexion.html';
  }

  /* ─── 3. Compteur panier ─────────────────────────── */
  function rafraichirPanier() {
    var panier = lire('kc_cart') || [];
    var total  = panier.reduce(function (t, a) { return t + (a.quantite || 1); }, 0);
    document.querySelectorAll('.cart-badge, .js-cart-count').forEach(function (el) {
      el.textContent = total;
      /* Cacher le badge si panier vide */
      el.style.display = total > 0 ? 'flex' : 'flex'; /* toujours visible */
    });
  }
  rafraichirPanier();

  /* ─── 4. Compteur favoris ────────────────────────── */
  function rafraichirFavoris() {
    var favs = lire('kc_favs') || [];
    var cpt  = document.getElementById('fav-badge');
    if (cpt) {
      cpt.textContent  = favs.length;
      cpt.style.display = favs.length > 0 ? 'flex' : 'none';
    }
  }
  rafraichirFavoris();

  /* ─── 5. Recherche en direct ─────────────────────── */
  var champRecherche = document.getElementById('search-input');
  var dropdown       = document.getElementById('search-dd');

  if (champRecherche && dropdown) {
    var timer;

    champRecherche.addEventListener('input', function () {
      clearTimeout(timer);
      var q = champRecherche.value.trim();

      if (q.length < 2) {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
        return;
      }

      /* Attendre 300ms avant d'envoyer (évite trop de requêtes) */
      timer = setTimeout(function () {
        fetch('api/misc.php?action=search&q=' + encodeURIComponent(q), {
          headers: { 'Authorization': 'Bearer ' + getToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.data.length) {
            dropdown.classList.remove('open');
            return;
          }

          dropdown.innerHTML = data.data.slice(0, 6).map(function (p) {
            var prix = parseFloat(p.prix_solde || p.prix).toFixed(2);
            return (
              '<div class="search-item" onclick="location.href=\'detail.html?id=' + p.id + '\'">' +
              '<img src="' + p.image + '" alt="' + p.nom + '" ' +
              'style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;">' +
              '<div style="flex:1;min-width:0;">' +
              '<div style="font-size:13px;font-weight:700;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + p.nom + '</div>' +
              '<div style="font-size:12px;color:#9b8ec4;font-weight:700;">' + prix + ' SWAPS</div>' +
              '</div></div>'
            );
          }).join('');

          dropdown.classList.add('open');
        })
        .catch(function () {});
      }, 300);
    });

    /* Fermer le dropdown en cliquant ailleurs */
    document.addEventListener('click', function (e) {
      if (!champRecherche.closest('*').contains(e.target) &&
          !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
      }
    });

    /* Entrée → aller à la page recherche */
    champRecherche.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && champRecherche.value.trim()) {
        location.href = 'Filtres.html?q=' + encodeURIComponent(champRecherche.value.trim());
      }
    });
  }

  /* ─── 6. Effet scroll : barre de nav ────────────── */
  var navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', function () {
      navbar.classList.toggle('scrolled', window.scrollY > 10);
    });
  }

  /* ─── 7. Synchroniser panier/favoris avec le serveur ─ */
  if (estConnecte()) {
    /* Charger le panier depuis le serveur */
    lireServeur('/panier.php')
      .then(function (r) {
        if (r.ok && r.data) {
          sauvegarder('kc_cart', r.data);
          rafraichirPanier();
        }
      }).catch(function () {});

    /* Charger les favoris depuis le serveur */
    lireServeur('/favoris.php')
      .then(function (r) {
        if (r.ok && r.data) {
          sauvegarder('kc_favs', r.data);
          rafraichirFavoris();
        }
      }).catch(function () {});
  }

});
