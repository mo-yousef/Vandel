<?php
/*
Plugin Name: Vandel Cleaning Booking
Description: A robust cleaning booking plugin with multi-step form and unified admin dashboard.
Version: 1.0.3
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
    define('VANDEL_VERSION', '1.0.3');
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
        VANDEL_PLUGIN_DIR . 'includes/location',
        VANDEL_PLUGIN_DIR . 'includes/post-types',
        VANDEL_PLUGIN_DIR . 'includes/database',
        VANDEL_PLUGIN_DIR . 'includes/admin',
        VANDEL_PLUGIN_DIR . 'assets/js/admin',
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
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/autoload.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';
}

// Load helpers early as they're used by many components
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-helpers.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/class-helpers.php';
}

// Load Database Installer first to ensure tables exist
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php';
    
    // Create/update tables on activation
    register_activation_hook(__FILE__, function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
        }
    });
    
    // Check for database updates on plugin load
    add_action('plugins_loaded', function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            if (method_exists($installer, 'needsUpdate') && $installer->needsUpdate()) {
                $installer->update();
            }
        }
    });
}

/**
 * Initialize the plugin
 * This is the main entry point for the plugin
 */
function vandel_plugin_init() {
    // Check if the plugin has already been initialized
    if (isset($GLOBALS['vandel_booking']) && $GLOBALS['vandel_booking'] !== null) {
        return $GLOBALS['vandel_booking'];
    }

    // Initialize the plugin using the Plugin class
    if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-plugin.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/class-plugin.php';
        if (class_exists('\\VandelBooking\\Plugin') && method_exists('\\VandelBooking\\Plugin', 'getInstance')) {
            $GLOBALS['vandel_booking'] = \VandelBooking\Plugin::getInstance();
            return $GLOBALS['vandel_booking'];
        }
    }

    // If Plugin class isn't available, return null
    return null;
}

// Initialize the plugin on 'plugins_loaded' hook with priority 5 (early)
add_action('plugins_loaded', 'vandel_plugin_init', 5);

// Add debugging function for development
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_footer', function() {
        if (isset($GLOBALS['vandel_booking']) && method_exists($GLOBALS['vandel_booking'], 'getLoadedComponents')) {
            echo '<div style="display:none;"><pre>';
            echo "Loaded components: " . print_r($GLOBALS['vandel_booking']->getLoadedComponents(), true);
            echo '</pre></div>';
        }
    });
}