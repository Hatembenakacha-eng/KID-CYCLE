/* ═══════════════════════════════════════════════════════════
   KidCycle — app.js
   Stack : HTML + CSS + JS pur. Aucun framework.
   Storage : localStorage (kc_users / kc_session / kc_favs / kc_avatar)
   ═══════════════════════════════════════════════════════════ */

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


/* ── SOUND ───────────────────────────────────────────────── */
(function(){
  var ac=null;
  function ctx(){ if(!ac) ac=new(window.AudioContext||window.webkitAudioContext)(); return ac; }
  function beep(f,v,d){ try{ var c=ctx(),o=c.createOscillator(),g=c.createGain(); o.connect(g); g.connect(c.destination); o.type='sine'; o.frequency.value=f; g.gain.setValueAtTime(v,c.currentTime); g.gain.exponentialRampToValueAtTime(.001,c.currentTime+d); o.start(); o.stop(c.currentTime+d); }catch(e){} }
  function cart(){ [523,659,784].forEach(function(f,i){ setTimeout(function(){ beep(f,.1,.18); },i*100); }); }
  document.addEventListener('click',function(e){
    var t=e.target;
    if(t.closest('.btn-add,.pcard-btn,.btn-cart-add,.btn-payer,.btn-paye')){ cart(); return; }
    if(t.closest('a,button,label,input[type=checkbox],input[type=radio],.tab,.page-btn')){ beep(1100,.05,.07); }
  },{passive:true});
})();


