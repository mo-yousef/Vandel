<?php
namespace VandelBooking;

/**
 * Class to handle shortcode registration and asset loading
 */
class BookingShortcodeRegister {
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode
        add_shortcode('vandel_booking_form', [$this, 'renderBookingFormShortcode']);
        
        // Register and enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
    }
    
    /**
     * Register and enqueue assets
     */
    public function registerAssets() {
        // Register styles
        wp_register_style(
            'vandel-booking-form',
            VANDEL_PLUGIN_URL . 'assets/css/frontend-style.css',
            [],
            VANDEL_VERSION
        );
        
        // Register scripts
        wp_register_script(
            'vandel-booking-form',
            VANDEL_PLUGIN_URL . 'assets/js/booking-form.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
    }
    
    /**
     * Render booking form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function renderBookingFormShortcode($atts) {
        // Ensure the BookingForm class is loaded
        if (!class_exists('\\VandelBooking\\Frontend\\BookingForm')) {
            require_once VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php';
        }
        
        // Enqueue necessary assets
        wp_enqueue_style('vandel-booking-form');
        wp_enqueue_script('vandel-booking-form');
        
        // Initialize Booking Form
        $booking_form = new Frontend\BookingForm();
        return $booking_form->render($atts);
    }
}

// Initialize the shortcode if not already loaded
if (!function_exists('vandel_register_booking_shortcode')) {
    function vandel_register_booking_shortcode() {
        new BookingShortcodeRegister();
    }
    add_action('init', 'vandel_register_booking_shortcode');
}