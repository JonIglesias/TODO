/* PHSBOT – Leads Admin JS (persiana, borrar, borrar masivo, reset navegador) */
(function($){
  'use strict';
  const ajax = (action, data={}) => $.post(PHSBOT_LEADS.ajax, Object.assign({ action, nonce: PHSBOT_LEADS.nonce }, data));

  /* Utilidades */
  function fmt(ts){ try{ return ts? new Date(ts*1000).toLocaleString():'' }catch(e){ return '' } }
  function truncateWords(txt, maxWords){
    if (!txt) return '';
    const words = txt.trim().split(/\s+/);
    if (words.length <= maxWords) return txt;
    return words.slice(0, maxWords).join(' ') + '…';
  }

  /* Filtro de filas */
  function filterRows(){
    const q = ($('#phsbot-leads-search').val()||'').toLowerCase().trim();
    const onlyOpen = $('#phsbot-leads-open-only').is(':checked');
    let visible = 0;
    $('#phsbot-leads-table tbody tr').each(function(){
      const $tr = $(this);
      if ($tr.hasClass('no-items') || $tr.hasClass('phsbot-detail')) return;
      const open = $tr.data('open') === 1 || $tr.data('open') === '1';
      const pass = (!q || $tr.text().toLowerCase().indexOf(q)!==-1) && (!onlyOpen || open);
      $tr.toggle(pass);
      const $next = $tr.next();
      if ($next.hasClass('phsbot-detail')) $next.toggle(pass);
      if (pass) visible++;
    });
    if (visible === 0) {
      if (!$('#phsbot-leads-table tbody tr.no-items').length) {
        $('#phsbot-leads-table tbody').append('<tr class="no-items"><td colspan="9">'+ PHSBOT_LEADS.i18n.no_rows +'</td></tr>');
      }
    } else {
      $('#phsbot-leads-table tbody tr.no-items').remove();
    }
  }

  /* Render detalle (resumen IA + conversación con IA truncada) */
  function buildConv(messages){
    let html = '<div class="conv">';
    if (!Array.isArray(messages) || !messages.length) {
      html += '<em>Sin mensajes registrados.</em>';
    } else {
      messages.forEach(m=>{
        const isUser = (m.role === 'user');
        const who = isUser ? 'Usuario' : 'IA';
        const when = m.ts ? ' <small>(' + fmt(m.ts) + ')</small>' : '';
        const full = (m.text || '').toString();
        if (isUser) {
          html += `<div class="bubble user"><div class="meta"><strong>${who}</strong>${when}</div><div class="text">${full}</div></div>`;
        } else {
          const trunc = truncateWords(full, 4);
          const needsMore = trunc !== full;
          html += `<div class="bubble bot"><div class="meta"><strong>${who}</strong>${when}</div>`;
          html += `<div class="text"><span class="msg-trunc">${trunc}</span>`;
          if (needsMore) html += ` <button type="button" class="link phsbot-more" data-full="${$('<div>').text(full).html()}">ver respuesta de la IA completa</button>`;
          html += `</div></div>`;
        }
      });
    }
    html += '</div>';
    return html;
  }

  function renderDetail(d){
    const s = (t)=> (t||'').toString();
    const score = (d.score != null ? d.score : '–');
    const meta = `
      <div class="meta-grid">
        <div><strong>CID:</strong> ${s(d.cid)}</div>
        <div><strong>Estado:</strong> ${d.closed ? 'Cerrado' : 'Abierto'}</div>
        <div><strong>Nombre:</strong> ${s(d.name)}</div>
        <div><strong>Email:</strong> ${s(d.email)}</div>
        <div><strong>Teléfono:</strong> ${s(d.phone)}</div>
        <div><strong>Página:</strong> ${s(d.page)}</div>
        <div><strong>Score:</strong> ${score}</div>
        <div><strong>Primera vez:</strong> ${fmt(d.first_ts)}</div>
        <div><strong>Último visto:</strong> ${fmt(d.last_seen)}</div>
      </div>`;
    const summary = (d.summary || '');
    const conv = buildConv(d.messages || []);
    return `<div class="phsbot-detail__wrap">${meta}${summary}${conv}</div>`;
  }

  function openUnderRow($row, html){
    $('#phsbot-leads-table tbody tr.phsbot-detail').remove();
    $('#phsbot-leads-table tbody tr.is-open').removeClass('is-open');
    const $detail = $(`<tr class="phsbot-detail"><td colspan="9">${html}</td></tr>`);
    $row.after($detail); $row.addClass('is-open');
  }

  /* Eventos */
  $(document)
    .on('input change', '#phsbot-leads-search, #phsbot-leads-open-only', filterRows)
    .on('click', '#phsbot-leads-refresh', ()=> location.reload())

    .on('click', '#phsbot-leads-purge', function(e){
      e.preventDefault();
      if (!confirm('¿Purgar todos los leads cerrados con más de 30 días? Esta acción no se puede deshacer.')) return;
      const $btn = $(this);
      const originalText = $btn.text();
      $btn.text('Purgando...').prop('disabled', true);
      ajax('phsbot_leads_purge').done(res=>{
        if (res && res.success) {
          alert('Leads purgados: ' + (res.data.deleted || 0));
          location.reload();
        } else {
          alert('Error al purgar leads.');
          $btn.text(originalText).prop('disabled', false);
        }
      }).fail(()=>{
        alert('Error al purgar leads.');
        $btn.text(originalText).prop('disabled', false);
      });
    })

    .on('click', '.phsbot-view', function(e){
      e.preventDefault();
      const $row = $(this).closest('tr');
      const $btn = $(this);
      const cid = $(this).data('cid');
      const $next = $row.next();
      if ($next.hasClass('phsbot-detail')) {
        $next.remove();
        $row.removeClass('is-open');
        $btn.text('Ver');
        return;
      }
      openUnderRow($row, '<div class="phsbot-detail__wrap"><em>Cargando…</em></div>');
      $btn.text('Ocultar');
      ajax('phsbot_leads_get', { cid }).done(res=>{
        if (res && res.success && res.data) {
          $row.next('.phsbot-detail').find('td').html(renderDetail(res.data));
        } else {
          $row.next('.phsbot-detail').find('td').html('<div class="phsbot-detail__wrap"><em>Error al cargar el detalle.</em></div>');
        }
      }).fail(()=>{
        $row.next('.phsbot-detail').find('td').html('<div class="phsbot-detail__wrap"><em>Error al cargar el detalle.</em></div>');
      });
    })

    .on('click', '.phsbot-detail', function(e){
      if ($(e.target).hasClass('phsbot-more')) {
        const $btn = $(e.target);
        const full = $btn.data('full');
        const $wrap = $btn.closest('.text');
        $wrap.find('.msg-trunc').replaceWith('<span class="msg-full">'+ full +'</span>');
        $btn.remove();
      }
    })

    .on('click', '.phsbot-close', function(e){
      e.preventDefault();
      if (!confirm(PHSBOT_LEADS.i18n.confirm_close)) return;
      const cid = $(this).data('cid');
      const $row = $(this).closest('tr');
      const $btn = $(this);
      ajax('phsbot_leads_close', { cid }).done(res=>{
        if (res && res.success) {
          // Actualizar visualmente: cambiar estado a "Cerrado" y ocultar botón Cerrar
          $row.find('.phsbot-state').removeClass('open').addClass('closed').text('Cerrado');
          $row.data('open', '0');
          $btn.remove(); // Quitar el botón Cerrar
          // Si está abierto el detalle, actualizarlo también
          const $next = $row.next();
          if ($next.hasClass('phsbot-detail')) {
            $next.find('.meta-grid div:contains("Estado:")').html('<strong>Estado:</strong> Cerrado');
          }
          filterRows();
        }
      });
    })

    .on('click', '.phsbot-del', function(e){
      e.preventDefault();
      if (!confirm(PHSBOT_LEADS.i18n.confirm_delete)) return;
      const cid = $(this).data('cid');
      const $row = $(this).closest('tr');
      ajax('phsbot_leads_delete', { cid }).done(res=>{
        if (res && res.success) {
          const $next = $row.next();
          if ($next.hasClass('phsbot-detail')) $next.remove();
          $row.remove(); filterRows();
        }
      });
    })

    .on('click', '#phsbot-leads-checkall', function(){
      $('.phsbot-lead-check').prop('checked', $(this).is(':checked'));
    })
    .on('click', '#phsbot-leads-del-selected', function(){
      const cids = $('.phsbot-lead-check:checked').map(function(){ return $(this).val(); }).get();
      if (!cids.length || !confirm(PHSBOT_LEADS.i18n.confirm_delete)) return;
      ajax('phsbot_leads_delete_bulk', { cids }).done(res=>{
        if (res && res.success) {
          cids.forEach(cid => {
            const $row = $('#phsbot-leads-table tr[data-cid="'+cid+'"]');
            const $next = $row.next(); if ($next.hasClass('phsbot-detail')) $next.remove();
            $row.remove();
          });
          filterRows();
        }
      });
    })

    // RESET navegador: borra memoria local y abre endpoint de refuerzo
    .on('click', '#phsbot-leads-reset-browser', function(e){
      e.preventDefault();
      ajax('phsbot_leads_browser_reset').done(res=>{
        if (res && res.success && res.data) {
          const url = res.data.reset_url || null;
          const v   = res.data.v || 0;
          const suggestedCid = res.data.new_cid || null;

          // Limpieza local rápida: cookies típicas y storages (no bloquea si falla)
          try {
            const delCookie=(n)=>document.cookie=n+"=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
            ['phsbot_cid','cid','phs_cid','phsbot_sid','sid','phs_sid','phsbot_session','session_id','phs_session','chat_sid','chat_cid'].forEach(delCookie);
            document.cookie = "phsbot_reset_v="+String(v)+"; path=/";
            const ncid = suggestedCid || ('cid_' + Math.random().toString(36).slice(2,8) + Date.now().toString(36));
            document.cookie = "phsbot_cid="+ncid+"; path=/";
            const keyMatches=/(cid|sid|session|phs|chat|memory|context)/i;
            try{ for(let i=0;i<localStorage.length;i++){ const k=localStorage.key(i); if(keyMatches.test(k)){ localStorage.removeItem(k); i--; } } }catch(_){ }
            try{ for(let i=0;i<sessionStorage.length;i++){ const k=sessionStorage.key(i); if(keyMatches.test(k)){ sessionStorage.removeItem(k); i--; } } }catch(_){ }
          } catch(_){}

          // Endpoint de refuerzo (por si alguna cookie tiene path/host raro)
          try { if (url) window.open(url, '_blank', 'width=420,height=180'); } catch(_){}

          alert(PHSBOT_LEADS.i18n.reset_done);
        } else {
          alert(PHSBOT_LEADS.i18n.reset_done);
        }
      }).fail(function(){
        alert(PHSBOT_LEADS.i18n.reset_done);
      });
    });

  $(filterRows);
})(jQuery);