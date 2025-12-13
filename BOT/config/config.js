/* v1.3.2 external JS (config.js) */
(function($){
  $(function(){
    // --- Tabs ---
    var $tabs = $('.phsbot-config-tabs .nav-tab'),
        $panels = $('.phsbot-config-panel');

    function showTab(sel){
      if(!sel || !$(sel).length) sel = '#tab-conexiones';
      $tabs.removeClass('nav-tab-active').attr('aria-selected','false');
      $panels.attr('aria-hidden','true');
      $tabs.filter('[href="'+sel+'"]').addClass('nav-tab-active').attr('aria-selected','true');
      $(sel).attr('aria-hidden','false');
    }
    $tabs.on('click', function(e){
      e.preventDefault();
      var sel = $(this).attr('href');
      if(history && history.replaceState) history.replaceState(null, '', sel);
      showTab(sel);
    });
    showTab(location.hash && $(location.hash).length ? location.hash : '#tab-conexiones');

    // --- Preview helpers ---
    var $pv = $('#phsbot-preview');
    var $pvMessages = $('#phsbot-preview .phs-messages');
    var $pvLauncher = $('#phsbot-launcher-preview');

    function setVar(name, value){
      if($pv.length) $pv[0].style.setProperty(name, value);
      if($pvLauncher.length) $pvLauncher[0].style.setProperty(name, value);
    }

    // --- Color bind helper ---
    function bindColor(name, varName){
      var $i = $('input[name="'+name+'"]');
      if(!$i.length) return;

      // inicial
      setVar(varName, $i.val() || '');
      if ($.fn.wpColorPicker){
        $i.wpColorPicker({
          change: function(e, ui){ setVar(varName, ui.color.toString()); },
          clear:  function(){ setVar(varName, ''); }
        });
      } else {
        $i.on('input change', function(){ setVar(varName, $i.val() || ''); });
      }
    }

    // --- Colors ---
    bindColor('color_primary',     '--phsbot-primary');
    bindColor('color_secondary',   '--phsbot-secondary');
    bindColor('color_background',  '--phsbot-bg');
    bindColor('color_text',        '--phsbot-text');
    bindColor('color_bot_bubble',  '--phsbot-bot-bubble');
    bindColor('color_user_bubble', '--phsbot-user-bubble');
    bindColor('color_whatsapp',    '--phsbot-whatsapp');
    bindColor('color_footer',      '--phsbot-footer');
    bindColor('color_launcher_bg', '--phsbot-launcher-bg');
    bindColor('color_launcher_icon', '--phsbot-launcher-icon');
    bindColor('color_launcher_text', '--phsbot-launcher-text');

    // --- Slider bind helper (con hook onUpdate opcional) ---
    function bindSlider(id, cssVar, unit, hiddenId, onUpdate){
      var $el = $('#'+id), $label = $('#'+id+'_val');
      if(!$el.length) return;

      function upd(){
        var v = parseInt($el.val(), 10);
        if (isNaN(v)) v = 0;

        // límites si es el de fuente
        if(id === 'bubble_font_size'){
          if(v < 12) v = 12;
          if(v > 22) v = 22;
          $el.val(v); // clamp en el propio control
        }

        var vv = v + (unit || '');
        if($label.length) $label.text(v + ' ' + (unit || ''));

        // Actualiza variable CSS
        if(cssVar) setVar(cssVar, vv);

        // Persistencia si hay hidden
        if(hiddenId){
          var $h = $('#'+hiddenId);
          if($h.length) $h.val(vv);
        }

        // Hook adicional
        if(typeof onUpdate === 'function'){ onUpdate(v, vv); }
      }

      $el.on('input change', upd);
      upd(); // inicial
    }

    // Width/height sliders (actualizan hidden para persistir)
    bindSlider('chat_width_slider',  '--phsbot-width',  'px', 'chat_width');
    bindSlider('chat_height_slider', '--phsbot-height', 'px', 'chat_height');

    // Tamaño de fuente de los globos: SIN hidden (el range ya tiene name)
    // Además, aplicamos directamente a la vista previa por si alguna regla antigua pisa la var.
    bindSlider('bubble_font_size', '--phsbot-bubble-fs', 'px', null, function(v, vv){
      if ($pvMessages.length) $pvMessages[0].style.fontSize = vv; // inline > css
    });

    // Otros (si existen en la UI)
    bindSlider('btn_height',     '--phsbot-btn-h',    'px');
    bindSlider('head_btn_size',  '--phsbot-head-btn', 'px');
    bindSlider('mic_stroke_w',   '--mic-stroke-w',    'px');
  });
})(jQuery);