/* ── PRODUCTS ────────────────────────────────────────────── */
var PRODUCTS = [
  {id:1, nom:'Combinaison Velours Bébé',   desc:'Velours doux certifié OEKO-TEX · 0–24 mois',          img:'cl1.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'tres-bon', prix:34, badge:'Nouveau'},
  {id:2, nom:'Robe Fleurie Fille',          desc:'Coton biologique · imprimé fleuri · 2–8 ans',           img:'cl2.png', cat:'fille',  genre:'fille',   taille:'2-3', etat:'bon',      prix:28, badge:'Tendance'},
  {id:3, nom:'Veste Matelassée Garçon',     desc:'Doublure chaude · coupe ajustée · 3–10 ans',            img:'cl3.png', cat:'garcon', genre:'garcon',  taille:'3-4', etat:'tres-bon', prix:45, badge:''},
  {id:4, nom:'Pyjama 2 Pièces Étoiles',     desc:'Coton doux certifié OEKO-TEX · 6 mois–4 ans',           img:'cl4.png', cat:'bebe',   genre:'unisexe', taille:'1-2', etat:'tres-bon', prix:22, badge:'Populaire'},
  {id:5, nom:'Ensemble Jogger Enfant',       desc:'Sweat zippé + pantalon coordonné · 4–12 ans',           img:'cl5.png', cat:'junior', genre:'unisexe', taille:'3-4', etat:'bon',      prix:38, badge:'Coup de cœur'},
  {id:6, nom:'Manteau Capuche Enfant',       desc:'Imperméable déperlant · capuche amovible · 2–10 ans',  img:'cl6.png', cat:'fille',  genre:'fille',   taille:'2-3', etat:'tres-bon', prix:52, badge:''},
  {id:7, nom:'Robe de Soirée Fille',         desc:'Dentelle et satin · occasion spéciale · 4–12 ans',      img:'cl1.png', cat:'fille',  genre:'fille',   taille:'3-4', etat:'bon',      prix:41, badge:'Exclusif'},
  {id:8, nom:'Doudoune Légère Enfant',       desc:'Garnissage ultra-léger · chaud sans alourdir · 2–10 ans', img:'cl2.png',cat:'junior',genre:'garcon', taille:'3-4', etat:'tres-bon', prix:49, badge:'Top vente'},
  {id:9, nom:'Salopette Denim Enfant',       desc:'100% coton denim · poches multiples · 3–12 ans',        img:'cl3.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'bon',      prix:31, badge:''},
  {id:10,nom:'Cardigan Bébé Pompon',         desc:'Laine douce · pompons colorés · 0–18 mois',             img:'cl4.png', cat:'bebe',   genre:'fille',   taille:'0-1', etat:'tres-bon', prix:26, badge:'Nouveau'},
  {id:11,nom:'Short Bermuda Garçon',         desc:'Coton léger · taille élastique · 2–10 ans',              img:'cl5.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'bon',      prix:19, badge:''},
  {id:12,nom:'Robe Smock Fille',             desc:'Broderie artisanale · coton doux · 6 mois–6 ans',        img:'cl6.png', cat:'fille',  genre:'fille',   taille:'1-2', etat:'tres-bon', prix:33, badge:'Recommandé'},
  {id:13,nom:'Parka Imperméable Junior',     desc:'Traitement déperlant · fermeture zip · 8–14 ans',        img:'cl1.png', cat:'junior', genre:'unisexe', taille:'3-4', etat:'tres-bon', prix:58, badge:''},
  {id:14,nom:'Legging Coton Bébé',           desc:'100% coton bio · taille réglable · 0–18 mois',           img:'cl2.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'bon',      prix:14, badge:''},
  {id:15,nom:'Sweat Capuche Animaux',        desc:'Molleton bio · motifs animaux · 2–10 ans',               img:'cl3.png', cat:'garcon', genre:'garcon',  taille:'2-3', etat:'tres-bon', prix:29, badge:'Coup de cœur'},
  {id:16,nom:'Robe Tutu Ballerine',          desc:'Satin et tulle · jupe évasée · 1–8 ans',                 img:'cl4.png', cat:'fille',  genre:'fille',   taille:'1-2', etat:'bon',      prix:24, badge:'Tendance'},
  {id:17,nom:'Ensemble 3 Pièces Bébé',       desc:'Velours côtelé · body + pantalon + veste · 0–24 mois',  img:'cl5.png', cat:'bebe',   genre:'unisexe', taille:'0-1', etat:'tres-bon', prix:39, badge:'Nouveau'},
  {id:18,nom:'Jean Slim Enfant',             desc:'Denim stretch · coupe slim moderne · 8–14 ans',          img:'cl6.png', cat:'junior', genre:'garcon',  taille:'3-4', etat:'bon',      prix:27, badge:''}
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

  var drop = document.createElement('div');
  drop.id = 'kc-drop';
  drop.style.cssText = 'display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;' +
    'background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.15);' +
    'z-index:99999;max-height:360px;overflow-y:auto;border:1px solid #f0eef8;';
  inp.closest('.search-wrap').style.position = 'relative';
  inp.closest('.search-wrap').appendChild(drop);

  inp.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    if (!q) { drop.style.display='none'; return; }
    var words = q.split(/\s+/).filter(Boolean);
    var res = PRODUCTS.filter(function(p){
      return words.every(function(w){
        return p.nom.toLowerCase().split(/\s+/).some(function(part){ return part.startsWith(w); });
      });
    });
    drop.innerHTML = res.length ? res.map(function(p){
      return '<a href="detail produit.html" style="display:flex;align-items:center;gap:12px;'+
        'padding:10px 16px;text-decoration:none;color:#333;border-bottom:1px solid #f5f5f5;'+
        'transition:background .15s" onmouseover="this.style.background=\'#f5f4fb\'" onmouseout="this.style.background=\'\'">' +
        '<img src="'+p.img+'" style="width:42px;height:42px;object-fit:contain;background:#f5f4fb;padding:4px;border-radius:8px;flex-shrink:0">' +
        '<div style="flex:1;min-width:0"><div style="font-weight:700;font-size:13px">'+hilite(p.nom,words)+'</div>'+
        '<div style="color:#aaa;font-size:11px;margin-top:2px">'+p.desc+'</div></div>'+
        '<span style="color:#b8a9d4;font-weight:800;font-size:13px;white-space:nowrap">'+p.prix+' S</span></a>';
    }).join('') : '<div style="padding:14px;text-align:center;color:#aaa;font-size:13px">Aucun résultat pour "'+esc(this.value)+'"</div>';
    drop.style.display='block';
  });
  document.addEventListener('click', function(e){ if(!inp.closest('.search-wrap').contains(e.target)) drop.style.display='none'; });
}
function hilite(s,words){ words.forEach(function(w){ s=s.replace(new RegExp('('+w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','gi'),'<mark style="background:#f0eef8;color:#b8a9d4;padding:0 2px;border-radius:3px">$1</mark>'); }); return s; }


/* ── FILTERS + GRID + PAGINATION ─────────────────────────── */
function buildFilters() {
  var panel = document.querySelector('.filters-panel');
  if (!panel) return;
  panel.innerHTML =
    '<div class="filters-top"><span class="filters-title">Filtres</span>'+
    '<button class="btn-clear" type="button" onclick="resetFilters()">Tout effacer 🗑</button></div>'+
    fg('cat','Catégorie',[{v:'bebe',l:'Bébé (0–2 ans)',n:5},{v:'fille',l:'Fille (2–8 ans)',n:6},{v:'garcon',l:'Garçon (2–8 ans)',n:5},{v:'junior',l:'Junior (8–14 ans)',n:4}])+
    fg('genre','Genre',[{v:'fille',l:'Fille'},{v:'garcon',l:'Garçon'},{v:'unisexe',l:'Unisexe'}])+
    fg('taille','Taille',[{v:'0-1',l:'0–1 an',n:5},{v:'1-2',l:'1–2 ans',n:4},{v:'2-3',l:'2–3 ans',n:5},{v:'3-4',l:'3–4 ans',n:6}])+
    fg('etat','État',[{v:'tres-bon',l:'Très bon état'},{v:'bon',l:'Bon état'}])+
    '<div class="fg"><div class="fg-head" style="border-bottom:none"><span class="fg-label">Prix max</span></div>'+
    '<div class="fg-opts" style="padding:10px 14px 14px">'+
    '<input type="range" id="kc-prix" min="10" max="100" value="100" style="width:100%;accent-color:#b8a9d4" '+
    'oninput="document.getElementById(\'kp\').textContent=this.value+\' S\';curPage=1;applyFilters()">'+
    '<div style="text-align:right;font-size:11px;color:#b8a9d4;font-weight:700;margin-top:4px">Max : <span id="kp">100 S</span></div>'+
    '</div></div>';
  panel.addEventListener('change',function(e){ if(e.target.type==='checkbox'){curPage=1;applyFilters();} });
}
function fg(k,label,opts){
  var id='fg-'+k;
  return '<div class="fg"><input type="checkbox" id="'+id+'" class="fg-toggle">'+
    '<label for="'+id+'" class="fg-head"><span class="fg-label">'+label+'</span><span class="chevron">▲</span></label>'+
    '<div class="fg-opts">'+opts.map(function(o){
      return '<div class="fg-row"><label><input type="checkbox" data-f="'+k+'" value="'+o.v+'"> '+o.l+'</label>'+(o.n?'<span class="fg-count">('+o.n+')</span>':'')+'</div>';
    }).join('')+'</div></div>';
}
function checked(k){ return [].slice.call(document.querySelectorAll('input[data-f="'+k+'"]:checked')).map(function(e){return e.value;}); }
function applyFilters(){
  var cats=checked('cat'),genres=checked('genre'),tailles=checked('taille'),etats=checked('etat');
  var max=parseInt((document.getElementById('kc-prix')||{value:'9999'}).value,10);
  filtered=PRODUCTS.filter(function(p){
    if(cats.length    && cats.indexOf(p.cat)<0)      return false;
    if(genres.length  && genres.indexOf(p.genre)<0)  return false;
    if(tailles.length && tailles.indexOf(p.taille)<0)return false;
    if(etats.length   && etats.indexOf(p.etat)<0)    return false;
    if(p.prix>max) return false;
    return true;
  });
  var cpt=document.querySelector('.info-bar span');
  if(cpt) cpt.textContent='Affichage de '+filtered.length+' résultats sur '+PRODUCTS.length;
  showPage(1);
}
function resetFilters(){
  document.querySelectorAll('.filters-panel input[type=checkbox]').forEach(function(c){c.checked=false;});
  var sl=document.getElementById('kc-prix');
  if(sl){sl.value=100; var kp=document.getElementById('kp'); if(kp)kp.textContent='100 S';}
  curPage=1; filtered=PRODUCTS.slice(); applyFilters();
}
function cardHTML(p,i){
  var fav=getFavs().some(function(f){return f.id===p.id;});
  var coeur=fav?'images/icon-heart-fill.svg':'images/icon-heart.svg';
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
  /* Pagination */
  var pag=document.querySelector('.pagination');
  if(!pag) return;
  var html='<a href="#" class="page-btn arrow kp-btn"'+(curPage===1?' style="opacity:.4;pointer-events:none"':'')+' data-p="'+(curPage-1)+'">‹ Précédent</a>';
  for(var i=1;i<=pages;i++) html+='<a href="#" class="page-btn kp-btn'+(i===curPage?' active':'')+'" data-p="'+i+'">'+i+'</a>';
  html+='<a href="#" class="page-btn arrow kp-btn"'+(curPage===pages?' style="opacity:.4;pointer-events:none"':'')+' data-p="'+(curPage+1)+'">Suivant ›</a>';
  pag.innerHTML=html;
  pag.querySelectorAll('.kp-btn').forEach(function(b){
    b.addEventListener('click',function(e){
      e.preventDefault();
      var p=parseInt(this.dataset.p,10);
      if(p<1||p>pages)return;
      showPage(p);
      grid.scrollIntoView({behavior:'smooth',block:'start'});
    });
  });
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
    list.push({id:id,nom:nom,img:img,prix:prix});
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
    if(img) img.src=ids.indexOf(id)>=0?'images/icon-heart-fill.svg':'images/icon-heart.svg';
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
      var img  = card?(card.dataset.img||'cl1.png'):'cl1.png';
      var prix = card?parseFloat(card.dataset.prix||0):0;
      toggleFav(id, nom.trim(), img.split('/').pop(), prix);
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
          '<img src="images/icon-heart-fill.svg" alt="retirer" style="width:14px;height:14px"></button>'+
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
  /* Avatar */
  var av=localStorage.getItem(S.AVATAR);
  if(av) document.querySelectorAll('.avatar img,.avatar-photo').forEach(function(i){i.src=av;});

  /* Avatar picker — edit button OR click on avatar */
  function openPicker(){
    var inp=document.createElement('input'); inp.type='file'; inp.accept='image/*';
    inp.addEventListener('change',function(){
      if(!inp.files[0]) return;
      var fr=new FileReader();
      fr.addEventListener('load',function(ev){
        var data=ev.target.result;
        document.querySelectorAll('.avatar img,.avatar-photo').forEach(function(i){i.src=data;});
        try{ localStorage.setItem(S.AVATAR,data); toast('📸 Photo mise à jour !'); }
        catch(e){ toast('Image trop grande. Choisissez une image plus petite.',false); }
      });
      fr.readAsDataURL(inp.files[0]);
    });
    inp.click();
  }
  var eb=document.querySelector('.avatar-edit');
  if(eb){ eb.style.cursor='pointer'; eb.addEventListener('click',openPicker); }
  var av2=document.querySelector('.avatar');
  if(av2&&!eb){ av2.style.cursor='pointer'; av2.title='Changer la photo'; av2.addEventListener('click',openPicker); }

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
