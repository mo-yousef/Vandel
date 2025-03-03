<?php
namespace VandelBooking\Admin;

/**
 * Admin Loader
 */
class AdminLoader {
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
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_bar_menu', [$this, 'addAdminBarLink'], 100);
    }
    
    /**
     * Load admin components
     */
    private function loadComponents() {
        new Dashboard();
        new BookingDetails();
        new ClientDetails();
        new SettingsPage();
    }
    
    /**
     * Register admin menu
     */
    public function registerMenu() {
        add_menu_page(
            __('Vandel Dashboard', 'vandel-booking'),
            __('Vandel Booking', 'vandel-booking'),
            'manage_options',
            'vandel-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-chart-area',
            26
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Clients', 'vandel-booking'), 
            __('Clients', 'vandel-booking'), 
            'manage_options', 
            'vandel-dashboard&tab=clients', 
            [$this, 'renderDashboard']
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Settings', 'vandel-booking'), 
            __('Settings', 'vandel-booking'), 
            'manage_options', 
            'vandel-dashboard&tab=settings', 
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render dashboard page
     */
    public function renderDashboard() {
        $dashboard = new Dashboard();
        $dashboard->render();
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        // Only load on plugin pages
        if ($hook === 'toplevel_page_vandel-dashboard' || 
            (isset($_GET['page']) && $_GET['page'] === 'vandel-booking-details')) {
            
            wp_enqueue_style(
                'vandel-admin-styles',
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
                'vandel-admin-script',
                VANDEL_PLUGIN_URL . 'assets/js/admin-script.js',
                ['wp-color-picker'],
                VANDEL_VERSION,
                true
            );
        }
        
        // Custom post type specific scripts
        global $post_type;
        if ($post_type === 'vandel_service' || $post_type === 'vandel_sub_service') {
            wp_enqueue_style(
                'vandel-cpt-styles',
                VANDEL_PLUGIN_URL . 'assets/css/cpt-style.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-cpt-scripts',
                VANDEL_PLUGIN_URL . 'assets/js/cpt-custom.js',
                ['jquery', 'jquery-ui-sortable'],
                VANDEL_VERSION,
                true
            );
        }
    }
    
    /**
     * Add link to admin bar
     * 
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object
     */
    public function addAdminBarLink($wp_admin_bar) {
        if (current_user_can('manage_options')) {
            $wp_admin_bar->add_node([
                'id'    => 'vandel_dashboard',
                'title' => '<span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>' . 
                           __('Vandel Dashboard', 'vandel-booking'),
                'href'  => admin_url('admin.php?page=vandel-dashboard'),
                'meta'  => [
                    'class' => 'vandel-dashboard-topbar-link', 
                    'title' => __('Go to Vandel Dashboard', 'vandel-booking')
                ]
            ]);
        }
    }
}