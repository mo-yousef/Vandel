<?php
namespace VandelBooking\Admin;

/**
 * Dashboard Class
 */
class Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize dashboard
    }
    
    /**
     * Render dashboard
     */
    public function render() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        
        echo '<div class="wrap vandel-dashboard-container">';
        
        echo '<div class="vandel-dashboard-header">';
        echo '<h1 class="vandel-dashboard-title">' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
        echo '</div>';
        
        $this->renderTabs($active_tab);
        
        switch ($active_tab) {
            case 'bookings':
                $this->renderBookingsTab();
                break;
                
            case 'clients':
                $this->renderClientsTab();
                break;
                
            case 'settings':
                $this->renderSettingsTab();
                break;
                
            default:
                $this->renderOverviewTab();
                break;
        }
        
        echo '</div>'; // .vandel-dashboard-container
    }
    
    /**
     * Render tabs navigation
     *
     * @param string $active_tab Current active tab
     */
    private function renderTabs($active_tab) {
        $tabs = [
            'overview' => __('Overview', 'vandel-booking'),
            'bookings' => __('Bookings', 'vandel-booking'),
            'clients' => __('Clients', 'vandel-booking'),
            'settings' => __('Settings', 'vandel-booking'),
        ];
        
        echo '<div class="vandel-tabs-navigation">';
        echo '<ul>';
        
        foreach ($tabs as $tab_id => $tab_label) {
            $active_class = ($active_tab === $tab_id) ? 'active' : '';
            $tab_url = admin_url('admin.php?page=vandel-dashboard&tab=' . $tab_id);
            
            echo '<li>';
            echo '<a href="' . esc_url($tab_url) . '" class="' . esc_attr($active_class) . '" data-tab="' . esc_attr($tab_id) . '">';
            echo esc_html($tab_label);
            echo '</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Render overview tab
     */
    private function renderOverviewTab() {
        echo '<div id="overview" class="vandel-tab-content">';
        
        echo '<div class="vandel-stats-grid">';
        
        // Pending Bookings
        echo '<div class="vandel-stat-card">';
        echo '<div class="vandel-stat-value">0</div>';
        echo '<div class="vandel-stat-label">' . __('Pending Bookings', 'vandel-booking') . '</div>';
        echo '</div>';
        
        // Total Bookings
        echo '<div class="vandel-stat-card">';
        echo '<div class="vandel-stat-value">0</div>';
        echo '<div class="vandel-stat-label">' . __('Total Bookings', 'vandel-booking') . '</div>';
        echo '</div>';
        
        // Total Clients
        echo '<div class="vandel-stat-card">';
        echo '<div class="vandel-stat-value">0</div>';
        echo '<div class="vandel-stat-label">' . __('Total Clients', 'vandel-booking') . '</div>';
        echo '</div>';
        
        // Total Revenue
        echo '<div class="vandel-stat-card">';
        echo '<div class="vandel-stat-value">$0</div>';
        echo '<div class="vandel-stat-label">' . __('Total Revenue', 'vandel-booking') . '</div>';
        echo '</div>';
        
        echo '</div>'; // .vandel-stats-grid
        
        // Recent Bookings
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h2 class="vandel-card-title">' . __('Recent Bookings', 'vandel-booking') . '</h2>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        echo '<p>' . __('No bookings found.', 'vandel-booking') . '</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // #overview
    }
    
    /**
     * Render bookings tab
     */
    private function renderBookingsTab() {
        echo '<div id="bookings" class="vandel-tab-content">';
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h2 class="vandel-card-title">' . __('All Bookings', 'vandel-booking') . '</h2>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        echo '<p>' . __('No bookings found.', 'vandel-booking') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // #bookings
    }
    
    /**
     * Render clients tab
     */
    private function renderClientsTab() {
        echo '<div id="clients" class="vandel-tab-content">';
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h2 class="vandel-card-title">' . __('All Clients', 'vandel-booking') . '</h2>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        echo '<p>' . __('No clients found.', 'vandel-booking') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // #clients
    }
    
    /**
     * Render settings tab
     */
    private function renderSettingsTab() {
        echo '<div id="settings" class="vandel-tab-content">';
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h2 class="vandel-card-title">' . __('Settings', 'vandel-booking') . '</h2>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        echo '<p>' . __('Settings will be available soon.', 'vandel-booking') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // #settings
    }
}