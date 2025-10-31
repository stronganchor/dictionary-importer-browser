jQuery(document).ready(function($){
    $('#dib-search-input').on('input', function(){
        let q = $(this).val().trim();
        if(q.length < 2){ $('#dib-results').empty(); return; }

        $.getJSON(dibAjax.ajaxurl, {action: 'dib_search', q}, function(rows){
            let html = rows.map(r =>
                `<div class="dib-row"><strong>${r.entry}</strong> (${r.gender_number || ''}) — ${r.definition}</div>`
            ).join('');
            $('#dib-results').html(html || '<em>No results found.</em>');
        });
    });
});
