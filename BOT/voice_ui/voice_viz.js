/* ======== PHSBOT VOICE VIZ — Overlay de audio (estilo ChatGPT ajustado) ======== */
/*
  Cambios solicitados:
  - Movimiento un poco más rápido que ChatGPT (fluido, sin saltos).
  - Barras 2 px, color negro; separación 1 px.
  - En silencio, altura ~1 px inmediato (decay rápido).
*/

(function () {
  'use strict';

  /* ======== CONFIG ======== */
  var BAR_WIDTH_CSSPX   = 1;      // grosor barra (CSS px)
  var BAR_GAP_CSSPX     = 1;      // separación entre barras (CSS px)
  var BARS_PER_SECOND   = 12.0;    // velocidad de desplazamiento → + rápido que 4.5
  var BOOST_GAIN        = 5.0;    // ganancia sobre RMS
  var GAMMA_SHAPE       = 0.5;    // <1 expande medios/altos
  var MIN_ACTIVE_FRAC   = 0.10;   // bajos ≈ 10% de la altura
  var MAX_ACTIVE_FRAC   = 0.98;   // tope visual
  var SILENCE_THRESHOLD = 0.07;   // por debajo = silencio (1 px) con caída rápida
  var BAR_COLOR         = '#000'; // barras negras
  /* ======== FIN CONFIG ======== */

  var micBtn, inputEl, containerEl, overlayEl, canvasEl, ctx;

  // WebAudio
  var audioCtx = null, mediaStream = null, sourceNode = null, analyser = null, dataArray = null;
  var useFallbackAnim = false;

  // Dibujo/tiempos
  var dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
  var barW = Math.max(1, Math.round(BAR_WIDTH_CSSPX * dpr));
  var gapW = Math.max(1, Math.round(BAR_GAP_CSSPX * dpr));

  var barsCount = 64;
  var trail = [];
  var smoothLevel = 0;      // nivel suavizado
  var rafId = 0;

  var scrollProgress = 0;   // [0..1)
  var lastTs = 0;           // ms frame anterior



  /* ======== init ======== */
  function initVoiceViz() {
    micBtn  = document.getElementById('phsbot-mic');
    inputEl = document.getElementById('phsbot-q');
    if (!micBtn || !inputEl) return;

    containerEl = closestByClass(inputEl, 'phsbot-input') || inputEl.parentElement || document.body;

    overlayEl = document.createElement('div');
    overlayEl.id = 'phsbot-voiceviz';
    overlayEl.setAttribute('aria-hidden', 'true');
    overlayEl.style.zIndex = '9999';

    canvasEl = document.createElement('canvas');
    canvasEl.id = 'phsbot-voiceviz-canvas';
    overlayEl.appendChild(canvasEl);
    ctx = canvasEl.getContext('2d');

    // Insertar inmediatamente después del textarea
    if (inputEl.nextSibling) containerEl.insertBefore(overlayEl, inputEl.nextSibling);
    else containerEl.appendChild(overlayEl);

    observeMicState();
    bindResizeObservers();
    syncOverlayRect(true);
    initTrail();
  } // ======== FIN init ========



  /* ======== mic state observer ======== */
  function observeMicState() {
    toggleViz(micBtn.classList.contains('is-recording'));
    var mo = new MutationObserver(function (list) {
      for (var i = 0; i < list.length; i++) {
        var m = list[i];
        if (m.type === 'attributes' && m.attributeName === 'class') {
          toggleViz(micBtn.classList.contains('is-recording'));
        }
      }
    });
    mo.observe(micBtn, { attributes: true, attributeFilter: ['class'] });
  } // ======== FIN mic state observer ========



  /* ======== toggle ======== */
  function toggleViz(isRecording) {
    if (isRecording) {
      startAudio();
      overlayEl.classList.add('phsbot-voiceviz--show');
      overlayEl.style.opacity = '1';
      syncOverlayRect(true);
      lastTs = performance.now();
      startRender();
    } else {
      overlayEl.classList.remove('phsbot-voiceviz--show');
      overlayEl.style.opacity = '0';
      stopRender();
      stopAudio();
    }
  } // ======== FIN toggle ========



  /* ======== audio ======== */
  function startAudio() {
    useFallbackAnim = false;
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      audioCtx = new AC();
    } catch (e) {
      useFallbackAnim = true;
      return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
      .then(function (stream) {
        mediaStream = stream;
        sourceNode  = audioCtx.createMediaStreamSource(stream);

        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 256;
        analyser.smoothingTimeConstant = 0.7;

        dataArray = new Uint8Array(analyser.frequencyBinCount); // 128
        sourceNode.connect(analyser);
      })
      .catch(function () { useFallbackAnim = true; });
  }

  function stopAudio() {
    try {
      if (sourceNode) { try { sourceNode.disconnect(); } catch (e) {} }
      sourceNode = null; analyser = null; dataArray = null;
      if (mediaStream) mediaStream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
      mediaStream = null;
      if (audioCtx && typeof audioCtx.close === 'function') audioCtx.close().catch(function () {});
    } finally {
      audioCtx = null; useFallbackAnim = false; smoothLevel = 0;
    }
  } // ======== FIN audio ========



  /* ======== render loop ======== */
  function startRender() {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(renderFrame);
  }
  function stopRender() {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = 0; clearCanvas();
  }

  function renderFrame(ts) {
    rafId = requestAnimationFrame(renderFrame);

    var now = ts || performance.now();
    var dt  = Math.max(0, now - (lastTs || now)); // ms
    lastTs = now;

    var level = sampleLevel();                     // [0..1]

    // avance fluido: barras/seg → progreso entre columnas
    var advance = (BARS_PER_SECOND / 1000) * dt;
    scrollProgress += advance;
    while (scrollProgress >= 1) {
      trail.shift();
      trail.push(level);
      scrollProgress -= 1;
    }

    drawBars();
  } // ======== FIN renderFrame ========



  /* ======== level sample ======== */
  function sampleLevel() {
    var lvl = 0;

    if (analyser && dataArray) {
      analyser.getByteTimeDomainData(dataArray);
      var sum = 0;
      for (var i = 0; i < dataArray.length; i++) {
        var v = (dataArray[i] - 128) / 128; // [-1..1]
        sum += v * v;
      }
      var rms = Math.sqrt(sum / dataArray.length); // [0..~0.5]
      lvl = Math.min(1, rms * BOOST_GAIN);
    } else if (useFallbackAnim) {
      var t = performance.now() / 1000;
      lvl = (Math.sin(t * 2.2) + 1) / 6 + 0.08;
    }

    // Suavizado con ataque y RELEASE más rápido (cae rápido a silencio)
    var attack  = 0.30; // sube
    var release = 0.65; // baja (rápido)
    smoothLevel += (lvl - smoothLevel) * (lvl > smoothLevel ? attack : release);

    // Si el nivel crudo es claramente silencio, forzamos caída extra
    if (lvl < SILENCE_THRESHOLD) {
      smoothLevel *= 0.4; // “apaga” colas rápidamente
    }

    var shaped = Math.pow(Math.max(0, smoothLevel), GAMMA_SHAPE);
    return Math.max(0, Math.min(1, shaped));
  } // ======== FIN sampleLevel ========



  /* ======== draw ======== */
  function drawBars() {
    if (!ctx) return;

    var w = canvasEl.width, h = canvasEl.height;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = BAR_COLOR;

    for (var i = 0; i < barsCount; i++) {
      var x = Math.round(i * (barW + gapW));
      var a = trail[i]     || 0;
      var b = trail[i + 1] || a;
      var frac = a + (b - a) * scrollProgress;      // LERP

      var bh = levelToPixels(frac, h);              // altura
      var y  = Math.round((h - bh) / 2);

      roundRect(ctx, x, y, barW, Math.max(1, bh), Math.min(barW, 4));
    }
  }

  // Mapeo: silencio real → 1 px; bajos ≈ 10%; altos ≈ 98%
  function levelToPixels(level, totalHpx) {
    if (level < SILENCE_THRESHOLD) return 1; // silencio: 1 px
    var frac = MIN_ACTIVE_FRAC + (MAX_ACTIVE_FRAC - MIN_ACTIVE_FRAC) * level;
    var px   = Math.round(frac * totalHpx);
    return Math.max(1, Math.min(totalHpx, px));
  } // ======== FIN levelToPixels ========



  /* ======== layout / utils ======== */
  function initTrail() {
    trail = [];
    for (var i = 0; i < barsCount + 1; i++) trail.push(0);
    scrollProgress = 0;
  }

  function syncOverlayRect(force) {
    if (!inputEl || !overlayEl || !containerEl) return;

    var cRect = containerEl.getBoundingClientRect();
    var iRect = inputEl.getBoundingClientRect();

    overlayEl.style.left   = Math.max(0, iRect.left - cRect.left) + 'px';
    overlayEl.style.top    = Math.max(0, iRect.top  - cRect.top)  + 'px';
    overlayEl.style.width  = iRect.width  + 'px';
    overlayEl.style.height = iRect.height + 'px';

    var cssW = Math.max(1, Math.floor(iRect.width));
    var cssH = Math.max(1, Math.floor(iRect.height));

    canvasEl.style.width  = cssW + 'px';
    canvasEl.style.height = cssH + 'px';

    canvasEl.width  = Math.max(1, Math.floor(cssW * dpr));
    canvasEl.height = Math.max(1, Math.floor(cssH * dpr));

    // Recalcula nº de barras con 2px + gap 1px
    barW = Math.max(1, Math.round(BAR_WIDTH_CSSPX * dpr));
    gapW = Math.max(1, Math.round(BAR_GAP_CSSPX   * dpr));

    var fullW = canvasEl.width;
    var perBar = (barW + gapW);
    var target = Math.max(24, Math.min(160, Math.floor((fullW + gapW) / perBar)));

    if (target !== barsCount || force) {
      barsCount = target;
      initTrail();
    }
  }

  function bindResizeObservers() {
    var ro = new ResizeObserver(function () { syncOverlayRect(); });
    try { ro.observe(inputEl); } catch (e) {}
    window.addEventListener('resize', debounce(function () { syncOverlayRect(true); }, 60));
    window.addEventListener('scroll', debounce(function () { syncOverlayRect(); }, 60), true);
  }

  function clearCanvas() { if (!ctx) return; ctx.clearRect(0, 0, canvasEl.width, canvasEl.height); }

  function roundRect(ctx, x, y, w, h, r) {
    var rr = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + rr, y);
    ctx.lineTo(x + w - rr, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + rr);
    ctx.lineTo(x + w, y + h - rr);
    ctx.quadraticCurveTo(x + w, y + h, x + w - rr, y + h);
    ctx.lineTo(x + rr, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - rr);
    ctx.lineTo(x, y + rr);
    ctx.quadraticCurveTo(x, y, x + rr, y);
    ctx.closePath();
    ctx.fill();
  }

  function debounce(fn, ms) {
    var t = 0;
    return function () { clearTimeout(t); var a = arguments; t = setTimeout(function () { fn.apply(null, a); }, ms); };
  }

  function closestByClass(el, className) {
    while (el) { if (el.classList && el.classList.contains(className)) return el; el = el.parentElement; }
    return null;
  }

  // Arranque
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVoiceViz, { once: true });
  } else {
    initVoiceViz();
  }
})();
