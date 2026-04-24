(function() {
  if (sessionStorage.getItem('kc_tutorial_seen')) return;

  var modal = document.createElement('div');
  modal.id = 'kc-tutorial-modal';
  modal.innerHTML = `
    <div id="kc-tut-overlay"></div>
    <div id="kc-tut-box">
      <button id="kc-tut-close" title="Fermer">✕</button>
      <div id="kc-tut-header">
        <div id="kc-tut-logo">KidCycle</div>
        <div id="kc-tut-subtitle">Bienvenue ! Voici comment utiliser notre plateforme</div>
      </div>
      <div id="kc-tut-screen">
        <canvas id="kc-tut-canvas" width="700" height="360"></canvas>
      </div>
      <div id="kc-tut-controls">
        <div id="kc-tut-dots"></div>
        <div id="kc-tut-btns">
          <button id="kc-tut-prev">‹ Précédent</button>
          <button id="kc-tut-next">Suivant ›</button>
        </div>
      </div>
      <div id="kc-tut-caption"></div>
      <div id="kc-tut-progress"><div id="kc-tut-bar"></div></div>
    </div>
  `;

  var style = document.createElement('style');
  style.textContent = `
    #kc-tutorial-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; }
    #kc-tut-overlay { position:absolute; inset:0; background:rgba(0,0,0,.65); backdrop-filter:blur(4px); }
    #kc-tut-box {
      position:relative; z-index:1; background:#fff; border-radius:20px;
      width:780px; max-width:96vw; padding:28px 32px 24px;
      box-shadow:0 24px 80px rgba(0,0,0,.3);
      animation:kc-slide-in .4s cubic-bezier(.22,1,.36,1);
    }
    @keyframes kc-slide-in { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:none} }
    #kc-tut-close {
      position:absolute; top:14px; right:16px; background:#f0f0f0; border:none;
      width:34px; height:34px; border-radius:8px; font-size:16px; cursor:pointer;
      color:#555; font-weight:700; transition:background .2s;
    }
    #kc-tut-close:hover { background:#e0e0e0; }
    #kc-tut-header { text-align:center; margin-bottom:16px; }
    #kc-tut-logo { font-size:22px; font-weight:900; color:#b8a9d4; letter-spacing:-1px; }
    #kc-tut-subtitle { font-size:13px; color:#888; margin-top:4px; }
    #kc-tut-screen { border-radius:12px; overflow:hidden; background:#f5f4fb; line-height:0; }
    #kc-tut-canvas { width:100%; height:auto; display:block; }
    #kc-tut-controls { display:flex; justify-content:space-between; align-items:center; margin-top:16px; }
    #kc-tut-dots { display:flex; gap:8px; }
    .kc-dot {
      width:10px; height:10px; border-radius:50%; background:#ddd; cursor:pointer; transition:background .2s, transform .2s;
    }
    .kc-dot.active { background:#b8a9d4; transform:scale(1.3); }
    #kc-tut-btns { display:flex; gap:10px; }
    #kc-tut-prev, #kc-tut-next {
      background:#b8a9d4; color:#fff; border:none; padding:9px 22px;
      border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit;
      transition:background .2s;
    }
    #kc-tut-prev:hover, #kc-tut-next:hover { background:#9e8cc4; }
    #kc-tut-prev:disabled { background:#ddd; cursor:default; }
    #kc-tut-caption { text-align:center; font-size:13px; color:#555; margin-top:14px; line-height:1.5; min-height:40px; }
    #kc-tut-progress { height:4px; background:#eee; border-radius:2px; margin-top:16px; overflow:hidden; }
    #kc-tut-bar { height:100%; background:#b8a9d4; border-radius:2px; transition:width .4s ease; }
  `;
  document.head.appendChild(style);
  document.body.appendChild(modal);

  var canvas = document.getElementById('kc-tut-canvas');
  var ctx = canvas.getContext('2d');
  var W = 700, H = 360;
  var step = 0;
  var animFrame = null;
  var t = 0;

  var STEPS = [
    {
      caption: "Bienvenue sur KidCycle ! Parcourez notre catalogue de vêtements pour enfants de qualité.",
      draw: function(t) {
        ctx.clearRect(0,0,W,H);
        var g = ctx.createLinearGradient(0,0,W,H);
        g.addColorStop(0,'#f5f4fb'); g.addColorStop(1,'#e8e4f4');
        ctx.fillStyle=g; ctx.fillRect(0,0,W,H);
        ctx.fillStyle='#b8a9d4';
        ctx.font='bold 36px Nunito,sans-serif';
        ctx.textAlign='center';
        var alpha = Math.min(1, t/30);
        ctx.globalAlpha=alpha;
        ctx.fillText('KidCycle', W/2, 80);
        ctx.font='16px Nunito,sans-serif';
        ctx.fillStyle='#888';
        ctx.fillText('Vêtements enfants de qualité', W/2, 112);
        ctx.globalAlpha=1;
        var cards = [{x:100,img:'cl1',color:'#e8f4e8'},{x:270,img:'cl2',color:'#f4e8e8'},{x:440,img:'cl3',color:'#e8e8f4'}];
        var delay = 40;
        cards.forEach(function(c, i) {
          var cardAlpha = Math.min(1, Math.max(0, (t - delay*(i+1))/30));
          ctx.globalAlpha = cardAlpha;
          var cy = 150 - 10*(1-cardAlpha);
          ctx.fillStyle=c.color; roundRect(ctx, c.x, cy, 160, 190, 12); ctx.fill();
          ctx.fillStyle='#b8a9d4'; ctx.fillRect(c.x+10,cy+10,140,120);
          ctx.fillStyle='#333'; ctx.font='bold 12px Nunito,sans-serif'; ctx.textAlign='center';
          ctx.fillText('Article enfant', c.x+80, cy+150);
          ctx.fillStyle='#f5a623'; ctx.font='bold 13px Nunito,sans-serif';
          ctx.fillText('S 34.00', c.x+80, cy+170);
        });
        ctx.globalAlpha=1;
        if(t > 120) {
          var cursorX = 290 + 30*Math.sin((t-120)*0.05);
          var cursorY = 180 + 10*Math.cos((t-120)*0.07);
          drawCursor(ctx, cursorX, cursorY);
        }
      }
    },
    {
      caption: "Cliquez sur un article pour voir tous les détails : taille, matière, état et photos du produit.",
      draw: function(t) {
        ctx.clearRect(0,0,W,H);
        ctx.fillStyle='#fafafa'; ctx.fillRect(0,0,W,H);
        ctx.fillStyle='#fff'; roundRect(ctx,60,20,580,320,16); ctx.fill();
        ctx.shadowColor='rgba(0,0,0,.08)'; ctx.shadowBlur=20; ctx.fill(); ctx.shadowBlur=0;
        ctx.fillStyle='#b8a9d4'; ctx.fillRect(70,30,200,200);
        var thumbs = [70,30,278,108,278,148,278,188];
        for(var i=0;i<3;i++) {
          ctx.fillStyle='#e0daf0'; ctx.fillRect(278,30+i*50,60,44);
        }
        ctx.fillStyle='#333'; ctx.font='bold 18px Nunito,sans-serif'; ctx.textAlign='left';
        ctx.fillText('Combinaison Velours Bébé', 355, 60);
        ctx.fillStyle='#b8a9d4'; ctx.font='13px Nunito,sans-serif';
        ctx.fillText('ID: #P-02468', 355, 82);
        ctx.fillStyle='#666'; ctx.font='12px Nunito,sans-serif';
        ctx.fillText('Velours certifié OEKO-TEX, fermeture zip.', 355, 108);
        ctx.fillText('Idéale pour bébé 0–24 mois.', 355, 126);
        ctx.fillStyle='#888'; ctx.fillText('Taille :', 355, 155);
        ['6-7','8-9','10-11','12-13'].forEach(function(s,i) {
          ctx.fillStyle= i===0?'#b8a9d4':'#f0f0f0';
          roundRect(ctx, 355+i*58, 162, 50, 30, 6); ctx.fill();
          ctx.fillStyle= i===0?'#fff':'#555'; ctx.font='bold 11px Nunito,sans-serif'; ctx.textAlign='center';
          ctx.fillText(s, 380+i*58, 182);
        });
        ctx.textAlign='left';
        ctx.fillStyle='#f5a623'; ctx.font='bold 20px Nunito,sans-serif';
        ctx.fillText('S 34.00', 355, 220);
        ctx.fillStyle='#b8a9d4'; roundRect(ctx,355,232,200,36,8); ctx.fill();
        ctx.fillStyle='#fff'; ctx.font='bold 13px Nunito,sans-serif'; ctx.textAlign='center';
        ctx.fillText('Ajouter au panier', 455, 255);
        if(t > 40) {
          var pulse = 0.8 + 0.2*Math.sin(t*0.15);
          ctx.globalAlpha=pulse;
          drawCursor(ctx, 430, 240);
          ctx.globalAlpha=1;
        }
      }
    },
    {
      caption: "Ajoutez vos articles au panier, choisissez la livraison et procédez au paiement sécurisé.",
      draw: function(t) {
        ctx.clearRect(0,0,W,H);
        ctx.fillStyle='#f5f4fb'; ctx.fillRect(0,0,W,H);
        var step3 = Math.floor(t/50);
        var steps = ['Panier','Livraison','Paiement'];
        steps.forEach(function(s,i) {
          var active = i <= step3 % 4;
          ctx.fillStyle = active ? '#b8a9d4' : '#ddd';
          ctx.beginPath(); ctx.arc(120+i*230, 55, 20, 0, Math.PI*2); ctx.fill();
          ctx.fillStyle='#fff'; ctx.font='bold 11px Nunito,sans-serif'; ctx.textAlign='center';
          ctx.fillText(i+1, 120+i*230, 60);
          ctx.fillStyle='#555'; ctx.font='12px Nunito,sans-serif';
          ctx.fillText(s, 120+i*230, 90);
          if(i<2) { ctx.fillStyle=active?'#b8a9d4':'#ddd'; ctx.fillRect(140+i*230,53,170,4); }
        });
        ctx.fillStyle='#fff'; roundRect(ctx,60,105,370,220,14); ctx.fill();
        ctx.fillStyle='#b8a9d4'; roundRect(ctx,80,120,80,80,8); ctx.fill();
        ctx.fillStyle='#333'; ctx.font='bold 13px Nunito,sans-serif'; ctx.textAlign='left';
        ctx.fillText('Combinaison Velours Bébé', 175, 148);
        ctx.fillStyle='#888'; ctx.font='12px Nunito,sans-serif'; ctx.fillText('Taille: 12 mois', 175, 168);
        ctx.fillStyle='#b8a9d4'; ctx.font='bold 14px Nunito,sans-serif'; ctx.fillText('S 34.00', 175, 190);
        ctx.fillStyle='#fff'; roundRect(ctx,455,105,220,220,14); ctx.fill();
        ctx.fillStyle='#333'; ctx.font='bold 14px Nunito,sans-serif'; ctx.textAlign='center';
        ctx.fillText('Total', 565, 135);
        ctx.fillStyle='#b8a9d4'; ctx.font='bold 20px Nunito,sans-serif'; ctx.fillText('S 90.00', 565, 168);
        ctx.fillStyle='#b8a9d4'; roundRect(ctx,470,200,195,40,10); ctx.fill();
        ctx.fillStyle='#fff'; ctx.font='bold 13px Nunito,sans-serif'; ctx.fillText('Passer au paiement', 565, 225);
        if(t>60) { ctx.globalAlpha=0.8+0.2*Math.sin(t*0.12); drawCursor(ctx,565,218); ctx.globalAlpha=1; }
      }
    },
    {
      caption: "Créez votre profil pour suivre vos commandes, gérer vos favoris et vos annonces facilement.",
      draw: function(t) {
        ctx.clearRect(0,0,W,H);
        var g2=ctx.createLinearGradient(0,0,W,H); g2.addColorStop(0,'#f5f4fb'); g2.addColorStop(1,'#ede8f8');
        ctx.fillStyle=g2; ctx.fillRect(0,0,W,H);
        ctx.fillStyle='#fff'; roundRect(ctx,40,20,200,320,14); ctx.fill();
        ctx.fillStyle='#e8e4f4'; ctx.beginPath(); ctx.arc(140,80,36,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#b8a9d4'; ctx.font='bold 14px Nunito,sans-serif'; ctx.textAlign='center';
        ctx.fillText('Sophie M.', 140, 130);
        ctx.fillStyle='#888'; ctx.font='11px Nunito,sans-serif'; ctx.fillText('Membre vérifié', 140, 148);
        var menu=['Mon profil','Mes commandes','Mes favoris','Mon panier'];
        menu.forEach(function(m,i) {
          ctx.fillStyle= i===0?'#f0eef8':'transparent';
          if(i===0){roundRect(ctx,55,170+i*42,170,36,8);ctx.fill();}
          ctx.fillStyle= i===0?'#b8a9d4':'#666';
          ctx.font=(i===0?'bold ':'')+'12px Nunito,sans-serif'; ctx.textAlign='center';
          ctx.fillText(m, 140, 192+i*42);
        });
        ctx.fillStyle='#fff'; roundRect(ctx,265,20,400,320,14); ctx.fill();
        ctx.fillStyle='#b8a9d4'; ctx.font='bold 16px Nunito,sans-serif'; ctx.textAlign='left';
        ctx.fillText('Mes commandes récentes', 285, 55);
        var orders=[{n:'Combinaison Velours Bébé',s:'Livré',c:'#4caf50'},{n:'Robe Fleurie Fille',s:'En cours',c:'#f5a623'},{n:'Veste Matelassée Garçon',s:'En préparation',c:'#b8a9d4'}];
        orders.forEach(function(o,i){
          var oy = 75+i*82;
          ctx.fillStyle='#fafafa'; roundRect(ctx,280,oy,370,72,10); ctx.fill();
          ctx.fillStyle='#b8a9d4'; roundRect(ctx,290,oy+8,54,54,8); ctx.fill();
          ctx.fillStyle='#333'; ctx.font='bold 12px Nunito,sans-serif'; ctx.fillText(o.n, 358, oy+26);
          ctx.fillStyle=o.c; roundRect(ctx,358,oy+36,80,22,12); ctx.fill();
          ctx.fillStyle='#fff'; ctx.font='bold 10px Nunito,sans-serif'; ctx.textAlign='center';
          ctx.fillText(o.s, 398, oy+51);
          ctx.textAlign='left';
        });
        if(t>30){ ctx.globalAlpha=0.9; drawCursor(ctx,140,208); ctx.globalAlpha=1; }
      }
    }
  ];

  function roundRect(ctx,x,y,w,h,r){
    ctx.beginPath(); ctx.moveTo(x+r,y);
    ctx.lineTo(x+w-r,y); ctx.quadraticCurveTo(x+w,y,x+w,y+r);
    ctx.lineTo(x+w,y+h-r); ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
    ctx.lineTo(x+r,y+h); ctx.quadraticCurveTo(x,y+h,x,y+h-r);
    ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y);
    ctx.closePath();
  }

  function drawCursor(ctx,x,y){
    ctx.fillStyle='rgba(0,0,0,.7)';
    ctx.beginPath(); ctx.moveTo(x,y); ctx.lineTo(x+14,y+14); ctx.lineTo(x+6,y+14); ctx.lineTo(x+10,y+22); ctx.lineTo(x+6,y+22); ctx.lineTo(x+2,y+14); ctx.lineTo(x-4,y+14); ctx.closePath(); ctx.fill();
  }

  function buildDots(){
    var dotsEl = document.getElementById('kc-tut-dots');
    dotsEl.innerHTML='';
    STEPS.forEach(function(_,i){
      var d=document.createElement('div'); d.className='kc-dot'+(i===step?' active':'');
      d.addEventListener('click',function(){goTo(i);});
      dotsEl.appendChild(d);
    });
  }

  function updateUI(){
    document.getElementById('kc-tut-caption').textContent = STEPS[step].caption;
    document.getElementById('kc-tut-prev').disabled = step===0;
    document.getElementById('kc-tut-next').textContent = step===STEPS.length-1 ? 'Commencer !' : 'Suivant ›';
    document.getElementById('kc-tut-bar').style.width = ((step+1)/STEPS.length*100)+'%';
    buildDots();
  }

  function goTo(i){
    step=i; t=0; updateUI();
    cancelAnimationFrame(animFrame);
    animate();
  }

  function animate(){
    STEPS[step].draw(t);
    t++;
    animFrame = requestAnimationFrame(animate);
  }

  document.getElementById('kc-tut-next').addEventListener('click',function(){
    if(step===STEPS.length-1){ closeModal(); return; }
    goTo(step+1);
  });
  document.getElementById('kc-tut-prev').addEventListener('click',function(){
    if(step>0) goTo(step-1);
  });
  document.getElementById('kc-tut-close').addEventListener('click', closeModal);
  document.getElementById('kc-tut-overlay').addEventListener('click', closeModal);

  function closeModal(){
    cancelAnimationFrame(animFrame);
    sessionStorage.setItem('kc_tutorial_seen','1');
    modal.style.opacity='0'; modal.style.transition='opacity .3s';
    setTimeout(function(){ modal.remove(); }, 300);
  }

  updateUI();
  animate();
})();
