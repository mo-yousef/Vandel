<?php
namespace VandelBooking\Frontend;

/**
 * Frontend Loader
 */
class FrontendLoader {
    /**
     * Constructor
     */
    public function __construct() {
        $this->initHooks();
        $this->loadComponents();
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks() {
        // Add frontend-specific hooks here
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    /**
     * Load frontend components
     */
    private function loadComponents() {
        // Initialize frontend components
        new BookingForm();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueAssets() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vandel_booking_form')) {
            wp_enqueue_style(
                'vandel-frontend',
                VANDEL_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-frontend',
                VANDEL_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
        }
        
    }
}