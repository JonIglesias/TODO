/* ======== PHSBOT VOICE UI — Prosody-assisted punctuation (ES/EN) ======== */
/*
  - Mic (Web Speech): clic 1 graba, clic 2 detiene y vuelca en #phsbot-q acumulando.
  - Enviar: aborta grabación y limpia #phsbot-q tras entregar al handler del chat.
  - Idioma: detectado por página (html[lang], metas, heurística; fallback navegador).
  - Puntuación:
      · Comas por pausa (~350–900 ms). Cierre (≥900 ms) con punto o interrogación.
      · Interrogación por prosodia: subida de F0 al final de la frase → ¿? (ES) / ? (EN).
  - Sin dependencias externas. Si el monitor de prosodia no puede iniciarse, se ignora.
*/

/* ===== CONFIG FINO (ajustable) ===== */
var PROSODY_CFG = {
  frameSize: 2048,
  minF0: 70,
  maxF0: 400,
  noiseRms: 0.01,
  windowMs: 1200,
  tailMs: 350,
  headMs: 350,
  minVoicedFrac: 0.35,
  riseRatio: 1.12,
  riseHzMin: 15
};


/* ===== PUNTUACIÓN LIGERA (texto) ===== */
function autoPunctuate(input, lang) {
  if (!input) return input;
  var L = (lang || '').toLowerCase();
  var parts = input.replace(/\s+/g, ' ').trim().split(/([.!?¡¿]+)\s*/g);
  var out = [];
  for (var i = 0; i < parts.length; i += 2) {
    var sentence = (parts[i] || '').trim();
    var end = (parts[i + 1] || '').trim();
    if (!sentence) { if (end) out.push(end); continue; }
    if (/[.!?¡¿]$/.test(sentence) || end) { out.push(capitalize(sentence)); if (end) out.push(end); continue; }
    out.push(capitalize(sentence));
  }
  return out.join(' ').replace(/\s+([.!?])/g, '$1');
} /* end autoPunctuate */


function addSpanishQuestionMarks(s){
  s = String(s||'').replace(/^[¿]+/,'').replace(/[?]+$/,'').trim();
  return '¿' + s + '?';
} /* end addSpanishQuestionMarks */


function capitalize(s){
  return String(s||'').replace(/^\s*([a-záéíóúñ])/i, function(_,c){ return c.toUpperCase(); });
} /* end capitalize */


/* ===== PAUSAS → coma/punto/¿? ===== */
function applyGapPunctuation(prevText, gapMs, langTag, prosodyHint) {
  var out = (prevText || '');
  if (!out) return out;
  if (/[,.?!¡¿…]$/.test(out.trim())) return out;

  var L = (langTag || '').toLowerCase();
  var isES = L.startsWith('es');

  if (gapMs >= 900) {
    if (prosodyHint === true && isES) {
      return addSpanishQuestionMarks(out);
    }
    return out + '.';
  }
  if (gapMs >= 350) return out + ',';
  return out;
} /* end applyGapPunctuation */


/* ===== Forzar ¿?/?: última oración ===== */
function forceQuestionOnLastSentence(text, langTag){
  var L = (langTag || '').toLowerCase();
  var isES = L.startsWith('es');
  var s = (text||'').trim();
  if (!s) return s;
  if (isES) {
    var m = s.match(/^(.*?)([^.?!¡¿]+)$/);
    if (!m) return addSpanishQuestionMarks(s);
    return (m[1]||'') + addSpanishQuestionMarks(m[2]||'');
  }
  return s.replace(/\s*$/, '') + '?';
} /* end forceQuestionOnLastSentence */


