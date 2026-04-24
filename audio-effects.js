(function() {
  var AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return;
  var ctx = null;

  function getCtx() {
    if (!ctx) ctx = new AC();
    return ctx;
  }

  function playTone(type, freq, vol, dur, decay) {
    try {
      var ac = getCtx();
      var osc = ac.createOscillator();
      var gain = ac.createGain();
      osc.connect(gain); gain.connect(ac.destination);
      osc.type = type; osc.frequency.value = freq;
      gain.gain.setValueAtTime(vol, ac.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + (decay||dur));
      osc.start(ac.currentTime); osc.stop(ac.currentTime + dur);
    } catch(e){}
  }

  function playChord(freqs, vol, dur) {
    freqs.forEach(function(f){ playTone('sine', f, vol/freqs.length, dur, dur*0.8); });
  }

  window.KC_AUDIO = {
    click: function() {
      playTone('sine', 880, 0.15, 0.08, 0.06);
    },
    addToCart: function() {
      playTone('sine', 523, 0.2, 0.12);
      setTimeout(function(){ playTone('sine', 659, 0.2, 0.12); }, 80);
      setTimeout(function(){ playTone('sine', 784, 0.2, 0.18); }, 160);
    },
    favorite: function() {
      playTone('sine', 659, 0.18, 0.1);
      setTimeout(function(){ playTone('sine', 880, 0.18, 0.15); }, 100);
    },
    success: function() {
      [523,659,784,1047].forEach(function(f,i){
        setTimeout(function(){ playTone('sine', f, 0.15, 0.2); }, i*90);
      });
    },
    notification: function() {
      playChord([440, 554, 659], 0.2, 0.3);
    },
    hover: function() {
      playTone('sine', 660, 0.05, 0.04, 0.03);
    }
  };

  function attachSounds() {
    document.querySelectorAll('.pcard-btn, .btn-add, .btn-cart-add').forEach(function(btn) {
      btn.addEventListener('click', function() { KC_AUDIO.addToCart(); });
    });
    document.querySelectorAll('.pcard-heart, .heart-btn, .heart-lbl').forEach(function(btn) {
      btn.addEventListener('click', function() { KC_AUDIO.favorite(); });
    });
    document.querySelectorAll('.nav-links a, .tab, .page-btn').forEach(function(el) {
      el.addEventListener('click', function() { KC_AUDIO.click(); });
    });
    document.querySelectorAll('.btn-paye, .btn-payer, .newsletter-btn').forEach(function(btn) {
      btn.addEventListener('click', function() { KC_AUDIO.success(); });
    });
    var badge = document.querySelector('.nav-badge');
    if (badge) {
      badge.addEventListener('click', function() { KC_AUDIO.notification(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachSounds);
  } else {
    attachSounds();
  }
})();
