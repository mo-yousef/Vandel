<?php
/*
Plugin Name: Vandel Cleaning Booking
Description: A robust cleaning booking plugin with multi-step form and unified admin dashboard.
Version: 1.0.2
Author: Mohammad Yousif
Text Domain: vandel-booking
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('VANDEL_VERSION')) {
    define('VANDEL_VERSION', '1.0.2');
}
if (!defined('VANDEL_PLUGIN_DIR')) {
    define('VANDEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('VANDEL_PLUGIN_URL')) {
    define('VANDEL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('VANDEL_PLUGIN_BASENAME')) {
    define('VANDEL_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Create required directories
function vandel_create_directories() {
    $directories = [
        VANDEL_PLUGIN_DIR . 'includes/client',
        VANDEL_PLUGIN_DIR . 'includes/ajax',
        VANDEL_PLUGIN_DIR . 'includes/booking',
        VANDEL_PLUGIN_DIR . 'includes/post-types',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}
register_activation_hook(__FILE__, 'vandel_create_directories');
vandel_create_directories();

// Include autoloader
require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';

// Load essential files
require_once VANDEL_PLUGIN_DIR . 'includes/class-helpers.php';
require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php';
require_once VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
require_once VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php';
require_once VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php';

// Force-load post type classes - This is the key fix for your services issue
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
        }
    });
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-sub-service-post-type.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-sub-service-post-type.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\SubServicePostType')) {
            new \VandelBooking\PostTypes\SubServicePostType();
        }
    });
}

// Check for post type registry class and use it if available
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-post-types-registry.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-post-types-registry.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\Registry')) {
            new \VandelBooking\PostTypes\Registry();
        }
    });
}

// Initialize AJAX Handler
function vandel_init_ajax_handler() {
    $ajax_handler_file = VANDEL_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
    if (file_exists($ajax_handler_file)) {
        require_once $ajax_handler_file;
        if (class_exists('VandelBooking\\Ajax\\AjaxHandler')) {
            new VandelBooking\Ajax\AjaxHandler();
        }
    }
}
add_action('init', 'vandel_init_ajax_handler');

// Initialize the shortcode
function vandel_register_booking_shortcode() {
    new VandelBooking\BookingShortcodeRegister();
}
add_action('init', 'vandel_register_booking_shortcode');

// Initialize the plugin
function vandel_booking_init() {
    return \VandelBooking\Plugin::getInstance();
}

// Start the plugin
$GLOBALS['vandel_booking'] = vandel_booking_init();

// Force database tables creation
function vandel_force_create_database_tables() {
    if (class_exists('\\VandelBooking\\Database\\Installer')) {
        $installer = new \VandelBooking\Database\Installer();
        $installer->install();
    }
}
add_action('plugins_loaded', 'vandel_force_create_database_tables', 20);





// /**
//  * Add this code to your main plugin file (vandel-cleaning-booking.php)
//  * or include it from there
//  */

// // Add client model
// require_once VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';

// // Add client database upgrade function
// require_once VANDEL_PLUGIN_DIR . 'includes/client/client-table-fix.php';

// // Add client statistics update function
// require_once VANDEL_PLUGIN_DIR . 'includes/client/update-client-stats.php';

// // Add client details admin class
// require_once VANDEL_PLUGIN_DIR . 'includes/admin/class-client-details.php';

// Enqueue client management scripts and styles
function vandel_enqueue_client_management_assets($hook) {
    // Only load on our plugin pages
    if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
        return;
    }
    
    // Get current tab
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    
    // Only load on client-related pages
    if ($tab === 'clients' || $tab === 'client-details') {
        // Enqueue styles
        wp_enqueue_style(
            'vandel-client-management',
            VANDEL_PLUGIN_URL . 'assets/css/client-management.css',
            [],
            VANDEL_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'vandel-client-management',
            VANDEL_PLUGIN_URL . 'assets/js/admin/client-management.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vandel-client-management', 'vandelClientAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_client_admin'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this client?', 'vandel-booking'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected clients?', 'vandel-booking'),
                'selectClientAndAction' => __('Please select both clients and an action to perform.', 'vandel-booking'),
                'fillRequired' => __('Please fill all required fields.', 'vandel-booking'),
                'recalculate' => __('Recalculate Stats', 'vandel-booking'),
                'recalculating' => __('Recalculating...', 'vandel-booking'),
                'recalculateError' => __('Failed to recalculate statistics.', 'vandel-booking')
            ]
        ]);
    }
}
add_action('admin_enqueue_scripts', 'vandel_enqueue_client_management_assets');




// Register admin-post handler for client import
function vandel_register_client_import_handler() {
    add_action('admin_post_vandel_import_clients', function() {
        // Check if Dashboard class exists and can handle the import
        if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            $dashboard = new \VandelBooking\Admin\Dashboard();
            if (method_exists($dashboard, 'handleClientImport')) {
                $dashboard->handleClientImport();
                return;
            }
        }
        
        // Fallback if Dashboard class can't handle it
        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=clients&action=import&error=handler'));
        exit;
    });
}
add_action('init', 'vandel_register_client_import_handler');

/**
 * Update client statistics after booking creation
 * 
 * @param int $booking_id New booking ID
 * @param array $booking_data Booking data
 */
function vandel_update_client_stats_after_booking($booking_id, $booking_data) {
    if (!isset($booking_data['client_id']) || !class_exists('\\VandelBooking\\Client\\ClientModel')) {
        return;
    }
    
    $client_model = new \VandelBooking\Client\ClientModel();
    $client_model->addBooking(
        $booking_data['client_id'],
        isset($booking_data['total_price']) ? $booking_data['total_price'] : 0,
        isset($booking_data['booking_date']) ? $booking_data['booking_date'] : null
    );
}
add_action('vandel_booking_created', 'vandel_update_client_stats_after_booking', 10, 2);

/**
 * Update client statistics after booking update
 * 
 * @param int $booking_id Booking ID
 * @param string $old_status Previous booking status
 * @param string $new_status New booking status
 */
function vandel_update_client_stats_after_status_change($booking_id, $old_status, $new_status) {
    if (!class_exists('\\VandelBooking\\Client\\ClientModel') || !class_exists('\\VandelBooking\\Booking\\BookingModel')) {
        return;
    }
    
    // Only proceed if status changes between completed and other statuses
    $affects_total = ($old_status === 'completed' || $new_status === 'completed');
    if (!$affects_total) {
        return;
    }
    
    // Get booking details
    $booking_model = new \VandelBooking\Booking\BookingModel();
    $booking = $booking_model->get($booking_id);
    
    if (!$booking || !isset($booking->client_id) || $booking->client_id <= 0) {
        return;
    }
    
    // Update client statistics
    $client_model = new \VandelBooking\Client\ClientModel();
    $client_model->recalculateStats($booking->client_id);
}
add_action('vandel_booking_status_changed', 'vandel_update_client_stats_after_status_change', 10, 3);