(function($){
  $(function(){
    // i18n from PHP (see enqueue.php)
    var t = (window.dibAjax && dibAjax.i18n) ? dibAjax.i18n : {
      search_placeholder: 'Search word',
      no_results: 'No results',
      results: 'Results',
      gender_m: 'fem.',
      gender_n: 'masc.',
      gender_z: 'pl.'
    };

    var $q = $('#dib-query');
    var $out = $('#dib-results');

    // Ensure placeholder matches locale
    if ($q.length) $q.attr('placeholder', t.search_placeholder);

    // Map DB gender_number codes (n/m/z) to localized labels
    function mapGender(code) {
      if (!code) return '';
      var c = (code + '').trim().toLowerCase();
      if (c === 'n') return t.gender_n;
      if (c === 'm') return t.gender_m;
      if (c === 'z') return t.gender_z;
      return ''; // unknown → don’t display
    }

    // Format a single row
    function formatRow(row){
      // row: {entry, definition, gender_number, entry_type, ...}
      var g = mapGender(row.gender_number);
      // only render parentheses if we actually have a mapped gender label
      var genderFrag = g ? ' (' + g + ')' : '';
      var def = row.definition || '';

      return '<div class="dib-item">' +
               '<div class="dib-entry"><strong>' + escapeHtml(row.entry) + '</strong>' + genderFrag + '</div>' +
               (def ? '<div class="dib-def">' + escapeHtml(def) + '</div>' : '') +
             '</div>';
    }

    function escapeHtml(s){
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    var pending = null;
    $q.on('input', function(){
      var val = $q.val().trim();
      if (pending) {
        clearTimeout(pending);
        pending = null;
      }
      pending = setTimeout(function(){
        if (!val){
          $out.empty(); // CSS rule #dib-results:empty {display:none} will hide this
          return;
        }
        $.getJSON((dibAjax && dibAjax.ajaxurl) || '/wp-admin/admin-ajax.php', {
          action: 'dib_search',
          q: val
        }).done(function(rows){
          if (!rows || !rows.length){
            $out.html('<div class="dib-empty">' + t.no_results + '</div>');
            return;
          }
          var html = '<div class="dib-header">' + t.results + ' (' + rows.length + ')</div>';
          for (var i=0; i<rows.length; i++){
            html += formatRow(rows[i]);
          }
          $out.html(html);
        }).fail(function(){
          $out.html('<div class="dib-empty">' + t.no_results + '</div>');
        });
      }, 180);
    });
  });
})(jQuery);
