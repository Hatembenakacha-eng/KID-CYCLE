

'use strict';

/* ── STORAGE ─────────────────────────────────────────────── */
var S = {
  USERS:   'kc_users',
  SESSION: 'kc_session',
  FAVS:    'kc_favs',
  AVATAR:  'kc_avatar'
};
function get(k)    { try{ return JSON.parse(localStorage.getItem(k)); }catch(e){ return null; } }
function set(k,v)  { try{ localStorage.setItem(k, JSON.stringify(v)); }catch(e){} }
function del(k)    { localStorage.removeItem(k); }

function getUsers()   { return get(S.USERS)   || []; }
function getSession() { return get(S.SESSION); }
function getFavs()    { return get(S.FAVS)    || []; }
function isLogged()   { return getSession() !== null; }

function findUser(email) {
  return getUsers().find(function(u){ return u.email.toLowerCase()===email.toLowerCase(); }) || null;
}
function saveSession(u) { set(S.SESSION, { email:u.email, nom:u.nom, prenom:u.prenom||'', tel:u.tel||'', adresse:u.adresse||'' }); }


/* ── TOAST ───────────────────────────────────────────────── */
function toast(msg, ok) {
  var t = document.getElementById('kc-toast');
  if (!t) {
    t = document.createElement('div'); t.id='kc-toast';
    t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);'+
      'padding:11px 22px;border-radius:10px;font-size:13px;font-weight:700;z-index:99999;'+
      'transition:transform .3s,opacity .3s;opacity:0;pointer-events:none;white-space:nowrap;';
    document.body.appendChild(t);
  }
  t.style.background = ok===false ? '#e04040' : '#1a1a2e';
  t.style.color = '#fff';
  t.textContent = msg;
  t.style.transform='translateX(-50%) translateY(0)'; t.style.opacity='1';
  clearTimeout(t._t);
  t._t = setTimeout(function(){
    t.style.transform='translateX(-50%) translateY(80px)'; t.style.opacity='0';
  }, 2600);
}



