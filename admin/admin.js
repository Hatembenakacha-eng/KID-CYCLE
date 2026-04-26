/* KidCycle Admin — admin.js */
var AAPI='../api';
function atok(){return localStorage.getItem('kc_admin_tok')||null;}
function auser(){try{return JSON.parse(localStorage.getItem('kc_admin'))}catch(e){return null}}
function checkAdmin(){var u=auser();if(!u||u.role!=='admin'){location.href='login.html';return null;}return u;}
function areq(method,path,data){
  var h={'Content-Type':'application/json'};
  if(atok())h['Authorization']='Bearer '+atok();
  var o={method:method,headers:h};
  if(data)o.body=JSON.stringify(data);
  return fetch(AAPI+path,o).then(function(r){return r.json();});
}
function aGET(p){return areq('GET',p);}
function aPOST(p,d){return areq('POST',p,d);}
function aPUT(p,d){return areq('PUT',p,d);}
function aDEL(p){return areq('DELETE',p);}

function toast(msg,type){
  var t=document.getElementById('a-toast');if(!t)return;
  var bg={ok:'linear-gradient(135deg,#6bbd8a,#4fa876)',err:'linear-gradient(135deg,#e04040,#c02020)',warn:'linear-gradient(135deg,#f5a623,#e59010)',info:'linear-gradient(135deg,#9b8ec4,#7d6fb0)'};
  t.style.background=bg[type||'info']||bg.info;
  t.textContent=(type==='err'?'❌ ':type==='ok'?'✅ ':type==='warn'?'⚠️ ':'ℹ️ ')+msg;
  t.classList.add('show');clearTimeout(t._x);t._x=setTimeout(function(){t.classList.remove('show');},2800);
}
function openM(id){var m=document.getElementById(id);if(m)m.classList.add('open');}
function closeM(id){var m=document.getElementById(id);if(m)m.classList.remove('open');}
function nowDate(){return new Date().toLocaleDateString('fr-FR');}
function confirm2(msg,onY){
  var old=document.getElementById('_ac');if(old)old.remove();
  var d=document.createElement('div');d.id='_ac';
  d.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(7px);z-index:19999;display:flex;align-items:center;justify-content:center';
  d.innerHTML='<div style="background:#fff;border-radius:18px;padding:30px;max-width:370px;width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center"><div style="font-size:46px;margin-bottom:12px">🤔</div><div style="font-size:14.5px;font-weight:700;color:#1a1a2e;line-height:1.5;margin-bottom:18px;white-space:pre-line">'+msg+'</div><div style="display:flex;gap:10px;justify-content:center"><button id="_ano" style="flex:1;max-width:110px;padding:10px;border-radius:9px;border:1.5px solid #e8e4f5;background:#fff;font-family:Nunito,sans-serif;font-size:13.5px;font-weight:700;cursor:pointer;color:#888">Annuler</button><button id="_aye" style="flex:1;max-width:110px;padding:10px;border-radius:9px;border:none;background:#9b8ec4;font-family:Nunito,sans-serif;font-size:13.5px;font-weight:700;cursor:pointer;color:#fff">Confirmer</button></div></div>';
  document.body.appendChild(d);
  d.querySelector('#_aye').onclick=function(){d.remove();if(onY)onY();};
  d.querySelector('#_ano').onclick=function(){d.remove();};
  d.onclick=function(e){if(e.target===d)d.remove();};
}
document.addEventListener('DOMContentLoaded',function(){
  checkAdmin();
  document.querySelectorAll('.a-modal').forEach(function(m){
    m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('open');});
    var btn=m.querySelector('.a-modal-close');
    if(btn)btn.addEventListener('click',function(){m.classList.remove('open');});
  });
});
