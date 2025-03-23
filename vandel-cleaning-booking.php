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

// Debugging functions - Keep these for troubleshooting
function vandel_debug_check_classes() {
    if (!current_user_can('manage_options')) return;
    
    $classes_to_check = [
        'VandelBooking\\Plugin' => VANDEL_PLUGIN_DIR . 'includes/class-plugin.php',
        'VandelBooking\\Admin\\AdminLoader' => VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php',
        'VandelBooking\\Admin\\Dashboard' => VANDEL_PLUGIN_DIR . 'includes/admin/class-dashboard.php',
        'VandelBooking\\Booking\\BookingModel' => VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php',
        'VandelBooking\\Client\\ClientModel' => VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php',
        'VandelBooking\\PostTypes\\ServicePostType' => VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php',
        'VandelBooking\\PostTypes\\SubServicePostType' => VANDEL_PLUGIN_DIR . 'includes/post-types/class-sub-service-post-type.php',
        'VandelBooking\\PostTypes\\Registry' => VANDEL_PLUGIN_DIR . 'includes/post-types/class-post-types-registry.php',
    ];
    
    echo '<div style="background:#f8f9fa; border:1px solid #ddd; padding:10px; margin:20px; font-family:monospace;margin-left: 200px;">';
    echo '<h3>Vandel Booking Debug</h3>';
    
    foreach ($classes_to_check as $class => $path) {
        echo '<p>';
        echo "Class: $class<br>";
        echo "Path: $path<br>";
        echo "File exists: " . (file_exists($path) ? 'Yes' : 'No') . "<br>";
        echo "Class exists: " . (class_exists($class) ? 'Yes' : 'No');
        echo '</p>';
    }
    
    // Post type checking
    echo '<h3>Post Type Check</h3>';
    $post_types = get_post_types([], 'objects');
    $vandel_post_types = [];
    foreach ($post_types as $type) {
        if (strpos($type->name, 'vandel_') === 0) {
            $vandel_post_types[] = $type->name;
        }
    }
    
    echo '<p>Vandel Post Types Found: ' . (empty($vandel_post_types) ? 'None' : implode(', ', $vandel_post_types)) . '</p>';
    
    // Check for service posts
    $services = new WP_Query([
        'post_type' => 'vandel_service',
        'posts_per_page' => -1
    ]);
    
    echo '<p>Services Count: ' . $services->found_posts . '</p>';
    
    echo '</div>';
}
add_action('admin_footer', 'vandel_debug_check_classes');

function vandel_check_database_tables() {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'vandel_bookings',
        $wpdb->prefix . 'vandel_clients',
        $wpdb->prefix . 'vandel_booking_notes'
    );
    
    echo '<div style="background:#fff; padding:20px; margin-top:20px; border:1px solid #ccc;margin-left: 200px;">';
    echo '<h2>Vandel Database Tables Check</h2>';
    
    foreach ($tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        echo "<p>Table {$table}: " . ($exists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">MISSING</span>') . "</p>";
    }
    
    $exists = false;
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $exists = true;
            break;
        }
    }
    
    if ($exists) {
        // Show some sample data from the bookings table
        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}vandel_bookings LIMIT 5");
        if ($bookings) {
            echo '<h3>Sample Booking Data:</h3>';
            // echo '<pre>' . print_r($bookings, true) . '</pre>';
        } else {
            echo '<p>No booking data found in the database.</p>';
        }
    }
    
    echo '</div>';
}
add_action('admin_footer', 'vandel_check_database_tables');