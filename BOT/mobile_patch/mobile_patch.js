/* PHSBOT – mobile_patch.js
 * Propósito: Gestionar el bloqueo/restauración de scroll en móvil cuando el chat se abre/cierra,
 * evitando saltos de scroll si no hubo bloqueo previo.
 * Requisitos: CSS existente que use la clase `phsbot-lock` en <html> para bloquear el scroll.
 * Compatibilidad: Observa `data-open="1|0"` en el contenedor del chat (#phsbot-root o [data-phsbot-root]).
 * Autor: Código (2025-10-15)
 */
(function () {
  'use strict';

  /* ======== ESTADO SCROLL LOCK ======== */
  // Estado global e idempotente para controlar el bloqueo de scroll en móvil.
  // - active: indica si hay bloqueo vigente.
  // - y: posición vertical guardada para restaurar al desbloquear.
  var PHS_SCROLL_LOCK = { active: false, y: 0 };
  /* ========FIN ESTADO SCROLL LOCK ===== */



  /* ======== lockScroll ======== */
  /**
   * Bloquea el scroll del documento en móvil de forma idempotente.
   * - Guarda la posición Y actual para poder restaurarla.
   * - Evita dobles bloqueos.
   */
  window.lockScroll = function lockScroll() {
    if (PHS_SCROLL_LOCK.active) return; // ya bloqueado

    var y = window.scrollY || document.documentElement.scrollTop || 0;
    PHS_SCROLL_LOCK.y = y;
    PHS_SCROLL_LOCK.active = true;

    var root = document.documentElement;
    if (!root.classList.contains('phsbot-lock')) {
      root.classList.add('phsbot-lock');
    }
  }; // end lockScroll
  /* ========FIN lockScroll ===== */



  /* ======== unlockScroll ======== */
  /**
   * Desbloquea el scroll solo si antes se bloqueó.
   * - Restaura la posición Y exactamente una vez.
   * - Tolerante a llamadas tempranas (no hace nada si no hubo lock).
   */
  window.unlockScroll = function unlockScroll() {
    if (!PHS_SCROLL_LOCK.active) return; // nada que restaurar

    var y = PHS_SCROLL_LOCK.y;

    var root = document.documentElement;
    if (root.classList.contains('phsbot-lock')) {
      root.classList.remove('phsbot-lock');
    }

    // Restaurar en el siguiente frame para evitar “saltos” visuales.
    requestAnimationFrame(function () {
      try {
        window.scrollTo(0, y);
      } catch (e) {
        // Fallback sin romper ejecución
        document.documentElement.scrollTop = y;
        if (document.body) document.body.scrollTop = y;
      }
    });

    // Limpiar estado
    PHS_SCROLL_LOCK.active = false;
    PHS_SCROLL_LOCK.y = 0;
  }; // end unlockScroll
  /* ========FIN unlockScroll ===== */



  /* ======== findChatRoot ======== */
  /**
   * Localiza el contenedor raíz del chat:
   * - Prioriza #phsbot-root
   * - Alternativa: [data-phsbot-root]
   * Devuelve: Element o null.
   */
  function findChatRoot() {
    return (
      document.getElementById('phsbot-root') ||
      document.querySelector('[data-phsbot-root]') ||
      null
    );
  } // end findChatRoot
  /* ========FIN findChatRoot ===== */



  /* ======== getOpenState ======== */
  /**
   * Lee el estado de apertura del chat desde el atributo `data-open`.
   * Convención: "1" => abierto, "0" o ausencia => cerrado.
   */
  function getOpenState(el) {
    if (!el) return false;
    var v = el.getAttribute('data-open');
    return v === '1';
  } // end getOpenState
  /* ========FIN getOpenState ===== */



  /* ======== handleOpenStateChange ======== */
  /**
   * Reacciona a un cambio en `data-open`:
   * - Abierto (1): lockScroll()
   * - Cerrado (0/null): unlockScroll()
   */
  function handleOpenStateChange(el) {
    if (!el) return;
    if (getOpenState(el)) {
      window.lockScroll();
    } else {
      window.unlockScroll();
    }
  } // end handleOpenStateChange
  /* ========FIN handleOpenStateChange ===== */



  /* ======== observeChatRoot ======== */
  /**
   * Observa cambios de atributo en el root del chat para `data-open`.
   * Configura el estado inicial al arrancar.
   */
  function observeChatRoot(rootEl) {
    if (!rootEl) return;

    // Ajustar al estado inicial
    handleOpenStateChange(rootEl);

    // Observar cambios en data-open
    var mo = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        if (m.type === 'attributes' && m.attributeName === 'data-open') {
          handleOpenStateChange(rootEl);
        }
      }
    });

    mo.observe(rootEl, { attributes: true, attributeFilter: ['data-open'] });
  } // end observeChatRoot
  /* ========FIN observeChatRoot ===== */



  /* ======== waitForChatRoot ======== */
  /**
   * Si el root no está aún en DOM, espera a que aparezca.
   * Llama a `observeChatRoot` en cuanto lo encuentra.
   */
  function waitForChatRoot() {
    var rootEl = findChatRoot();
    if (rootEl) {
      observeChatRoot(rootEl);
      return;
    }

    var bodyMo = new MutationObserver(function (mutations, observer) {
      var target = findChatRoot();
      if (target) {
        observer.disconnect();
        observeChatRoot(target);
      }
    });

    bodyMo.observe(document.documentElement || document, {
      childList: true,
      subtree: true
    });
  } // end waitForChatRoot
  /* ========FIN waitForChatRoot ===== */



  /* ======== bindCustomEvents ======== */
  /**
   * Se suscribe a eventos personalizados opcionales por si el módulo de chat los emite:
   * - `phsbot:open`  => lockScroll()
   * - `phsbot:close` => unlockScroll()
   * Estos eventos no son obligatorios, pero no estorban si existen.
   */
  function bindCustomEvents() {
    window.addEventListener('phsbot:open', function () {
      window.lockScroll();
    });

    window.addEventListener('phsbot:close', function () {
      window.unlockScroll();
    });
  } // end bindCustomEvents
  /* ========FIN bindCustomEvents ===== */



  /* ======== initMobilePatch ======== */
  /**
   * Arranque del parche móvil:
   * - Espera DOM listo.
   * - Localiza/observa el root del chat y se suscribe a eventos opcionales.
   */
  function initMobilePatch() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function onReady() {
        document.removeEventListener('DOMContentLoaded', onReady);
        waitForChatRoot();
        bindCustomEvents();
      });
    } else {
      waitForChatRoot();
      bindCustomEvents();
    }
  } // end initMobilePatch
  /* ========FIN initMobilePatch ===== */



  // Inicializar inmediatamente
  initMobilePatch();
})();