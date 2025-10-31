<?php
if (!defined('ABSPATH')) exit;

function dib_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'dictionary_entries';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        entry VARCHAR(255) NOT NULL,
        definition TEXT,
        gender_number VARCHAR(10),
        entry_type VARCHAR(50),
        parent VARCHAR(255),
        needs_review VARCHAR(50),
        page_number VARCHAR(50),
        entry_lang VARCHAR(50),
        def_lang VARCHAR(50),
        PRIMARY KEY (id),
        INDEX (entry),
        INDEX (entry_lang),
        INDEX (def_lang)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function dib_import_tsv($file_path, $entry_lang, $def_lang) {
    global $wpdb;
    $table = $wpdb->prefix . 'dictionary_entries';
    $handle = fopen($file_path, 'r');
    if (!$handle) return new WP_Error('file_error', 'Could not open TSV file.');

    $header = fgetcsv($handle, 0, "\t"); // Skip header
    $count = 0;
    while (($data = fgetcsv($handle, 0, "\t")) !== false) {
        if (count($data) < 7) continue;

        list($entry, $definition, $gender_number, $entry_type, $parent, $needs_review, $page_number) = $data;

        // Skip if needs_review has serious issues but keep '1'
        if (!empty($needs_review) && $needs_review !== '1') continue;

        $wpdb->insert($table, [
            'entry' => trim($entry),
            'definition' => trim($definition),
            'gender_number' => trim($gender_number),
            'entry_type' => trim($entry_type),
            'parent' => trim($parent),
            'needs_review' => trim($needs_review),
            'page_number' => trim($page_number),
            'entry_lang' => $entry_lang,
            'def_lang' => $def_lang
        ]);
        $count++;
    }

    fclose($handle);
    return $count;
}
