jQuery(function($){
  function renderMessage(msg, isError){
    var cls = isError ? 'dbk-msg error' : 'dbk-msg';
    $('#dbk-results').html('<div class="'+cls+'">'+msg+'</div>');
  }

  function statusPill(value){
    var v  = (value || '').toString();
    var lc = v.toLowerCase();
    var cls = 'status-pill';

    // Map to visual types
    if (lc.indexOf('approved') !== -1) {
      cls += ' status-approved';
    } else if (lc.indexOf('upcoming') !== -1 || lc.indexOf('confirmed') !== -1) {
      cls += ' status-upcoming';
    } else if (lc.indexOf('cancel') !== -1 || lc.indexOf('rejected') !== -1 || lc.indexOf('declined') !== -1) {
      cls += ' status-bad';
    } else {
      cls += ' status-neutral';
    }

    return '<span class="'+cls+'" title="'+(v || '')+'">'+(v || '')+'</span>';
  }

  function renderTable(items){
    var html = '<table class="dbk-table"><thead><tr>';
    html += '<th>Booking ID</th><th>Patient Name</th><th>Status</th><th>Present Status</th>';
    html += '</tr></thead><tbody>';

    items.forEach(function(it){
      html += '<tr>';
      html += '<td>' + (it.booking_id || '') + '</td>';
      html += '<td>' + (it.patient_name || '') + '</td>';
      html += '<td>' + statusPill(it.status) + '</td>';
      html += '<td>' + statusPill(it.present_status) + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table>';
    $('#dbk-results').html(html);
  }

  function doSearch(){
    var q = $('#dbk-search-input').val().trim();

    if (!q) {
      renderMessage('Type a Booking ID (e.g. ' + (DocBookerSearch.example || '#WPDB-5932') + ').');
      return;
    }

    // Client-side strict validation: booking ID only
    var ok = /^[#A-Za-z0-9\-]+$/.test(q);
    if (!ok) {
      renderMessage('Please search using Booking ID only (e.g. #WPDB-5932).', true);
      return;
    }

    renderMessage('Searching...');

    $.post(DocBookerSearch.ajax_url, {
      action: 'docbooker_search',
      q:      q,
      nonce:  DocBookerSearch.nonce
    }).done(function(resp){
      if (!resp || typeof resp.success === 'undefined') {
        renderMessage('Unexpected response from server.', true);
        return;
      }

      if (!resp.success) {
        renderMessage('Error: ' + (resp.data || 'Unknown'), true);
        return;
      }

      var data = resp.data || {};

      if (!data.found || !data.items || data.items.length === 0) {
        var m = data.message || 'No booking found.';
        renderMessage(m);
        return;
      }

      renderTable(data.items);
    }).fail(function(jqXHR, textStatus){
      renderMessage('AJAX error: ' + textStatus, true);
    });
  }

  $('#dbk-search-btn').on('click', doSearch);

  $('#dbk-reset-btn').on('click', function(){
    $('#dbk-search-input').val('');
    $('#dbk-results').html('');
  });

  $('#dbk-search-input').on('keypress', function(e){
    if (e.which === 13) doSearch();
  });
});
