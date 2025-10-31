<?php
if (!defined('ABSPATH')) exit;

/**
 * Only enqueue the browser UI assets when the [dictionary_browser] shortcode
 * exists on the current page. This prevents an extra, confusing results box
 * from appearing under the search shortcode UI.
 */
add_action('wp_enqueue_scripts', function() {
    if (!is_singular()) return;

    $post = get_queried_object();
    if (!isset($post->post_content)) return;

    if (has_shortcode($post->post_content, 'dictionary_browser')) {
        wp_enqueue_style('dib-style', DIB_URL . 'assets/css/style.css');
        wp_enqueue_script('dib-search', DIB_URL . 'assets/js/search.js', ['jquery'], null, true);
        wp_localize_script('dib-search', 'dibAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
});
