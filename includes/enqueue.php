<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function() {
    // CSS with filemtime version
    $css_rel  = 'assets/css/style.css';
    $css_path = DIB_PATH . $css_rel;
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : null;
    wp_enqueue_style('dib-style', DIB_URL . $css_rel, [], $css_ver);

    // JS with filemtime version
    $js_rel  = 'assets/js/search.js';
    $js_path = DIB_PATH . $js_rel;
    $js_ver  = file_exists($js_path) ? filemtime($js_path) : null;
    wp_enqueue_script('dib-search', DIB_URL . $js_rel, ['jquery'], $js_ver, true);

    wp_localize_script('dib-search', 'dibAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
