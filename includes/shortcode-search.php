<?php
/**
 * Shortcode: [zazaki_dict_search]
 * Renders a single input and a hidden results panel. No extra assets; inline JS.
 */
function zdict_render_search_shortcode() {
    $nonce = wp_create_nonce('zdict_search');
    ob_start(); ?>
    <div class="zdict-wrap" style="max-width:900px;margin:0 auto;">
        <input id="zdict-q" type="search" placeholder="Search word..." style="width:100%;padding:.9rem;border:1px solid #ccc;border-radius:4px;outline:none;">
        <div id="zdict-results" style="display:none;margin-top:12px;border:1px solid #eee;border-radius:4px;padding:8px;"></div>
    </div>
    <script>
    (function(){
        const input = document.getElementById('zdict-q');
        const resultsBox = document.getElementById('zdict-results');
        let timer = null;

        function renderItems(items, query, note) {
            if (!items || !items.length) {
                resultsBox.style.display = 'block';
                resultsBox.innerHTML = `<div>No results for "<strong>${query}</strong>"</div>` + (note ? `<div style="opacity:.75;margin-top:6px;">${note}</div>` : '');
                return;
            }
            const html = items.map((row) => {
                const entry = row.entry_html || row.entry;
                const def   = row.definition_html || row.definition;
                const meta  = [];
                if (row.gender_number) meta.push(row.gender_number);
                if (row.entry_type)    meta.push(row.entry_type);
                return `
                    <div style="padding:10px 8px;border-bottom:1px solid #f1f1f1;">
                        <div style="font-weight:600;font-size:1.05rem;">${entry}</div>
                        ${meta.length ? `<div style="opacity:.75;font-size:.9rem;margin:.15rem 0;">${meta.join(" · ")}</div>` : ``}
                        <div style="margin-top:4px;">${def}</div>
                    </div>
                `;
            }).join('');
            resultsBox.style.display = 'block';
            resultsBox.innerHTML = html;
        }

        function search(q) {
            if (!q || !q.trim()) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }
            const body = new URLSearchParams();
            body.set('action', 'zdict_search');
            body.set('_ajax_nonce', '<?php echo esc_js($nonce); ?>');
            body.set('q', q);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: body.toString()
            })
            .then(r => r.json())
            .then(payload => {
                if (payload && payload.status === 'ok') {
                    renderItems(payload.items, q, payload.note || '');
                } else {
                    renderItems([], q, '');
                }
            })
            .catch(() => renderItems([], q, ''));
        }

        input.addEventListener('input', function(){
            clearTimeout(timer);
            timer = setTimeout(() => search(input.value), 180);
        });

        // submit on Enter without page reload
        input.addEventListener('keydown', function(e){
            if (e.key === 'Enter') { e.preventDefault(); search(input.value); }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('zazaki_dict_search', 'zdict_render_search_shortcode');


// --- SERVER SIDE: SEARCH HANDLER ------------------------------------------

/**
 * Normalize to support diacritic-insensitive compare and simple phonetic fallback.
 * Maps Zazaki/Turkish accented letters to ASCII-ish proxies and lowercases.
 */
function zdict_normalize($s) {
    $s = mb_strtolower($s, 'UTF-8');
    $map = [
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ç'=>'c','ğ'=>'g','ş'=>'s','ı'=>'i','İ'=>'i',
        'ʼ'=>"", '’'=>"", '‘'=>"", '“'=>"", '”'=>"",
        '–'=>'-', '—'=>'-', '‐'=>'-', '´'=>'', '`'=>'', 'ˆ'=>''
    ];
    $s = strtr($s, $map);
    // collapse extra spaces & punctuation that often sneaks in
    $s = preg_replace('~[^\p{L}\p{N}\-\s\']+~u', ' ', $s);
    $s = preg_replace('~\s+~', ' ', $s);
    return trim($s);
}

add_action('wp_ajax_zdict_search', 'zdict_ajax_search');
add_action('wp_ajax_nopriv_zdict_search', 'zdict_ajax_search');

function zdict_ajax_search() {
    check_ajax_referer('zdict_search');

    global $wpdb;
    // TODO: if your table name is different, change this line:
    $table = $wpdb->prefix . 'zazaki_dictionary';

    $q_raw = isset($_POST['q']) ? wp_unslash($_POST['q']) : '';
    $q = trim($q_raw);
    if ($q === '') {
        wp_send_json(['status'=>'ok','items'=>[]]);
    }

    // Skip “needs_review” rows; allow '1' as OK (per your rule).
    // Empty, NULL, '0', 'yes', 'y', etc. are considered "needs review".
    $skip_review_sql = "(
        COALESCE(NULLIF(needs_review,''),'0') NOT IN ('yes','y','true','t','review','rev')
        OR needs_review='1'
    )";

    // We’ll search entries & definitions, accent-insensitive.
    // Build a MySQL-side "normalized" expression using REPLACE chain
    $norm_expr = function($col) {
        // minimal but effective mapping
        return "
            LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,
            'â','a'),'ê','e'),'î','i'),'ô','o'),'û','u'),
            'ç','c'),'ğ','g'),'ş','s'),'ı','i'),'İ','i'),
            'ö','o'),'ü','u'),'á','a'),'é','e'),'í','i'),'ó','o'))
        ";
    };

    $q_norm = zdict_normalize($q);
    $like_any   = '%' . $wpdb->esc_like($q) . '%';
    $like_norm  = '%' . $wpdb->esc_like($q_norm) . '%';

    // First pass: pull candidates where either raw or normalized LIKE hit entry or definition.
    // (Keep limit generous; we’ll rank in PHP precisely.)
    $sql = $wpdb->prepare("
        SELECT id, entry, definition, gender_number, entry_type
        FROM $table
        WHERE $skip_review_sql
          AND (
              entry LIKE %s
           OR definition LIKE %s
           OR {$norm_expr('entry')} LIKE %s
           OR {$norm_expr('definition')} LIKE %s
          )
        LIMIT 250
    ", $like_any, $like_any, $like_norm, $like_norm);

    $rows = $wpdb->get_results($sql, ARRAY_A);

    // PHP-side ranking: exact → prefix → infix (both entry & definition; using normalization)
    $score = function($entry, $def) use ($q, $q_norm) {
        $e  = zdict_normalize($entry);
        $d  = zdict_normalize($def ?? '');
        $eq = zdict_normalize($q_norm);

        // exact entry or exact definition
        if ($e === $eq || $d === $eq) return 1;
        // starts-with (entry first, then def)
        if (strpos($e, $eq) === 0)   return 2;
        if (strpos($d, $eq) === 0)   return 3;
        // contains (entry first, then def)
        if (strpos($e, $eq) !== false) return 4;
        if (strpos($d, $eq) !== false) return 5;
        return 9;
    };

    foreach ($rows as &$r) {
        $r['_rank'] = $score($r['entry'], $r['definition']);
        // simple highlight (entry first); safe-ish: convert special chars, then bold the query (normalized compare)
        $entry_disp = esc_html($r['entry']);
        $def_disp   = esc_html($r['definition']);

        $needle = preg_quote($q_norm, '~');
        $entry_norm = zdict_normalize($r['entry']);
        $def_norm   = zdict_normalize($r['definition']);

        // highlight only the first occurrence (keeps it clean)
        $r['entry_html'] = $entry_disp;
        $r['definition_html'] = $def_disp;

        if ($needle !== '') {
            // try to locate the substring boundaries in the raw string using the normalized positions
            $posE = mb_stripos($entry_norm, $q_norm, 0, 'UTF-8');
            if ($posE !== false) {
                $r['entry_html'] = esc_html(mb_substr($r['entry'], 0, $posE)) .
                                   '<strong>' . esc_html(mb_substr($r['entry'], $posE, mb_strlen($q))) . '</strong>' .
                                   esc_html(mb_substr($r['entry'], $posE + mb_strlen($q)));
            }
            $posD = mb_stripos($def_norm, $q_norm, 0, 'UTF-8');
            if ($posD !== false) {
                $r['definition_html'] = esc_html(mb_substr($r['definition'], 0, $posD)) .
                                        '<strong>' . esc_html(mb_substr($r['definition'], $posD, mb_strlen($q))) . '</strong>' .
                                        esc_html(mb_substr($r['definition'], $posD + mb_strlen($q)));
            }
        }
    }
    unset($r);

    usort($rows, function($a, $b){
        if ($a['_rank'] === $b['_rank']) {
            // tie-breaker: shorter entry first, then by id
            $aL = mb_strlen($a['entry'],'UTF-8');
            $bL = mb_strlen($b['entry'],'UTF-8');
            if ($aL === $bL) return ($a['id'] <=> $b['id']);
            return $aL <=> $bL;
        }
        return $a['_rank'] <=> $b['_rank'];
    });

    // If nothing found, do a fuzzy suggestion over a capped sample (diacritic-agnostic Levenshtein)
    $note = '';
    if (empty($rows)) {
        $qN = zdict_normalize($q);
        $sample = $wpdb->get_results("
            SELECT id, entry, definition, gender_number, entry_type
            FROM $table
            WHERE $skip_review_sql
            ORDER BY id ASC
            LIMIT 1200
        ", ARRAY_A);

        $candidates = [];
        foreach ($sample as $s) {
            $dist = levenshtein($qN, zdict_normalize($s['entry']));
            // keep only reasonably close (tune threshold by length)
            $threshold = max(1, min(4, (int) floor(mb_strlen($qN)/3)));
            if ($dist <= $threshold) {
                $s['_rank'] = 50 + $dist; // worse than any real match, but ordered by closeness
                $candidates[] = $s;
            }
        }
        usort($candidates, fn($a,$b) => $a['_rank'] <=> $b['_rank']);
        $rows = array_slice($candidates, 0, 10);
        if (!empty($rows)) {
            $note = 'No exact matches. Showing closest suggestions.';
        }
    }

    // Final trim + output
    $rows = array_map(function($r){
        unset($r['_rank']);
        return $r;
    }, array_slice($rows, 0, 50));

    wp_send_json([
        'status' => 'ok',
        'items'  => $rows,
        'note'   => $note
    ]);
}

// (Re)register shortcode explicitly in case plugin structure differs
function zdict_register_search_shortcode() {
    add_shortcode('zazaki_dict_search', 'zdict_render_search_shortcode');
}
add_action('init', 'zdict_register_search_shortcode');