/* ===== Estimación F0 por autocorrelación simple ===== */
function estimateF0ACF(samples, sr, fmin, fmax){
  var minLag = Math.floor(sr / fmax);
  var maxLag = Math.ceil(sr / fmin);
  var bestLag = -1, bestCorr = 0;

  for (var lag=minLag; lag<=maxLag; lag++){
    var corr=0, sum0=0, sum1=0;
    for (var i=0;i<samples.length-lag;i++){
      var a = samples[i], b = samples[i+lag];
      corr += a*b; sum0 += a*a; sum1 += b*b;
    }
    var denom = Math.sqrt((sum0||1)*(sum1||1));
    var r = denom ? (corr/denom) : 0;
    if (r > bestCorr){ bestCorr=r; bestLag=lag; }
  }
  if (bestCorr < 0.6 || bestLag <= 0) return 0;
  return sr / bestLag;
} /* end estimateF0ACF */


/* ============================================================================================================ */
(function () {
  'use strict';

  var RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
  var micBtn, sendBtn, inputEl;

  var recognizing = false;
  var recognition = null;
  var finalTranscript = '';
    interimTranscript = '';
    interimAggregate = '';
  var interimTranscript = '';
  var interimAggregate = '';
  var forceStopRequested = false;
  var suppressAppendOnSend = false;
  var isProgrammaticSend = false;
  var pendingSendAfterStop = false;

  var lastFinalAt = 0;

    interimTranscript = '';
    interimAggregate = '';
  var pageSpeechLang = null;
  var pageLangBase = 'es';

  // Prosodia
  var prosodyStream = null;
  var prosodyMon = null;
  var endFallbackTimer = null;


  /* ======== initMicButton: une eventos y prepara idioma ======== */
  function initMicButton() {
    micBtn   = document.getElementById('phsbot-mic');
    sendBtn  = document.getElementById('phsbot-send');
    inputEl  = document.getElementById('phsbot-q');
    if (!micBtn || !inputEl) return;

    pageSpeechLang = resolvePageSpeechLang();
    pageLangBase = (pageSpeechLang.split('-')[0] || 'es').toLowerCase();

    if (!RecognitionCtor) {
      micBtn.addEventListener('click', function () {
        toast('Tu navegador no soporta reconocimiento de voz. Prueba con Chrome o Edge en escritorio.');
      });
    } else {
      document.dispatchEvent(new CustomEvent('phsbot:voice:request-stop'));
      micBtn.addEventListener('click', onMicClick, { passive: true });
      micBtn.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); onMicClick(); }
      });
    }

    if (sendBtn) sendBtn.addEventListener('click', onSendInitiated, true);
    inputEl.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) { onSendInitiated(); }
    }, true);
  } /* end initMicButton */


  /* ======== onMicClick: toggle start/stop ======== */
  function onMicClick() {
    if (!recognizing) startRecognition(); else stopRecognition();
  } /* end onMicClick */


  /* ======== startRecognition: inicia ASR y prosodia ======== */
  async function startRecognition() {
    if (recognizing) return;
    try { recognition = new RecognitionCtor(); }
    catch (e) { toast('No se pudo inicializar el reconocimiento de voz en este navegador.'); return; }

    recognizing = true;
    forceStopRequested = false;
    suppressAppendOnSend = false;
    finalTranscript = '';
    interimTranscript = '';
    interimAggregate = '';
    lastFinalAt = 0;

    interimTranscript = '';
    interimAggregate = '';

    recognition.lang = pageSpeechLang || (navigator.language || 'es-ES');
    recognition.continuous = true;
    recognition.interimResults = true;

    recognition.onstart = function () { setMicRecordingUI(true); };

    recognition.onerror = function (ev) {
      if (ev && ev.error !== 'aborted') toast('Error de micrófono: ' + (ev.error||'desconocido'));
      cleanupRecognition();
    };

    recognition.onresult = function (event) {
      if (suppressAppendOnSend) return;
      for (var i = event.resultIndex; i < event.results.length; i++) {
        var res = event.results[i];
        if (res.isFinal && res[0] && res[0].transcript) {
          var now = Date.now();
          var gap = lastFinalAt ? (now - lastFinalAt) : 0;

          if (finalTranscript && gap) {
            finalTranscript = applyGapPunctuation(finalTranscript, gap, recognition.lang, tryAnalyzeProsodyQuestion());
          }

          lastFinalAt = now;

          var chunk = res[0].transcript;
          appendProcessedTranscript(chunk);

          // Acumula histórico para puntuación basada en pausas
          finalTranscript = (finalTranscript ? (finalTranscript + ' ') : '') + String(chunk||'').trim();
          interimAggregate = '';
          interimTranscript = '';
        }

        else {
          /* Acumula interims (delta o full) */
          try {
            var _chunk = String(res[0] && res[0].transcript || '').trim();
            if (_chunk) {
              interimTranscript = _chunk;
              if (!interimAggregate) interimAggregate = _chunk;
              else if (_chunk.indexOf(interimAggregate) === 0) interimAggregate = _chunk; /* superset → reemplaza */
              else if (interimAggregate.indexOf(_chunk) === 0) { /* keep longer */ }
              else interimAggregate = (interimAggregate + ' ' + _chunk).replace(/\s+/g,' ').trim();
            }
          } catch(_){}
        }
      }
    };

    recognition.onend = function () {
      if (endFallbackTimer) { clearTimeout(endFallbackTimer); endFallbackTimer = null; }
      cleanupRecognition();
    };

    try { recognition.start(); }
    catch (e) { toast('No se pudo iniciar la grabación de voz.'); cleanupRecognition(); return; }

    // Prosodia (best effort)
    try {
      var micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
      prosodyStream = micStream;
      prosodyMon = startProsodyMonitor(micStream);
    } catch(_){}
  } /* end startRecognition */


  /* ======== stopRecognition: pide stop y DEJA que onend limpie ======== */
  function stopRecognition() {
    forceStopRequested = true;

    try {
      if (recognition && recognizing) {
        recognition.stop(); // No llamar a cleanup aquí para no perder el último onresult
        // Fallback: si algún navegador no dispara onend, limpiamos tras 1.5s
        if (endFallbackTimer) clearTimeout(endFallbackTimer);
        endFallbackTimer = setTimeout(function(){ cleanupRecognition(); }, 1500);
      } else {
        cleanupRecognition();
      }
    } catch(_) {
      cleanupRecognition();
    }
  } /* end stopRecognition */


  /* ======== onSendInitiated: aborta ASR y limpia caja ======== */
  function onSendInitiated(ev) {
    // If this click was triggered programmatically after stopping mic,
    // let chat.js handle it normally (no interception).
    if (typeof isProgrammaticSend !== 'undefined' && isProgrammaticSend) {
      isProgrammaticSend = false;
      return; /* allow default */
    }

    // If we're recording, first stop recognition and then send.
    try {
      if (recognizing) {
        // We want the final onresult to append the processed text, so do NOT suppress.
        // Block the current send to avoid sending before the text lands.
        if (ev && ev.cancelable) ev.preventDefault();
        if (ev && ev.stopImmediatePropagation) ev.stopImmediatePropagation();

        pendingSendAfterStop = true;
        stopRecognition(); // behaves like toggling the mic off
        return false;
      }
    } catch(_){}

    // If not recording, do nothing special and allow chat.js to send.
  } /* end onSendInitiated */


  /* ======== cleanupRecognition: resetea estado/UI y prosodia ======== */
  function cleanupRecognition() {
    recognizing = false;

    if (recognition) {
      recognition.onstart = null;
      recognition.onerror = null;
      recognition.onresult = null;
      recognition.onend = null;
    }
    recognition = null;

    setMicRecordingUI(false);
    lastFinalAt = 0;

    interimTranscript = '';
    interimAggregate = '';

    try { if (prosodyMon) prosodyMon.stop(); } catch(_){}
    prosodyMon = null;

    try {
      if (prosodyStream) {
        prosodyStream.getTracks().forEach(function(t){ try{ t.stop(); }catch(_){ } });
      }
    } catch(_){}
    prosodyStream = null;

    // If we stopped due to a Send click, now trigger the real send
    try {
      if (pendingSendAfterStop && sendBtn) {
        pendingSendAfterStop = false;
        isProgrammaticSend = true;
        setTimeout(function(){ try { sendBtn.click(); } catch(_){} }, 0);
      }
    } catch(_){ pendingSendAfterStop = false; isProgrammaticSend = false; }
  } /* end cleanupRecognition */



  /* ======== SET MIC RECORDING UI ======== */
  /* Toggle visual/aria state of mic button and hide/show the send button while recording */
  function setMicRecordingUI(isRecording) {
    if (!micBtn) return;

    if (isRecording) {
      micBtn.classList.add('is-recording');
      micBtn.setAttribute('aria-pressed','true');

      if (sendBtn) {
        try {
          if (sendBtn.dataset.prevDisplay === undefined) {
            sendBtn.dataset.prevDisplay = sendBtn.style.display || '';
          }
        } catch (_){}
        // sendBtn.style.display = 'none'; // REVERT keep visible
        // sendBtn.setAttribute('aria-hidden', 'true'); // REVERT keep visible
        // sendBtn.setAttribute('tabindex', '-1'); // REVERT keep visible
      }
    } else {
      micBtn.classList.remove('is-recording');
      micBtn.setAttribute('aria-pressed','false');

      if (sendBtn) {
        try {
          var prev = sendBtn.dataset.prevDisplay;
          if (typeof prev !== 'undefined') { sendBtn.style.display = prev; }
          else { sendBtn.style.display = ''; }
        } catch (_){ sendBtn.style.display = ''; }
        sendBtn.removeAttribute('aria-hidden');
        sendBtn.removeAttribute('tabindex');
      }
    }
  } /* end setMicRecordingUI */



  /* ======== appendProcessedTranscript: inserta texto en #phsbot-q ======== */
  function appendProcessedTranscript(rawText) {
    var langTag = (pageSpeechLang || navigator.language || 'es-ES');
    var text = autoPunctuate(rawText, langTag);

    try {
      var an = prosodyMon && prosodyMon.analyze();
      var prosodyQ = !!(an && an.ok && an.isQuestion);
      if (prosodyQ && !/[?!]$/.test(text.trim())) {
        text = forceQuestionOnLastSentence(text, langTag);
      }
    } catch(_){}

    try {
      var prev = inputEl.value || '';
      var merged = (prev ? (prev.replace(/\s+$/,'') + ' ') : '') + text.trim();
      var dict = buildContextCasingDict();
      inputEl.value = applyContextCasing(merged, dict);
      inputEl.dispatchEvent(new Event('input', { bubbles: true }));
    } catch(_){}
  } /* end appendProcessedTranscript */



  /* ======== tryAnalyzeProsodyQuestion: helper ======== */
  function tryAnalyzeProsodyQuestion(){
    try {
      var an = prosodyMon && prosodyMon.analyze();
      return !!(an && an.ok && an.isQuestion);
    } catch(_){ return false; }
  } /* end tryAnalyzeProsodyQuestion */



  /* ======== buildContextCasingDict: capitaliza términos frecuentes del contexto ======== */
  function buildContextCasingDict(){
    try {
      var contextText = '';
      var bubbles = document.querySelectorAll('.phsbot-messages .phsbot-msg.bot .phsbot-bubble');
      for (var i=0;i<bubbles.length;i++){
        var t = (bubbles[i].innerText || bubbles[i].textContent || '').trim();
        if (!t) continue; contextText += ' ' + t;
        if (contextText.length > 3000) break;
      }
      var freq = {}, dict = {};
      contextText.split(/\s+/).slice(-600).forEach(function(w){
        var clean = w.replace(/^[^A-Za-zÁÉÍÓÚÑÜáéíóúñü0-9]+|[^A-Za-zÁÉÍÓÚÑÜáéíóúñü0-9]+$/g,'');
        if (!clean) return; var key = clean.toLowerCase(); freq[key]=(freq[key]||0)+1;
        if (/[A-ZÁÉÍÓÚÑÜ]/.test(clean) && /[a-záéíóúñü]/.test(clean)) dict[key]=clean;
      });
      Object.keys(freq).forEach(function(k){
        if (freq[k] >= 3 && k.length >= 4 && !dict[k]) {
          var re = new RegExp('\\b' + k + '\\b', 'i'); var m = contextText.match(re);
          if (m && /[A-ZÁÉÍÓÚÑÜ]/.test(m[0].charAt(0))) dict[k] = m[0].charAt(0).toUpperCase() + m[0].slice(1);
        }
      });
      return dict;
    } catch(_){ return null; }
  } /* end buildContextCasingDict */



  /* ======== applyContextCasing: aplica diccionario de mayúsculas ======== */
  function applyContextCasing(text, dict) {
    if (!dict) return text;
    return text.replace(/\b([A-Za-zÁÉÍÓÚÑÜáéíóúñü0-9]{3,})\b/g, function (m, token) {
      var key = token.toLowerCase(); return dict[key] || m;
    });
  } /* end applyContextCasing */



  /* ======== resolvePageSpeechLang: detecta tag de idioma ======== */
  function resolvePageSpeechLang() {
    var tag = getExplicitLangFromDom(); if (tag) return mapToSpeechTag(tag);
    var guess = guessLangByStopwords(); if (guess) return mapToSpeechTag(guess);
    return mapToSpeechTag(navigator.language || 'es-ES');
  } /* end resolvePageSpeechLang */



  /* ======== getExplicitLangFromDom: lee lang del DOM ======== */
  function getExplicitLangFromDom() {
    var html = document.documentElement;
    var dataLang = html.getAttribute('data-lang') || html.getAttribute('data-language'); if (dataLang) return normalizeTag(dataLang);
    var attrLang = html.getAttribute('lang'); if (attrLang) return normalizeTag(attrLang);
    var cls = (html.className || '') + ' ' + (document.body ? document.body.className || '' : '');
    var m = cls.match(/\b(lang|idioma)-([a-z]{2}(?:-[A-Z]{2})?)\b/); if (m) return normalizeTag(m[2]);
    return null;
  } /* end getExplicitLangFromDom */



  /* ======== guessLangByStopwords: heurística básica ======== */
  function guessLangByStopwords() {
    var text = '';
    try {
      text += ' ' + (document.title || '');
      var metas = document.querySelectorAll('meta[name="description"],meta[property="og:description"]');
      for (var i=0;i<metas.length;i++){ text += ' ' + (metas[i].getAttribute('content')||''); }
    } catch(_){}
    text = text.toLowerCase();
    if (/\b(el|la|los|las|de|del|que|para|con|por|una|un|en|y|o)\b/.test(text)) return 'es';
    if (/\b(the|and|of|to|in|for|with|on|at|is|are)\b/.test(text)) return 'en';
    return null;
  } /* end guessLangByStopwords */



  /* ======== normalizeTag: normaliza es-ES/en-US ======== */
  function normalizeTag(tag) {
    return String(tag||'').trim().replace('_','-').replace(/^[a-z]{2}$/, function(m){ return m.toLowerCase(); })
           .replace(/^([a-z]{2})-([a-z]{2})$/, function(_,a,b){ return a.toLowerCase()+'-'+b.toUpperCase(); });
  } /* end normalizeTag */



  /* ======== mapToSpeechTag: mapea base a variante del ASR ======== */
  function mapToSpeechTag(tag) {
    var norm = normalizeTag(tag||'');
    if (!norm) return (navigator.language || 'es-ES');
    var parts = norm.split('-'); var base = parts[0]; var region = parts[1];
    if (region) return base + '-' + region;
    var nav = normalizeTag(navigator.language || '');
    switch (base) {
      case 'es': return nav.startsWith('es-') ? nav : 'es-ES';
      case 'en': return (nav === 'en-GB' || nav === 'en-IE' || nav === 'en-AU') ? nav : 'en-US';
      case 'fr': return 'fr-FR'; case 'de': return 'de-DE'; case 'it': return 'it-IT';
      case 'pt': return (nav === 'pt-BR') ? 'pt-BR' : 'pt-PT'; case 'nl': return 'nl-NL';
      case 'ca': return 'ca-ES'; case 'gl': return 'gl-ES'; case 'eu': return 'eu-ES';
      case 'ru': return 'ru-RU'; case 'ja': return 'ja-JP'; case 'ko': return 'ko-KR';
      case 'zh': return (nav === 'zh-TW' || nav === 'zh-HK') ? nav : 'zh-CN';
      default: return norm.length === 2 ? (nav || 'en-US') : norm;
    }
  } /* end mapToSpeechTag */



  /* ======== toast: aviso simple ======== */
  function toast(msg) {
    try {
      var host = document.getElementById('phsbot-root') || document.body;
      var el = document.createElement('div'); el.className = 'phsbot-toast'; el.textContent = msg; host.appendChild(el);
      setTimeout(function(){ el.classList.add('show'); setTimeout(function(){ el.classList.remove('show'); setTimeout(function(){ if (el && el.parentNode) el.parentNode.removeChild(el); },300); },2400); },10);
    } catch (e) { alert(msg); }
  } /* end toast */


  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMicButton, { once:true });
  else initMicButton();

})(); /* IIFE end */


