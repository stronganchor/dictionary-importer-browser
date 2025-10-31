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
    $term = sanitize_text_field($_GET['q'] ?? '');
    $table = $wpdb->prefix . 'dictionary_entries';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT entry, definition, gender_number, entry_type, entry_lang, def_lang
         FROM $table
         WHERE entry LIKE %s
         ORDER BY entry ASC
         LIMIT 50", "%$term%"
    ));

    wp_send_json($rows);
}
