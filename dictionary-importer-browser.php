<?php
/*
Plugin Name: Dictionary Importer & Browser
Description: Import TSV dictionaries and provide searchable/browsable shortcodes.
Version: 1.0
Author: Strong Anchor Tech
*/

if (!defined('ABSPATH')) exit;

define('DIB_PATH', plugin_dir_path(__FILE__));
define('DIB_URL', plugin_dir_url(__FILE__));

// Includes
require_once DIB_PATH . 'includes/db-functions.php';
require_once DIB_PATH . 'includes/admin-import-page.php';
require_once DIB_PATH . 'includes/shortcode-search.php';
require_once DIB_PATH . 'includes/shortcode-browser.php';
require_once DIB_PATH . 'includes/enqueue.php';

// Activation: create DB table
register_activation_hook(__FILE__, 'dib_create_table');
