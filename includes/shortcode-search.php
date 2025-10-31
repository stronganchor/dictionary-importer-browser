<?php
if (!defined('ABSPATH')) exit;

add_shortcode('dictionary_search', function($atts) {
    ob_start(); ?>
    <div id="dib-search">
        <input type="text" id="dib-search-input" placeholder="Search word..." />
        <div id="dib-results"></div>
    </div>
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_dib_search', 'dib_ajax_search');
add_action('wp_ajax_nopriv_dib_search', 'dib_ajax_search');

function dib_ajax_search() {
    global $wpdb;
    $term_raw = sanitize_text_field($_GET['q'] ?? '');
    $term = trim($term_raw);
    if ($term === '') { wp_send_json([]); }

    $table = $wpdb->prefix . 'dictionary_entries';

    // Normalize function for diacritics & Turkish-specific letters
    $normalize = function($s) {
        $map = [
            'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u','Â'=>'a','Ê'=>'e','Î'=>'i','Ô'=>'o','Û'=>'u',
            'á'=>'a','à'=>'a','ä'=>'a','ã'=>'a','å'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Ã'=>'a','Å'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','É'=>'e','È'=>'e','Ë'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','õ'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u',
            'ş'=>'s','Ş'=>'s','ç'=>'c','Ç'=>'c','ğ'=>'g','Ğ'=>'g',
            'İ'=>'i','ı'=>'i'
        ];
        $s = strtr($s, $map);
        $s = preg_replace('~[^a-z0-9\s\-\'"]~i', '', $s); // strip other diacritics/symbols
        return mb_strtolower($s, 'UTF-8');
    };

    $term_norm = $normalize($term);

    // LIKE patterns (escape user input for LIKE)
    $like_esc = $wpdb->esc_like($term);
    $like_norm_esc = $wpdb->esc_like($term_norm);
    $like_any   = '%' . $like_esc . '%';
    $like_pref  = $like_esc . '%';
    $like_any_n = '%' . $like_norm_esc . '%';
    $like_pref_n= $like_norm_esc . '%';

    // Primary query:
    // Order by exact (entry==term), then exact on definition, then prefix (entry), prefix (definition),
    // then contains (entry), contains (definition). Secondary sort by entry ASC.
    // We also include normalized-term comparisons as fallbacks in both WHERE and ORDER BY.
    $sql = "
        SELECT entry, definition, gender_number, entry_type, entry_lang, def_lang
        FROM $table
        WHERE
            entry LIKE %s OR definition LIKE %s
            OR entry LIKE %s OR definition LIKE %s
        ORDER BY
            CASE
                WHEN entry = %s THEN 0
                WHEN definition = %s THEN 1
                WHEN entry LIKE %s THEN 2
                WHEN definition LIKE %s THEN 3
                WHEN entry LIKE %s THEN 4
                WHEN definition LIKE %s THEN 5
                WHEN entry LIKE %s THEN 6
                WHEN definition LIKE %s THEN 7
                ELSE 8
            END,
            entry ASC
        LIMIT 50
    ";

    $rows = $wpdb->get_results($wpdb->prepare(
        $sql,
        $like_any,      // entry LIKE %term%
        $like_any,      // definition LIKE %term%
        $like_any_n,    // entry LIKE %term_norm%
        $like_any_n,    // definition LIKE %term_norm%
        $term,          // entry = term
        $term,          // definition = term
        $like_pref,     // entry LIKE term%
        $like_pref,     // definition LIKE term%
        $like_any,      // entry LIKE %term%
        $like_any,      // definition LIKE %term%
        $like_pref_n,   // entry LIKE term_norm%
        $like_pref_n    // definition LIKE term_norm%
    ));

    // If nothing decent came back, do a fuzzy fallback:
    // Grab a modest slice of candidates by initial letter(s) and rank by Levenshtein on normalized strings.
    if (empty($rows)) {
        $seed = mb_substr($term_norm, 0, max(1, min(3, mb_strlen($term_norm, 'UTF-8'))), 'UTF-8');
        $like_seed = $wpdb->esc_like($seed) . '%';

        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT entry, definition, gender_number, entry_type, entry_lang, def_lang
             FROM $table
             WHERE entry LIKE %s OR entry LIKE %s OR entry LIKE %s
             ORDER BY entry ASC
             LIMIT 300",
             $like_seed,
             '%' . $wpdb->esc_like($seed) . '%',
             '%' . $wpdb->esc_like(mb_substr($seed, 0, 1, 'UTF-8')) . '%'
        ));

        // Score by Levenshtein distance on normalized entry
        $scored = [];
        foreach ($candidates as $r) {
            $e_norm = $normalize($r->entry);
            // Guard: levenshtein works best on <=255 length; entries should be short
            $dist = levenshtein($term_norm, $e_norm);
            $scored[] = ['row' => $r, 'dist' => $dist];
        }
        usort($scored, function($a, $b){
            if ($a['dist'] === $b['dist']) {
                return strcasecmp($a['row']->entry, $b['row']->entry);
            }
            return $a['dist'] <=> $b['dist'];
        });

        $rows = array_map(function($x){ return $x['row']; }, array_slice($scored, 0, 20));
    }

    wp_send_json($rows ?: []);
}

