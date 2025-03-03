<?php
/*
Plugin Name: Vandel Cleaning Booking
Description: A robust cleaning booking plugin with multi-step form and unified admin dashboard.
Version: 1.0.0
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

// Initialize the plugin
function vandel_booking_init() {
    return \VandelBooking\Plugin::getInstance();
}

// Start the plugin
$GLOBALS['vandel_booking'] = vandel_booking_init();


// Check if WordPress is loaded
if (!function_exists('add_action')) {
    echo 'WordPress not loaded correctly';
    exit;
}

// Add a debugging function to check class loading
function vandel_debug_check_classes() {
    $classes_to_check = [
        'VandelBooking\\Plugin' => VANDEL_PLUGIN_DIR . 'includes/class-plugin.php',
        'VandelBooking\\Admin\\AdminLoader' => VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php',
        'VandelBooking\\Admin\\Dashboard' => VANDEL_PLUGIN_DIR . 'includes/admin/class-dashboard.php',
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
