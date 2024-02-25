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
 * Schedule import products event
 */
function scheduleImportProductsEvent() {
    if (! wp_next_scheduled('import_products')) {
        wp_schedule_event(
            strtotime('tomorrow midnight'), 
            'daily', 
            'import_products'
        );
    }
}

register_activation_hook(__FILE__, 'scheduleImportProductsEvent');

/**
 * Unschedule import products event
 */
function unscheduleImportProductsEvent() {
    if ($timestamp = wp_next_scheduled('import_products')) {
        wp_unschedule_event(
            $timestamp, 
            'import_products'
        );
    }
}

register_deactivation_hook(__FILE__, 'unscheduleImportProductsEvent');

/**
 * Import products
 */
add_action('import_products', new Task);

/**
 * Force products import
 */
function forceProductsImport() {
    wp_schedule_single_event(time(), 'import_products');
    $result = spawn_cron();

    wp_send_json(['success' => $result,]);
    wp_die();
}

add_action('wp_ajax_force_products_import', 'forceProductsImport');

/**
 * Schedule delete logs event
 */
function scheduleDeleteLogsEvent() {
    if (! wp_next_scheduled('delete_logs')) {
        wp_schedule_event(
            strtotime('tomorrow midnight'), 
            'daily', 
            'delete_logs'
        );
    }
}

register_activation_hook(__FILE__, 'scheduleDeleteLogsEvent');

/**
 * Unschedule delete logs event
 */
function unscheduleDeleteLogsEven() {
    if ($timestamp = wp_next_scheduled('delete_logs')) {
        wp_unschedule_event(
            $timestamp, 
            'delete_logs'
        );
    }
}

register_deactivation_hook(__FILE__, 'unscheduleDeleteLogsEven');

/**
 * Delete logs
 */
add_action('delete_logs', function () {
    $files = array_filter(
        scandir(ICAR_API_ROOT . '/logs/imports'), 
        fn($file) => ! in_array($file, ['.', '..',])
    );
    foreach ($files as $file) {
        $time = strtotime(basename($file, '.log'));
        if (time() - $time >= MONTH_IN_SECONDS) {
            unlink(ICAR_API_ROOT . '/logs/imports/' . $file);
        }
    }
});