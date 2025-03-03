<?php
namespace VandelBooking;

/**
 * Assets Manager
 */
class Assets {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_head', [$this, 'injectCustomStyles']);
        add_action('admin_head', [$this, 'injectCustomStyles']);
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueueStyles() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vandel_booking_form')) {
            wp_enqueue_style(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_style(
                'vandel-frontend',
                VANDEL_PLUGIN_URL . 'assets/css/frontend-style.css',
                [],
                VANDEL_VERSION
            );
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueueScripts() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vandel_booking_form')) {
            wp_enqueue_script(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr',
                [],
                VANDEL_VERSION,
                true
            );
            
            wp_enqueue_script(
                'vandel-frontend',
                VANDEL_PLUGIN_URL . 'assets/js/booking-form.js',
                ['jquery', 'flatpickr'],
                VANDEL_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('vandel-frontend', 'vandelAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_booking_nonce'),
                'currency' => get_option('vandel_currency', 'USD'),
                'currencySymbol' => \VandelBooking\Helpers::getCurrencySymbol()
            ]);
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAdminAssets($hook) {
        // Dashboard page
        if ($hook === 'toplevel_page_vandel-dashboard' || strpos($hook, 'page_vandel-dashboard') !== false) {
            wp_enqueue_style(
                'vandel-admin',
                VANDEL_PLUGIN_URL . 'assets/css/admin-style.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                null,
                true
            );
            
            wp_enqueue_script(
                'vandel-admin',
                VANDEL_PLUGIN_URL . 'assets/js/admin-script.js',
                ['jquery', 'chart-js'],
                VANDEL_VERSION,
                true
            );
            
            // Only on settings page
            if (isset($_GET['tab']) && $_GET['tab'] === 'settings') {
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                wp_enqueue_media();
            }
        }
        
        // Custom post types
        global $post_type;
        if (in_array($post_type, ['vandel_service', 'vandel_sub_service'])) {
            wp_enqueue_style(
                'vandel-cpt',
                VANDEL_PLUGIN_URL . 'assets/css/cpt-style.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-cpt',
                VANDEL_PLUGIN_URL . 'assets/js/cpt-custom.js',
                ['jquery', 'jquery-ui-sortable'],
                VANDEL_VERSION,
                true
            );
            
            // Localize script for CPT
            wp_localize_script('vandel-cpt', 'vandelAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_admin_nonce'),
                'isNew' => isset($_GET['post']) ? false : true,
                'postType' => $post_type,
                'messages' => [
                    'saving' => __('Saving...', 'vandel-booking'),
                    'saved' => __('Saved!', 'vandel-booking'),
                    'updating' => __('Updating...', 'vandel-booking'),
                    'updated' => __('Updated!', 'vandel-booking'),
                    'error' => __('Error occurred', 'vandel-booking'),
                    'deleteConfirm' => __('Are you sure you want to delete this item?', 'vandel-booking')
                ]
            ]);
        }
    }
    
    /**
     * Inject custom styles
     */
    public function injectCustomStyles() {
        $primary_color = get_option('vandel_primary_color', '#286cd6');
        echo "<style>:root { --primary: {$primary_color} !important; --vandel-primary-color: {$primary_color} !important; }</style>";
    }
}