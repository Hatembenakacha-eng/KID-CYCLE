/* ═══════════════════════════════════════════════════════════════
   KidCycle — app.js  |  Moteur JS global
   ═══════════════════════════════════════════════════════════════ */
;(function(window){
'use strict';

var KC = {};

/* ── Config ─────────────────────────────────────────────────── */
KC.API = 'api';

/* ── Storage ────────────────────────────────────────────────── */
KC.store = {
  get: function(k){ try{ return JSON.parse(localStorage.getItem(k)) }catch(e){ return null } },
  set: function(k,v){ localStorage.setItem(k, JSON.stringify(v)) },
  del: function(k){ localStorage.removeItem(k) }
};

/* ── Auth ───────────────────────────────────────────────────── */
KC.tok  = function(){ return localStorage.getItem('kc_tok') || null };
KC.user = function(){ return KC.store.get('kc_user') };
KC.isLogged = function(){ return !!KC.tok() && !!KC.user() };

KC.setSession = function(user, token){
  KC.store.set('kc_user', user);
  localStorage.setItem('kc_tok', token);
};
KC.logout = function(){
  KC.store.del('kc_user'); KC.store.del('kc_cart'); KC.store.del('kc_favs');
  localStorage.removeItem('kc_tok');
};

/* ── API Request ────────────────────────────────────────────── */
KC.req = function(method, path, data){
  var h = { 'Content-Type': 'application/json' };
  if(KC.tok()) h['Authorization'] = 'Bearer ' + KC.tok();
  var opts = { method: method, headers: h };
  if(data) opts.body = JSON.stringify(data);
  return fetch(KC.API + path, opts).then(function(r){ return r.json(); });
};
KC.GET  = function(p){ return KC.req('GET',    p); };
KC.POST = function(p,d){ return KC.req('POST',  p, d); };
KC.PUT  = function(p,d){ return KC.req('PUT',   p, d); };
KC.DEL  = function(p){ return KC.req('DELETE', p); };

/* ── Image helpers ─────────────────────────────────────────── */
KC.img = function(src){
  var s = (src || '').toString().trim();
  if(!s) return 'photos/cl1.png';

  s = s.replace(/\\/g, '/');

  // Fix legacy/wrong prefixes stored in DB (images/* should be photos/*)
  if(/^images\//i.test(s)) s = s.replace(/^images\//i, 'photos/');

  // Convert absolute URLs that point to this local project uploads folder
  if(/^https?:\/\//i.test(s) && /\/uploads\//i.test(s)){
    var m = s.match(/\/uploads\/.*/i);
    if(m && m[0]) s = m[0].replace(/^\//, '');
  }                                                        

  // Keep valid absolute URLs (CDN, etc.) as-is
  if(/^https?:\/\//i.test(s)) return s;

  // Strip leading ./ or /
  s = s.replace(/^\.?\//, '');

  if(/^photos\//i.test(s) || /^uploads\//i.test(s)) return s;

  // If only filename is provided, assume project photos folder
  if(/\.(png|jpe?g|svg|webp|gif)$/i.test(s)) return 'photos/' + s.split('/').pop();

  return 'photos/cl1.png';
};

/* ── Cart ───────────────────────────────────────────────────── */
KC.getCart  = function(){ return KC.store.get('kc_cart') || []; };
KC.saveCart = function(c){ KC.store.set('kc_cart', c); KC.updateBadge(); };
KC.cartCount = function(){ return KC.getCart().reduce(function(s,x){ return s+(x.quantite||1); },0); };

KC.addCart = function(prod, taille, couleur, qty){
  if(!KC.requireLogin('ajouter au panier')) return false;
  taille = taille||'M'; couleur = couleur||'Standard'; qty = qty||1;
  var c = KC.getCart();
  var idx = c.findIndex(function(x){ return x.produit_id===prod.id && x.taille===taille; });
  if(idx>=0) c[idx].quantite = (c[idx].quantite||1) + qty;
  else c.push({ produit_id:prod.id, nom:prod.nom, prix:prod.prix, image:KC.img(prod.image), taille:taille, couleur:couleur, quantite:qty });
  KC.saveCart(c);
  KC.toast(prod.nom + ' ajouté au panier !', 'ok');
  KC.playSound();
  // Sync with server
  if(KC.tok()){
    KC.POST('/panier.php', { produit_id:prod.id, taille:taille, couleur:couleur, quantite:qty }).catch(function(){});
  }
  return true;
};

KC.updateBadge = function(){
  var n = KC.cartCount();
  document.querySelectorAll('.cart-badge, .js-cart-count').forEach(function(el){ el.textContent = n; });
};

/* ── Favoris ─────────────────────────────────────────────────── */
KC.getFavs  = function(){ return KC.store.get('kc_favs') || []; };
KC.saveFavs = function(f){ KC.store.set('kc_favs', f); };
KC.isFav    = function(id){ return KC.getFavs().some(function(f){ return (f.produit_id||f.id)==id; }); };

KC.toggleFav = function(prod, btn){
  if(!KC.requireLogin('gérer vos favoris')) return;
  var f = KC.getFavs();
  var idx = f.findIndex(function(x){ return (x.produit_id||x.id)==prod.id; });
  var added;
  if(idx>=0){
    f.splice(idx,1); KC.toast('Retiré des favoris', 'info'); added=false;
  } else {
    f.push({ produit_id:prod.id, nom:prod.nom, prix:prod.prix, image:KC.img(prod.image) }); KC.toast(prod.nom+' ajouté aux favoris !','ok'); added=true;
  }
  KC.saveFavs(f);
  
  if(btn){
    var img = btn.querySelector('img');
    if(img) img.src = added ? 'photos/icon-heart-fill.svg' : 'photos/icon-heart.svg';
    btn.classList.toggle('active', added);
    // Animate
    btn.style.animation='none'; requestAnimationFrame(function(){ btn.style.animation='heartBeat .5s ease'; });
  }
  if(KC.tok()){
    KC.POST('/favoris.php', { produit_id:prod.id }).catch(function(){});
  }
};

/* ── Sound ──────────────────────────────────────────────────── */
KC.playSound = function(){
  try{
    var ctx = new (window.AudioContext||window.webkitAudioContext)();
    [[523,.08],[659,.06],[784,.05]].forEach(function(f,i){
      setTimeout(function(){
        var o=ctx.createOscillator(), g=ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        o.type='sine'; o.frequency.value=f[0];
        g.gain.setValueAtTime(f[1],ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+.2);
        o.start(); o.stop(ctx.currentTime+.2);
      }, i*90);
    });
  }catch(e){}
};

/* ── Toast ──────────────────────────────────────────────────── */
KC.toast = function(msg, type){
  var t = document.getElementById('kc-toast');
  if(!t){ t=document.createElement('div'); t.id='kc-toast'; document.body.appendChild(t); }
  var bg={ok:'linear-gradient(135deg,#6bbd8a,#4fa876)',err:'linear-gradient(135deg,#e04040,#c02020)',warn:'linear-gradient(135deg,#f5a623,#e59010)',info:'linear-gradient(135deg,#9b8ec4,#7d6fb0)'};
  t.style.cssText='position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(90px);padding:13px 26px;border-radius:14px;font-family:Nunito,sans-serif;font-size:14px;font-weight:700;color:#fff;z-index:99999;opacity:0;pointer-events:none;transition:all .45s cubic-bezier(.34,1.56,.64,1);white-space:nowrap;box-shadow:0 10px 36px rgba(0,0,0,.22);background:'+(bg[type||'info']||bg.info);
  t.textContent = (type==='err'?'❌ ':type==='ok'?'✅ ':type==='warn'?'⚠️ ':'ℹ️ ') + msg;
  t.style.transform='translateX(-50%) translateY(0)'; t.style.opacity='1';
  clearTimeout(t._x); t._x=setTimeout(function(){t.style.transform='translateX(-50%) translateY(90px)';t.style.opacity='0';},2800);
};

/* ── Confirm ────────────────────────────────────────────────── */
KC.confirm = function(msg, onY, onN){
  if(window.confirm(msg)) { if(onY) onY(); }
  else { if(onN) onN(); }
};

/* ── Require login ──────────────────────────────────────────── */
KC.requireLogin = function(action){
  if(KC.isLogged()) return true;
  KC.confirm('Pour '+action+',\nvous devez être connecté(e).', function(){
    sessionStorage.setItem('kc_redirect', location.href);
    location.href='connexion.html';
  });
  return false;
};

/* ── Render product card ────────────────────────────────────── */
KC.prodCard = function(p){
  var img = KC.img(p.image);
  var prixAff = p.prix_solde ? parseFloat(p.prix_solde) : parseFloat(p.prix);
  var isFaved = KC.isFav(p.id);
  return '<div class="prod-card" onclick="location.href=\'detail.html?id='+p.id+'\'">'
    +(p.badge?'<span class="prod-badge">'+p.badge+'</span>':'')
    +(p.prix_solde?'<span class="prod-badge sale" style="left:auto;right:10px;">Promo</span>':'')
    +'<div class="prod-card-img-wrap">'
      +'<img src="'+img+'" alt="'+p.nom+'" loading="lazy" onerror="this.src=\'photos/cl1.png\';this.onerror=null;">'
      +'<button class="prod-card-fav'+(isFaved?' active':'')+'" data-id="'+p.id+'" data-nom="'+encodeURIComponent(p.nom||'')+'" data-prix="'+(p.prix_solde||p.prix)+'" data-img="'+img+'" onclick="event.stopPropagation();KC._favBtn(this)">'
        +'<img src="photos/'+(isFaved?'icon-heart-fill':'icon-heart')+'.svg">'
      +'</button>'
    +'</div>'
    +'<div class="prod-card-body">'
      +'<div class="prod-card-name">'+p.nom+'</div>'
      +'<div class="prod-card-sub">'+(p.description||p.categorie_nom||'')+'</div>'
      +'<div class="prod-card-bottom">'
        +'<div class="prod-card-price">'
          +(p.prix_solde?'<span class="prod-old-price">'+parseFloat(p.prix).toFixed(2)+'</span>':'')
          +'<span class="swaps-coin">ii</span> '+prixAff.toFixed(2)
        +'</div>'
        +'<button class="prod-card-btn" data-id="'+p.id+'" data-nom="'+encodeURIComponent(p.nom||'')+'" data-prix="'+(p.prix_solde||p.prix)+'" data-img="'+img+'" onclick="event.stopPropagation();KC._cartBtn(this)">Ajouter au panier</button>'
      +'</div>'
    +'</div>'
    +'</div>';
};

KC._favBtn = function(btn){
  var prod={id:parseInt(btn.dataset.id),nom:decodeURIComponent(btn.dataset.nom||''),prix:btn.dataset.prix,image:btn.dataset.img};
  KC.toggleFav(prod, btn);
};
KC._cartBtn = function(btn){
  var prod={id:parseInt(btn.dataset.id),nom:decodeURIComponent(btn.dataset.nom||''),prix:btn.dataset.prix,image:btn.dataset.img};
  if(KC.addCart(prod,'M','Standard',1)){
    var orig=btn.textContent; btn.textContent='✓ Ajouté !'; btn.classList.add('added');
    setTimeout(function(){ btn.textContent=orig; btn.classList.remove('added'); },2000);
  }
};

/* ── Bind product cards ─────────────────────────────────────── */
KC.bindCards = function(container){ /* Cards use inline onclick — nothing extra needed */ };

/* ── Search ─────────────────────────────────────────────────── */
KC.initSearch = function(inputId, ddId){
  var inp = document.getElementById(inputId);
  var dd  = document.getElementById(ddId);
  if(!inp || !dd) return;
  var timer;
  inp.addEventListener('input', function(){
    clearTimeout(timer);
    var q = inp.value.trim();
    if(q.length<2){ dd.classList.remove('open'); return; }
    timer = setTimeout(function(){
      KC.GET('/misc.php?action=search&q='+encodeURIComponent(q))
      .then(function(r){
        if(!r.ok||!r.data.length){ dd.classList.remove('open'); return; }
        dd.innerHTML = r.data.slice(0,6).map(function(p){
          var img = KC.img(p.image);
          return '<div class="search-item" onclick="location.href=\'detail.html?id='+p.id+'\'">'
            +'<img src="'+img+'" alt="'+p.nom+'" onerror="this.src=\'photos/cl1.png\';this.onerror=null;">'
            +'<div class="search-item-info"><div class="search-item-name">'+p.nom+'</div>'
            +'<div class="search-item-price"><span class="swaps-coin" style="width:16px;height:16px;font-size:8px">ii</span> '+parseFloat(p.prix_solde||p.prix).toFixed(2)+'</div></div>'
            +'</div>';
        }).join('');
        dd.classList.add('open');
      }).catch(function(){});
    }, 280);
  });
  document.addEventListener('click', function(e){ if(!inp.closest('.nav-search-wrap').contains(e.target)) dd.classList.remove('open'); });
  inp.addEventListener('keydown', function(e){ if(e.key==='Enter'&&inp.value.trim()) location.href='nouveautes.html?q='+encodeURIComponent(inp.value.trim()); });
};

/* ── Init navbar ────────────────────────────────────────────── */
KC.initNav = function(){
  KC.updateBadge();
  var u = KC.user();
  // Scroll effect
  window.addEventListener('scroll', function(){
    var nb = document.querySelector('.navbar');
    if(nb) nb.classList.toggle('scrolled', window.scrollY>10);
  });
  // Sync from server
  if(KC.isLogged()){
    KC.GET('/panier.php').then(function(r){ if(r.ok&&r.data){ KC.saveCart(r.data); } }).catch(function(){});
    KC.GET('/favoris.php').then(function(r){ if(r.ok&&r.data){ KC.saveFavs(r.data); } }).catch(function(){});
  }
};

/* ── Intersection observer for animations ───────────────────── */
KC.initAnimations = function(){
  if(!('IntersectionObserver' in window)) return;
  var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); } });
  },{ threshold:.1, rootMargin:'0px 0px -50px 0px' });
  document.querySelectorAll('.fade-up').forEach(function(el){ obs.observe(el); });
};

/* ── Spinner button ──────────────────────────────────────────── */
KC.spin  = function(btn){ if(btn){ btn.disabled=true; } };
KC.unspin= function(btn){ if(btn){ btn.disabled=false; } };

/* ── Parallax on mouse move ──────────────────────────────────── */
KC.initParallax = function(){ };

/* ── Touch ripple effect ─────────────────────────────────────── */
KC.initRipple = function(){ };

window.KC = KC;
})(window);
