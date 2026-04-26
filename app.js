/* =====================================================
   app.js — Fonctions globales KidCycle
   =====================================================
   Ce fichier contient toutes les fonctions utilisées
   sur TOUTES les pages du site :

   - KC.isLogged()       → est-ce que l'utilisateur est connecté ?
   - KC.getCart()        → lire le panier
   - KC.addCart()        → ajouter un produit au panier
   - KC.toggleFav()      → ajouter / retirer des favoris
   - KC.toast()          → afficher un message en bas de l'écran
   - KC.prodCard()       → créer la carte HTML d'un produit
   - KC.initNav()        → initialiser la barre de navigation
   - KC.initSearch()     → initialiser la recherche
   - KC.initAnimations() → animer les éléments au défilement
   - KC.GET() / KC.POST()→ contacter le serveur PHP

   COMMENT FONCTIONNE KC ?
   KC est un objet JavaScript. Penser à KC comme une
   "boîte à outils" : toutes les fonctions importantes
   du site sont dedans.
   ===================================================== */

var KC = {};   /* On crée la boîte à outils KC */


/* =====================================================
   PARTIE 1 : Lire / Écrire dans la mémoire du navigateur
   ===================================================== */
KC.store = {
  /* Lire */
  get: function (cle) {
    try { return JSON.parse(localStorage.getItem(cle)); } catch (e) { return null; }
  },
  /* Écrire */
  set: function (cle, valeur) {
    localStorage.setItem(cle, JSON.stringify(valeur));
  },
  /* Supprimer */
  del: function (cle) {
    localStorage.removeItem(cle);
  }
};


/* =====================================================
   PARTIE 2 : Authentification (connexion / déconnexion)
   ===================================================== */

/* Est-ce que l'utilisateur est connecté ? */
KC.isLogged = function () {
  return KC.store.get('kc_user') !== null;
};

/* Obtenir les infos de l'utilisateur connecté */
KC.user = function () {
  return KC.store.get('kc_user');
};

/* Se déconnecter */
KC.logout = function () {
  KC.store.del('kc_user');
  KC.store.del('kc_cart');
  KC.store.del('kc_favs');
  window.location.href = 'index.html';
};

/* Demander à l'utilisateur de se connecter (si pas déjà connecté) */
KC.requireLogin = function (action) {
  if (KC.isLogged()) return true;  /* OK, déjà connecté */

  /* Afficher une boîte de dialogue pour demander de se connecter */
  KC.confirm(
    'Pour ' + action + ',\nvous devez être connecté(e).',
    function () {
      /* Si l'utilisateur dit OUI → aller à la page de connexion */
      sessionStorage.setItem('kc_redirect', location.href);
      location.href = 'Connexion.html';
    }
  );
  return false;
};


/* =====================================================
   PARTIE 3 : Contacter le serveur PHP (API)
   ===================================================== */

KC.API = 'api';  /* Le dossier où se trouvent les fichiers PHP */

/* Fonction générale pour envoyer une requête au serveur */
KC.req = function (methode, chemin, donnees) {
  var options = {
    method: methode,
    headers: { 'Content-Type': 'application/json' }
  };
  /* Si on envoie des données (pour POST/PUT), les convertir en texte JSON */
  if (donnees) {
    options.body = JSON.stringify(donnees);
  }
  /* fetch() envoie la requête et retourne une promesse */
  return fetch(KC.API + chemin, options).then(function (reponse) {
    return reponse.json();  /* Convertir la réponse en objet JavaScript */
  });
};

/* Raccourcis pratiques */
KC.GET  = function (chemin)          { return KC.req('GET',    chemin); };
KC.POST = function (chemin, donnees) { return KC.req('POST',   chemin, donnees); };
KC.PUT  = function (chemin, donnees) { return KC.req('PUT',    chemin, donnees); };
KC.DEL  = function (chemin)          { return KC.req('DELETE', chemin); };


/* =====================================================
   PARTIE 4 : Panier
   ===================================================== */

/* Lire le panier */
KC.getCart = function () {
  return KC.store.get('kc_cart') || [];
};

/* Sauvegarder le panier et mettre à jour le compteur */
KC.saveCart = function (panier) {
  KC.store.set('kc_cart', panier);
  KC.updateBadge();
};

/* Compter le nombre total d'articles dans le panier */
KC.cartCount = function () {
  return KC.getCart().reduce(function (total, article) {
    return total + (article.quantite || 1);
  }, 0);
};

