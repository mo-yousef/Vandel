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
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks() {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    /**
     * Load admin components
     */
    private function loadComponents() {
        // Only load components that exist
        if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            new Dashboard();
        }
        
        if (class_exists('\\VandelBooking\\Admin\\BookingDetails')) {
            new BookingDetails();
        }
        
        if (class_exists('\\VandelBooking\\Admin\\ClientDetails')) {
            new ClientDetails();
        }
        
        if (class_exists('\\VandelBooking\\Admin\\SettingsPage')) {
            new SettingsPage();
        }
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
            'dashicons-calendar-alt',
            26
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Bookings', 'vandel-booking'), 
            __('Bookings', 'vandel-booking'), 
            'manage_options', 
            'vandel-dashboard&tab=bookings', 
            [$this, 'renderDashboard']
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
        if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            $dashboard = new Dashboard();
            if (method_exists($dashboard, 'render')) {
                $dashboard->render();
            } else {
                echo '<div class="wrap">';
                echo '<h1>' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
                echo '<p>' . __('Dashboard is under development.', 'vandel-booking') . '</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
            echo '<p>' . __('Dashboard is under development.', 'vandel-booking') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        // Only load on plugin pages
        if ($hook === 'toplevel_page_vandel-dashboard' || 
            strpos($hook, 'page_vandel-dashboard') !== false) {
            
            wp_enqueue_style(
                'vandel-admin-styles',
                VANDEL_PLUGIN_URL . 'assets/css/admin-style.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-admin-script',
                VANDEL_PLUGIN_URL . 'assets/js/admin-script.js',
                ['jquery'],
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
