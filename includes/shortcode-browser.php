<?php
if (!defined('ABSPATH')) exit;

add_shortcode('dictionary_browser', function($atts) {
    global $wpdb;
    $table = $wpdb->prefix . 'dictionary_entries';
    $letter = isset($_GET['letter']) ? sanitize_text_field($_GET['letter']) : 'A';

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT entry, definition, gender_number, entry_type
         FROM $table
         WHERE entry LIKE %s
         ORDER BY entry ASC
         LIMIT 500", $letter . '%'
    ));

    ob_start(); ?>
    <div class="dib-browser">
        <div class="dib-letters">
            <?php foreach (range('A', 'Z') as $ltr): ?>
                <a href="?letter=<?php echo $ltr; ?>" class="<?php echo $ltr == $letter ? 'active' : ''; ?>">
                    <?php echo $ltr; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <ul class="dib-list">
            <?php foreach ($entries as $row): ?>
                <li>
                    <strong><?php echo esc_html($row->entry); ?></strong>
                    <?php if ($row->gender_number): ?>
                        <em class="dib-gender">(<?php echo esc_html($row->gender_number); ?>)</em>
                    <?php endif; ?>
                    — <?php echo esc_html($row->definition); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
});