/* Ajouter un produit au panier */
KC.addCart = function (produit, taille, couleur, quantite) {
  /* Vérifier que l'utilisateur est connecté */
  if (!KC.requireLogin('ajouter au panier')) return false;

  taille   = taille   || 'M';
  couleur  = couleur  || 'Standard';
  quantite = quantite || 1;

  var panier = KC.getCart();

  /* Est-ce que ce produit (dans la même taille) est déjà dans le panier ? */
  var index = panier.findIndex(function (article) {
    return article.produit_id === produit.id && article.taille === taille;
  });

  if (index >= 0) {
    /* Le produit est déjà là → augmenter la quantité */
    panier[index].quantite = (panier[index].quantite || 1) + quantite;
  } else {
    /* Nouveau produit → l'ajouter */
    panier.push({
      produit_id: produit.id,
      nom:        produit.nom,
      prix:       produit.prix,
      image:      produit.image,
      taille:     taille,
      couleur:    couleur,
      quantite:   quantite
    });
  }

  KC.saveCart(panier);
  KC.toast(produit.nom + ' ajouté au panier !', 'ok');

  /* Synchroniser avec le serveur si connecté */
  KC.POST('/panier.php', {
    produit_id: produit.id,
    taille:     taille,
    couleur:    couleur,
    quantite:   quantite
  }).catch(function () {});

  return true;
};

/* Mettre à jour le compteur du panier affiché dans la navigation */
KC.updateBadge = function () {
  var nombre = KC.cartCount();
  document.querySelectorAll('.cart-badge, .js-cart-count').forEach(function (el) {
    el.textContent = nombre;
  });
};


/* =====================================================
   PARTIE 5 : Favoris
   ===================================================== */

/* Lire les favoris */
KC.getFavs = function () {
  return KC.store.get('kc_favs') || [];
};

/* Sauvegarder les favoris */
KC.saveFavs = function (favs) {
  KC.store.set('kc_favs', favs);
};

/* Est-ce que ce produit est dans les favoris ? */
KC.isFav = function (produitId) {
  return KC.getFavs().some(function (f) {
    return (f.produit_id || f.id) === produitId;
  });
};

/* Ajouter ou retirer un produit des favoris */
KC.toggleFav = function (produit, bouton) {
  if (!KC.requireLogin('gérer vos favoris')) return;

  var favs  = KC.getFavs();
  var index = favs.findIndex(function (f) {
    return (f.produit_id || f.id) === produit.id;
  });

  var ajoute;
  if (index >= 0) {
    /* Déjà dans les favoris → retirer */
    favs.splice(index, 1);
    KC.toast('Retiré des favoris', 'info');
    ajoute = false;
  } else {
    /* Pas encore dans les favoris → ajouter */
    favs.push({
      produit_id: produit.id,
      nom:        produit.nom,
      prix:       produit.prix,
      image:      produit.image
    });
    KC.toast(produit.nom + ' ajouté aux favoris !', 'ok');
    ajoute = true;
  }

  KC.saveFavs(favs);

  /* Mettre à jour l'icône cœur sur le bouton */
  if (bouton) {
    var img = bouton.querySelector('img');
    if (img) {
      img.src = ajoute ? 'images/icon-heart-fill.svg' : 'images/icon-heart.svg';
    }
    bouton.classList.toggle('active', ajoute);
  }

  /* Synchroniser avec le serveur */
  KC.POST('/favoris.php', { produit_id: produit.id }).catch(function () {});
};


/* =====================================================
   PARTIE 6 : Affichage d'un message temporaire (Toast)
   Un "toast" est une petite notification qui apparaît
   en bas de l'écran et disparaît après quelques secondes.
   ===================================================== */