/* ── PRODUCTS ────────────────────────────────────────────── */
var PRODUCTS = [
  {id:1, nom:'Combinaison Velours Bébé',   desc:'Velours doux certifié OEKO-TEX · 0–24 mois',          img:'photos/cl1.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'tres-bon', prix:34, badge:'Nouveau'},
  {id:2, nom:'Robe Fleurie Fille',          desc:'Coton biologique · imprimé fleuri · 2–8 ans',           img:'photos/cl2.png', cat:'fille',  genre:'fille',   taille:'2-3', etat:'bon',      prix:28, badge:'Tendance'},
  {id:3, nom:'Veste Matelassée Garçon',     desc:'Doublure chaude · coupe ajustée · 3–10 ans',            img:'photos/cl3.png', cat:'garcon', genre:'garcon',  taille:'3-4', etat:'tres-bon', prix:45, badge:''},
  {id:4, nom:'Pyjama 2 Pièces Étoiles',     desc:'Coton doux certifié OEKO-TEX · 6 mois–4 ans',           img:'photos/cl4.png', cat:'bebe',   genre:'unisexe', taille:'1-2', etat:'tres-bon', prix:22, badge:'Populaire'},
  {id:5, nom:'Ensemble Jogger Enfant',       desc:'Sweat zippé + pantalon coordonné · 4–12 ans',           img:'photos/cl5.png', cat:'junior', genre:'unisexe', taille:'3-4', etat:'bon',      prix:38, badge:'Coup de cœur'},
  {id:6, nom:'Manteau Capuche Enfant',       desc:'Imperméable déperlant · capuche amovible · 2–10 ans',  img:'photos/cl6.png', cat:'fille',  genre:'fille',   taille:'2-3', etat:'tres-bon', prix:52, badge:''},
  {id:7, nom:'Robe de Soirée Fille',         desc:'Dentelle et satin · occasion spéciale · 4–12 ans',      img:'photos/cl1.png', cat:'fille',  genre:'fille',   taille:'3-4', etat:'bon',      prix:41, badge:'Exclusif'},
  {id:8, nom:'Doudoune Légère Enfant',       desc:'Garnissage ultra-léger · chaud sans alourdir · 2–10 ans', img:'photos/cl2.png',cat:'junior',genre:'garcon', taille:'3-4', etat:'tres-bon', prix:49, badge:'Top vente'},
  {id:9, nom:'Salopette Denim Enfant',       desc:'100% coton denim · poches multiples · 3–12 ans',        img:'photos/cl3.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'bon',      prix:31, badge:''},
  {id:10,nom:'Cardigan Bébé Pompon',         desc:'Laine douce · pompons colorés · 0–18 mois',             img:'photos/cl4.png', cat:'bebe',   genre:'fille',   taille:'0-1', etat:'tres-bon', prix:26, badge:'Nouveau'},
  {id:11,nom:'Short Bermuda Garçon',         desc:'Coton léger · taille élastique · 2–10 ans',              img:'photos/cl5.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'bon',      prix:19, badge:''},
  {id:12,nom:'Robe Smock Fille',             desc:'Broderie artisanale · coton doux · 6 mois–6 ans',        img:'photos/cl6.png', cat:'fille',  genre:'fille',   taille:'1-2', etat:'tres-bon', prix:33, badge:'Recommandé'},
  {id:13,nom:'Parka Imperméable Junior',     desc:'Traitement déperlant · fermeture zip · 8–14 ans',        img:'photos/cl1.png', cat:'junior', genre:'unisexe', taille:'3-4', etat:'tres-bon', prix:58, badge:''},
  {id:14,nom:'Legging Coton Bébé',           desc:'100% coton bio · taille réglable · 0–18 mois',           img:'photos/cl2.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'bon',      prix:14, badge:''},
  {id:15,nom:'Sweat Capuche Animaux',        desc:'Molleton bio · motifs animaux · 2–10 ans',               img:'photos/cl3.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'tres-bon', prix:29, badge:'Coup de cœur'},
  {id:16,nom:'Robe Tutu Ballerine',          desc:'Satin et tulle · jupe évasée · 1–8 ans',                 img:'photos/cl4.png', cat:'fille',  genre:'fille',   taille:'1-2', etat:'bon',      prix:24, badge:'Tendance'},
  {id:17,nom:'Ensemble 3 Pièces Bébé',       desc:'Velours côtelé · body + pantalon + veste · 0–24 mois',  img:'photos/cl5.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'tres-bon', prix:39, badge:'Nouveau'},
  {id:18,nom:'Jean Slim Enfant',             desc:'Denim stretch · coupe slim moderne · 8–14 ans',          img:'photos/cl6.png', cat:'junior', genre:'garcon',  taille:'3-4', etat:'bon',      prix:27, badge:''}
];
var PER_PAGE = 6, curPage = 1, filtered = PRODUCTS.slice();


/* ── HELPERS ─────────────────────────────────────────────── */
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
function imgStyle(h){ return 'style="width:100%;height:'+(h||185)+'px;object-fit:contain;display:block;background:#f5f4fb;padding:10px;box-sizing:border-box"'; }


/* ── NAV: update header on every page ───────────────────── */
function updateNav() {
  var s = getSession();
  var n = getFavs().length;

  /* Favourite badge */
  document.querySelectorAll('#fav-nav-count,.fav-nav-badge').forEach(function(el){
    el.textContent = n;
    el.style.display = n>0 ? 'flex' : 'none';
  });

  /* User icon → profile if logged, connexion if not */
  document.querySelectorAll('a.nav-icon[title="Mon compte"]').forEach(function(a){
    a.href  = s ? 'profile.html' : 'Connexion.html';
    a.title = s ? ('Mon compte — '+(s.prenom||s.nom||s.email)) : 'Se connecter';
  });

  /* "Mes produits" nav link */
  document.querySelectorAll('.nav-links a').forEach(function(a){
    if((a.textContent||'').trim()==='Mes produits'){
      a.href = s ? 'produits profile.html' : 'Connexion.html';
    }
  });

  /* Show/hide "Déconnexion" on profile pages */
  var logoutBtn = document.getElementById('kc-logout');
  if (logoutBtn) {
    logoutBtn.style.display = s ? 'block' : 'none';
    logoutBtn.addEventListener('click', function(e){
      e.preventDefault(); del(S.SESSION);
      toast('👋 Déconnecté !'); setTimeout(function(){ location.href='page acceuil.html'; },700);
    });
  }
}


/* ── PROTECTED PAGES ─────────────────────────────────────── */
var PROTECTED = ['panier','panier profile','favoris profile','profile',
  'modifier profile','produits profile','mes commande profile',
  'livraison','paiment','commandes','ajout produit'];

function checkAccess() {
  var pg = decodeURIComponent(location.pathname.split('/').pop().replace('.html','')).toLowerCase();
  if (PROTECTED.some(function(p){ return pg.includes(p); }) && !isLogged()) {
    if (confirm('Cette page nécessite un compte KidCycle.\nVoulez-vous en créer un ?')) {
      location.href = 'formulaire.html';
    } else {
      location.href = 'page acceuil.html';
    }
  }
}

function interceptProtected() {
  document.querySelectorAll('a,.btn-add,.pcard-btn').forEach(function(el){
    var href  = (el.getAttribute('href')||'').toLowerCase();
    var txt   = (el.textContent||'').trim().toLowerCase();
    var prot  = PROTECTED.some(function(p){ return href.includes(p); }) || txt==='mes produits';
    if (!prot) return;
    el.addEventListener('click', function(e){
      if (!isLogged()) {
        e.preventDefault();
        if (confirm('Pour accéder à cette fonctionnalité, créez un compte.\nVoulez-vous vous inscrire ?')) {
          location.href = 'formulaire.html';
        }
      }
    });
  });
}


/* ── SEARCH ──────────────────────────────────────────────── */
function initSearch() {
  var inp = document.querySelector('.search-wrap input');
  if (!inp) return;
  inp.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    if (!q) return;
    var match = PRODUCTS.find(function(p){
      return p.nom.toLowerCase().indexOf(q) >= 0;
    });
    if (match) location.href = 'detail produit.html';
  });
}
function hilite(s,words){ return s; }


/* ── FILTERS + GRID + PAGINATION ─────────────────────────── */
function buildFilters() {
  var panel = document.querySelector('.filters-panel');
  if (!panel) return;
  panel.innerHTML = '<div style="padding:12px 14px;color:#aaa;font-size:13px">Filtres simplifiés</div>';
}
function fg(k,label,opts){
  return '';
}
function checked(k){ return [].slice.call(document.querySelectorAll('input[data-f="'+k+'"]:checked')).map(function(e){return e.value;}); }
function applyFilters(){
  filtered=PRODUCTS.slice();
  var cpt=document.querySelector('.info-bar span');
  if(cpt) cpt.textContent='Affichage de '+filtered.length+' résultats sur '+PRODUCTS.length;
  showPage(1);
}
function resetFilters(){
  curPage=1; filtered=PRODUCTS.slice(); applyFilters();
}
function cardHTML(p,i){
  var fav=getFavs().some(function(f){return f.id===p.id;});
  var coeur=fav?'photos/icon-heart-fill.svg':'photos/icon-heart.svg';
  var badge=p.badge?'<span class="badge">'+p.badge+'</span>':'';
  var caId='ca'+i;
  return '<div class="product-card kc-card" data-id="'+p.id+'" data-nom="'+esc(p.nom)+'" data-img="'+p.img+'" data-prix="'+p.prix+'">'+
    '<a href="#" class="heart-btn" data-coeur="'+p.id+'"><img src="'+coeur+'" alt="favoris" style="width:18px;height:18px"></a>'+
    badge+
    '<a href="detail produit.html" style="display:block;line-height:0">'+
      '<img src="'+p.img+'" alt="'+esc(p.nom)+'" '+imgStyle(185)+'></a>'+
    '<div class="card-body">'+
      '<div class="card-name">'+p.nom+'</div>'+
      '<div class="card-meta">'+
        '<span class="card-sub">'+p.desc+'</span>'+
        '<span class="card-price"><span class="coin">S</span> '+p.prix.toFixed(2)+'</span>'+
      '</div>'+
      '<div class="cart-added" id="'+caId+'">✓ Ajouté !</div>'+
      '<a href="#'+caId+'" class="btn-add">Ajouter au panier</a>'+
    '</div></div>';
}
function showPage(n){
  var grid=document.querySelector('.products-grid');
  if(!grid) return;
  curPage=n;
  var total=filtered.length, pages=Math.max(1,Math.ceil(total/PER_PAGE));
  if(curPage<1)curPage=1; if(curPage>pages)curPage=pages;
  var start=(curPage-1)*PER_PAGE, slice=filtered.slice(start,start+PER_PAGE);
  if(!slice.length){
    grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:50px;color:#aaa">'+
      'Aucun produit.<br><button class="btn-add" style="width:auto;padding:10px 24px;margin-top:12px" onclick="resetFilters()">Réinitialiser</button></div>';
  } else {
    grid.innerHTML=slice.map(function(p,i){return cardHTML(p,start+i+1);}).join('');
    bindHearts(grid);
  }
  var pag=document.querySelector('.pagination');
  if(!pag) return;
  var html='<span style="color:#aaa;font-size:13px">'+filtered.length+' produits affichés</span>';
  pag.innerHTML=html;
}


/* ── FAVOURITES ──────────────────────────────────────────── */
function toggleFav(id,nom,img,prix) {
  if (!isLogged()) {
    if (confirm('Pour ajouter aux favoris, vous devez avoir un compte.\nVoulez-vous créer un compte ?')) {
      location.href='formulaire.html';
    }
    return;
  }
  var list=getFavs(), idx=list.findIndex(function(f){return f.id===id;});
  if (idx<0) {
    list.push({id:id,nom:nom,img:img,image:img,prix:prix});
    set(S.FAVS, list);
    toast('❤️ Ajouté aux favoris !');
    updateHearts(); updateNav();
    setTimeout(function(){location.href='Favoris.html';},800);
  } else {
    list.splice(idx,1);
    set(S.FAVS, list);
    toast('🤍 Retiré des favoris');
    updateHearts(); updateNav();
    renderFavsPage();
  }
}
function updateHearts(){
  var ids=getFavs().map(function(f){return f.id;});
  document.querySelectorAll('[data-coeur]').forEach(function(b){
    var id=parseInt(b.dataset.coeur,10);
    var img=b.querySelector('img');
    if(img) img.src=ids.indexOf(id)>=0?'photos/icon-heart-fill.svg':'photos/icon-heart.svg';
  });
}
function bindHearts(root){
  (root||document).querySelectorAll('[data-coeur]').forEach(function(b){
    if(b._hb) return; b._hb=true;
    b.addEventListener('click',function(e){
      e.preventDefault(); e.stopPropagation();
      var id=parseInt(b.dataset.coeur,10);
      var card=b.closest('[data-id]');
      var nom  = card?(card.dataset.nom||(card.querySelector('.card-name,.pcard-name,.product-name')||{}).textContent||''):'';
      var img  = card?(card.dataset.img||'photos/cl1.png'):'photos/cl1.png';
      var prix = card?parseFloat(card.dataset.prix||0):0;
      toggleFav(id, nom.trim(), img, prix);
    });
  });
}
function renderFavsPage(){
  var z=document.getElementById('kc-favs-grid');
  if(!z) return;
  var list=getFavs();
  if(!list.length){
    z.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:60px;color:#aaa">'+
      '<div style="font-size:48px;margin-bottom:14px">🤍</div>'+
      '<div style="font-size:16px;font-weight:800;color:#b8a9d4;margin-bottom:8px">Vos favoris sont vides</div>'+
      '<p style="font-size:13px;margin-bottom:20px">Cliquez ❤ pour sauvegarder un article.</p>'+
      '<a href="Nouveautes.html" class="btn-add" style="display:inline-block;width:auto;padding:10px 24px">Découvrir</a></div>';
    return;
  }
  z.innerHTML=list.map(function(p){
    return '<div class="fav-card-wrap"><div class="product-card" data-id="'+p.id+'" data-nom="'+esc(p.nom||'')+'" data-img="'+p.img+'" data-prix="'+p.prix+'">'+
      '<div class="product-img" style="position:relative">'+
        '<button data-coeur="'+p.id+'" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.9);border:none;width:30px;height:30px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2">'+
          '<img src="photos/icon-heart-fill.svg" alt="retirer" style="width:14px;height:14px"></button>'+
        '<span class="img-icon"><img src="'+p.img+'" alt="'+esc(p.nom||'')+'" '+imgStyle(185)+'></span>'+
      '</div>'+
      '<div class="product-info">'+
        '<div class="product-top"><span class="product-name">'+esc(p.nom||'')+'</span><span class="product-price"><span class="coin">S</span> '+(p.prix||0).toFixed(2)+'</span></div>'+
        '<div style="margin-top:10px"><a href="detail produit.html" class="btn btn-primary" style="display:block;text-align:center;padding:10px;font-size:12px">Voir le produit</a></div>'+
      '</div></div></div>';
  }).join('');
  bindHearts(z);
}
/* Prepare static heart buttons (pages without JS-rendered grid) */
function prepareStaticHearts(){
  document.querySelectorAll('.heart-btn,.pcard-heart,.heart-lbl').forEach(function(b){
    if(b.dataset.coeur) return;
    var card=b.closest('[data-id]');
    if(card&&card.dataset.id) b.dataset.coeur=card.dataset.id;
  });
  bindHearts();
}


/* ── LOGIN ───────────────────────────────────────────────── */
function initLogin(){
  var btn=document.querySelector('.btn-connect');
  if(!btn) return;
  btn.addEventListener('click',function(e){
    e.preventDefault();
    var email=(document.getElementById('email')||{value:''}).value.trim();
    var pwd  =(document.getElementById('pwd')  ||{value:''}).value;
    if(!email||!pwd){toast('Veuillez remplir tous les champs.',false);return;}
    var u=findUser(email);
    if(!u){toast('Aucun compte avec cet email.',false);return;}
    if(u.motdepasse!==pwd){toast('Mot de passe incorrect.',false);return;}
    saveSession(u);
    toast('✅ Bienvenue '+u.nom+' !');
    setTimeout(function(){location.href='page acceuil.html';},800);
  });
}


/* ── REGISTER ────────────────────────────────────────────── */
function initRegister(){
  var btn=document.querySelector('button[type=submit]');
  if(!btn) return;
  btn.addEventListener('click',function(e){
    e.preventDefault();
    var nom   =(document.querySelector('input[placeholder="Nom"]')   ||{value:''}).value.trim();
    var prenom=(document.querySelector('input[placeholder="Prénom"]')||{value:''}).value.trim();
    var email =(document.querySelector('input[type=email]')          ||{value:''}).value.trim();
    var pwds  =document.querySelectorAll('input[type=password]');
    var mdp   =pwds[0]?pwds[0].value:'';
    var conf  =pwds[1]?pwds[1].value:'';
    if(!nom||!prenom||!email||!mdp){toast('Remplissez tous les champs obligatoires.',false);return;}
    if(!email.includes('@')){toast('Email invalide.',false);return;}
    if(mdp.length<6){toast('Mot de passe : 6 caractères minimum.',false);return;}
    if(mdp!==conf){toast('Mots de passe différents.',false);return;}
    if(findUser(email)){toast('Un compte existe déjà avec cet email.',false);setTimeout(function(){location.href='Connexion.html';},1200);return;}
    var users=getUsers();
    users.push({nom:nom,prenom:prenom,email:email,motdepasse:mdp});
    set(S.USERS,users);
    saveSession({email:email,nom:nom,prenom:prenom});
    toast('✅ Bienvenue '+nom+' !');
    setTimeout(function(){location.href='page acceuil.html';},800);
  });
}


/* ── PROFILE DISPLAY ─────────────────────────────────────── */
function loadProfileDisplay(){
  var s=getSession();
  if(!s) return;
  /* Nom */
  var en=document.getElementById('pf-name');
  if(en) en.innerHTML=esc(((s.prenom||'')+' '+(s.nom||'')).trim()||s.email)+' <span class="verified-badge">✓</span>';
  /* Email */
  var ee=document.getElementById('pf-email'); if(ee) ee.textContent=s.email||'—';
  /* Tel */
  var et=document.getElementById('pf-tel'); if(et) et.textContent=s.tel||'—';
  /* Adresse */
  var ea=document.getElementById('pf-adresse'); if(ea) ea.textContent=s.adresse||'—';
  /* Avatar */
  var av=localStorage.getItem(S.AVATAR);
  if(av) document.querySelectorAll('.avatar img,.avatar-photo').forEach(function(i){i.src=av;});
}


/* ── EDIT PROFILE FORM ───────────────────────────────────── */
function initEditProfile(){
  var form=document.querySelector('.edit-card');
  if(!form) return;
  var s=getSession();
  if(!s) return;
  /* Pre-fill */
  var map={nom:s.nom,prenom:s.prenom,email:s.email,tel:s.tel,adresse:s.adresse};
  Object.keys(map).forEach(function(k){
    var el=document.querySelector('[data-field="'+k+'"]');
    if(el&&map[k]) el.value=map[k];
  });
  /* Save */
  var save=document.querySelector('.btn-save');
  if(!save) return;
  save.addEventListener('click',function(e){
    e.preventDefault();
    var np=document.querySelector('[data-field="new-pwd"]');
    var cp=document.querySelector('[data-field="conf-pwd"]');
    var er=document.getElementById('kc-pwd-err');
    if(np&&np.value){
      if(np.value.length<6){if(er)er.textContent='⚠ Minimum 6 caractères.';return;}
      if(cp&&np.value!==cp.value){if(er)er.textContent='⚠ Mots de passe différents.';return;}
    }
    if(er) er.textContent='';
    var upd={};
    ['nom','prenom','email','tel','adresse'].forEach(function(k){
      var el=document.querySelector('[data-field="'+k+'"]');
      if(el) upd[k]=el.value.trim();
    });
    /* Update session */
    var ns=Object.assign({},s,upd);
    set(S.SESSION,ns);
    /* Update kc_users */
    var users=getUsers().map(function(u){
      if(u.email.toLowerCase()===s.email.toLowerCase()){
        return Object.assign({},u,upd,{motdepasse:np&&np.value?np.value:u.motdepasse});
      }
      return u;
    });
    set(S.USERS,users);
    toast('✅ Profil enregistré !');
    setTimeout(function(){location.href='profile.html';},900);
  });
}


/* ── DELETE ACCOUNT ──────────────────────────────────────── */
function initDeleteAccount(){
  var btn=document.querySelector('.btn-supprimer');
  if(!btn) return;
  btn.addEventListener('click',function(e){
    e.preventDefault();
    if(!confirm('Supprimer définitivement votre compte KidCycle ?\n\n⚠️ Cette action est irréversible.')) return;
    var s=getSession();
    if(s){
      var users=getUsers().filter(function(u){return u.email.toLowerCase()!==s.email.toLowerCase();});
      set(S.USERS,users);
    }
    del(S.SESSION); del(S.FAVS); del(S.AVATAR);
    alert('Votre compte a été supprimé. Merci d\'avoir utilisé KidCycle !');
    location.href='page acceuil.html';
  });
}


/* ── HOME TABS ───────────────────────────────────────────── */
function initTabs(){
  var map={'tout':'','bébé':'bebe','fille':'fille','garçon':'garcon','junior':'junior'};
  document.querySelectorAll('.filter-tabs .tab').forEach(function(tab){
    tab.addEventListener('click',function(){
      document.querySelectorAll('.filter-tabs .tab').forEach(function(t){t.classList.remove('active');});
      tab.classList.add('active');
      var cat=map[tab.textContent.trim().toLowerCase()]||'';
      document.querySelectorAll('.pcard,.product-card').forEach(function(c){
        if(!cat){c.style.display='';return;}
        var nm=(c.querySelector('.card-name,.pcard-name')||{}).textContent||'';
        var p=PRODUCTS.find(function(x){return x.nom===nm.trim();});
        c.style.display=(p&&p.cat===cat)?'':'none';
      });
    });
  });
}


/* ── BOOTSTRAP ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',function(){
  checkAccess();
  updateNav();
  interceptProtected();

  buildFilters();
  filtered=PRODUCTS.slice();
  applyFilters();

  initSearch();
  initTabs();
  initLogin();
  initRegister();
  loadProfileDisplay();
  initEditProfile();
  initDeleteAccount();

  renderFavsPage();
  prepareStaticHearts();
  updateHearts();
});
