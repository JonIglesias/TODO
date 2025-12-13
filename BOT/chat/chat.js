(function () {
  'use strict';
  if (window.__PHSBOT_MIN_INIT__) return;
  window.__PHSBOT_MIN_INIT__ = true;

  var CFG = window.PHSBOT_CHAT || {};
  var UI  = window.PHSBOT_CHAT_UI || { send:'Enviar', ph:'Escribe tu pregunta...', typing:'Escribiendo…' };

  var ALLOW_HTML = !!CFG.allowHTML;
  var WELCOME    = (CFG.welcome || '').trim();
  var ANCHOR_P   = !!CFG.anchorPara;
  var CONV_KEY   = 'phsbot:conv';
  var OPEN_KEY   = 'phsbot:isOpen';
  var __INIT_DONE__ = false;

  // Scroll “air” (px) para el inicio de la última burbuja del bot
  var SCROLL_AIR_TOP = 24;

  // Si tras rehidratar, al abrir debemos bajar al fondo
  var NEED_SCROLL_BOTTOM_ON_OPEN = false;

  /* ========UTIL ======== */
  function __phs_norm_text(t){
    try { return String(t||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim(); }
    catch(e){ return ''; }
  }
  function __phs_strip_tags(html){
    var div = document.createElement('div');
    div.innerHTML = html || '';
    return __phs_norm_text(div.textContent || div.innerText || '');
  }
  function __phs_signature(m){
    var role = (m && m.role==='user') ? 'user' : 'bot';
    var base = m && m.html ? __phs_strip_tags(m.html) : __phs_norm_text((m && m.content) || '');
    if (!base) return '';
    return role + '|' + base;
  }

  function __phs_insert_rich_css_once(){
    try {
      if (document.getElementById('phsbot-rich-style')) return;
      var css = [
        ".phsbot-card .phsbot-msg.bot p{margin:0 0 .6em;line-height:1.5}",
        ".phsbot-card .phsbot-msg.bot ul,.phsbot-card .phsbot-msg.bot ol{margin:.6em 0 .6em 1.2em;padding-left:1.2em}",
        ".phsbot-card .phsbot-msg.bot li{margin:.2em 0}",
        ".phsbot-card .phsbot-msg.bot strong{font-weight:600}",
        ".phsbot-card .phsbot-msg.bot em{font-style:italic}",
        ".phsbot-card .phsbot-msg.bot pre{white-space:pre-wrap;padding:.5em;border-radius:.25em;overflow:auto}",
        ".phsbot-card .phsbot-msg.bot code{font-family:monospace}",
        ".phsbot-card .phsbot-msg.bot a{text-decoration:underline}"
      ].join("");
      var st = document.createElement('style');
      st.id = 'phsbot-rich-style'; st.type = 'text/css';
      st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);
    } catch(e){}
  }

  function __phs_md_to_html_safe(src){
    var txt = String(src||''); if (!txt) return '';
    txt = txt.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;").replace(/'/g,"&#39;");
    txt = txt.replace(/```([\s\S]*?)```/g, function(_, code){ return "<pre><code>"+code+"</code></pre>"; });
    txt = txt.replace(/`([^`]+?)`/g, "<code>$1</code>");
    txt = txt.replace(/\*\*([^*]+?)\*\*/g, "<strong>$1</strong>");
    txt = txt.replace(/\*([^*]+?)\*/g, "<em>$1</em>");
    txt = txt.replace(/(https?:\/\/[^\s<)]+)/g, function(m){ return '<a href="'+m+'" target="_blank" rel="noopener">'+m+'</a>'; });
    var blocks = txt.split(/\n{2,}/), html=[];
    for (var i=0;i<blocks.length;i++){
      var block = blocks[i];
      if (/(^|\n)\s*[-*]\s+/.test(block)){
        var lis=[]; block.split(/\n/).forEach(function(line){ var m=line.match(/^\s*[-*]\s+(.*)$/); if(m) lis.push("<li>"+m[1]+"</li>"); });
        if (lis.length){ html.push("<ul>"+lis.join("")+"</ul>"); continue; }
      }
      if (/(^|\n)\s*\d+\.\s+/.test(block)){
        var olis=[]; block.split(/\n/).forEach(function(line){ var m=line.match(/^\s*\d+\.\s+(.*)$/); if(m) olis.push("<li>"+m[1]+"</li>"); });
        if (olis.length){ html.push("<ol>"+olis.join("")+"</ol>"); continue; }
      }
      html.push("<p>"+block.replace(/\n/g,"<br>")+"</p>");
    }
    return html.join("");
  }

  function $(s,c){ return (c||document).querySelector(s); }
  function R(){
    var root = $('#phsbot-widget');
    return {
      root,
      card:   root && root.querySelector('.phsbot-card'),
      body:   root && root.querySelector('#phsbot-body'),
      msgs:   root && root.querySelector('#phsbot-messages'),
      ta:     root && root.querySelector('#phsbot-q'),
      send:   root && root.querySelector('#phsbot-send'),
      head:   root && root.querySelector('#phsbot-header'),
      launch: $('#phsbot-launcher'),
      typing: root && root.querySelector('#phsbot-typing'),
      voiceSlot: root && root.querySelector('#phsbot-voice-slot'),
      composer:  root && root.querySelector('.phsbot-input')
    };
  }

  /* ======== Anclas ======== */
(function neutralizeRootAnchors(){
  // AÑADIR: Neutralizar TAMBIÉN el hash vacío
  if (location.hash === '#phsbot-root' || location.hash === '#') {
    try { 
      history.replaceState(null, '', location.pathname + location.search);
      // Forzar scroll al top
      window.scrollTo(0, 0);
    } catch(e){}
  }
  
  window.addEventListener('hashchange', function(){
    if (location.hash === '#phsbot-root' || location.hash === '#') {
      try { history.replaceState(null, '', location.pathname + location.search); } catch(e){}
    }
  }, true);

  })();

  /* ======== Altura dinámica ======== */
  function computeVhLimitPx(){
    var vh = (window.visualViewport && window.visualViewport.height) ||
             (document.documentElement && document.documentElement.clientHeight) ||
             window.innerHeight || 0;
    var maxVH = parseInt(CFG.maxVH || 70, 10);
    if (!(maxVH>=1)) maxVH = 70;
    if (maxVH < 50) maxVH = 50;
    if (maxVH > 95) maxVH = 95;
    return Math.round(vh * (maxVH/100));
  }
  function readConfHeightPx(root){
    if (!root) return 560;
    try{
      var v = getComputedStyle(root).getPropertyValue('--phsbot-height') || '';
      var px = parseFloat((v||'').replace('px','').trim());
      return px > 0 ? px : 560;
    }catch(_){ return 560; }
  }
  var BASE_H = 0, VH_MAX = 0;
  function setInitialHeight(){
    var r = R(); if (!r.card || !r.root) return;
    BASE_H = readConfHeightPx(r.root);
    VH_MAX = computeVhLimitPx();
    var h0 = Math.min(BASE_H, VH_MAX);
    r.card.style.maxHeight = VH_MAX + 'px';
    r.card.style.height    = h0 + 'px';
  }
  function desiredCardHeight(){
    var r = R(); if (!r.card) return BASE_H || 560;
    var headH  = r.head    ? r.head.offsetHeight    : 0;
    var compH  = r.composer? r.composer.offsetHeight: 0;
    var typingVisible = r.typing && r.typing.style.display !== 'none';
    var typingH= typingVisible ? r.typing.offsetHeight : 0;
    var msgsH  = r.msgs ? Math.max(r.msgs.scrollHeight, r.msgs.getBoundingClientRect().height || 0) : 0;
    var needed = headH + msgsH + typingH + compH;
    if (!needed || needed < 60) needed = BASE_H || 560;
    return needed;
  }
  function maybeGrowToContent(){
    var r = R(); if (!r.card) return;
    var need = desiredCardHeight();
    var lim  = VH_MAX || computeVhLimitPx();
    var next = Math.min(Math.max(need, BASE_H || 0), lim);
    var cur  = parseFloat((r.card.style.height||'').replace('px','')) || 0;
    if (!cur || next > cur + 1){
      r.card.style.height = Math.round(next) + 'px';
    }
  }
  function clampToLimits(){
    var r = R(); if (!r.card) return;
    BASE_H = readConfHeightPx(r.root);
    VH_MAX = computeVhLimitPx();
    r.card.style.maxHeight = VH_MAX + 'px';
    var cur  = parseFloat((r.card.style.height||'').replace('px','')) || 0;
    var next = Math.min(Math.max(cur||BASE_H, BASE_H), VH_MAX);
    r.card.style.height = Math.round(next) + 'px';
  }
  function watchContentGrowth(){
    var r = R(); if (!r.card) return false;
    if (watchContentGrowth.__on) return true;
    var kick = function(){ requestAnimationFrame(maybeGrowToContent); };
    if (window.ResizeObserver){
      try{
        var ro = new ResizeObserver(kick);
        if (r.msgs)     ro.observe(r.msgs);
        if (r.composer) ro.observe(r.composer);
        if (r.typing)   ro.observe(r.typing);
        watchContentGrowth.__ro = ro;
      }catch(e){}
    }
    try{
      if (r.msgs){
        var mo = new MutationObserver(kick);
        mo.observe(r.msgs, { childList:true, subtree:true });
        watchContentGrowth.__mo = mo;
      }
    }catch(e){}
    watchContentGrowth.__on = true;
    kick();
    return true;
  }

  /* ======== Estado y apertura ======== */
  function isOpen(){ var r=R(); return !!(r.card && r.card.getAttribute('data-open')==='1'); }
  function openChat(){
    var r=R(); if (!r.root || !r.card) return;
    r.card.setAttribute('data-open','1'); r.root.setAttribute('data-open','1');
    if (r.launch) r.launch.style.display='none';

    // Guardar estado abierto en sessionStorage
    try{ sessionStorage.setItem(OPEN_KEY, '1'); }catch(e){}

    setInitialHeight();

    try{
      var hasMsgs = !!(r.msgs && r.msgs.childElementCount>0);
      var hasHist = (localStorage.getItem(CONV_KEY)||'').length>2;
      if (!hasMsgs && !hasHist && WELCOME){
        addRow('bot', WELCOME, ALLOW_HTML);
        saveHistory();
      }
    }catch(e){}

    setTimeout(maybeGrowToContent, 40);
    watchContentGrowth();

    // Si venimos de rehidratar, al abrir vamos AL FONDO
    if (NEED_SCROLL_BOTTOM_ON_OPEN && r.msgs) {
      requestAnimationFrame(function(){
        scrollToMessagesBottom(10, true);
        setTimeout(function(){ scrollToMessagesBottom(2, false); }, 220);
      });
      NEED_SCROLL_BOTTOM_ON_OPEN = false;
    }
  }
  function closeChat(){
    var r=R(); if (!r.root || !r.card) return;
    r.card.setAttribute('data-open','0'); r.root.setAttribute('data-open','0');
    if (r.launch) r.launch.style.display='';

    // Guardar estado cerrado en sessionStorage
    try{ sessionStorage.setItem(OPEN_KEY, '0'); }catch(e){}
  }
  window.__PHSBOT_OPEN__  = openChat;
  window.__PHSBOT_CLOSE__ = closeChat;

  /* ======== Crear nodo ======== */
  function el(tag, cls){ var n=document.createElement(tag); if(cls) n.className=cls; return n; }

  /* ======== Typing ======== */
  function setTyping(on){
    var r=R(); if(!r.typing) return;
    r.typing.style.display = on ? '' : 'none';
    r.typing.textContent = on ? (UI.typing||'…') : '';
    if (on) { requestAnimationFrame(maybeGrowToContent); }
  }

  /* ======== Ejecutar scripts embebidos ======== */
  function runScriptsIn(node){
    if (!node) return;
    var list = node.querySelectorAll('script');
    for (var i=0;i<list.length;i++){
      var sc = list[i];
      var type = (sc.getAttribute('type')||'').trim();
      if (type && type!=='text/javascript' && type!=='application/javascript' && type!=='module') continue;
      var s  = document.createElement('script');
      for (var j=0;j<sc.attributes.length;j++){ var a = sc.attributes[j]; s.setAttribute(a.name, a.value); }
      if (!sc.src) s.text = sc.textContent;
      if (s.getAttribute('type')!=='module') s.async = false;
      sc.parentNode.replaceChild(s, sc);
    }
  }

  /* ======== SCROLL HELPERS ======== */
  // Ir al INICIO de la última burbuja del BOT con “aire” arriba
  function scrollToLastBotTop(airPx){
    var r = R(); if (!r.msgs) return;
    var c = r.msgs;
    var target = c.querySelector('.phsbot-msg.bot:last-of-type .phsbot-bubble') ||
                 c.querySelector('.phsbot-msg.bot:last-of-type');
    if (!target) return;
    var AIR = (typeof airPx === 'number') ? airPx : SCROLL_AIR_TOP;
    requestAnimationFrame(function(){
      var crect = c.getBoundingClientRect();
      var trect = target.getBoundingClientRect();
      var top = (trect.top - crect.top) + c.scrollTop - AIR;
      c.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    });
  }

  // Ir al FINAL de todas las burbujas (p.ej. tras rehidratar o al enviar usuario)
  function scrollToMessagesBottom(retryFrames, smooth){
    var r = R(); if (!r.msgs) return;
    var c = r.msgs;
    var tries = Math.max(1, retryFrames || 6);
    function tick(){
      if (smooth && c.scrollTo) c.scrollTo({ top: c.scrollHeight, behavior: 'smooth' });
      else c.scrollTop = c.scrollHeight;
      if (--tries) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  /* ======== Añadir fila ======== */
  function addRow(cls, content, allowHTML){
    var r=R(); if(!r.msgs) return;
    var row = el('div','phsbot-msg '+cls), b = el('div','phsbot-bubble');
    if (allowHTML && /<[^>]+>/.test(content)) {
      b.innerHTML = content;
      if (cls==='bot' && ANCHOR_P){
        var p = b.querySelector('p'); if (p && !p.id) p.id = 'phsbot-first';
      }
      runScriptsIn(b);
    } else {
      b.textContent = content;
    }
    row.appendChild(b); r.msgs.appendChild(row);

    requestAnimationFrame(function(){
      maybeGrowToContent();
      // Nota: el scroll fino se hace en send() (user) y en fetch.then (bot)
    });
  }

  /* ======== Guardar historial ======== */
  function saveHistory(){
    var r=R(); if(!r.msgs) return [];
    var arr=[];
    r.msgs.querySelectorAll('.phsbot-msg').forEach(function(node){
      var b = node.querySelector('.phsbot-bubble'); if(!b) return;
      var role = node.classList.contains('user') ? 'user' : 'assistant';
      if (role==='assistant' && ALLOW_HTML && b.querySelector('p,ul,ol,pre,code,strong,em,a')){
        arr.push({ role: role, html: b.innerHTML });
      } else {
        arr.push({ role: role, content: (b.innerText || b.textContent || '') });
      }
    });
    var mh = parseInt((CFG.maxHistory||10),10);
    if (mh>0 && arr.length>mh*2) arr = arr.slice(arr.length - mh*2);
    try{ localStorage.setItem(CONV_KEY, JSON.stringify(arr)); }catch(e){}
    return arr;
  }

  /* ======== Dedup DOM ======== */
  function __phs_dedup_dom_full(container){
    if (!container) return;
    try {
      var msgs = Array.from(container.querySelectorAll('.phsbot-msg'));
      if (!msgs.length) return;
      var seen = new Set();
      for (var i=0;i<msgs.length;i++){
        var n = msgs[i];
        if (n.classList.contains('phsbot-typing') || n.getAttribute('data-typing') === '1') continue;
        var role = n.classList.contains('user') ? 'user' : 'bot';
        var text = __phs_norm_text(n.textContent || n.innerText || '');
        if (!text) continue;
        var key = role + '|' + text;
        if (seen.has(key)) { n.parentNode && n.parentNode.removeChild(n); i--; continue; }
        seen.add(key);
        n.setAttribute('data-msg-hash', key);
      }
    } catch(e){}
  }

  /* ======== Restaurar historial ======== */
  function restoreHistory(){
    var r=R(); if(!r.msgs) return 0;
    var raw=[]; try{ raw = JSON.parse(localStorage.getItem(CONV_KEY)||'[]'); }catch(e){}
    if (!Array.isArray(raw) || !raw.length){
      setInitialHeight();
      return 0;
    }

    __phs_insert_rich_css_once();

    var seen = {};
    var cleaned = [];
    for (var i=0;i<raw.length;i++){
      var m = raw[i] || {};
      var sig = __phs_signature(m);
      if (!sig || seen[sig]) continue;
      seen[sig] = 1;
      cleaned.push(m);
    }

    for (var j=0;j<cleaned.length;j++){
      var m2 = cleaned[j] || {};
      var role = (m2 && m2.role==='user') ? 'user' : 'bot';
      if (m2.html && ALLOW_HTML){
        addRow(role, m2.html, true);
      } else {
        var text = (m2.content || '');
        if (ALLOW_HTML && /(^|\n)(\s*[-*]\s+|\s*\d+\.\s+)/.test(text)){
          addRow(role, __phs_md_to_html_safe(text), true);
        } else {
          addRow(role, text, false);
        }
      }
    }

    __phs_dedup_dom_full(r.msgs);

    try{ localStorage.setItem(CONV_KEY, JSON.stringify(cleaned)); }catch(e){}

    NEED_SCROLL_BOTTOM_ON_OPEN = true;

    requestAnimationFrame(function(){
      maybeGrowToContent();
      if (isOpen()) scrollToMessagesBottom(6, true);
    });
    return cleaned.length;
  }

  /* ======== Enviar ======== */
  function send(){
    var r=R(); if(!r.ta) return;
    var text=(r.ta.value||'').trim(); if(!text) return;

    // Añade burbuja de usuario
    r.ta.value=''; r.ta.style.height='';
    addRow('user', text, false);

    // >>> NUEVO: asegura que la burbuja del usuario se vea (bajar al fondo)
    requestAnimationFrame(function(){ scrollToMessagesBottom(6, true); });

    setTyping(true);
    var history = saveHistory();

    var payload = new FormData();
    payload.append('action','phsbot_chat');
    payload.append('_ajax_nonce', (CFG.nonce||''));
    payload.append('q', text);
    payload.append('url', window.location.href);
    payload.append('cid', 'cid-float');
    payload.append('history', JSON.stringify(history));

    try {
      var ctx = __phs_collect_page_context();
      payload.append('ctx', JSON.stringify(ctx));
    } catch(e) {}

    fetch((CFG.ajaxUrl||'/wp-admin/admin-ajax.php'), { method:'POST', credentials:'same-origin', body: payload })
      .then(function(r){ return r.json(); })
      .then(function(data){
        setTyping(false);
        if (!data || !data.ok){
          addRow('bot', (data && data.error) ? String(data.error) : 'Error.', false);
          scrollToLastBotTop(SCROLL_AIR_TOP); // con aire
          return;
        }
        addRow('bot', data.html ? data.html : (data.text||''), ALLOW_HTML);
        saveHistory();
        scrollToLastBotTop(SCROLL_AIR_TOP); // con aire
      })
      .catch(function(){
        setTyping(false);
        addRow('bot','Error de red.', false);
        scrollToLastBotTop(SCROLL_AIR_TOP);
      });
  }

  /* ======== Utils de contexto ======== */
  function __phs_get_meta(key){
    var m = document.querySelector('meta[name="'+key+'"]') || document.querySelector('meta[property="'+key+'"]');
    return m ? (m.getAttribute('content')||'').trim() : '';
  }
  function __phs_get_canonical(){
    var l = document.querySelector('link[rel="canonical"]');
    return l ? (l.getAttribute('href')||'') : window.location.href;
  }
  function __phs_get_h1(){
    var h = document.querySelector('h1');
    var t = h ? (h.textContent||h.innerText||'').trim() : '';
    if (!t) t = (document.title||'').trim();
    return t;
  }
  function __phs_extract_main(maxChars){
    var n = document.querySelector('main, article, [role="main"], .entry-content, .post-content, .page-content');
    var txt = n ? (n.textContent||n.innerText||'') : '';
    txt = __phs_norm_text(txt);
    if (maxChars && txt.length > maxChars) txt = txt.slice(0, maxChars);
    return txt;
  }
  function __phs_breadcrumbs(){
    var trail = '';
    var bc = document.querySelector('.breadcrumb, .breadcrumbs, nav[aria-label="breadcrumb"]');
    if (bc) trail = __phs_norm_text(bc.textContent||bc.innerText||'');
    if (!trail){
      try{
        var scripts = document.querySelectorAll('script[type="application/ld+json"]');
        for (var i=0;i<scripts.length;i++){
          var j = JSON.parse(scripts[i].textContent||'{}');
          var li = (j && (j.itemListElement || (j["@graph"]||[]).find(x=>x.itemListElement)?.itemListElement)) || [];
          if (Array.isArray(li) && li.length){
            trail = li.map(function(it){ return (it.name|| (it.item&&it.item.name) ||'').trim(); }).filter(Boolean).join(' > ');
            if (trail) break;
          }
        }
      }catch(e){}
    }
    return trail;
  }
  function __phs_user_selection(maxChars){
    var s = ''; try{ s = String(window.getSelection ? (window.getSelection().toString()||'') : ''); }catch(e){}
    s = __phs_norm_text(s);
    if (maxChars && s.length>maxChars) s = s.slice(0, maxChars);
    return s;
  }
  function __phs_collect_page_context(){
    var url   = __phs_get_canonical();
    var h1    = __phs_get_h1();
    var title = (document.title||'').trim();
    var mdesc = __phs_get_meta('description') || __phs_get_meta('og:description');
    var ogt   = __phs_get_meta('og:title');
    var bcs   = __phs_breadcrumbs();
    var lang  = (document.documentElement && document.documentElement.lang) || '';
    var sel   = __phs_user_selection(400);
    var main  = __phs_extract_main(1200);
    var topic = h1 || ogt || title || '';
    return {
      url: url,
      path: window.location.pathname || '',
      h1: h1,
      title: title,
      topic: topic,
      meta_description: mdesc,
      og_title: ogt,
      breadcrumbs: bcs,
      lang: lang,
      selection: sel,
      main_excerpt: main
    };
  }

  /* ======== Bindings ======== */
  function bindSend(){
    var r=R(); if(!r.ta || !r.send) return false;
    if (!r.send.__bound){
      r.send.addEventListener('click', function(e){ e.preventDefault(); send(); });
      r.ta.addEventListener('keydown', function(e){ if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); send(); } });
      r.ta.addEventListener('input', function(){ r.ta.style.height='42px'; var h=r.ta.scrollHeight; r.ta.style.height = Math.min(h,120)+'px'; });
      r.send.__bound = true;
    }
    return true;
  }
  function bindToggles(){
    var r=R(); if(!r.launch || !r.head || !r.card) return false;
    if (!r.launch.__bound){
      r.launch.addEventListener('click', function(e){
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        try{ if(document.activeElement&&document.activeElement.blur) document.activeElement.blur(); }catch(_){}
        openChat();
      }, {passive:false});
      r.launch.__bound = true;
    }
    if (!r.head.__bound){
      r.head.addEventListener('click', function(e){
        if (e.target && e.target.closest && e.target.closest('#phsbot-voice-slot')) return;
        if (isOpen()){
          if (e.cancelable) e.preventDefault();
          e.stopPropagation();
          closeChat();
        } else {
          if (e.cancelable) e.preventDefault();
          e.stopPropagation();
          openChat();
        }
      }, {passive:false});
      r.head.__bound = true;
    }
    return true;
  }
  function bindViewport(){
    function applyVH(){
      clampToLimits();
      requestAnimationFrame(maybeGrowToContent);
    }
    applyVH();
    (window.visualViewport || window).addEventListener('resize', applyVH);
    return true;
  }

  /* ======== Textos UI ======== */
  function initTexts(){
    var r=R();
    if (r.send) r.send.textContent = UI.send || 'Enviar';
    if (r.ta)   r.ta.placeholder   = UI.ph   || 'Escribe tu pregunta...';
  }

  /* ======== Restaurar estado de apertura ======== */
  function restoreOpenState(){
    try{
      var savedState = sessionStorage.getItem(OPEN_KEY);
      if (savedState === '1'){
        openChat();
      } else if (savedState === '0'){
        closeChat();
      }
      // Si no hay estado guardado, mantener el estado por defecto (cerrado)
    }catch(e){}
  }

  /* ======== API pública ======== */
  (function exposeAPI(){
    window.PHSBOT = window.PHSBOT || {};
    window.PHSBOT.openChat  = openChat;
    window.PHSBOT.closeChat = closeChat;
    window.PHSBOT.mountVoiceToggle = function(node){
      var r=R(); if (!r.voiceSlot || !node) return false;
      r.voiceSlot.innerHTML=''; r.voiceSlot.appendChild(node); return true;
    };
  })();

  /* ======== INIT ======== */
  function init(){
    if (__INIT_DONE__) return;
    __INIT_DONE__ = true;

    initTexts();
    restoreHistory();
    restoreOpenState();

    if (!watchContentGrowth()){
      var i0 = 0, t0=setInterval(function(){ i0++; if (watchContentGrowth()||i0>40) clearInterval(t0); }, 50);
    }
    if (!bindToggles()){ var i1=0, t1=setInterval(function(){ i1++; if (bindToggles()||i1>60) clearInterval(t1); }, 50); }
    if (!bindSend()){   var i2=0, t2=setInterval(function(){ i2++; if (bindSend()||i2>60)   clearInterval(t2); }, 50); }
    if (!bindViewport()){var i3=0, t3=setInterval(function(){ i3++; if (bindViewport()||i3>60)clearInterval(t3);}, 50); }
  }

  /* ======== ARRANQUE ======== */
  (function boot(){
    if (document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', init);
      window.addEventListener('load', init);
    } else {
      init();
    }
  })();

})();

/* ======== Fallback inline para el border-radius dinámico ======== */
;(() => {
  function updateTextareaRadius(el){
    if(!el) return;
    const cs = window.getComputedStyle(el);
    const lh = parseFloat(cs.lineHeight) || 20;
    const lines = Math.max(1, Math.round(el.scrollHeight / lh));
    const isEmpty = el.value.trim() === '';
    if (!isEmpty && lines > 2) el.classList.add('phsbot-q--multi');
    else el.classList.remove('phsbot-q--multi');
  }

  function init(){
    const ta = document.getElementById('phsbot-q');
    if(!ta) return;
    const handler = () => updateTextareaRadius(ta);
    ta.addEventListener('input', handler);
    ta.addEventListener('change', handler);
    if (window.ResizeObserver) {
      const ro = new ResizeObserver(handler);
      ro.observe(ta);
    }
    handler();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
