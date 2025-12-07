;/* File: inject/inject.js */
/* global phsbotInjectData */
(function () {
  'use strict';
  var D = (typeof phsbotInjectData === 'object' && phsbotInjectData) ? phsbotInjectData : {context:'front', rules:[]};

  function qs(s, c){ return (c||document).querySelector(s); }
  function qsa(s, c){ return Array.prototype.slice.call((c||document).querySelectorAll(s)); }
  function onReady(fn){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  function deburr(s){ try { return (s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); } catch(e){ return (s||'').toLowerCase(); } }
  function normKeywords(kw){ var arr = Array.isArray(kw) ? kw : String(kw||'').split(','); return arr.map(function(x){ return deburr(String(x||'').trim()); }).filter(Boolean); }
  function containsAll(t,a){ for(var i=0;i<a.length;i++) if(t.indexOf(a[i])===-1) return false; return true; }
  function containsAny(t,a){ for(var i=0;i<a.length;i++) if(t.indexOf(a[i])!==-1) return true; return false; }

  /* ================= FRONT ================= */

  // Contenedor de mensajes y contenedor scrolleable real
  function messagesRoot(){
    var rs=(D.selectors&&D.selectors.root)||null, bs=(D.selectors&&D.selectors.body)||null;
    if(rs&&bs){ var r=qs(rs); if(r){ var b=qs(bs,r); if(b) return b; } }
    var c=['#phsbot-messages','.phsbot-messages','#phsbot__messages','#phs_messages','.phsbot-body','.messages','.chat-messages','.chat__messages','[data-msg-root]','[data-role="messages"]'];
    for(var i=0;i<c.length;i++){ var el=qs(c[i]); if(el) return el; } return null;
  }

  // FIX: sólo devolver contenedores del chat; nunca el documento
  function getScrollContainer(){
    var root = messagesRoot();
    if (!root) return null;
    var el = root;
    // Si root o algún padre inmediato es scrolleable, úsalo
    while (el && el !== document.body && el !== document.documentElement) {
      var cs = window.getComputedStyle(el);
      var scrollable = /(auto|scroll)/.test(cs.overflowY) && (el.scrollHeight > el.clientHeight + 2);
      if (scrollable) return el;
      el = el.parentElement;
    }
    // NO volver al documento para evitar saltos de página
    return null;
  }

  // Mantener pegado abajo después de inyectar contenido “pesado”
  // FIX: si no hay contenedor scrolleable, no hacemos nada
  function pinToBottomSeries(){
    var sc = getScrollContainer(); if(!sc) return;
    var fn = function(){ try{ sc.scrollTop = sc.scrollHeight; }catch(e){} }; // sin +2000
    requestAnimationFrame(fn);
    setTimeout(fn, 60);
    setTimeout(fn, 200);
    setTimeout(fn, 500);
    setTimeout(fn, 900);
    setTimeout(fn, 1200);
  }

  function ensureEmbedSize(ctx){
    qsa('.phs-embed', ctx).forEach(function(el){
      el.style.width='100%';
      var rect=el.getBoundingClientRect();
      if(rect.height<40){
        el.style.position='relative'; el.style.paddingBottom='56.25%'; el.style.height='0'; el.style.overflow='hidden'; el.style.margin='10px 0';
        var ifr=el.querySelector('iframe'); if(ifr){ ifr.style.position='absolute'; ifr.style.top='0'; ifr.style.left='0'; ifr.style.width='100%'; ifr.style.height='100%'; ifr.style.border='0'; }
      }
      qsa('iframe', el).forEach(function(ifr){
        ifr.setAttribute('loading','lazy');
        try{ var u=new URL(ifr.src||'', window.location.origin); if(!u.searchParams.get('rel')) u.searchParams.set('rel','0'); ifr.src=u.toString(); }catch(e){}
        var allow=ifr.getAttribute('allow')||''; if(!/autoplay/.test(allow)) ifr.setAttribute('allow',(allow?allow+'; ':'')+'autoplay; encrypted-media; picture-in-picture; accelerometer; gyroscope');
      });
    });
  }

  function buildInjectBlock(html){
    var w=document.createElement('div'); w.className='phsbot-inject-block'; w.style.width='100%';
    var i=document.createElement('div'); i.className='phsbot-inject-html'; i.style.width='100%'; i.innerHTML=html||'';
    w.appendChild(i); ensureEmbedSize(w);
    // Evita “salto” hacia arriba
    pinToBottomSeries();
    // Reajusta cuando cargan imágenes/iframes/video
    qsa('img,iframe,video', w).forEach(function(m){
      m.addEventListener('load', pinToBottomSeries, {once:true});
      m.addEventListener('loadeddata', pinToBottomSeries, {once:true});
    });
    return w;
  }

  function shouldTrigger(txt,rule){
    var k = normKeywords(rule.keywords);
    if (!k.length) return false;
    return (rule.match === 'all') ? containsAll(txt,k) : containsAny(txt,k);
  }

  function after(el,newEl){ if(!el||!el.parentNode)return; el.parentNode.insertBefore(newEl, el.nextSibling); }

  // ========= Silenciar / parar YouTube de forma robusta =========
  function muteIframeYT(f){
    try{
      var post = function(cmd){ f.contentWindow && f.contentWindow.postMessage(JSON.stringify({event:'command', func:cmd, args:[]}), '*'); };
      ['pauseVideo','mute'].forEach(function(cmd, idx){
        setTimeout(function(){ post(cmd); }, 40 + idx*120);
        setTimeout(function(){ post(cmd); }, 280 + idx*120);
        setTimeout(function(){ post(cmd); }, 800 + idx*120);
      });
    }catch(e){}
  }
  function silenceAllInjects(hide){
    qsa('.phsbot-inject-block').forEach(function(b){
      qsa('iframe', b).forEach(function(f){
        var src = (f.getAttribute('src')||'');
        if(/youtube\.com\/embed/.test(src)){
          muteIframeYT(f);
          if(!f.dataset.phsSrc) f.dataset.phsSrc = src;
          setTimeout(function(){
            try { f.src = 'about:blank'; f.dataset.phsBlanked = '1'; } catch(e){}
          }, 250);
        }
      });
      if(hide) b.style.visibility='hidden';
    });
  }
  function restoreAllInjects(){
    qsa('.phsbot-inject-block').forEach(function(b){
      qsa('iframe', b).forEach(function(f){
        if(f.dataset.phsBlanked === '1' && f.dataset.phsSrc){
          var s = f.dataset.phsSrc;
          try{
            var u = new URL(s, window.location.origin);
            if(u.searchParams) u.searchParams.set('autoplay','0');
            f.src = u.toString();
          }catch(e){
            f.src = s.replace(/([?&])autoplay=1/,'$1autoplay=0');
          }
          delete f.dataset.phsBlanked;
        }
      });
      b.style.visibility='';
    });
  }

  // Detección de visibilidad de bloques inyectados
  function isVisibleDeep(el){
    if(!el) return false;
    var n = el;
    while(n && n !== document.body){
      if(n.hasAttribute && n.getAttribute('aria-hidden') === 'true') return false;
      var cs = window.getComputedStyle(n);
      if(cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
      n = n.parentElement;
    }
    var r = el.getBoundingClientRect();
    return (r.width > 1 && r.height > 1 && r.bottom > 0 && r.right > 0);
  }

  var lastVisibleCount = -1;
  function visibilityHeartbeat(){
    var blocks = qsa('.phsbot-inject-block');
    if(!blocks.length){ lastVisibleCount = -1; return; }
    var visible = 0; for(var i=0;i<blocks.length;i++){ if(isVisibleDeep(blocks[i])) visible++; }
    if(lastVisibleCount === -1){ lastVisibleCount = visible; return; }
    if(visible === 0 && lastVisibleCount > 0){ silenceAllInjects(true); }
    if(visible > 0 && lastVisibleCount === 0){ restoreAllInjects(); }
    lastVisibleCount = visible;
  }

  function isChatOpen(){
    var body=qs('#phsbot-root .phsbot-body');
    if(!body) return true;
    var cs=window.getComputedStyle(body);
    return (cs.display!=='none'&&cs.visibility!=='hidden'&&parseFloat(cs.height||'1')>0);
  }

  function hookOpenClose(){
    // Click en cabecera/botón cerrar
    document.addEventListener('click', function(e){
      var t=e.target; if(!t) return;
      if(t.closest('.phsbot-head') || ((t.getAttribute&&((t.getAttribute('aria-label')||'').toLowerCase().indexOf('cerrar')!==-1)))){
        setTimeout(function(){
          if(!isChatOpen()) { silenceAllInjects(true); }
          else { restoreAllInjects(); pinToBottomSeries(); } // pin sólo afecta al contenedor del chat
        },200);
      }
    });
    // Mutaciones de estado en varios candidatos
    ['#phsbot-root .phsbot-card','#phsbot-root','.phsbot-wrap','.phsbot-body'].forEach(function(sel){
      var n = qs(sel); if(!n) return;
      new MutationObserver(function(){
        setTimeout(function(){
          if(!isChatOpen()) { silenceAllInjects(true); }
          else { restoreAllInjects(); pinToBottomSeries(); }
        },0);
      }).observe(n,{attributes:true,attributeFilter:['style','class','data-open']});
    });
  }

  // Gestión de “after” y “only”
  var pendingAfter = [];     // items {html}
  var suppressNextBot = 0;   // número de respuestas del bot a suprimir (only)

  function processUserRow(u,rules){
    if(!u||u.dataset.phsInjected==='1')return;
    var txt=deburr(u.textContent||''); if(!txt){ u.dataset.phsInjected='1'; return; }

    var fired=false;
    rules.forEach(function(r){
      try{
        if(!shouldTrigger(txt,r)) return;
        if(r.place === 'after'){
          pendingAfter.push({html:r.html});
        } else {
          after(u,buildInjectBlock(r.html||''));
          if(r.place === 'only') suppressNextBot = Math.max(1, suppressNextBot+1);
        }
        fired=true;
      }catch(e){ if(D.debug)console.warn('[inject] render error:',e); }
    });

    u.dataset.phsInjected='1';
    if(D.debug&&fired)console.log('[inject] rule fired:',txt);
    pinToBottomSeries();
  }

  function initFront(){
    hookOpenClose();

    // Heartbeat de visibilidad
    setInterval(visibilityHeartbeat, 400);

    var rules=Array.isArray(D.rules)?D.rules:[]; 
    if(!rules.length) return;

    var root=messagesRoot(); if(!root){ setTimeout(initFront,300); return; }

    // Inyectar existentes
    qsa('.phsbot-msg.user, .message.user, .chat__message.user', root).forEach(function(n){ processUserRow(n,rules); });

    // Vigilar nuevas burbujas + bot para AFTER/ONLY
    new MutationObserver(function(list){
      list.forEach(function(m){
        if(m.type!=='childList'||!m.addedNodes)return;
        Array.prototype.forEach.call(m.addedNodes,function(n){
          if(!(n instanceof HTMLElement))return;
          var cls=n.classList||{contains:function(){return false;}};
          // Mensaje del usuario
          if(cls.contains('user') && (cls.contains('phsbot-msg')||cls.contains('message')||cls.contains('chat__message'))){
            processUserRow(n,rules);
          }
          // Mensaje del bot
          if(cls.contains('bot') && (cls.contains('phsbot-msg')||cls.contains('message')||cls.contains('chat__message'))){
            if(suppressNextBot>0){
              try{ n.remove(); }catch(e){ n.style.display='none'; }
              suppressNextBot--; return;
            }
            if(pendingAfter.length){
              pendingAfter.forEach(function(it){ after(n, buildInjectBlock(it.html||'')); });
              pendingAfter.length = 0;
            }
            pinToBottomSeries();
          }
        });
      });
    }).observe(root,{childList:true,subtree:true});
  }

  /* ================= ADMIN (inline edit con AJAX) ================= */
  function renumberRows(rows){
    var i=0; qsa('tr.phsbot-inject-row', rows).forEach(function(tr){
      qsa('input,select,textarea', tr).forEach(function(el){
        ['name','id','for'].forEach(function(a){ var v=el.getAttribute(a); if(!v)return; el.setAttribute(a, v.replace(/\[items\]\[\d+\]/g,'[items]['+i+']')); });
      });
      tr.id='phs-edit-'+i; i++;
    });
  }
  function getHiddenInputs(i){
    var base = '#phs-edit-'+i, root = qs(base); if(!root) return null;
    return {
      row: root,
      enabled: qs('input[name*="[items]['+i+'][enabled]"]', root),
      keywords: qs('input[name*="[items]['+i+'][keywords]"]', root),
      type: qs('select[name*="[items]['+i+'][type]"]', root),
      payload_html: qs('textarea[name*="[items]['+i+'][payload_html]"]', root),
      payload_sc: qs('input[name*="[items]['+i+'][payload_sc]"]', root),
      video: qs('input[name*="[items]['+i+'][video]"]', root),
      autoplay: qs('input[name*="[items]['+i+'][autoplay]"]', root),
      match: qs('select[name*="[items]['+i+'][match]"]', root),
      place: qs('select[name*="[items]['+i+'][place]"]', root)
    };
  }
  function buildInlineEditor(i){
    var H = getHiddenInputs(i); if(!H) return null;

    var tr = document.createElement('tr'); tr.className = 'phs-inline-editor-row'; tr.setAttribute('data-id', i);
    var td = document.createElement('td'); td.colSpan = 6; tr.appendChild(td);

    var wrap = document.createElement('div'); wrap.className = 'phs-inline-editor';
    wrap.innerHTML =
      '<label style="margin-right:10px;"><input type="checkbox" class="ie-enabled"> Activo</label>' +
      '<input type="text" class="ie-keywords regular-text" style="min-width:240px" placeholder="palabras, separadas, por, comas">' +
      ' <select class="ie-type"><option value="html">HTML</option><option value="shortcode">Shortcode</option><option value="video">Vídeo YouTube</option></select>' +
      ' <span class="ie-field ie-html"><textarea class="ie-payload-html large-text code" rows="3" style="width:480px"></textarea></span>' +
      ' <span class="ie-field ie-sc" style="display:none"><input type="text" class="ie-payload-sc regular-text code" style="width:420px" placeholder=\'[elementor-template id="123"]\'></span>' +
      ' <span class="ie-field ie-video" style="display:none"><input type="url" class="ie-video-url regular-text" style="width:420px" placeholder="https://youtu.be/..."> ' +
      '   <label style="margin-left:6px;"><input type="checkbox" class="ie-autoplay"> Autoplay</label></span>' +
      ' <select class="ie-match" style="margin-left:6px;"><option value="any">cualquiera</option><option value="all">todas</option></select>' +
      ' <select class="ie-place" title="Posición de la respuesta en el chat"><option value="before">antes</option><option value="after">después</option><option value="only">sólo trigger</option></select>' +
      ' <span class="ie-help">Antes: se inserta tras el mensaje del usuario · Después: tras la siguiente respuesta del bot · Sólo trigger: se suprime esa respuesta del bot.</span>' +
      ' <span class="actions" style="float:right;"><button class="button button-primary ie-save">Guardar</button> <button class="button ie-close">Cerrar</button></span>';
    td.appendChild(wrap);

    // valores actuales
    qs('.ie-enabled', wrap).checked = !!(H.enabled && H.enabled.checked);
    qs('.ie-keywords', wrap).value = H.keywords ? H.keywords.value : '';
    qs('.ie-type', wrap).value = H.type ? H.type.value : 'html';
    qs('.ie-payload-html', wrap).value = H.payload_html ? H.payload_html.value : '';
    qs('.ie-payload-sc', wrap).value = H.payload_sc ? H.payload_sc.value : '';
    qs('.ie-video-url', wrap).value = H.video ? H.video.value : '';
    qs('.ie-autoplay', wrap).checked = !!(H.autoplay && H.autoplay.checked);
    qs('.ie-match', wrap).value = H.match ? H.match.value : 'any';
    qs('.ie-place', wrap).value = H.place ? H.place.value : 'before';

    function applyTypeUI(type){
      qsa('.ie-field', wrap).forEach(function(s){ s.style.display='none'; });
      if(type==='html') qs('.ie-html', wrap).style.display='';
      else if(type==='shortcode') qs('.ie-sc', wrap).style.display='';
      else if(type==='video') qs('.ie-video', wrap).style.display='';
    }
    applyTypeUI(qs('.ie-type', wrap).value);

    // Sincroniza hacia inputs ocultos
    wrap.addEventListener('input', function(e){
      var t=e.target;
      if(t.classList.contains('ie-keywords') && H.keywords) H.keywords.value = t.value;
      if(t.classList.contains('ie-payload-html') && H.payload_html) H.payload_html.value = t.value;
      if(t.classList.contains('ie-payload-sc') && H.payload_sc) H.payload_sc.value = t.value;
      if(t.classList.contains('ie-video-url') && H.video) H.video.value = t.value;
    });
    wrap.addEventListener('change', function(e){
      var t=e.target;
      if(t.classList.contains('ie-enabled') && H.enabled) H.enabled.checked = t.checked;
      if(t.classList.contains('ie-type') && H.type){ H.type.value = t.value; applyTypeUI(t.value); }
      if(t.classList.contains('ie-autoplay') && H.autoplay) H.autoplay.checked = t.checked;
      if(t.classList.contains('ie-match') && H.match) H.match.value = t.value;
      if(t.classList.contains('ie-place') && H.place) H.place.value = t.value;
    });

    // Guardar via AJAX
    qs('.ie-save', wrap).addEventListener('click', function(e){
      e.preventDefault();
      var payload = {
        action: 'phsbot_inject_save_item',
        nonce: D.nonce,
        id: i,
        enabled: qs('.ie-enabled', wrap).checked ? 1 : 0,
        keywords: qs('.ie-keywords', wrap).value || '',
        type: qs('.ie-type', wrap).value || 'html',
        payload_html: qs('.ie-payload-html', wrap).value || '',
        payload_sc: qs('.ie-payload-sc', wrap).value || '',
        video: qs('.ie-video-url', wrap).value || '',
        autoplay: qs('.ie-autoplay', wrap).checked ? 1 : 0,
        match: qs('.ie-match', wrap).value || 'any',
        place: qs('.ie-place', wrap).value || 'before'
      };
      fetch(D.ajax_url, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams(payload).toString()
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(!res || !res.success){ alert('No se pudo guardar. Revisa campos.'); return; }
        var d = res.data;

        // localizar y actualizar fila del listado
        var hostRow = qs('#phs-list-table tr[data-id="'+i+'"]') || qs('#phs-list-table tr[data-id="'+d.id+'"]');
        if(hostRow){
          var tds = hostRow.querySelectorAll('td');
          var colTitle   = tds[0], colType = tds[1], colState = tds[2], colPreview = tds[3];
          if(colTitle){
            var st = colTitle.querySelector('.row-title');     if(st)  st.textContent  = d.title;
            var sb = colTitle.querySelector('.row-subtitle');  if(sb)  sb.textContent  = d.keywords;
          }
          if(colType)    colType.innerHTML   = (d.type_html || d.type || 'HTML');
          if(colState)   colState.textContent   = d.enabled_text || 'Activo';
          if(colPreview) colPreview.textContent = d.preview || '';
          var del = hostRow.querySelector('a.phs-del-one');
          if(!del){
            var span = hostRow.querySelector('.column-actions .disabled');
            if(span){
              del = document.createElement('a');
              del.className = 'button button-small button-link-delete phs-del-one';
              del.textContent = 'Eliminar';
              del.href = d.delete_url || '#';
              span.replaceWith(del);
            }
          } else if(d.delete_url){
            del.setAttribute('href', d.delete_url);
          }
          var editBtn = hostRow.querySelector('.phs-edit-btn'); if(editBtn) editBtn.setAttribute('data-id', d.id);
          hostRow.setAttribute('data-id', d.id);
          var cb = hostRow.querySelector('th.check-column input[type="checkbox"]'); if(cb) cb.value = d.id;
        }

        // cerrar editor inline
        var row = tr; if(row && row.parentNode) row.parentNode.removeChild(row);
      })
      .catch(function(){ alert('Error de red guardando el trigger.'); });
    });

    qs('.ie-close', wrap).addEventListener('click', function(e){ e.preventDefault(); var row=tr; if(row && row.parentNode) row.parentNode.removeChild(row); });

    return tr;
  }

  function initAdmin(){
    var rowsBank = qs('#phsbot-inject-rows'); if(!rowsBank) return;

    // Inyectar ayuda visible (sin tocar PHP)
    var wrap = qs('.phsbot-inject-admin');
    if(wrap && !qs('.phsbot-inject-admin .phs-help')){
      var p = document.createElement('p');
      p.className = 'phs-help';
      p.textContent = 'Posición: "Antes" inserta tras el mensaje del usuario · "Después" lo hace tras la siguiente respuesta del bot · "Sólo trigger" suprime esa respuesta del bot.';
      var title = qs('.phsbot-inject-admin h2.title') || wrap.firstChild;
      wrap.insertBefore(p, title ? title.nextSibling : wrap.firstChild);
    }

    // Añadir y abrir editor
    var btnTop = qs('#phsbot-add-row-top');
    if(btnTop){
      btnTop.addEventListener('click', function(e){
        e.preventDefault();
        var tpl = qs('#phsbot-inject-template').value;
        var i = qsa('tr.phsbot-inject-row', rowsBank).length;
        rowsBank.insertAdjacentHTML('beforeend', tpl.replace(/\{i\}/g, i));
        renumberRows(rowsBank);

        var list = qs('#the-list'), trList = document.createElement('tr');
        trList.setAttribute('data-id', i);
        trList.innerHTML =
          '<th class="check-column"><input type="checkbox" name="ids[]" value="'+i+'"></th>' +
          '<td class="title column-title page-title"><strong class="row-title">#'+i+'</strong><div class="row-subtitle"></div></td>' +
          '<td class="col-type"><span class="dashicons dashicons-editor-code"></span> HTML</td>' +
          '<td class="col-state">Activo</td>' +
          '<td class="col-preview"></td>' +
          '<td class="column-actions" style="text-align:right;white-space:nowrap;">' +
            '<a href="#" class="button button-small phs-edit-btn" data-id="'+i+'">Editar</a> ' +
            '<span class="button button-small disabled">Eliminar</span>' +
          '</td>';
        list.appendChild(trList);

        var ie = buildInlineEditor(i);
        if(ie) trList.parentNode.insertBefore(ie, trList.nextSibling);
      });
    }

    // Editar inline
    var listTable = qs('#phs-list-table');
    if(listTable){
      listTable.addEventListener('click', function(e){
        var t = e.target;
        if(t && t.classList && t.classList.contains('phs-edit-btn')){
          e.preventDefault();
          qsa('.phs-inline-editor-row').forEach(function(r){ if(r.parentNode) r.parentNode.removeChild(r); });
          var id = t.getAttribute('data-id');
          var hostRow = t.closest('tr');
          var ie = buildInlineEditor(id);
          if(ie) hostRow.parentNode.insertBefore(ie, hostRow.nextSibling);
        }
      });
    }

    // Banco oculto: mantener UI si se usa manualmente
    rowsBank.addEventListener('change', function(e){
      var t=e.target;
      if(t && t.classList && t.classList.contains('phs-type')){
        var tr=t.closest('tr'); if(!tr) return; var type=t.value||'html';
        qsa('.phs-field', tr).forEach(function(f){ f.style.display='none'; });
        if(type==='html') qs('.phs-field--html', tr).style.display='';
        else if(type==='shortcode') qs('.phs-field--shortcode', tr).style.display='';
        else if(type==='video') qs('.phs-field--video', tr).style.display='';
      }
    });
  }

  onReady(function(){ if(D.context==='admin') initAdmin(); else initFront(); });
})();
