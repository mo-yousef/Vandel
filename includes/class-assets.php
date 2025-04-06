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
        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        
        // Admin
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Inject global style
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
            
            // wp_enqueue_script(
            //     'vandel-frontend',
            //     VANDEL_PLUGIN_URL . 'assets/js/booking-form.js',
            //     ['jquery', 'flatpickr'],
            //     VANDEL_VERSION,
            //     true
            // ); 
            
            // Localize script
            wp_localize_script('vandel-frontend', 'vandelAjax', [
                'ajaxurl'        => admin_url('admin-ajax.php'),
                'nonce'          => wp_create_nonce('vandel_booking_nonce'),
                'currency'       => get_option('vandel_currency', 'USD'),
                'currencySymbol' => \VandelBooking\Helpers::getCurrencySymbol(),
            ]);
        }
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueueAdminAssets($hook) {
        // Dashboard pages
        if ($hook === 'toplevel_page_vandel-dashboard' || strpos($hook, 'page_vandel-dashboard') !== false) {
            // Admin core styles
            // wp_enqueue_style(
            //     'vandel-admin-style',
            //     VANDEL_PLUGIN_URL . 'assets/css/admin-style.css',
            //     [],
            //     VANDEL_VERSION
            // );
            
            // Settings-specific styles
            // wp_enqueue_style(
            //     'vandel-settings-style',
            //     VANDEL_PLUGIN_URL . 'assets/css/admin-settings.css',
            //     [],
            //     VANDEL_VERSION
            // );
            
            // Chart.js (for possible dashboard charts)
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                null,
                true
            );
            
            // Core admin scripts
            wp_enqueue_script(
                'vandel-admin-script',
                VANDEL_PLUGIN_URL . 'assets/js/admin-script.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
            
            // Settings-specific scripts
            wp_enqueue_script(
                'vandel-settings-script',
                VANDEL_PLUGIN_URL . 'assets/js/admin/settings.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
            
            // Localize for admin functionality
            wp_localize_script('vandel-admin-script', 'vandelAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('vandel_admin_nonce'),
                'messages' => [
                    'saving'       => __('Saving...', 'vandel-booking'),
                    'saved'        => __('Saved!', 'vandel-booking'),
                    'error'        => __('Error occurred', 'vandel-booking'),
                    'deleteConfirm'=> __('Are you sure you want to delete this item?', 'vandel-booking'),
                ],
            ]);
            
            // If you only want color-picker/media on certain pages:
            // if (isset($_GET['tab']) && $_GET['tab'] === 'settings') {
            //     wp_enqueue_style('wp-color-picker');
            //     wp_enqueue_script('wp-color-picker');
            //     wp_enqueue_media();
            // }
            
            // Otherwise, to always include them, uncomment these:
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();
        }
        
        // Custom post types
        global $post_type;
        if (in_array($post_type, ['vandel_service', 'vandel_sub_service'])) {
            wp_enqueue_style(
                'vandel-cpt',
                VANDEL_PLUGIN_URL . 'assets/css/cpt-custom.css',
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
        }
    }

    /**
     * Inject custom styles (frontend & admin)
     */
    public function injectCustomStyles() {
        $primary_color = get_option('vandel_primary_color', '#286cd6');
        echo "<style>
            :root { 
                --primary: {$primary_color} !important;
                --vandel-primary-color: {$primary_color} !important;
            }
        </style>";
    }
}