/* ===== Prosody monitor (ligero); best effort ===== */
function startProsodyMonitor(stream){
  var ctx = null, src = null, proc = null;
  var lastFrames = [];
  var lastAnalyze = { ok:false, isQuestion:false };

  try {
    ctx = new (window.AudioContext || window.webkitAudioContext)();
    src = ctx.createMediaStreamSource(stream);
    proc = ctx.createScriptProcessor(PROSODY_CFG.frameSize, 1, 1);
    src.connect(proc); proc.connect(ctx.destination);
  } catch(_){ return { stop:function(){}, analyze:function(){ return lastAnalyze; } }; }

  proc.onaudioprocess = function(e){
    try {
      var buf = e.inputBuffer.getChannelData(0);
      var rms = 0; for (var i=0;i<buf.length;i++){ rms += buf[i]*buf[i]; } rms = Math.sqrt(rms / (buf.length||1));
      if (rms < PROSODY_CFG.noiseRms) return;

      var f0 = estimateF0ACF(buf, ctx.sampleRate, PROSODY_CFG.minF0, PROSODY_CFG.maxF0);
      var now = Date.now();
      lastFrames.push({ t: now, f: f0, rms:rms });
      var cutoff = now - PROSODY_CFG.windowMs;
      while (lastFrames.length && lastFrames[0].t < cutoff) lastFrames.shift();

      var headFrom = now - PROSODY_CFG.windowMs;
      var headTo   = now - PROSODY_CFG.tailMs - 1;
      var tailFrom = now - PROSODY_CFG.tailMs;

      var head = lastFrames.filter(function(fr){ return fr.t >= headFrom && fr.t <= headTo && fr.f > 0; });
      var tail = lastFrames.filter(function(fr){ return fr.t >= tailFrom && fr.f > 0; });

      var voicedFrac = (lastFrames.filter(function(fr){ return fr.f > 0; }).length) / (lastFrames.length || 1);
      var avg = function(arr){ if (!arr.length) return 0; var s=0; for (var i=0;i<arr.length;i++) s+=arr[i].f; return s/arr.length; };

      var h = avg(head), t = avg(tail);
      var isRise = (t >= h * PROSODY_CFG.riseRatio) && ((t - h) >= PROSODY_CFG.riseHzMin);

      lastAnalyze = { ok: voicedFrac >= PROSODY_CFG.minVoicedFrac, isQuestion: !!isRise };
    } catch(_){}
  };

  return {
    stop: function(){
      try { proc.disconnect(); } catch(_){}
      try { src.disconnect(); } catch(_){}
      try { ctx.close(); } catch(_){}
    }, /* end stop */

    analyze: function(){
      return lastAnalyze;
    } /* end analyze */
  };
} /* end startProsodyMonitor */