// Fallback: inicializa cualquier .phsbot-color suelto (excluye los que ya tienen bindColor)
jQuery(function($){
  if ($.fn.wpColorPicker) {
    $('.phsbot-color').not('[name="color_primary"], [name="color_secondary"], [name="color_background"], [name="color_text"], [name="color_bot_bubble"], [name="color_user_bubble"], [name="color_whatsapp"], [name="color_footer"], [name="color_launcher_bg"], [name="color_launcher_icon"], [name="color_launcher_text"]').wpColorPicker();
  }

  // --- Validar Licencia del Bot ---
  $('#phsbot-validate-license').on('click', function(e){
    e.preventDefault();
    var $btn = $(this);
    var $status = $('#phsbot-license-status');
    var licenseKey = $('#bot_license_key').val().trim();
    var apiUrl = $('#bot_api_url').val().trim();

    if (!licenseKey) {
      $status.html('<div class="notice notice-error inline"><p>⚠️ Por favor, introduce una clave de licencia.</p></div>');
      return;
    }

    if (!apiUrl) {
      $status.html('<div class="notice notice-error inline"><p>⚠️ Por favor, introduce la URL de la API.</p></div>');
      return;
    }

    // Obtener dominio actual desde PHP (lo pasamos como variable global)
    var domain = window.location.hostname;

    $btn.prop('disabled', true).text('Validando...');
    $status.html('<div class="notice notice-info inline"><p>🔄 Validando licencia...</p></div>');

    // Construir URL de validación (usando GET con parámetros)
    var validateUrl = apiUrl.replace(/\/+$/, '') + '?route=bot/validate';

    $.ajax({
      url: validateUrl,
      method: 'GET',
      data: {
        license_key: licenseKey,
        domain: domain
      },
      timeout: 10000,
      success: function(response){
        if (response && response.success && response.data && response.data.valid) {
          var lic = response.data.license;
          var tokensPercent = lic.tokens_limit > 0 ? Math.round((lic.tokens_used / lic.tokens_limit) * 100) : 0;

          $status.html(
            '<div class="notice notice-success inline">' +
            '<p><strong>✅ Licencia válida</strong></p>' +
            '<ul style="margin:10px 0 0 20px;">' +
            '<li><strong>Plan:</strong> ' + lic.plan_name + '</li>' +
            '<li><strong>Estado:</strong> ' + lic.status + '</li>' +
            '<li><strong>Dominio:</strong> ' + (lic.domain || 'Sin asignar') + '</li>' +
            '<li><strong>Tokens disponibles:</strong> ' + lic.tokens_available.toLocaleString() + ' / ' + lic.tokens_limit.toLocaleString() + ' (' + tokensPercent + '% usado)</li>' +
            '<li><strong>Expira:</strong> ' + lic.expires_at + '</li>' +
            '</ul>' +
            '</div>'
          );
        } else {
          var reason = (response && response.data && response.data.reason) ? response.data.reason : 'Licencia no válida';
          $status.html('<div class="notice notice-error inline"><p>❌ ' + reason + '</p></div>');
          // Ocultar widget si hay error
          $('#phsbot-plan-widget').hide();
        }
      },
      error: function(xhr, status, error){
        var errorMsg = 'Error de conexión con la API';

        try {
          var response = JSON.parse(xhr.responseText);
          if (response && response.error && response.error.message) {
            errorMsg = response.error.message;
          } else if (response && response.message) {
            errorMsg = response.message;
          }
        } catch(e) {
          errorMsg += ' (código ' + xhr.status + ')';
        }

        $status.html('<div class="notice notice-error inline"><p>❌ ' + errorMsg + '</p></div>');
      },
      complete: function(){
        $btn.prop('disabled', false).text('Validar Licencia');
      }
    });
  });

  // Función para cargar y mostrar el widget del plan
  function loadPlanWidget() {
    var licenseKey = $('#bot_license_key').val().trim();
    var apiUrl = $('#bot_api_url').val().trim();

    if (!licenseKey || !apiUrl) {
      return;
    }

    var statusUrl = apiUrl.replace(/\/+$/, '') + '?route=bot/status';

    $.ajax({
      url: statusUrl,
      method: 'GET',
      data: {
        license_key: licenseKey
      },
      timeout: 8000,
      success: function(response){
        if (response && response.success && response.data && response.data.license) {
          var lic = response.data.license;

          // Actualizar widget
          $('#widget-plan-name').text(lic.plan_name || 'Desconocido');
          $('#widget-plan-status').text(lic.status || '-');
          $('#widget-tokens-available').text((lic.tokens_remaining || 0).toLocaleString('es-ES'));
          $('#widget-tokens-limit').text((lic.tokens_limit || 0).toLocaleString('es-ES'));

          var usagePercent = lic.usage_percentage || 0;
          $('#widget-tokens-progress').css('width', usagePercent + '%');

          if (lic.expires_at) {
            var expiryDate = new Date(lic.expires_at);
            $('#widget-renewal-date').text(expiryDate.toLocaleDateString('es-ES'));

            var daysRemaining = lic.days_remaining || 0;
            $('#widget-days-remaining').text(daysRemaining + ' días restantes');
          } else {
            $('#widget-renewal-date').text('No disponible');
            $('#widget-days-remaining').text('');
          }

          // Mostrar widget
          $('#phsbot-plan-widget').fadeIn();
        }
      },
      error: function(){
        // Silenciosamente ocultar widget si hay error
        $('#phsbot-plan-widget').hide();
      }
    });
  }

  // Cargar widget al inicio si hay licencia
  if ($('#bot_license_key').val().trim()) {
    loadPlanWidget();
  }

  // Recargar widget cuando cambia la licencia o API URL
  $('#bot_license_key, #bot_api_url').on('change', function() {
    loadPlanWidget();
  });
});
