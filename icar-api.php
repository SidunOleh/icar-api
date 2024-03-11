<?php

/**
 * Plugin Name: ICAR API
 * Description: ICAR API
 * Author: Sidun Oleh
 */

use IcarAPI\ImportProductsTask;;
use IcarAPI\UpdateProductsTask;

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
    if (! wp_next_scheduled('products_update')) {
        wp_schedule_event(
            strtotime('tomorrow midnight'), 
            'daily', 
            'products_update'
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
    if ($timestamp = wp_next_scheduled('products_update')) {
        wp_unschedule_event(
            $timestamp, 
            'products_update'
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
function importProducts() {    
    try {
        $filepath = $_FILES['xlsx']['tmp_name'] ?? '';

        if (! $filepath) {
            throw new Exception('No file was passed.');
        }

        (new ImportProductsTask)($filepath);

        wp_send_json_success();
    } catch (Exception $e) {
        wp_send_json_error(['msg' => $e->getMessage(),]);
    }

    wp_die();
}

add_action('wp_ajax_import_products', 'importProducts');

/**
 * Update products
 */
add_action('products_update', fn() => (new UpdateProductsTask)());

/**
 * Force products update
 */
function forceProductsUpdate() {
    ignore_user_abort(true);
    
    header('Connection: close');
    flush();

    (new UpdateProductsTask)();
    
    wp_die();
}

add_action('wp_ajax_force_products_update', 'forceProductsUpdate');

/**
 * Delete logs
 */
function deleteLogs() {
    $dir = ICAR_API_ROOT . '/logs/updates/';
    $files = array_filter(scandir($dir), function ($file) {
        return ! in_array($file, ['.', '..',]);
    });
    foreach ($files as $file) {
        $time = strtotime(basename($file, '.log'));
        if (time() - $time >= MONTH_IN_SECONDS) {
            unlink($dir . $file);
        }
    }
}

add_action('delete_logs', 'deleteLogs');
