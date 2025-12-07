<?php
// File: formuchat/formuchat.php
if (!defined('ABSPATH')) exit;

/**
 * FORM → CHAT + OVERLAY (independiente)
 * - Captura el submit de formularios de Elementor en el front.
 * - Toma el campo de mensaje (por defecto el primero de form_fields[] o el indicado con data-phsbot-field).
 * - Abre el chat, pone el texto en la caja y pulsa Enviar.
 * - Muestra un overlay negro semitransparente bajo el chat para llamar la atención.
 *
 * Uso recomendado en Elementor:
 *  1) En el widget "Formulario", pestaña Avanzado → Atributos:
 *     data-phsbot-field="message"   (o el "key" del campo que quieras usar)
 *  2) ¡Listo! Si no lo pones, intentaremos usar el primer form_fields[] de texto/textarea.
 */

add_action('wp_footer', function () {
    if (is_admin() || wp_doing_ajax()) return;

    // Evita inyección doble si otro hook ya lo metió
    if (defined('PHSBOT_FORM2CHAT_LOADED')) return;
    define('PHSBOT_FORM2CHAT_LOADED', true);
    ?>
    <style id="phsbot-form2chat-css">
      /* Overlay negro por debajo del chat (el chat usa z-index 99999/100000) */
      #phsbot-overlay{
        position:fixed; inset:0; z-index:99998;
        display:none; align-items:center; justify-content:center;
        background:rgb(0 0 0 / 83%);
        backdrop-filter:saturate(120%) blur(1px);
      }
      #phsbot-overlay.show{ display:flex; animation:phsFade .18s ease-out both; }
      #phsbot-overlay .phs-tip{
        color:#fff; text-align:center;
        font: 15px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        background:rgba(0,0,0,.32);
        border:1px solid rgba(255,255,255,.22);
        border-radius:12px; padding:12px 16px;
        max-width:min(90vw, 420px);
        box-shadow:0 8px 40px rgba(0,0,0,.35);
      }
      #phsbot-overlay .phs-tip b{ font-weight:700; }
      @keyframes phsFade{ from{opacity:0} to{opacity:1} }
      @media (prefers-reduced-motion: reduce){
        #phsbot-overlay{ backdrop-filter:none; }
      }
    </style>

    <script id="phsbot-form2chat-js">
    (function(){
      // Espera a que exista un selector en DOM (sin depender de otros módulos)
      function waitFor(sel, timeout){
        timeout = timeout || 6000;
        return new Promise(function(res, rej){
          var n = document.querySelector(sel);
          if (n) return res(n);
          var obs = new MutationObserver(function(){
            var n2 = document.querySelector(sel);
            if (n2){ obs.disconnect(); res(n2); }
          });
          obs.observe(document.documentElement, {subtree:true, childList:true});
          setTimeout(function(){ obs.disconnect(); rej(new Error('timeout '+sel)); }, timeout);
        });
      }

      // Abre el chat sin tocar su código interno
      function openChat(){
        var card = document.querySelector('.phsbot-card');
        var launcher = document.querySelector('.phsbot-launcher');
        if (card){
          if (card.getAttribute('data-open') !== '1'){
            card.setAttribute('data-open', '1');
            card.style.display = 'block';
            if (launcher) launcher.style.display = 'none';
          }
          return Promise.resolve(card);
        }
        if (launcher){ launcher.click(); }
        return waitFor('.phsbot-card', 6000);
      }

      // Overlay helpers
      function showOverlay(msg){
        var o = document.getElementById('phsbot-overlay');
        if (!o){
          o = document.createElement('div');
          o.id = 'phsbot-overlay';
          o.innerHTML = '<div class="phs-tip"></div>';
          document.body.appendChild(o);
          o.addEventListener('click', hideOverlay, {once:true});
        }
        o.querySelector('.phs-tip').innerHTML = msg || '<b>Gracias.</b> Te respondemos en el chat 👇';
        document.documentElement.style.overflow = 'hidden';
        o.classList.add('show');
        clearTimeout(showOverlay._t);
        showOverlay._t = setTimeout(hideOverlay, 6000);
      }
      function hideOverlay(){
        var o = document.getElementById('phsbot-overlay');
        if (o) o.classList.remove('show');
        document.documentElement.style.overflow = '';
      }

      // Obtiene el texto del formulario enviado
      function getFormMessage(form){
        // 1) Si el contenedor del widget tiene data-phsbot-field, usamos ese key
        var wrap = form.closest('.elementor-element');
        var key  = wrap ? (wrap.getAttribute('data-phsbot-field')||'').trim() : '';
        if (key){
          var byKey = form.querySelector('[name="form_fields['+key+']"], [name="'+key+'"]');
          if (byKey && byKey.value) return (byKey.value+'').trim();
        }
        // 2) Fallback: primer campo de texto/textarea de form_fields[]
        var any = form.querySelector('textarea[name^="form_fields["], input[type="text"][name^="form_fields["]');
        return any && any.value ? (any.value+'').trim() : '';
      }

      // Enlazamos el submit de formularios de Elementor (no bloquea su AJAX)
      document.addEventListener('submit', function(ev){
        var form = ev.target;
        if (!form || !form.classList || !form.classList.contains('elementor-form')) return;

        var q = getFormMessage(form);
        if (!q) return;

        // Tras dejar a Elementor enviar su AJAX, abrimos el chat y disparamos la pregunta
        setTimeout(function(){
          openChat().then(function(){
            waitFor('#phsbot-q', 4000).then(function(box){
              // Poner texto y enviar
              box.value = q;
              box.dispatchEvent(new Event('input', {bubbles:true}));
              showOverlay('<b>Perfecto.</b> Tu pregunta se está contestando en el chat 👉');

              var btn = document.getElementById('phsbot-send');
              if (btn) btn.click();

              // Ocultar overlay al interactuar con el chat
              var card = document.querySelector('.phsbot-card');
              if (card) card.addEventListener('click', hideOverlay, {once:true});
              box.addEventListener('focus', hideOverlay, {once:true});
            }).catch(function(){ /* sin caja, sin overlay */ });
          }).catch(function(){ /* sin chat, no hacemos nada */ });
        }, 200); // pequeño margen para UX de Elementor
      }, true);
    })();
    </script>
    <?php
});