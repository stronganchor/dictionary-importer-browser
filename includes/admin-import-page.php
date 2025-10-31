<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_menu_page(
        'Dictionary Import',
        'Dictionary Import',
        'manage_options',
        'dictionary-importer',
        'dib_render_import_page',
        'dashicons-translation',
        40
    );
});

function dib_render_import_page() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['submit']) && !empty($_FILES['tsv_file']['tmp_name'])) {
        $entry_lang = sanitize_text_field($_POST['entry_lang']);
        $def_lang = sanitize_text_field($_POST['def_lang']);

        $uploaded = $_FILES['tsv_file']['tmp_name'];
        $count = dib_import_tsv($uploaded, $entry_lang, $def_lang);

        echo '<div class="updated"><p><strong>' . esc_html($count) . ' entries imported successfully.</strong></p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Dictionary TSV Import</h1>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label>TSV File</label></th>
                    <td><input type="file" name="tsv_file" accept=".tsv,text/tab-separated-values" required></td>
                </tr>
                <tr>
                    <th><label>Entry Language</label></th>
                    <td><input type="text" name="entry_lang" value="Zazaki" required></td>
                </tr>
                <tr>
                    <th><label>Definition Language</label></th>
                    <td><input type="text" name="def_lang" value="Turkish" required></td>
                </tr>
            </table>
            <?php submit_button('Import Dictionary'); ?>
        </form>
    </div>
    <?php
}
