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

// Define plugin constants only if not already defined
if (!defined('VANDEL_VERSION')) {
    define('VANDEL_VERSION', '1.0.0');
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

// Include autoloader
require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';

// Manually include critical model classes to ensure they're loaded
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php';
}

// Make sure the client directory exists
if (!file_exists(VANDEL_PLUGIN_DIR . 'includes/client')) {
    wp_mkdir_p(VANDEL_PLUGIN_DIR . 'includes/client');
}

// Include client model - this is essential
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
} else if (file_exists(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-client-model.php')) {
    // If it's in the wrong location, copy it to the right place
    $client_model_content = file_get_contents(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-client-model.php');
    file_put_contents(VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php', $client_model_content);
    require_once VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-helpers.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/class-helpers.php';
}

// Register booking form shortcode
require_once VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php';
require_once VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php';

// Initialize AJAX Handler
function vandel_init_ajax_handler() {
    // First make sure the class file exists
    $ajax_handler_file = VANDEL_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
    if (file_exists($ajax_handler_file)) {
        require_once $ajax_handler_file;
        // Check if the class exists before instantiating
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

/**
 * Create required directories
 */
function vandel_create_directories() {
    $directories = [
        VANDEL_PLUGIN_DIR . 'includes/client',
        VANDEL_PLUGIN_DIR . 'includes/ajax',
        VANDEL_PLUGIN_DIR . 'includes/booking',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}
register_activation_hook(__FILE__, 'vandel_create_directories');

// Run directory creation on plugin load to ensure all directories exist
vandel_create_directories();

// Add a debugging function to check class loading
function vandel_debug_check_classes() {
    $classes_to_check = [
        'VandelBooking\\Plugin' => VANDEL_PLUGIN_DIR . 'includes/class-plugin.php',
        'VandelBooking\\Admin\\AdminLoader' => VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php',
        'VandelBooking\\Admin\\Dashboard' => VANDEL_PLUGIN_DIR . 'includes/admin/class-dashboard.php',
        'VandelBooking\\Booking\\BookingModel' => VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php',
        'VandelBooking\\Client\\ClientModel' => VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php',


        'VandelBooking\\Booking\\BookingModel' => VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php',
        'VandelBooking\\Client\\ClientModel' => VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php',

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
    
    // Check for active hooks
    echo '<h4>Admin Menu Hooks</h4>';
    global $wp_filter;
    
    if (isset($wp_filter['admin_menu'])) {
        echo '<p>Admin menu hooks registered: Yes</p>';
        echo '<ul>';
        
        foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $callback) {
                $callback_name = '';
                
                if (is_string($callback['function'])) {
                    $callback_name = $callback['function'];
                } elseif (is_array($callback['function'])) {
                    if (is_object($callback['function'][0])) {
                        $callback_name = get_class($callback['function'][0]) . '->' . $callback['function'][1];
                    } else {
                        $callback_name = $callback['function'][0] . '::' . $callback['function'][1];
                    }
                }
                
                echo "<li>Priority $priority: $callback_name</li>";
            }
        }
        
        echo '</ul>';
    } else {
        echo '<p>Admin menu hooks registered: No</p>';
    }
    
    echo '</div>';
}

// Add the debug output to the admin footer
add_action('admin_footer', 'vandel_debug_check_classes');

/**
 * Debug AJAX functionality
 */
function vandel_debug_ajax() {
    // Create a debug log file
    $log_file = WP_CONTENT_DIR . '/vandel-debug.log';
    
    // Log all AJAX requests
    add_action('wp_ajax_nopriv_vandel_get_service_details', 'vandel_log_ajax_request', 1);
    add_action('wp_ajax_vandel_get_service_details', 'vandel_log_ajax_request', 1);
    
    // This will run before your actual AJAX handler
    function vandel_log_ajax_request() {
        $log_file = WP_CONTENT_DIR . '/vandel-debug.log';
        
        // Log request data
        $log_data = [
            'time' => current_time('mysql'),
            'action' => $_POST['action'] ?? 'not_set',
            'nonce' => isset($_POST['nonce']) ? 'present' : 'missing',
            'service_id' => $_POST['service_id'] ?? 'not_set',
            'user_logged_in' => is_user_logged_in() ? 'yes' : 'no',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'get_data' => $_GET
        ];
        
        // Write to log file
        file_put_contents(
            $log_file, 
            date('Y-m-d H:i:s') . " - AJAX Debug: " . print_r($log_data, true) . "\n", 
            FILE_APPEND
        );
    }
}

// Initialize debug
vandel_debug_ajax();

/**
 * Log AJAX requests for debugging
 */
function vandel_debug_ajax_requests() {
    // Only enable when WP_DEBUG is on
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Create log file in uploads directory
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/vandel-ajax-debug.log';
    
    // Log AJAX requests
    add_action('wp_ajax_nopriv_vandel_get_service_details', 'vandel_log_ajax_request', 1);
    add_action('wp_ajax_vandel_get_service_details', 'vandel_log_ajax_request', 1);
    
    // Log function
    function vandel_log_ajax_request() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/vandel-ajax-debug.log';
        
        // Prepare log data
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => isset($_REQUEST['action']) ? $_REQUEST['action'] : 'unknown',
            'nonce' => isset($_REQUEST['nonce']) ? 'present' : 'missing',
            'POST' => $_POST,
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'HTTP_X_REQUESTED_WITH' => isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'not set'
        ];
        
        // Write to log file
        file_put_contents($log_file, print_r($log_data, true) . "\n\n", FILE_APPEND);
    }
}

// Initialize AJAX debugging
vandel_debug_ajax_requests();




/**
 * Add diagnostic tool to dashboard
 */
function vandel_add_diagnostic_tool() {
    if (isset($_GET['page']) && $_GET['page'] === 'vandel-dashboard' && isset($_GET['tab']) 
        && $_GET['tab'] === 'settings' && isset($_GET['run_diagnostic'])) {
        
        // Include the diagnostic tool file
        $diagnostic_file = VANDEL_PLUGIN_DIR . 'includes/vandel-diagnostic-tool.php';
        
        if (file_exists($diagnostic_file)) {
            require_once $diagnostic_file;
            $diagnostic = new VandelDiagnosticTool();
            $diagnostic->run();
            exit;
        }
    }
}
add_action('admin_init', 'vandel_add_diagnostic_tool');

/**
 * Add diagnostic link to settings page
 */
function vandel_add_diagnostic_link($content) {
    if (isset($_GET['page']) && $_GET['page'] === 'vandel-dashboard' && isset($_GET['tab']) && $_GET['tab'] === 'settings') {
        echo '<div style="margin-top: 20px; padding: 10px; background-color: #f8f9fa; border: 1px solid #ddd;">';
        echo '<h3>Database Diagnostics</h3>';
        echo '<p>If you\'re having trouble with bookings not showing up, run the diagnostic tool to check your database setup.</p>';
        echo '<a href="' . admin_url('admin.php?page=vandel-dashboard&tab=settings&run_diagnostic=1') . '" class="button button-primary">Run Diagnostic Tool</a>';
        echo '</div>';
    }
    
    return $content;
}
add_action('admin_notices', 'vandel_add_diagnostic_link');

/**
 * Force table creation on plugin activation
 */
function vandel_force_table_creation() {
    // Include the installer class
    if (file_exists(VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php';
        $installer = new VandelBooking\Database\Installer();
        $installer->install();
    }
}
register_activation_hook(__FILE__, 'vandel_force_table_creation');