<?php

/**
 * Plugin Name: ICAR API
 * Description: ICAR API
 * Author: Sidun Oleh
 */

use IcarAPI\Task;

defined('ABSPATH') or die;

/**
 * Plugin root
 */
const ICAR_API_ROOT = __DIR__;

/**
 * Composer autoloader
 */
require_once ICAR_API_ROOT . '/vendor/autoload.php';

/**
 * Plugin activation
 */
function icarApiActivation() {
    if (! wp_next_scheduled('import_products')) {
        wp_schedule_event(
            strtotime('tomorrow midnight'), 
            'daily', 
            'import_products'
        );
    }

    if (! wp_next_scheduled('delete_logs')) {
        wp_schedule_event(
            strtotime('tomorrow midnight'), 
            'daily', 
            'delete_logs'
        );
    }
}

register_activation_hook(__FILE__, 'icarApiActivation');

/**
 * Plugin deactivation
 */
function icarApiDeactivation() {
    if ($timestamp = wp_next_scheduled('import_products')) {
        wp_unschedule_event(
            $timestamp, 
            'import_products'
        );
    }

    if ($timestamp = wp_next_scheduled('delete_logs')) {
        wp_unschedule_event(
            $timestamp, 
            'delete_logs'
        );
    }
}

register_deactivation_hook(__FILE__, 'icarApiDeactivation');

/**
 * Add settings page
 */
function addSettingsPage() {
    add_submenu_page(
        'edit.php?post_type=product',
        __('ICAR API'),
        __('ICAR API'),
        'manage_options',
        'icar-api',
        function () {
            require_once ICAR_API_ROOT . '/src/templates/settings-page.php';
        }
    );
}

add_action('admin_menu', 'addSettingsPage');

/**
 * Update settings
 */
function updateSettings() {
    $settings = $_POST['settings'] ?? [];

    update_option('icar_api_settings', $settings);

    wp_send_json_success();
    wp_die();
}

add_action('wp_ajax_icar_api_update_settings', 'updateSettings');

/**
 * Import products
 */
add_action('import_products', new Task);

/**
 * Force products import
 */
function forceProductsImport() {
    ignore_user_abort(true);
    (new Task)();
    wp_die();
}

add_action('wp_ajax_force_products_import', 'forceProductsImport');

/**
 * Delete logs
 */
function deleteLogs() {
    $files = scandir(ICAR_API_ROOT . '/logs/imports');
    $files = array_filter($files, function ($file) {
        return ! in_array($file, ['.', '..', '.htaccess',]);
    });
    foreach ($files as $file) {
        $time = strtotime(basename($file, '.log'));
        if (time() - $time >= MONTH_IN_SECONDS) {
            unlink(ICAR_API_ROOT . '/logs/imports/' . $file);
        }
    }
}

add_action('delete_logs', 'deleteLogs');
