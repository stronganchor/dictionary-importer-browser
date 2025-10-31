<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dib-style', DIB_URL . 'assets/css/style.css');
    wp_enqueue_script('dib-search', DIB_URL . 'assets/js/search.js', ['jquery'], null, true);
    wp_localize_script('dib-search', 'dibAjax', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
});
