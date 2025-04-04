<?php
namespace VandelBooking\Admin;

use VandelBooking\Admin\Dashboard\Tab_Interface;

/**
 * Dashboard Controller
 * Main controller for the admin dashboard
 */
class Dashboard_Controller {
    /**
     * @var array Registered dashboard tabs
     */
    private $tabs = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register settings on admin_init
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add dashboard page to admin menu
        add_action('admin_menu', [$this, 'register_admin_menu']);
        
        // Initialize tabs
        $this->initialize_tabs();
    }
    
    /**
     * Register admin menu page
     */
    public function register_admin_menu() {
        // Check if we should register this menu
        if (!apply_filters('vandel_should_register_menu', true)) {
            return;
        }
        add_menu_page(
            __('Vandel Booking', 'vandel-booking'),
            __('Vandel Booking', 'vandel-booking'),
            'manage_options',
            'vandel-dashboard',
            [$this, 'render'],
            'dashicons-calendar-alt',
            30
        );
    }
    
    /**
     * Initialize dashboard tabs
     */
    public function initialize_tabs() {
        // Include tab classes
        $this->load_tab_classes();
        
        // Register available tabs
        $this->tabs = [
            'overview'  => new Dashboard\Overview_Tab(),
            'bookings'  => new Dashboard\Bookings_Tab(),
            'clients'   => new Dashboard\Clients_Tab(),
            'analytics' => new Dashboard\Analytics_Tab(),
            'calendar'  => new Dashboard\Calendar_Tab(),
            'settings'  => new Dashboard\Settings_Tab(),
        ];
        
        // Initialize each tab
        foreach ($this->tabs as $tab) {
            if ($tab instanceof Tab_Interface) {
                $tab->register_hooks();
            }
        }
    }
    
    /**
     * Load tab class files
     */
    private function load_tab_classes() {
        $tab_classes = [
            'class-tab-interface.php',
            'class-overview-tab.php',
            'class-bookings-tab.php',
            'class-clients-tab.php',
            'class-analytics-tab.php',
            'class-calendar-tab.php',
            'class-settings-tab.php',
        ];
        
        foreach ($tab_classes as $class_file) {
            $file_path = VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/' . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Register all plugin settings
     */
    public function register_settings() {
        // 1. General Settings
        register_setting('vandel_general_settings', 'vandel_business_name');
        register_setting('vandel_general_settings', 'vandel_primary_color');
        register_setting('vandel_general_settings', 'vandel_base_price');
        register_setting('vandel_general_settings', 'vandel_default_timezone');
        register_setting('vandel_general_settings', 'vandel_business_hours_start');
        register_setting('vandel_general_settings', 'vandel_business_hours_end');
        register_setting('vandel_general_settings', 'vandel_currency');

        // 2. Booking Settings
        register_setting('vandel_booking_settings', 'vandel_min_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_max_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_booking_cancellation_window');
        register_setting('vandel_booking_settings', 'vandel_booking_cancellation_policy');
        register_setting('vandel_booking_settings', 'vandel_default_booking_status');
        register_setting('vandel_booking_settings', 'vandel_enable_multiple_bookings');
        register_setting('vandel_booking_settings', 'vandel_booking_slots_interval');

        // 3. Notification Settings
        register_setting('vandel_notification_settings', 'vandel_enable_email_notifications');
        register_setting('vandel_notification_settings', 'vandel_email_sender_name');
        register_setting('vandel_notification_settings', 'vandel_email_sender_address');
        register_setting('vandel_notification_settings', 'vandel_notification_email');
        register_setting('vandel_notification_settings', 'vandel_email_subject');
        register_setting('vandel_notification_settings', 'vandel_email_message');
        register_setting('vandel_notification_settings', 'vandel_sms_notifications');

        // 4. Integration Settings
        register_setting('vandel_integration_settings', 'vandel_enable_paypal');
        register_setting('vandel_integration_settings', 'vandel_enable_stripe');
        register_setting('vandel_integration_settings', 'vandel_enable_gcal');
        register_setting('vandel_integration_settings', 'vandel_enable_mailchimp');
        register_setting('vandel_integration_settings', 'vandel_enable_zip_code_feature');
    }
    
    /**
     * Render dashboard
     */
    public function render() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        
        echo '<div class="wrap vandel-dashboard-container">';
        
        $this->render_header();
        $this->render_tabs_navigation($active_tab);
        
        // Handle special detail tabs
        if ($active_tab === 'booking-details') {
            $this->render_booking_details();
        } elseif ($active_tab === 'client-details') {
            $this->render_client_details();
        } elseif (isset($this->tabs[$active_tab])) {
            // Render standard tab
            $this->tabs[$active_tab]->process_actions();
            $this->tabs[$active_tab]->render();
        } else {
            // Default to overview if tab not found
            if (isset($this->tabs['overview'])) {
                $this->tabs['overview']->render();
            }
        }
        
        echo '</div>'; // .vandel-dashboard-container
    }
    
    /**
     * Render dashboard header
     */
    private function render_header() {
        echo '<div class="vandel-dashboard-header">';
        echo '<h1 class="vandel-dashboard-title">' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
        
        // Quick Action Buttons
        echo '<div class="vandel-quick-actions">';
        echo '<a href="' . admin_url('post-new.php?post_type=vandel_service') . '" class="button button-primary vandel-action-button"><span class="dashicons dashicons-plus"></span> ' . __('Add Service', 'vandel-booking') . '</a>';
        echo '<a href="' . admin_url('admin.php?page=vandel-dashboard&tab=bookings') . '" class="button vandel-action-button"><span class="dashicons dashicons-list-view"></span> ' . __('View Bookings', 'vandel-booking') . '</a>';
        echo '<a href="' . admin_url('admin.php?page=vandel-dashboard&tab=settings&section=general') . '" class="button vandel-action-button"><span class="dashicons dashicons-admin-generic"></span> ' . __('Settings', 'vandel-booking') . '</a>';
        echo '</div>'; // .vandel-quick-actions
        
        echo '</div>'; // .vandel-dashboard-header
    }
    
    /**
     * Render tabs navigation
     * 
     * @param string $active_tab Currently active tab
     */
    private function render_tabs_navigation($active_tab) {
        // Handle special tabs that should highlight their parent tab
        $parent_tab_mapping = [
            'booking-details' => 'bookings',
            'client-details' => 'clients'
        ];
        
        // If current tab is a child tab, highlight the parent tab
        $highlight_tab = $active_tab;
        if (isset($parent_tab_mapping[$active_tab])) {
            $highlight_tab = $parent_tab_mapping[$active_tab];
        }
        ?>
        <nav class="vandel-tabs-navigation">
            <ul>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=overview')); ?>" 
                       class="<?php echo $highlight_tab === 'overview' ? 'active' : ''; ?>" 
                       data-tab="overview">
                        <span class="dashicons dashicons-dashboard"></span>
                        <?php _e('Overview', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings')); ?>" 
                       class="<?php echo $highlight_tab === 'bookings' ? 'active' : ''; ?>" 
                       data-tab="bookings">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e('Bookings', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=clients')); ?>" 
                       class="<?php echo $highlight_tab === 'clients' ? 'active' : ''; ?>" 
                       data-tab="clients">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Clients', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=analytics')); ?>" 
                       class="<?php echo $highlight_tab === 'analytics' ? 'active' : ''; ?>" 
                       data-tab="analytics">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('Analytics', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=calendar')); ?>" 
                       class="<?php echo $highlight_tab === 'calendar' ? 'active' : ''; ?>" 
                       data-tab="calendar">
                        <span class="dashicons dashicons-calendar"></span>
                        <?php _e('Calendar', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings')); ?>" 
                       class="<?php echo $highlight_tab === 'settings' ? 'active' : ''; ?>" 
                       data-tab="settings">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'vandel-booking'); ?>
                    </a>
                </li>
            </ul>
        </nav>
        <?php
    }
    
    /**
     * Render booking details tab
     */
    private function render_booking_details() {
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        
        // Check if BookingDetails class exists and use it
        if (class_exists('\\VandelBooking\\Admin\\BookingDetails')) {
            $booking_details = new \VandelBooking\Admin\BookingDetails();
            if (method_exists($booking_details, 'render')) {
                $booking_details->render($booking_id);
                return;
            }
        }
        
        // Fallback view if the class doesn't exist
        echo '<div id="booking-details" class="vandel-tab-content">';
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h3>' . __('Booking Details', 'vandel-booking') . '</h3>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        
        if ($booking_id > 0) {
            // Attempt to get booking data
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'vandel_bookings';
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE id = %d", $booking_id));
            
            if ($booking) {
                echo '<h4>' . __('Booking', 'vandel-booking') . ' #' . $booking_id . '</h4>';
                echo '<p><strong>' . __('Client:', 'vandel-booking') . '</strong> ' . esc_html($booking->customer_name) . '</p>';
                echo '<p><strong>' . __('Date:', 'vandel-booking') . '</strong> ' . esc_html($booking->booking_date) . '</p>';
                echo '<p><strong>' . __('Status:', 'vandel-booking') . '</strong> ' . esc_html(ucfirst($booking->status)) . '</p>';
            } else {
                echo '<p>' . __('Booking not found.', 'vandel-booking') . '</p>';
            }
        } else {
            echo '<p>' . __('No booking selected.', 'vandel-booking') . '</p>';
        }
        
        echo '</div>'; // .vandel-card-body
        echo '</div>'; // .vandel-card
        echo '</div>'; // #booking-details
    }
    
    /**
     * Render client details tab
     */
    private function render_client_details() {
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        
        // Check if ClientDetails class exists and use it
        if (class_exists('\\VandelBooking\\Admin\\ClientDetails')) {
            $client_details = new \VandelBooking\Admin\ClientDetails();
            if (method_exists($client_details, 'render')) {
                $client_details->render($client_id);
                return;
            }
        }
        
        // Fallback view if the class doesn't exist
        echo '<div id="client-details" class="vandel-tab-content">';
        echo '<div class="vandel-card">';
        echo '<div class="vandel-card-header">';
        echo '<h3>' . __('Client Details', 'vandel-booking') . '</h3>';
        echo '</div>';
        echo '<div class="vandel-card-body">';
        
        if ($client_id > 0) {
            // Attempt to get client data
            global $wpdb;
            $clients_table = $wpdb->prefix . 'vandel_clients';
            $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id = %d", $client_id));
            
            if ($client) {
                echo '<h4>' . __('Client', 'vandel-booking') . ' #' . $client_id . '</h4>';
                echo '<p><strong>' . __('Name:', 'vandel-booking') . '</strong> ' . esc_html($client->name) . '</p>';
                echo '<p><strong>' . __('Email:', 'vandel-booking') . '</strong> ' . esc_html($client->email) . '</p>';
                if (!empty($client->phone)) {
                    echo '<p><strong>' . __('Phone:', 'vandel-booking') . '</strong> ' . esc_html($client->phone) . '</p>';
                }
            } else {
                echo '<p>' . __('Client not found.', 'vandel-booking') . '</p>';
            }
        } else {
            echo '<p>' . __('No client selected.', 'vandel-booking') . '</p>';
        }
        
        echo '</div>'; // .vandel-card-body
        echo '</div>'; // .vandel-card
        echo '</div>'; // #client-details
    }
}