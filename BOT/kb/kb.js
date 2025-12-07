/* PHSBOT – KB Admin JS */
(function($){
  const KEY_ACC = 'phsbotKBAccState_v2';
  const KEY_HELP = 'phsbotKBHelpShown_v1';
  let autoPromptCache = { text: (window.phsbotKBData && phsbotKBData.defaultPrompt) || '', root: '' };
  let userEditedPrompt = false;

  function ajax(action, data, onOk, onErr){
    $.ajax({
      url: phsbotKBData.ajaxurl,
      method: 'POST',
      data: $.extend({ action: action, nonce: phsbotKBData.nonce }, data || {}),
      dataType: 'json'
    }).done(function(res){
      if (res && res.success) onOk && onOk(res.data);
      else onErr && onErr(res && res.data ? res.data : {message:'Error'});
    }).fail(function(xhr){
      const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error de red';
      onErr && onErr({message: msg});
    });
  }

  /* ================= Error bar ================= */
  function showErrorBar(err){
    const $bar = $('#phsbot-kb-errorbar');
    if (!err || !err.message) { $bar.hide().text(''); return; }
    let html = '<strong>No se pudo generar el documento:</strong> ' + $('<div/>').text(err.message).html();
    if (err.data) {
      const d = err.data;
      const parts = [];
      if (d.kind) parts.push('Motivo: <code>'+ d.kind +'</code>');
      if (d.http_code) parts.push('HTTP: <code>'+ d.http_code +'</code>');
      if (d.used_model) parts.push('Modelo: <code>'+ d.used_model +'</code>');
      if (d.selected_model && d.selected_model !== d.used_model) parts.push('Seleccionado: <code>'+ d.selected_model +'</code>');
      if (parts.length) html += ' · ' + parts.join(' · ');
    }
    if (err.when) html += ' · <em>'+ err.when +'</em>';
    $bar.html(html).show();
  }
  function fetchErrorBar(){
    ajax('phsbot_kb_error_get', {}, function(d){
      if (d && d.error && d.error.message) showErrorBar(d.error);
      else showErrorBar(null);
    });
  }
  function clearErrorBar(){
    ajax('phsbot_kb_error_clear', {}, function(){ showErrorBar(null); });
  }

  /* ================= Tabs ================= */
  function initTabs(){
    $('.phsbot-kb-tabs .nav-tab').on('click', function(e){
      e.preventDefault();
      const tab = $(this).data('tab');
      $('.phsbot-kb-tabs .nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      $('.phsbot-kb-tab').hide();
      $('#phsbot-kb-tab-' + tab).show();

      if (tab === 'info') {
        loadDebugInfo();
      }
    });
  }

  /* ================= Help persistent ================= */
  function initHelp(){
    const $help  = $('#phsbot-kb-help');
    const $toggle= $('#phsbot-kb-help-toggle');
    const stored = localStorage.getItem(KEY_HELP);
    const shown  = stored === null ? true : (stored === '1');
    $help.toggle(shown);
    $toggle.text(shown ? 'Ocultar ayuda' : 'Mostrar ayuda');
    $toggle.attr('aria-expanded', shown ? 'true' : 'false');

    $toggle.on('click', function(){
      const vis = !$help.is(':visible');
      $help.toggle(vis);
      $toggle.text(vis ? 'Ocultar ayuda' : 'Mostrar ayuda');
      $toggle.attr('aria-expanded', vis ? 'true' : 'false');
      localStorage.setItem(KEY_HELP, vis ? '1':'0');
    });
  }

  /* ================= Accordion (persianas) ================= */
  function initAccordion(){
    const st = JSON.parse(localStorage.getItem(KEY_ACC) || '{}');
    $('.phsbot-kb-section').each(function(i){
      const $sec = $(this);
      const id = $sec.find('.acc-head').text().trim();
      const stateKnown = (typeof st[id] !== 'undefined');
      const isOpen = stateKnown ? !!st[id] : (i === 0); // solo la primera abierta si no hay estado
      if (!isOpen) $sec.addClass('collapsed');
      $sec.find('.acc-head').attr('role','button').attr('tabindex',0).on('click keydown', function(ev){
        if (ev.type === 'keydown' && ev.key !== 'Enter' && ev.key !== ' ') return;
        $sec.toggleClass('collapsed');
        st[id] = !$sec.hasClass('collapsed');
        localStorage.setItem(KEY_ACC, JSON.stringify(st));
      });
    });
  }

  /* ================= Model refresh ================= */
  function initModels(){
    $('#phsbot-kb-refresh-models').on('click', function(){
      ajax('phsbot_kb_refresh_models', {}, function(data){
        const $sel = $('#phsbot_kb_model').empty();
        (data.models || []).forEach(function(m){
          const opt = $('<option/>').val(m).text(m);
          if (m === phsbotKBData.selectedModel) opt.attr('selected', 'selected');
          $sel.append(opt);
        });
        alert(phsbotKBData.i18n.models_ok);
      }, function(){ alert(phsbotKBData.i18n.models_err); });
    });
  }

  /* ================= Save settings ================= */
  function collectSettings(){
    return {
      prompt:        $('#phsbot_kb_prompt').val(),
      extra_prompt:  $('#phsbot_kb_extra_prompt').val(),
      extra_domains: $('#phsbot_kb_extra_domains').val(),
      max_urls:      parseInt($('#phsbot_kb_max_urls').val() || 80, 10),
      max_pages_main:parseInt($('#phsbot_kb_max_pages_main').val() || 50, 10),
      max_posts_main:parseInt($('#phsbot_kb_max_posts_main').val() || 20, 10),
      model:         $('#phsbot_kb_model').val(),
      ov_on:         $('#phsbot_kb_site_override_on').is(':checked') ? 1 : 0,
      ov_val:        $('#phsbot_kb_site_override').val()
    };
  }
  function saveSettings(cb){
    const s = collectSettings();
    ajax('phsbot_kb_save_settings', s, function(){
      cb && cb();
    }, function(e){ alert((e && e.message) || 'Error guardando'); cb && cb(); });
  }
  $('#phsbot-kb-save-config-global').on('click', function(){
    saveSettings(function(){ /* nada */ });
  });

  /* ================= Prompt default & override live ================= */
  function setPrompt(text, root){
    autoPromptCache.text = text;
    autoPromptCache.root = root || '';
    const $ta = $('#phsbot_kb_prompt');
    if (!userEditedPrompt || $ta.val().trim() === '' || $ta.val().trim() === autoPromptCache.text) {
      $ta.val(text);
    }
  }
  function fetchDefaultPromptLive(){
    const ov_on = $('#phsbot_kb_site_override_on').is(':checked') ? 1 : 0;
    const ov_val= $('#phsbot_kb_site_override').val();
    ajax('phsbot_kb_default_prompt_live', { ov_on: ov_on, ov_val: ov_val }, function(d){
      setPrompt(d.prompt, d.root);
      $('#phsbot-kb-using-inline').text( ov_on ? ov_val : d.root );
    });
  }

  $('#phsbot-kb-fill-default').on('click', function(){
    userEditedPrompt = false;
    fetchDefaultPromptLive();
  });
  $('#phsbot_kb_prompt').on('input', function(){ userEditedPrompt = true; });

  $('#phsbot_kb_site_override_on').on('change', fetchDefaultPromptLive);
  $('#phsbot_kb_site_override').on('change input', function(){
    fetchDefaultPromptLive();
  });

  /* ================= Progress topbar ================= */
  function topbar(on){
    const $bar = $('#phsbot-kb-topbar');
    if (on) {
      $bar.attr('aria-hidden','false').addClass('active');
      $('#phsbot-kb-topbar-bar').addClass('animate');
    } else {
      $bar.attr('aria-hidden','true').removeClass('active');
      $('#phsbot-kb-topbar-bar').removeClass('animate').css('width','0%');
    }
  }

  /* ================= Generate ================= */
  $('#phsbot-kb-generate').on('click', function(){
    // Mostrar barra inmediatamente (a la primera)
    topbar(true);
    // Guardar configuración antes de generar
    saveSettings(function(){
      const model = $('#phsbot_kb_model').val();
      ajax('phsbot_kb_generate', { model: model }, function(d){
        topbar(false);
        clearErrorBar(); // éxito → limpiar aviso

        // Editor -> poner HTML nuevo
        if (window.tinymce && tinymce.get('phsbot_kb_editor')) {
          tinymce.get('phsbot_kb_editor').setContent(d.document || '');
        }
        $('#phsbot-kb-version-badge, #phsbot-kb-version-top').text(d.version || '');
        $('#phsbot-kb-updated').text(d.updated || '');
        $('#phsbot-kb-status').text(d.last_run ? ('Última generación: ' + d.last_run) : '');

        // Chips de fuentes (cuenta y hosts extra)
        const stats = d.stats || {};
        const extras = (stats.extra_hosts && stats.extra_hosts.length) ? (' · extra: ' + stats.extra_hosts.join(', ')) : '';
        const $src = $('#phsbot-kb-sources');
        $src.text('Fuentes: principal ' + (stats.main_urls||0) + ' · adicionales ' + (stats.extra_urls||0) + extras).show();

        // Refrescar pestaña de info
        loadDebugInfo();
      }, function(err){
        topbar(false);
        // Mostrar barra de error con el último error registrado en servidor
        fetchErrorBar();
        alert((err && err.message) || 'Error generando');
      });
    });
  });

  /* ================= Save document ================= */
  $('#phsbot-kb-save').on('click', function(){
    if (window.tinymce && tinymce.get('phsbot_kb_editor')) {
      tinymce.get('phsbot_kb_editor').save();
    }
    const html = $('#phsbot_kb_editor').val();
    ajax('phsbot_kb_save_doc', { html: html }, function(d){
      $('#phsbot-kb-version-badge, #phsbot-kb-version-top').text(d.version || '');
      $('#phsbot-kb-updated').text(d.updated || '');
      alert(phsbotKBData.i18n.saved);
    }, function(err){
      alert((err && err.message) || 'Error guardando documento');
    });
  });

  /* ================= Info tab load ================= */
  function loadDebugInfo(){
    ajax('phsbot_kb_debug_get', {}, function(d){
      const $sum = $('#phsbot-kb-debug-summary').empty();
      const $tree= $('#phsbot-kb-tree').empty();
      const $tab = $('#phsbot-kb-sources-table').empty();
      const $err = $('#phsbot-kb-last-error').empty();

      // Summary
      const stats = d.stats || {};
      const used  = d.used_model || '—';
      const sel   = d.selected_model || '—';
      const fb    = d.fallback_note || '';
      $('<p/>').html(
        '<strong>Modelo seleccionado:</strong> <code>'+ sel +'</code> · '+
        '<strong>Usado:</strong> <code>'+ used +'</code> '+
        (fb?('· <em>'+fb+'</em>'):'') + '<br>' +
        '<strong>Versión:</strong> '+ (d.version||'—') +
        ' · <strong>Inicio:</strong> '+(d.started||'—')+
        ' · <strong>Fin:</strong> '+(d.finished||'—')
      ).appendTo($sum);
      $('<p/>').html(
        '<strong>URLs principal:</strong> '+(stats.main_urls||0)+
        ' (páginas: '+(stats.main_pages||0)+', entradas: '+(stats.main_posts||0)+') · '+
        '<strong>URLs adicionales:</strong> '+(stats.extra_urls||0)+
        (stats.extra_hosts&&stats.extra_hosts.length?(' · Hosts: '+stats.extra_hosts.join(', ')):'')
      ).appendTo($sum);

      // Last error
      const err = d.error || {};
      if (err.message) {
        let html = '<div class="notice notice-error"><p><strong>'+ $('<div/>').text(err.message).html() +'</strong>';
        if (err.data) {
          const dd = err.data;
          html += '<br>';
          if (dd.kind) html += 'Motivo: <code>'+dd.kind+'</code> · ';
          if (dd.http_code) html += 'HTTP: <code>'+dd.http_code+'</code> · ';
          if (dd.used_model) html += 'Modelo: <code>'+dd.used_model+'</code> ';
        }
        if (err.when) html += '<br><em>'+err.when+'</em>';
        html += '</p></div>';
        $err.html(html);
      } else {
        $err.text('—');
      }

      // Tree
      function renderTree(obj, depth){
        const ul = $('<ul/>');
        Object.keys(obj).forEach(function(k){
          if (k === '__children' || k === '__count' || k === '__leafs') return;
          const it = obj[k];
          const li = $('<li/>').text(k + (it.__count ? ' ('+it.__count+')' : ''));
          if (it.__children) li.append(renderTree(it.__children, depth+1));
          ul.append(li);
        });
        return ul;
      }
      $tree.append(renderTree(d.site_tree || {}, 0));

      // Sources table
      const sources = d.sources || [];
      if (!sources.length) { $tab.text('—'); return; }
      const tbl = $('<table class="widefat striped"/>');
      tbl.append('<thead><tr><th>#</th><th>Dominio</th><th>Tipo</th><th>Host</th><th>URL</th><th>Tamaño</th></tr></thead>');
      const tb = $('<tbody/>');
      sources.forEach(function(s, i){
        $('<tr/>')
          .append('<td>'+(i+1)+'</td>')
          .append('<td>'+ (s.domain || '—') +'</td>')
          .append('<td>'+ (s.type || '—') +'</td>')
          .append('<td>'+ (s.src_host || '—') +'</td>')
          .append('<td><code>'+ s.url +'</code></td>')
          .append('<td>'+ (s.chars || 0) +'</td>')
          .appendTo(tb);
      });
      tbl.append(tb);
      $tab.append(tbl);
    });
  }

  /* ================= Init ================= */
  $(function(){
    initTabs();
    initHelp();
    initAccordion();
    initModels();

    // Mostrar último error (si lo hubiese) al cargar
    fetchErrorBar();

    // Pre-cargar prompt por defecto con el root actual (si estaba vacío o igual al auto)
    setPrompt(phsbotKBData.defaultPrompt || '', (phsbotKBData.activeBase && (phsbotKBData.activeBase.scheme + '://' + phsbotKBData.activeBase.host + phsbotKBData.activeBase.path)) || '');

    // Si hay un job corriendo, reflejar
    if (phsbotKBData.job && phsbotKBData.job.status === 'running') {
      topbar(true);
    }
  });
})(jQuery);