KC.toast = function (message, type) {
  /* Créer ou trouver la zone de message */
  var toast = document.getElementById('kc-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'kc-toast';
    document.body.appendChild(toast);
  }

  /* Couleur selon le type de message */
  var couleurs = {
    ok:   'linear-gradient(135deg, #6bbd8a, #4fa876)',
    err:  'linear-gradient(135deg, #e04040, #c02020)',
    warn: 'linear-gradient(135deg, #f5a623, #e59010)',
    info: 'linear-gradient(135deg, #9b8ec4, #7d6fb0)'
  };

  /* Icône selon le type */
  var icones = { ok: '✅ ', err: '❌ ', warn: '⚠️ ', info: 'ℹ️ ' };

  /* Style du toast */
  toast.style.cssText = [
    'position:fixed', 'bottom:28px', 'left:50%',
    'transform:translateX(-50%) translateY(0)',
    'padding:13px 26px', 'border-radius:14px',
    'font-family:Nunito,sans-serif', 'font-size:14px',
    'font-weight:700', 'color:#fff', 'z-index:99999',
    'opacity:1', 'pointer-events:none',
    'transition:all .45s ease',
    'box-shadow:0 10px 36px rgba(0,0,0,.22)',
    'white-space:nowrap',
    'background:' + (couleurs[type] || couleurs.info)
  ].join(';');

  toast.textContent = (icones[type] || icones.info) + message;

  /* Faire disparaître le toast après 2.8 secondes */
  clearTimeout(toast._timer);
  toast._timer = setTimeout(function () {
    toast.style.opacity = '0';
  }, 2800);
};


/* =====================================================
   PARTIE 7 : Boîte de dialogue de confirmation
   ===================================================== */
KC.confirm = function (message, siOui, siNon) {
  /* Supprimer une éventuelle ancienne boîte */
  var ancienne = document.getElementById('_kc_confirm');
  if (ancienne) ancienne.remove();

  /* Créer la boîte de dialogue */
  var fond = document.createElement('div');
  fond.id = '_kc_confirm';
  fond.style.cssText = [
    'position:fixed', 'inset:0',
    'background:rgba(0,0,0,.5)',
    'z-index:19999',
    'display:flex', 'align-items:center', 'justify-content:center'
  ].join(';');

  fond.innerHTML =
    '<div style="background:#fff;border-radius:20px;padding:36px 32px;' +
    'max-width:400px;width:90vw;text-align:center;">' +
    '<div style="font-size:48px;margin-bottom:14px">🤔</div>' +
    '<div style="font-size:15px;font-weight:700;color:#1a1a2e;line-height:1.6;' +
    'margin-bottom:22px;white-space:pre-line">' + message + '</div>' +
    '<div style="display:flex;gap:12px;justify-content:center">' +
    '<button id="_kc_non" style="flex:1;max-width:130px;padding:12px;' +
    'border-radius:12px;border:1.5px solid #e8e4f5;background:#fff;' +
    'font-family:Nunito,sans-serif;font-size:14px;font-weight:700;cursor:pointer;color:#888">' +
    'Annuler</button>' +
    '<button id="_kc_oui" style="flex:1;max-width:130px;padding:12px;' +
    'border-radius:12px;border:none;background:linear-gradient(135deg,#9b8ec4,#7d6fb0);' +
    'font-family:Nunito,sans-serif;font-size:14px;font-weight:700;cursor:pointer;color:#fff">' +
    'Confirmer</button>' +
    '</div></div>';

  document.body.appendChild(fond);

  /* Bouton OUI */
  fond.querySelector('#_kc_oui').onclick = function () {
    fond.remove();
    if (siOui) siOui();
  };

  /* Bouton NON */
  fond.querySelector('#_kc_non').onclick = function () {
    fond.remove();
    if (siNon) siNon();
  };

  /* Cliquer en dehors ferme la boîte */
  fond.onclick = function (e) {
    if (e.target === fond) {
      fond.remove();
      if (siNon) siNon();
    }
  };
};


/* =====================================================
   PARTIE 8 : Créer la carte HTML d'un produit
   Cette fonction retourne le code HTML d'une carte produit.
   ===================================================== */
