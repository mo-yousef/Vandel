<?php
/**
 * Vandel Booking Form Implementation
 * 
 * This file contains all the steps needed to implement the booking form in your WordPress site.
 * 1. Copy this file to your plugin directory at:
 *    vandel-cleaning-booking/includes/setup-booking-form.php
 * 2. Include this file in your main plugin file
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Set up the booking form functionality
 */
function vandel_setup_booking_form() {
    // Make sure paths are set
    if (!defined('VANDEL_PLUGIN_DIR') || !defined('VANDEL_PLUGIN_URL')) {
        return;
    }
    
    // Create the required directories if they don't exist
    $dirs = [
        VANDEL_PLUGIN_DIR . 'assets/css',
        VANDEL_PLUGIN_DIR . 'assets/js',
        VANDEL_PLUGIN_DIR . 'includes/frontend'
    ];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Copy CSS file
    $css_content = file_get_contents(__DIR__ . '/assets/css/frontend-style.css');
    if (!empty($css_content)) {
        file_put_contents(VANDEL_PLUGIN_DIR . 'assets/css/frontend-style.css', $css_content);
    }
    
    // Copy JS file
    $js_content = file_get_contents(__DIR__ . '/assets/js/booking-form.js');
    if (!empty($js_content)) {
        file_put_contents(VANDEL_PLUGIN_DIR . 'assets/js/booking-form.js', $js_content);
    }
    
    // Register shortcode
    require_once VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php';
    require_once VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php';
    
    // Initialize the shortcode (if not already done)
    if (!function_exists('vandel_register_booking_shortcode')) {
        function vandel_register_booking_shortcode() {
            new \VandelBooking\BookingShortcodeRegister();
        }
        add_action('init', 'vandel_register_booking_shortcode');
    }
}

// Add the setup function to initialization hook
add_action('plugins_loaded', 'vandel_setup_booking_form');