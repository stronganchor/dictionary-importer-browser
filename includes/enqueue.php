<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function() {
    // Detect Turkish site language (tr_* or 'tr')
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $is_tr  = (stripos($locale, 'tr') === 0);

    // i18n strings for JS & placeholders
    $i18n = $is_tr ? [
        'search_placeholder' => 'Kelime ara',
        'no_results'         => 'Sonuç bulunamadı',
        'results'            => 'Sonuçlar',
        'gender_m'           => 'dişil',
        'gender_n'           => 'eril',
        'gender_z'           => 'çoğul',
    ] : [
        'search_placeholder' => 'Search word',
        'no_results'         => 'No results',
        'results'            => 'Results',
        'gender_m'           => 'fem.',
        'gender_n'           => 'masc.',
        'gender_z'           => 'pl.',
    ];

    // CSS with filemtime versioning
    $css_rel  = 'assets/css/style.css';
    $css_path = DIB_PATH . $css_rel;
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : null;
    wp_enqueue_style('dib-style', DIB_URL . $css_rel, [], $css_ver);

    // JS with filemtime versioning
    $js_rel  = 'assets/js/search.js';
    $js_path = DIB_PATH . $js_rel;
    $js_ver  = file_exists($js_path) ? filemtime($js_path) : null;
    wp_enqueue_script('dib-search', DIB_URL . $js_rel, ['jquery'], $js_ver, true);

    wp_localize_script('dib-search', 'dibAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'i18n'    => $i18n,
    ]);
});