KC.prodCard = function (p) {
  /* Prix affiché (prix soldé si disponible, sinon prix normal) */
  var prix = p.prix_solde ? parseFloat(p.prix_solde) : parseFloat(p.prix);

  /* Est-ce que ce produit est dans les favoris ? */
  var estFavori = KC.isFav(p.id);

  /* Construire le HTML de la carte */
  return (
    '<div class="prod-card" onclick="location.href=\'detail.html?id=' + p.id + '\'">' +

      /* Badge (ex: "Nouveau", "Promo") */
      (p.badge ? '<span class="prod-badge">' + p.badge + '</span>' : '') +
      (p.prix_solde ? '<span class="prod-badge sale" style="left:auto;right:10px;">Promo</span>' : '') +

      /* Image + bouton favoris */
      '<div class="prod-card-img-wrap">' +
        '<img src="' + p.image + '" alt="' + p.nom + '" loading="lazy">' +
        '<button class="prod-card-fav' + (estFavori ? ' active' : '') + '"' +
          ' data-id="' + p.id + '"' +
          ' data-nom="' + encodeURIComponent(p.nom || '') + '"' +
          ' data-prix="' + (p.prix_solde || p.prix) + '"' +
          ' data-img="' + p.image + '"' +
          ' onclick="event.stopPropagation(); KC._favBtn(this)">' +
          '<img src="images/' + (estFavori ? 'icon-heart-fill' : 'icon-heart') + '.svg">' +
        '</button>' +
      '</div>' +

      /* Infos produit */
      '<div class="prod-card-body">' +
        '<div class="prod-card-name">' + p.nom + '</div>' +
        '<div class="prod-card-sub">' + (p.description || p.categorie_nom || '') + '</div>' +
        '<div class="prod-card-bottom">' +
          '<div class="prod-card-price">' +
            (p.prix_solde ? '<span class="prod-old-price">' + parseFloat(p.prix).toFixed(2) + '</span>' : '') +
            '<span class="swaps-coin"><img src="images/logo.png" alt="ii" style="width:100%;height:100%;object-fit:contain;border-radius:50%;"></span> ' +
            prix.toFixed(2) +
          '</div>' +
          '<button class="prod-card-btn"' +
            ' data-id="' + p.id + '"' +
            ' data-nom="' + encodeURIComponent(p.nom || '') + '"' +
            ' data-prix="' + (p.prix_solde || p.prix) + '"' +
            ' data-img="' + p.image + '"' +
            ' onclick="event.stopPropagation(); KC._cartBtn(this)">' +
            'Ajouter au panier' +
          '</button>' +
        '</div>' +
      '</div>' +

    '</div>'
  );
};

/* Clic sur le bouton favoris d'une carte produit */
KC._favBtn = function (btn) {
  var prod = {
    id:    parseInt(btn.dataset.id),
    nom:   decodeURIComponent(btn.dataset.nom || ''),
    prix:  btn.dataset.prix,
    image: btn.dataset.img
  };
  KC.toggleFav(prod, btn);
};

/* Clic sur le bouton "Ajouter au panier" d'une carte produit */
KC._cartBtn = function (btn) {
  var prod = {
    id:    parseInt(btn.dataset.id),
    nom:   decodeURIComponent(btn.dataset.nom || ''),
    prix:  btn.dataset.prix,
    image: btn.dataset.img
  };
  if (KC.addCart(prod, 'M', 'Standard', 1)) {
    /* Changer temporairement le texte du bouton */
    var texteOriginal = btn.textContent;
    btn.textContent = '✓ Ajouté !';
    btn.classList.add('added');
    setTimeout(function () {
      btn.textContent = texteOriginal;
      btn.classList.remove('added');
    }, 2000);
  }
};


/* =====================================================
   PARTIE 9 : Initialisation de la barre de navigation
   ===================================================== */
KC.initNav = function () {
  /* Mettre à jour le compteur panier */
  KC.updateBadge();

  /* Effet de scroll : assombrir la nav quand on descend */
  window.addEventListener('scroll', function () {
    var nav = document.querySelector('.navbar');
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 10);
  });

  /* Si connecté, synchroniser panier et favoris avec le serveur */
  if (KC.isLogged()) {
    KC.GET('/panier.php').then(function (r) {
      if (r.ok && r.data) KC.saveCart(r.data);
    }).catch(function () {});

    KC.GET('/favoris.php').then(function (r) {
      if (r.ok && r.data) KC.saveFavs(r.data);
    }).catch(function () {});
  }
};


/* =====================================================
   PARTIE 10 : Recherche
   ===================================================== */
