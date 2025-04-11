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
    }
    
    /**
     * Load admin components
     */
    private function loadComponents() {
        // Only load components that exist or can be included
        
        // Try to include Dashboard class directly
        $dashboard_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-dashboard.php';
        if (file_exists($dashboard_file)) {
            require_once $dashboard_file;
            if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
                new Dashboard();
            }
        }
        
        // Try to include BookingDetails class
        $booking_details_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-booking-details.php';
        if (file_exists($booking_details_file)) {
            require_once $booking_details_file;
            if (class_exists('\\VandelBooking\\Admin\\BookingDetails')) {
                new BookingDetails();
            }
        }
        
        // Try to include ClientDetails class
        $client_details_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-client-details.php';
        if (file_exists($client_details_file)) {
            require_once $client_details_file;
            if (class_exists('\\VandelBooking\\Admin\\ClientDetails')) {
                new ClientDetails();
            }
        }
    }
    
    /**
     * Register admin menu
     */
    public function registerMenu() {
        // Check if we should register this menu
        if (!apply_filters('vandel_should_register_menu', true)) {
            return;
        }
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
            __('Dashboard', 'vandel-booking'), 
            __('Dashboard', 'vandel-booking'), 
            'manage_options', 
            'vandel-dashboard', 
            [$this, 'renderDashboard']
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
        // Try to include Dashboard class if not already loaded
        if (!class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            $dashboard_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-dashboard.php';
            if (file_exists($dashboard_file)) {
                require_once $dashboard_file;
            }
        }
        
        if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            $dashboard = new Dashboard();
            if (method_exists($dashboard, 'render')) {
                $dashboard->render();
            } else {
                $this->renderFallbackDashboard();
            }
        } else {
            $this->renderFallbackDashboard();
        }
    }
    

// Add to the bottom of the renderDashboard method in AdminLoader class
private function printDebugInfo() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vandel_bookings';
    
    // Count bookings
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Get the latest booking
    $latest = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
    
    echo '<div style="background:#f8f8f8; border:1px solid #ddd; padding:15px; margin-top:30px;">';
    echo '<h3>Debug Information</h3>';
    
    echo '<p><strong>Total Bookings:</strong> ' . intval($total) . '</p>';
    
    if ($latest) {
        echo '<p><strong>Latest Booking:</strong></p>';
        echo '<pre>' . print_r($latest, true) . '</pre>';
    } else {
        echo '<p>No bookings found in the database.</p>';
    }
    
    echo '<p><strong>Current URL:</strong> ' . esc_html($_SERVER['REQUEST_URI']) . '</p>';
    
    // Check if tab parameter exists
    $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    echo '<p><strong>Current Tab:</strong> ' . esc_html($current_tab) . '</p>';
    
    echo '</div>';
}

    /**
     * Render a fallback dashboard if Dashboard class is not available
     */
    private function renderFallbackDashboard() {
        global $wpdb;
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
        
        // Show some basic booking data
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        
        echo '<div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc;">';
        echo '<h2>Booking Stats</h2>';
        echo '<p>Total Bookings: ' . intval($count) . '</p>';
        
        // Show recent bookings
        $recent = $wpdb->get_results("SELECT * FROM $bookings_table ORDER BY created_at DESC LIMIT 5");
        
        if ($recent) {
            echo '<h3>Recent Bookings</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Name</th><th>Date</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($recent as $booking) {
                echo '<tr>';
                echo '<td>' . esc_html($booking->id) . '</td>';
                echo '<td>' . esc_html($booking->customer_name) . '</td>';
                echo '<td>' . esc_html($booking->booking_date) . '</td>';
                echo '<td>' . esc_html($booking->status) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>No bookings found.</p>';
        }
        
        echo '</div>';
        echo '</div>';
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
            
            // wp_enqueue_style(
            //     'vandel-admin-styles',
            //     VANDEL_PLUGIN_URL . 'assets/css/admin-style.css',
            //     [],
            //     VANDEL_VERSION
            // );
            
            wp_enqueue_script(
                'vandel-admin-script',
                VANDEL_PLUGIN_URL . 'assets/js/admin-script.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
        }
    }



}