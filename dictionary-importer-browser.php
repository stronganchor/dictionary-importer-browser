<?php
/*
Plugin Name: Dictionary Importer & Browser
Description: Import TSV dictionaries and provide searchable/browsable shortcodes.
Version: 1.0.1
Update URI: https://github.com/stronganchor/dictionary-importer-browser
Author: Strong Anchor Tech
*/

if (!defined('ABSPATH')) exit;

define('DIB_PATH', plugin_dir_path(__FILE__));
define('DIB_URL', plugin_dir_url(__FILE__));

function dib_get_update_branch() {
    $branch = 'main';

    if ( defined( 'DIB_UPDATE_BRANCH' ) && is_string( DIB_UPDATE_BRANCH ) ) {
        $override = trim( DIB_UPDATE_BRANCH );
        if ( '' !== $override ) {
            $branch = $override;
        }
    }

    return (string) apply_filters( 'dib_update_branch', $branch );
}

function dib_bootstrap_update_checker() {
    $checker_file = plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
    if ( ! file_exists( $checker_file ) ) {
        return;
    }

    require_once $checker_file;

    if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
        return;
    }

    $repo_url = (string) apply_filters( 'dib_update_repository', 'https://github.com/stronganchor/dictionary-importer-browser' );
    $slug     = dirname( plugin_basename( __FILE__ ) );

    $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $repo_url,
        __FILE__,
        $slug
    );

    $update_checker->setBranch( dib_get_update_branch() );

    foreach ( array( 'DIB_GITHUB_TOKEN', 'STRONGANCHOR_GITHUB_TOKEN', 'ANCHOR_GITHUB_TOKEN' ) as $constant_name ) {
        if ( ! defined( $constant_name ) || ! is_string( constant( $constant_name ) ) ) {
            continue;
        }

        $token = trim( (string) constant( $constant_name ) );
        if ( '' !== $token ) {
            $update_checker->setAuthentication( $token );
            break;
        }
    }
}

dib_bootstrap_update_checker();

// Includes
require_once DIB_PATH . 'includes/db-functions.php';
require_once DIB_PATH . 'includes/admin-import-page.php';
require_once DIB_PATH . 'includes/shortcode-search.php';
require_once DIB_PATH . 'includes/shortcode-browser.php';
require_once DIB_PATH . 'includes/enqueue.php';

// Activation: create DB table
register_activation_hook(__FILE__, 'dib_create_table');
