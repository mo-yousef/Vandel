<?php
namespace VandelBooking\Admin;

/**
 * Dashboard
 */
class Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_get_month_bookings', [$this, 'getMonthBookings']);
    }
    
    /**
     * Render dashboard
     */
    public function render() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        
        if ($active_tab === 'booking-details') {
            $details_page = new BookingDetails();
            $details_page->render();
            return;
        }
        
        if ($active_tab === 'client-details') {
            $details_page = new ClientDetails();
            $details_page->render();
            return;
        }
        
        $this->renderNavigation($active_tab);
        
        echo '<div class="vandel-plugin-container">';
        echo '<div class="vandel-inner-container">';
        
        switch ($active_tab) {
            case 'dashboard':
                $this->renderDashboardTab();
                break;
                
            case 'bookings':
                $this->renderBookingsTab();
                break;
                
            case 'clients':
                $this->renderClientsTab();
                break;
                
            case 'services':
                $this->renderServicesTab();
                break;
                
            case 'sub_services':
                $this->renderSubServicesTab();
                break;
                
            case 'settings':
                $settings_page = new SettingsPage();
                $settings_page->render();
                break;
                
            default:
                echo '<p>' . __('Tab not found. Please select a valid option.', 'vandel-booking') . '</p>';
                break;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render navigation
     * 
     * @param string $active_tab Active tab
     */
    private function renderNavigation($active_tab) {
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/navigation.php';
    }
    
    /**
     * Render dashboard tab
     */
    private function renderDashboardTab() {
        $booking_model = new \VandelBooking\Booking\BookingModel();
        $client_model = new \VandelBooking\Client\ClientModel();
        
        // Get booking statistics
        $booking_stats = $booking_model->getStatusCounts();
        
        // Get total revenue
        global $wpdb;
        $total_revenue = $wpdb->get_var("SELECT SUM(total_price) FROM {$wpdb->prefix}vandel_bookings");
        
        // Get recent clients
        $recent_clients = $client_model->getAll([
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 3
        ]);
        
        // Include dashboard template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
    }
    
    /**
     * Render bookings tab
     */
    private function renderBookingsTab() {
        $booking_model = new \VandelBooking\Booking\BookingModel();
        
        // Get bookings
        $bookings = $booking_model->getAll([
            'limit' => 10
        ]);
        
        // Include bookings template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/bookings.php';
    }
    
    /**
     * Render clients tab
     */
    private function renderClientsTab() {
        $client_model = new \VandelBooking\Client\ClientModel();
        
        // Get clients
        $clients = $client_model->getAll();
        
        // Include clients template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/clients.php';
    }
    
    /**
     * Render services tab
     */
    private function renderServicesTab() {
        $services = get_posts([
            'post_type' => 'vandel_service',
            'numberposts' => -1
        ]);
        
        // Include services template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/services.php';
    }
    
    /**
     * Render sub-services tab
     */
    private function renderSubServicesTab() {
        $sub_services = get_posts([
            'post_type' => 'vandel_sub_service',
            'numberposts' => -1
        ]);
        
        // Include sub-services template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/sub-services.php';
    }
    
    /**
     * Get month bookings AJAX handler
     */
    public function getMonthBookings() {
        check_ajax_referer('booking_calendar_nonce', 'nonce');
        
        $year = intval($_POST['year']);
        $month = intval($_POST['month']);
        
        $booking_model = new \VandelBooking\Booking\BookingModel();
        $bookings = $booking_model->getCalendarBookings($year, $month);
        
        wp_send_json_success($bookings);
    }
}