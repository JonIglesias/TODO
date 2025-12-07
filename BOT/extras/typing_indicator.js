/* PHSBOT — Indicador “Escribiendo…” (watcher scoped al widget, remonta siempre) */
(function(){
  'use strict';

  // Config opcional antes de cargar:
  // window.PHSBOT_EXTRAS = { typingSelector:'', pollMs:1200 }
  var CFG = (window.PHSBOT_EXTRAS || {});
  var POLL_MS = typeof CFG.pollMs === 'number' ? CFG.pollMs : 1200;

  var ROOTS = ['#phsbot-widget', '#phsbot-root', '.phsbot-card', '.phsbot', '.phs-widget'];
  var TYPING_SEL = CFG.typingSelector || '.phsbot-typing, .typing, [data-role="typing"], .phsbot-status, .bot-typing, .chat-typing';
  var RE_TXT = /(escribiendo(\u2026|\.{3})?|typing(\u2026|\.{3})?)/i;

  function $(s, c){ try { return (c||document).querySelector(s); } catch(e){ return null; } }
  function $all(s, c){ try { return (c||document).querySelectorAll(s); } catch(e){ return []; } }

  function isVisible(el){
    if (!el) return false;
    var cs = window.getComputedStyle ? getComputedStyle(el) : null;
    if (!cs) return true;
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    if (el.offsetWidth === 0 && el.offsetHeight === 0) return false;
    return true;
  }

  function findRoot(){
    for (var i=0;i<ROOTS.length;i++){ var r = $(ROOTS[i]); if (r) return r; }
    return null;
  }

  function keyboardSVG(){
    return ''+
    '<svg viewBox="0 0 88 56" aria-hidden="true" focusable="false">'+
    ' <rect class="phs-kbd-shell" x="1" y="5" width="86" height="48" rx="8" />'+
    ' <rect class="phs-kbd-monitor" x="8" y="10" width="72" height="5" rx="2.5" />'+
    ' <g transform="translate(7,20)">'+
    '  <rect class="phs-key key-1"  x="0"  y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-2"  x="8"  y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-3"  x="16" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-4"  x="24" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-5"  x="32" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-6"  x="40" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-7"  x="48" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-8"  x="56" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-9"  x="64" y="0" width="6.8" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-10" x="72" y="0" width="6.8" height="8" rx="1.6" />'+
    ' </g>'+
    ' <g transform="translate(11,32)">'+
    '  <rect class="phs-key key-11" x="0"  y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-12" x="9"  y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-13" x="18" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-14" x="27" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-15" x="36" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-16" x="45" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-17" x="54" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-18" x="63" y="0" width="7.6" height="8" rx="1.6" />'+
    '  <rect class="phs-key key-19" x="72" y="0" width="7.6" height="8" rx="1.6" />'+
    ' </g>'+
    ' <g transform="translate(18,44)">'+
    '  <rect class="phs-key space" x="0" y="0" width="52" height="7.8" rx="2.0" />'+
    ' </g>'+
    '</svg>';
  }

  function buildWrap(text){
    var wrap = document.createElement('span');
    wrap.className = 'phs-typing-wrap';
    wrap.setAttribute('aria-live','polite');

    var sr = document.createElement('span');
    sr.className = 'phs-typing-text';
    sr.textContent = text || 'Escribiendo…';
    wrap.appendChild(sr);

    var icon = document.createElement('span');
    icon.className = 'phs-typing-icon';
    icon.innerHTML = keyboardSVG();
    wrap.appendChild(icon);

    wrap.classList.add('has-icon');
    return wrap;
  }

  function rand(min, max){ return Math.floor(Math.random()*(max-min+1))+min; }

  function startPressLoop(wrap){
    var svg = wrap.querySelector('svg'); if (!svg) return;
    var keys = svg.querySelectorAll('.phs-key'); if (!keys || !keys.length) return;

    var pool = [], spaceIdx = -1;
    for (var i=0;i<keys.length;i++){
      if (keys[i].classList.contains('space')) { spaceIdx = i; continue; }
      pool.push(keys[i]);
    }
    var pressed = null, lastIdx = -1, timer = null, alive = true;

    function chooseKey(){
      if (spaceIdx !== -1 && Math.random() < 0.20) return keys[spaceIdx];
      if (!pool.length) return null;
      var idx; do { idx = Math.floor(Math.random()*pool.length); } while (idx === lastIdx && pool.length > 1);
      lastIdx = idx; return pool[idx];
    }
    function step(){
      if (!alive || !document.body.contains(wrap)) { cleanup(); return; }
      if (pressed) { pressed.classList.remove('is-pressed'); pressed = null; }
      var k = chooseKey();
      if (k) {
        k.classList.add('is-pressed'); pressed = k;
        setTimeout(function(){ if (pressed) pressed.classList.remove('is-pressed'); }, 180);
      }
      timer = setTimeout(step, rand(120, 320));
    }
    function cleanup(){
      alive = false;
      if (timer){ clearTimeout(timer); timer=null; }
      if (pressed){ pressed.classList.remove('is-pressed'); pressed=null; }
    }
    document.addEventListener('visibilitychange', function(){ if (document.hidden) cleanup(); });
    window.addEventListener('pagehide', cleanup);
    window.addEventListener('beforeunload', cleanup);
    step();
  }

  // ---------- núcleo: montar si hace falta ----------
  function mountIfNeeded(root){
    var n = $(TYPING_SEL, root);
    if (!n || !isVisible(n)) return false;

    // si ya está nuestro wrapper, nada
    if (n.querySelector && n.querySelector('.phs-typing-wrap')) return true;

    // solo si contiene el texto de “Escribiendo…/Typing…”
    var txt = (n.innerText || n.textContent || '').trim();
    if (!RE_TXT.test(txt)) return false;

    var wrap = buildWrap(txt);
    n.innerHTML = '';
    n.appendChild(wrap);
    startPressLoop(wrap);
    return true;
  }

  function wire(root){
    if (!root) return;
    // 1) intento inmediato
    mountIfNeeded(root);

    // 2) observer SOLO en el root del chat, throttled
    if ('MutationObserver' in window){
      var pending = false;
      var mo = new MutationObserver(function(){
        if (pending) return;
        pending = true;
        (window.requestAnimationFrame || setTimeout)(function(){
          pending = false;
          mountIfNeeded(root);
        }, 16);
      });
      mo.observe(root, {childList:true, subtree:true, characterData:true});
    }

    // 3) backup: pequeño poll por si el chat reemplaza el nodo entero
    setInterval(function(){
      mountIfNeeded(root);
    }, POLL_MS);
  }

  function bootstrap(){
    var root = findRoot();
    if (!root) return;
    wire(root);
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();