KC.initSearch = function (idInput, idDropdown) {
  var input    = document.getElementById(idInput);
  var dropdown = document.getElementById(idDropdown);
  if (!input || !dropdown) return;

  var timer;

  /* Quand l'utilisateur tape dans la barre de recherche */
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var query = input.value.trim();

    /* Ne rien chercher si moins de 2 caractères */
    if (query.length < 2) {
      dropdown.classList.remove('open');
      return;
    }

    /* Attendre 280ms avant d'envoyer la requête (évite trop de requêtes) */
    timer = setTimeout(function () {
      KC.GET('/misc.php?action=search&q=' + encodeURIComponent(query))
        .then(function (r) {
          if (!r.ok || !r.data.length) {
            dropdown.classList.remove('open');
            return;
          }
          /* Afficher les 6 premiers résultats */
          dropdown.innerHTML = r.data.slice(0, 6).map(function (p) {
            return (
              '<div class="search-item" onclick="location.href=\'detail.html?id=' + p.id + '\'">' +
              '<img src="' + p.image + '" alt="' + p.nom + '">' +
              '<div class="search-item-info">' +
              '<div class="search-item-name">' + p.nom + '</div>' +
              '<div class="search-item-price">' + parseFloat(p.prix_solde || p.prix).toFixed(2) + ' SWAPS</div>' +
              '</div></div>'
            );
          }).join('');
          dropdown.classList.add('open');
        })
        .catch(function () {});
    }, 280);
  });

  /* Fermer le dropdown en cliquant ailleurs */
  document.addEventListener('click', function (e) {
    var zone = input.closest('.nav-search-wrap');
    if (zone && !zone.contains(e.target)) dropdown.classList.remove('open');
  });

  /* Appuyer sur Entrée → aller à la page de recherche */
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && input.value.trim()) {
      location.href = 'nouveautes.html?q=' + encodeURIComponent(input.value.trim());
    }
  });
};


/* =====================================================
   PARTIE 11 : Animations au défilement (scroll)
   Les éléments avec la classe "fade-up" apparaissent
   progressivement quand ils deviennent visibles.
   ===================================================== */
KC.initAnimations = function () {
  /* IntersectionObserver détecte quand un élément devient visible */
  if (!('IntersectionObserver' in window)) return;

  var observateur = new IntersectionObserver(function (entrees) {
    entrees.forEach(function (entree) {
      if (entree.isIntersecting) {
        /* L'élément est visible → ajouter la classe "visible" */
        entree.target.classList.add('visible');
        observateur.unobserve(entree.target);  /* Ne plus surveiller */
      }
    });
  }, { threshold: 0.1 });

  /* Observer tous les éléments "fade-up" */
  document.querySelectorAll('.fade-up').forEach(function (el) {
    observateur.observe(el);
  });
};


/* =====================================================
   PARTIE 12 : Effet de clic (ripple) sur les boutons
   ===================================================== */
KC.initRipple = function (selecteur) {
  document.querySelectorAll(selecteur).forEach(function (el) {
    el.addEventListener('click', function (e) {
      /* Créer un cercle animé à l'endroit du clic */
      var cercle = document.createElement('span');
      var rect   = el.getBoundingClientRect();
      var taille = Math.max(el.offsetWidth, el.offsetHeight);

      cercle.style.cssText = [
        'position:absolute', 'border-radius:50%',
        'background:rgba(255,255,255,.4)',
        'width:' + taille + 'px', 'height:' + taille + 'px',
        'top:'  + (e.clientY - rect.top  - taille / 2) + 'px',
        'left:' + (e.clientX - rect.left - taille / 2) + 'px',
        'transform:scale(0)', 'animation:ripple .5s ease',
        'pointer-events:none'
      ].join(';');

      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(cercle);

      setTimeout(function () { cercle.remove(); }, 500);
    });
  });

  /* CSS pour l'animation ripple */
  var style = document.createElement('style');
  style.textContent = '@keyframes ripple { to { transform:scale(2); opacity:0 } }';
  document.head.appendChild(style);
};


/* =====================================================
   PARTIE 13 : Effet parallaxe (image qui bouge avec la souris)
   ===================================================== */
KC.initParallax = function (selecteur, profondeur) {
  profondeur = profondeur || 20;
  var elements = document.querySelectorAll(selecteur);
  if (!elements.length) return;

  document.addEventListener('mousemove', function (e) {
    /* Calculer le décalage par rapport au centre de l'écran */
    var dx = (e.clientX - window.innerWidth  / 2) / window.innerWidth;
    var dy = (e.clientY - window.innerHeight / 2) / window.innerHeight;

    elements.forEach(function (el) {
      el.style.transform = 'translate(' + (dx * profondeur) + 'px, ' + (dy * profondeur) + 'px)';
    });
  });
};


/* =====================================================
   PARTIE 14 : Spinner (roue de chargement) sur un bouton
   ===================================================== */
KC.spin = function (btn) {
  btn._originalHTML = btn.innerHTML;
  btn.innerHTML = '<span class="spinner"></span>';
  btn.disabled = true;
};

KC.unspin = function (btn) {
  if (btn._originalHTML) btn.innerHTML = btn._originalHTML;
  btn.disabled = false;
};

/* Rendre KC disponible dans toute la page */
window.KC = KC;
