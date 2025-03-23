<?php
namespace VandelBooking\Admin;

/**
 * Enhanced Dashboard with Modern UI and Insights
 */
class Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        // Register all settings on admin_init
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Register all plugin settings
     */
    public function registerSettings() {
        // Register settings as before
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
 * Render the dashboard
 */
public function render() {
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    
    echo '<div class="wrap vandel-dashboard-container">';
    
    echo '<div class="vandel-dashboard-header">';
    echo '<h1 class="vandel-dashboard-title">' . __('Vandel Booking Dashboard', 'vandel-booking') . '</h1>';
    
    // Quick Action Buttons
    echo '<div class="vandel-quick-actions">';
    echo '<a href="' . admin_url('post-new.php?post_type=vandel_service') . '" class="button button-primary vandel-action-button"><span class="dashicons dashicons-plus"></span> ' . __('Add Service', 'vandel-booking') . '</a>';
    echo '<a href="' . admin_url('admin.php?page=vandel-dashboard&tab=bookings') . '" class="button vandel-action-button"><span class="dashicons dashicons-list-view"></span> ' . __('View Bookings', 'vandel-booking') . '</a>';
    echo '<a href="' . admin_url('admin.php?page=vandel-dashboard&tab=settings&section=general') . '" class="button vandel-action-button"><span class="dashicons dashicons-admin-generic"></span> ' . __('Settings', 'vandel-booking') . '</a>';
    echo '</div>'; // .vandel-quick-actions
    
    echo '</div>'; // .vandel-dashboard-header
    
    $this->renderTabs($active_tab);
    
    // Handle special detail tabs
    if ($active_tab === 'booking-details') {
        $this->renderBookingDetailsTab();
    } elseif ($active_tab === 'client-details') {
        $this->renderClientDetailsTab();
    } else {
        // Handle standard tabs
        switch ($active_tab) {
            case 'bookings':
                $this->renderBookingsTab();
                break;
            case 'clients':
                $this->renderClientsTab();
                break;
            case 'analytics':
                $this->renderAnalyticsTab();
                break;
            case 'calendar':
                $this->renderCalendarTab();
                break;
            case 'settings':
                $this->renderSettingsTab();
                break;
            default:
                $this->renderOverviewTab();
                break;
        }
    }
    
    echo '</div>'; // .vandel-dashboard-container
}

/**
 * Render booking details tab
 */
private function renderBookingDetailsTab() {
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
private function renderClientDetailsTab() {
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

    /**
     * Render dashboard tabs navigation
     * 
     * @param string $active_tab Currently active tab
     */
/**
 * Render dashboard tabs navigation with improved active tab detection
 * 
 * @param string $active_tab Currently active tab
 */
private function renderTabs($active_tab) {
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
     * Render settings tab
     */
    private function renderSettingsTab() {
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        ?>
        <div id="settings" class="vandel-tab-content">
            <div class="vandel-settings-container">
                <!-- Settings Navigation -->
                <div class="vandel-settings-nav">
                    <ul>
                        <li <?php echo $active_section === 'general' ? 'class="active"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=general')); ?>">
                                <span class="dashicons dashicons-admin-generic"></span> 
                                <?php _e('General', 'vandel-booking'); ?>
                            </a>
                        </li>
                        <li <?php echo $active_section === 'booking' ? 'class="active"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=booking')); ?>">
                                <span class="dashicons dashicons-calendar-alt"></span> 
                                <?php _e('Booking', 'vandel-booking'); ?>
                            </a>
                        </li>
                        <li <?php echo $active_section === 'notifications' ? 'class="active"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=notifications')); ?>">
                                <span class="dashicons dashicons-email-alt"></span> 
                                <?php _e('Notifications', 'vandel-booking'); ?>
                            </a>
                        </li>
                        <li <?php echo $active_section === 'integrations' ? 'class="active"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=integrations')); ?>">
                                <span class="dashicons dashicons-randomize"></span> 
                                <?php _e('Integrations', 'vandel-booking'); ?>
                            </a>
                        </li>
                        <li <?php echo $active_section === 'zip-codes' ? 'class="active"' : ''; ?>>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes')); ?>">
                                <span class="dashicons dashicons-location-alt"></span> 
                                <?php _e('Service Areas', 'vandel-booking'); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Settings Content -->
                <div class="vandel-settings-content">
                    <?php
                    switch ($active_section) {
                        case 'general':
                            $this->renderGeneralSettings();
                            break;
                        case 'booking':
                            $this->renderBookingSettings();
                            break;
                        case 'notifications':
                            $this->renderNotificationSettings();
                            break;
                        case 'integrations':
                            $this->renderIntegrationSettings();
                            break;
                        case 'zip-codes':
                            // Check if we should render ZIP code settings with our class or use ZipCodeSettings if available
                            if (class_exists('\\VandelBooking\\Admin\\ZipCodeSettings')) {
                                $zip_code_settings = new \VandelBooking\Admin\ZipCodeSettings();
                                $zip_code_settings->render();
                            } else {
                                $this->renderZipCodeSettings();
                            }
                            break;
                        default:
                            $this->renderGeneralSettings();
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render ZIP Code settings
     */
    private function renderZipCodeSettings() {
        global $wpdb;
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Service Areas Management', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-intro">
                <p><?php _e('Manage the areas you service with ZIP code-based pricing. Set up service areas, adjust pricing, and manage locations.', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-grid-row">
                <div class="vandel-grid-col">
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Add New Service Area', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <form method="post" action="">
                                <?php wp_nonce_field('vandel_add_zip_code', 'vandel_zip_code_nonce'); ?>
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label><?php _e('ZIP Code', 'vandel-booking'); ?> <span class="required">*</span></label>
                                        <input type="text" name="zip_code" required class="widefat">
                                    </div>
                                    <div class="vandel-col">
                                        <label><?php _e('City', 'vandel-booking'); ?> <span class="required">*</span></label>
                                        <input type="text" name="city" required class="widefat">
                                    </div>
                                </div>
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label><?php _e('State/Province', 'vandel-booking'); ?></label>
                                        <input type="text" name="state" class="widefat">
                                    </div>
                                    <div class="vandel-col">
                                        <label><?php _e('Country', 'vandel-booking'); ?> <span class="required">*</span></label>
                                        <input type="text" name="country" required class="widefat" value="United States">
                                    </div>
                                </div>
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                        <div class="vandel-input-group">
                                            <span class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                            <input type="number" name="price_adjustment" step="0.01" min="-100" max="100" class="widefat" value="0">
                                        </div>
                                        <p class="description"><?php _e('Amount to add or subtract from base price for this area', 'vandel-booking'); ?></p>
                                    </div>
                                    <div class="vandel-col">
                                        <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                        <div class="vandel-input-group">
                                            <span class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                            <input type="number" name="service_fee" step="0.01" min="0" class="widefat" value="0">
                                        </div>
                                        <p class="description"><?php _e('Additional fee for servicing this area', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-toggle-controls">
                                    <div class="vandel-toggle-field">
                                        <label class="vandel-toggle">
                                            <input type="checkbox" name="is_serviceable" value="yes" checked>
                                            <span class="vandel-toggle-slider"></span>
                                        </label>
                                        <span class="vandel-toggle-label"><?php _e('Serviceable Area', 'vandel-booking'); ?></span>
                                    </div>
                                </div>
                                <button type="submit" name="vandel_add_zip_code" class="button button-primary">
                                    <?php _e('Add Service Area', 'vandel-booking'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="vandel-grid-col">
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Import/Export', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-import-section">
                                <h4><?php _e('Import ZIP Codes', 'vandel-booking'); ?></h4>
                                <p><?php _e('Upload a CSV file with ZIP code data to bulk import.', 'vandel-booking'); ?></p>
                                <form method="post" enctype="multipart/form-data">
                                    <div class="vandel-form-row">
                                        <div class="vandel-col">
                                            <input type="file" name="zip_codes_file" id="vandel-zip-codes-file" accept=".csv,.xlsx,.xls">
                                        </div>
                                        <div class="vandel-col">
                                            <button type="button" id="vandel-import-zip-codes" class="button button-secondary">
                                                <span class="dashicons dashicons-upload"></span> <?php _e('Import', 'vandel-booking'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <p class="description"><?php _e('CSV format: ZIP Code, City, State, Country, Price Adjustment, Service Fee, Serviceable (yes/no)', 'vandel-booking'); ?></p>
                            </div>
                            <div class="vandel-export-section">
                                <h4><?php _e('Export ZIP Codes', 'vandel-booking'); ?></h4>
                                <p><?php _e('Download all your service areas as a CSV file.', 'vandel-booking'); ?></p>
                                <button type="button" id="vandel-export-zip-codes" class="button button-secondary">
                                    <span class="dashicons dashicons-download"></span> <?php _e('Export CSV', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vandel-card">
                <div class="vandel-card-header vandel-flex-header">
                    <h3><?php _e('Your Service Areas', 'vandel-booking'); ?></h3>
                    <div class="vandel-filter-controls">
                        <input type="text" id="vandel-zip-search" placeholder="<?php _e('Search ZIP codes...', 'vandel-booking'); ?>" class="regular-text">
                    </div>
                </div>
                <div class="vandel-card-body">
                    <table class="wp-list-table widefat fixed striped vandel-data-table">
                        <thead>
                            <tr>
                                <th><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                                <th><?php _e('City', 'vandel-booking'); ?></th>
                                <th><?php _e('State', 'vandel-booking'); ?></th>
                                <th><?php _e('Country', 'vandel-booking'); ?></th>
                                <th><?php _e('Price Adjustment', 'vandel-booking'); ?></th>
                                <th><?php _e('Service Fee', 'vandel-booking'); ?></th>
                                <th><?php _e('Status', 'vandel-booking'); ?></th>
                                <th><?php _e('Actions', 'vandel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $table_name = $wpdb->prefix . 'vandel_zip_codes';
                            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                            
                            if ($table_exists) {
                                $zip_codes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY zip_code ASC LIMIT 20");
                                
                                if (!empty($zip_codes)) {
                                    foreach ($zip_codes as $zip) {
                                        echo '<tr>';
                                        echo '<td>' . esc_html($zip->zip_code) . '</td>';
                                        echo '<td>' . esc_html($zip->city) . '</td>';
                                        echo '<td>' . esc_html($zip->state ?: '—') . '</td>';
                                        echo '<td>' . esc_html($zip->country) . '</td>';
                                        echo '<td>' . ($zip->price_adjustment >= 0 ? '+' : '') . \VandelBooking\Helpers::formatPrice($zip->price_adjustment) . '</td>';
                                        echo '<td>' . \VandelBooking\Helpers::formatPrice($zip->service_fee) . '</td>';
                                        echo '<td>' . ($zip->is_serviceable === 'yes' ? 
                                            '<span class="vandel-badge vandel-badge-success">' . __('Active', 'vandel-booking') . '</span>' : 
                                            '<span class="vandel-badge vandel-badge-danger">' . __('Inactive', 'vandel-booking') . '</span>') . '</td>';
                                        echo '<td>';
                                        echo '<div class="vandel-row-actions">';
                                        echo '<a href="#" class="vandel-edit-zip-code" data-zip-code="' . esc_attr($zip->zip_code) . '">' . __('Edit', 'vandel-booking') . '</a> | ';
                                        echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes&action=delete_zip_code&zip_code=' . urlencode($zip->zip_code)), 'delete_zip_code_' . $zip->zip_code)) . '" class="vandel-delete-zip-code">' . __('Delete', 'vandel-booking') . '</a>';
                                        echo '</div>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="8">' . __('No service areas found.', 'vandel-booking') . '</td></tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8">' . __('ZIP codes table not found. Please check the plugin installation.', 'vandel-booking') . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render General Settings
     */
    private function renderGeneralSettings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('General Settings', 'vandel-booking'); ?></h2>

            <div class="vandel-settings-intro">
                <p><?php _e('Configure the basic settings for your booking system including business information, operating hours, and appearance.', 'vandel-booking'); ?></p>
            </div>

            <!-- Use WP Settings API -->
            <form method="post" action="options.php">
                <?php 
                    // This loads hidden fields + nonce for "vandel_general_settings"
                    settings_fields('vandel_general_settings'); 
                ?>
                
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Business Hours', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label><?php _e('Operating Hours', 'vandel-booking'); ?></label>
                            <div class="vandel-business-hours">
                                <div class="vandel-time-input">
                                    <label><?php _e('Start Time', 'vandel-booking'); ?></label>
                                    <input 
                                        type="time" 
                                        name="vandel_business_hours_start" 
                                        value="<?php echo esc_attr(get_option('vandel_business_hours_start', '09:00')); ?>"
                                        class="regular-text"
                                    >
                                </div>
                                <div class="vandel-time-input">
                                    <label><?php _e('End Time', 'vandel-booking'); ?></label>
                                    <input 
                                        type="time" 
                                        name="vandel_business_hours_end" 
                                        value="<?php echo esc_attr(get_option('vandel_business_hours_end', '17:00')); ?>"
                                        class="regular-text"
                                    >
                                </div>
                            </div>
                            <p class="description"><?php _e('Default operating hours for bookings', 'vandel-booking'); ?></p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_base_price"><?php _e('Base Service Price', 'vandel-booking'); ?></label>
                            <div class="vandel-input-group">
                                <span class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                <input 
                                    type="number" 
                                    id="vandel_base_price" 
                                    name="vandel_base_price" 
                                    value="<?php echo esc_attr(get_option('vandel_base_price', 0)); ?>" 
                                    step="0.01" 
                                    min="0"
                                    class="regular-text"
                                >
                            </div>
                            <p class="description"><?php _e('Default base price for services when not specified', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save General Settings', 'vandel-booking')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Booking Settings
     */
    private function renderBookingSettings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Booking Settings', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-intro">
                <p><?php _e('Configure how your booking system works, including time slots, advance booking rules, and cancellation policies.', 'vandel-booking'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php 
                    // This loads hidden fields + nonce for "vandel_booking_settings"
                    settings_fields('vandel_booking_settings'); 
                ?>
                
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Booking Rules', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_min_advance_booking"><?php _e('Minimum Advance Booking', 'vandel-booking'); ?></label>
                            <div class="vandel-input-with-suffix">
                                <input 
                                    type="number" 
                                    id="vandel_min_advance_booking" 
                                    name="vandel_min_advance_booking" 
                                    value="<?php echo esc_attr(get_option('vandel_min_advance_booking', 1)); ?>" 
                                    min="0"
                                    class="small-text"
                                >
                                <span class="vandel-input-suffix"><?php _e('hours', 'vandel-booking'); ?></span>
                            </div>
                            <p class="description"><?php _e('Minimum hours in advance required for booking (e.g., 1 = customers must book at least 1 hour before service time)', 'vandel-booking'); ?></p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_max_advance_booking"><?php _e('Maximum Advance Booking', 'vandel-booking'); ?></label>
                            <div class="vandel-input-with-suffix">
                                <input 
                                    type="number" 
                                    id="vandel_max_advance_booking" 
                                    name="vandel_max_advance_booking" 
                                    value="<?php echo esc_attr(get_option('vandel_max_advance_booking', 90)); ?>" 
                                    min="1"
                                    class="small-text"
                                >
                                <span class="vandel-input-suffix"><?php _e('days', 'vandel-booking'); ?></span>
                            </div>
                            <p class="description"><?php _e('Maximum days in advance customers can book (e.g., 90 = booking allowed up to 3 months ahead)', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Time Slots & Scheduling', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_booking_slots_interval"><?php _e('Time Slot Interval', 'vandel-booking'); ?></label>
                            <div class="vandel-input-with-suffix">
                                <select 
                                    id="vandel_booking_slots_interval" 
                                    name="vandel_booking_slots_interval"
                                    class="regular-text"
                                >
                                    <?php 
                                    $intervals = [
                                        15 => __('15 minutes', 'vandel-booking'),
                                        30 => __('30 minutes', 'vandel-booking'),
                                        60 => __('1 hour', 'vandel-booking'),
                                        120 => __('2 hours', 'vandel-booking')
                                    ];
                                    $current_interval = get_option('vandel_booking_slots_interval', 30);
                                    
                                    foreach ($intervals as $minutes => $label) {
                                        echo '<option value="' . esc_attr($minutes) . '" ' . 
                                             selected($current_interval, $minutes, false) . '>' . 
                                             esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <p class="description"><?php _e('Time interval between available booking slots', 'vandel-booking'); ?></p>
                        </div>

                        <div class="vandel-setting-row">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="vandel_enable_multiple_bookings" 
                                    value="yes" 
                                    <?php checked(get_option('vandel_enable_multiple_bookings', 'no'), 'yes'); ?>
                                >
                                <?php _e('Allow Multiple Bookings per Time Slot', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('If enabled, multiple customers can book the same time slot (for businesses with multiple staff)', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Cancellation Policy', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_booking_cancellation_window"><?php _e('Cancellation Window', 'vandel-booking'); ?></label>
                            <div class="vandel-input-with-suffix">
                                <input 
                                    type="number" 
                                    id="vandel_booking_cancellation_window" 
                                    name="vandel_booking_cancellation_window" 
                                    value="<?php echo esc_attr(get_option('vandel_booking_cancellation_window', 24)); ?>" 
                                    min="0"
                                    class="small-text"
                                >
                                <span class="vandel-input-suffix"><?php _e('hours before appointment', 'vandel-booking'); ?></span>
                            </div>
                            <p class="description">
                                <?php _e('How many hours before the appointment customers can cancel without penalty', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_booking_cancellation_policy">
                                <?php _e('Cancellation Policy Text', 'vandel-booking'); ?>
                            </label>
                            <textarea 
                                id="vandel_booking_cancellation_policy" 
                                name="vandel_booking_cancellation_policy" 
                                rows="4" 
                                class="widefat"
                            ><?php echo esc_textarea(get_option('vandel_booking_cancellation_policy', __('Cancellations must be made at least 24 hours before your scheduled appointment time. Late cancellations may be subject to a cancellation fee.', 'vandel-booking'))); ?></textarea>
                            <p class="description">
                                <?php _e('Your booking cancellation policy (displayed to customers during booking)', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Booking Status', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_default_booking_status">
                                <?php _e('Default Status for New Bookings', 'vandel-booking'); ?>
                            </label>
                            <select 
                                id="vandel_default_booking_status" 
                                name="vandel_default_booking_status"
                                class="regular-text"
                            >
                                <?php 
                                $statuses = [
                                    'pending'   => __('Pending (requires confirmation)', 'vandel-booking'),
                                    'confirmed' => __('Confirmed (automatically approved)', 'vandel-booking')
                                ];
                                $current_status = get_option('vandel_default_booking_status', 'pending');
                                
                                foreach ($statuses as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '" ' . 
                                         selected($current_status, $value, false) . '>' . 
                                         esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php _e('Status assigned to new bookings when they are created', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save Booking Settings', 'vandel-booking')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Notification Settings
     */
    private function renderNotificationSettings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Notification Settings', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-intro">
                <p><?php _e('Configure how you and your customers receive notifications about bookings, including email templates and settings.', 'vandel-booking'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('vandel_notification_settings'); ?>
                
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Email Notifications', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label class="vandel-switch-label">
                                <input 
                                    type="checkbox" 
                                    name="vandel_enable_email_notifications" 
                                    value="yes" 
                                    <?php checked(get_option('vandel_enable_email_notifications', 'yes'), 'yes'); ?>
                                    class="vandel-switch-input"
                                >
                                <span class="vandel-switch"></span>
                                <?php _e('Enable Email Notifications', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Send email notifications for new bookings, cancellations, and updates', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_email_sender_name">
                                <?php _e('Email Sender Name', 'vandel-booking'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="vandel_email_sender_name" 
                                name="vandel_email_sender_name" 
                                value="<?php echo esc_attr(get_option('vandel_email_sender_name', get_bloginfo('name'))); ?>" 
                                class="regular-text"
                            >
                            <p class="description">
                                <?php _e('Name that appears in the "From" field of notification emails', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_email_sender_address">
                                <?php _e('Sender Email Address', 'vandel-booking'); ?>
                            </label>
                            <input 
                                type="email" 
                                id="vandel_email_sender_address" 
                                name="vandel_email_sender_address" 
                                value="<?php echo esc_attr(get_option('vandel_email_sender_address', get_option('admin_email'))); ?>" 
                                class="regular-text"
                            >
                            <p class="description">
                                <?php _e('Email address that appears in the "From" field of notification emails', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_notification_email">
                                <?php _e('Admin Notification Email', 'vandel-booking'); ?>
                            </label>
                            <input 
                                type="email" 
                                id="vandel_notification_email" 
                                name="vandel_notification_email" 
                                value="<?php echo esc_attr(get_option('vandel_notification_email', get_option('admin_email'))); ?>" 
                                class="regular-text"
                            >
                            <p class="description">
                                <?php _e('Email address to receive booking notifications (multiple emails can be separated by commas)', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Email Templates', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_email_subject">
                                <?php _e('Email Subject Line', 'vandel-booking'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="vandel_email_subject" 
                                name="vandel_email_subject" 
                                value="<?php echo esc_attr(get_option('vandel_email_subject', __('Your Booking Confirmation - {booking_id}', 'vandel-booking'))); ?>" 
                                class="regular-text"
                            >
                            <p class="description">
                                <?php _e('Subject line for customer confirmation emails. You can use {booking_id} as a placeholder.', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_email_message">
                                <?php _e('Email Message Template', 'vandel-booking'); ?>
                            </label>
                            <textarea 
                                id="vandel_email_message" 
                                name="vandel_email_message" 
                                rows="8" 
                                class="widefat code"
                            ><?php echo esc_textarea(get_option('vandel_email_message', __("Dear {customer_name},\n\nThank you for your booking. Your booking details are as follows:\n\nService: {service_name}\nDate: {booking_date}\nBooking Reference: {booking_id}\n\nIf you need to make any changes to your booking, please contact us.\n\nWe look forward to serving you!\n\nRegards,\n{site_name} Team", 'vandel-booking'))); ?></textarea>
                            <p class="description">
                                <?php _e('Template for customer confirmation emails. You can use the following placeholders: {customer_name}, {service_name}, {booking_date}, {booking_id}, {site_name}', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('SMS Notifications', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label class="vandel-switch-label">
                                <input 
                                    type="checkbox" 
                                    name="vandel_sms_notifications" 
                                    value="yes" 
                                    <?php checked(get_option('vandel_sms_notifications', 'no'), 'yes'); ?>
                                    disabled
                                    class="vandel-switch-input"
                                >
                                <span class="vandel-switch"></span>
                                <?php _e('Enable SMS Notifications', 'vandel-booking'); ?>
                            </label>
                            <p class="description vandel-premium-feature">
                                <?php _e('Send SMS notifications for bookings (Premium Feature)', 'vandel-booking'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save Notification Settings', 'vandel-booking')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Integration Settings
     */
    private function renderIntegrationSettings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Integrations', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-intro">
                <p><?php _e('Connect your booking system with popular services and apps for payments, marketing, and calendars.', 'vandel-booking'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('vandel_integration_settings'); ?>
                
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Location-Based Features', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label class="vandel-switch-label">
                                <input 
                                    type="checkbox" 
                                    name="vandel_enable_zip_code_feature" 
                                    value="yes" 
                                    <?php checked(get_option('vandel_enable_zip_code_feature', 'no'), 'yes'); ?>
                                    class="vandel-switch-input"
                                >
                                <span class="vandel-switch"></span>
                                <?php _e('Enable Service Area & ZIP Code Features', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Enable location-based pricing and service area restrictions', 'vandel-booking'); ?>
                            </p>
                        </div>
                        
                        <div class="vandel-feature-detail">
                            <div class="vandel-feature-icon">
                                <span class="dashicons dashicons-location-alt"></span>
                            </div>
                            <div class="vandel-feature-info">
                                <h4><?php _e('Service Area Management', 'vandel-booking'); ?></h4>
                                <p><?php _e('With this feature enabled, you can set up service areas by ZIP code, adjust pricing for different locations, and restrict bookings to your service area.', 'vandel-booking'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes')); ?>" class="button vandel-feature-btn">
                                    <?php _e('Manage Service Areas', 'vandel-booking'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Payment Gateways', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-integrations-grid">
                            <div class="vandel-integration-item">
                                <div class="vandel-integration-content">
                                    <div class="vandel-integration-logo">
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/paypal.svg" alt="PayPal" width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>PayPal</h4>
                                        <p><?php _e('Accept payments via PayPal', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input 
                                            type="checkbox" 
                                            name="vandel_enable_paypal" 
                                            value="yes" 
                                            <?php checked(get_option('vandel_enable_paypal', 'no'), 'yes'); ?>
                                            disabled
                                        >
                                        <span class="vandel-switch"></span>
                                    </label>
                                    <span class="vandel-badge vandel-badge-warning">
                                        <?php _e('Coming Soon', 'vandel-booking'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="vandel-integration-item">
                                <div class="vandel-integration-content">
                                    <div class="vandel-integration-logo">
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/stripe.svg" alt="Stripe" width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Stripe</h4>
                                        <p><?php _e('Accept credit card payments', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input 
                                            type="checkbox" 
                                            name="vandel_enable_stripe" 
                                            value="yes" 
                                            <?php checked(get_option('vandel_enable_stripe', 'no'), 'yes'); ?>
                                            disabled
                                        >
                                        <span class="vandel-switch"></span>
                                    </label>
                                    <span class="vandel-badge vandel-badge-warning">
                                        <?php _e('Coming Soon', 'vandel-booking'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Calendar Sync', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-integrations-grid">
                            <div class="vandel-integration-item">
                                <div class="vandel-integration-content">
                                    <div class="vandel-integration-logo">
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/google-calendar.svg" alt="Google Calendar" width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Google Calendar</h4>
                                        <p><?php _e('Sync bookings with Google Calendar', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input 
                                            type="checkbox" 
                                            name="vandel_enable_gcal" 
                                            value="yes" 
                                            <?php checked(get_option('vandel_enable_gcal', 'no'), 'yes'); ?>
                                            disabled
                                        >
                                        <span class="vandel-switch"></span>
                                    </label>
                                    <span class="vandel-badge vandel-badge-warning">
                                        <?php _e('Coming Soon', 'vandel-booking'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Marketing Integrations', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-integrations-grid">
                            <div class="vandel-integration-item">
                                <div class="vandel-integration-content">
                                    <div class="vandel-integration-logo">
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/mailchimp.svg" alt="Mailchimp" width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Mailchimp</h4>
                                        <p><?php _e('Add clients to your email marketing lists', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input 
                                            type="checkbox" 
                                            name="vandel_enable_mailchimp" 
                                            value="yes" 
                                            <?php checked(get_option('vandel_enable_mailchimp', 'no'), 'yes'); ?>
                                            disabled
                                        >
                                        <span class="vandel-switch"></span>
                                    </label>
                                    <span class="vandel-badge vandel-badge-warning">
                                        <?php _e('Coming Soon', 'vandel-booking'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vandel-integration-disclaimer">
                    <div class="vandel-disclaimer-icon">
                        <span class="dashicons dashicons-info-outline"></span>
                    </div>
                    <div class="vandel-disclaimer-content">
                        <p>
                            <strong><?php _e('Note:', 'vandel-booking'); ?></strong> 
                            <?php _e('Premium integrations are currently in development. Stay tuned for future updates!', 'vandel-booking'); ?>
                        </p>
                    </div>
                </div>

                <?php submit_button(__('Save Integration Settings', 'vandel-booking')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render overview tab content
     */
    private function renderOverviewTab() {
        global $wpdb;
        
        // Get statistics
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $clients_table = $wpdb->prefix . 'vandel_clients';
        
        // Check if tables exist
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        $clients_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table;
        
        // Get counts
        $total_bookings = $bookings_table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table") : 0;
        $total_clients = $clients_table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $clients_table") : 0;
        
        // Get booking status counts
        $booking_stats = [
            'total' => intval($total_bookings),
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'canceled' => 0
        ];
        
        if ($bookings_table_exists) {
            $status_counts = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $bookings_table GROUP BY status");
            if ($status_counts) {
                foreach ($status_counts as $status) {
                    if (isset($booking_stats[$status->status])) {
                        $booking_stats[$status->status] = intval($status->count);
                    }
                }
            }
        }
        
        // Get total revenue
        $total_revenue = $bookings_table_exists ? $wpdb->get_var("SELECT SUM(total_price) FROM $bookings_table WHERE status != 'canceled'") : 0;
        $total_revenue = floatval($total_revenue);
        
        // Get upcoming bookings
        $upcoming_bookings = [];
        if ($bookings_table_exists) {
            $upcoming_bookings = $wpdb->get_results(
                "SELECT * FROM $bookings_table 
                 WHERE booking_date > NOW() 
                 AND status IN ('pending', 'confirmed') 
                 ORDER BY booking_date ASC 
                 LIMIT 5"
            );
        }
        
        // Get recent bookings
        $recent_bookings = [];
        if ($bookings_table_exists) {
            $recent_bookings = $wpdb->get_results(
                "SELECT * FROM $bookings_table 
                 ORDER BY created_at DESC 
                 LIMIT 5"
            );
        }
        
        // Get services
        $services_count = 0;
        $services = [];
        if (post_type_exists('vandel_service')) {
            $services_query = new \WP_Query([
                'post_type' => 'vandel_service',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            $services_count = $services_query->found_posts;
            
            // Get top services
            $services = get_posts([
                'post_type' => 'vandel_service',
                'posts_per_page' => 5,
                'orderby' => 'meta_value_num',
                'meta_key' => '_vandel_service_booking_count', // This meta key might need to be created
                'order' => 'DESC'
            ]);
        }
        
        ?>
        <div id="overview" class="vandel-tab-content">
            
            <div class="vandel-dashboard-welcome">
                <div class="vandel-welcome-content">
                    <h2><?php _e('Welcome to Your Booking Dashboard', 'vandel-booking'); ?></h2>
                    <p><?php _e('Manage your bookings, services, and clients all in one place. Here\'s a snapshot of your business.', 'vandel-booking'); ?></p>
                </div>
                <div class="vandel-quick-stats">
                    <div class="vandel-stat-cards">
                        <div class="vandel-stat-card vandel-stat-bookings">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($booking_stats['total']); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-clients">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-groups"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($total_clients); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Clients', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-services">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-admin-generic"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($services_count); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Active Services', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-revenue">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-chart-line"></span></span>
                                <span class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($total_revenue); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Revenue', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vandel-dashboard-grid">
                <div class="vandel-dashboard-main">
                    <?php if (!$bookings_table_exists || $booking_stats['total'] === 0): ?>
                        <!-- First Time Setup Section if no bookings -->
                        <div class="vandel-card vandel-setup-card">
                            <div class="vandel-card-header">
                                <h3><?php _e('Getting Started with Vandel Booking', 'vandel-booking'); ?></h3>
                            </div>
                            <div class="vandel-card-body">
                                <div class="vandel-setup-steps">
                                    <div class="vandel-setup-step <?php echo $services_count > 0 ? 'completed' : ''; ?>">
                                        <div class="vandel-setup-step-number">1</div>
                                        <div class="vandel-setup-step-content">
                                            <h4><?php _e('Create Services', 'vandel-booking'); ?></h4>
                                            <p><?php _e('Start by setting up the services you offer to your customers.', 'vandel-booking'); ?></p>
                                            <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="button button-primary"><?php _e('Add a Service', 'vandel-booking'); ?></a>
                                        </div>
                                    </div>
                                    
                                    <div class="vandel-setup-step">
                                        <div class="vandel-setup-step-number">2</div>
                                        <div class="vandel-setup-step-content">
                                            <h4><?php _e('Add the Booking Form to Your Website', 'vandel-booking'); ?></h4>
                                            <p><?php _e('Use the shortcode to add the booking form to any page on your website.', 'vandel-booking'); ?></p>
                                            <div class="vandel-shortcode-display">
                                                <code>[vandel_booking_form]</code>
                                                <button class="vandel-copy-shortcode" data-shortcode="[vandel_booking_form]">
                                                    <span class="dashicons dashicons-clipboard"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="vandel-setup-step">
                                        <div class="vandel-setup-step-number">3</div>
                                        <div class="vandel-setup-step-content">
                                            <h4><?php _e('Customize Your Settings', 'vandel-booking'); ?></h4>
                                            <p><?php _e('Configure your business hours, notification emails, and other settings.', 'vandel-booking'); ?></p>
                                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=settings'); ?>" class="button button-secondary"><?php _e('Go to Settings', 'vandel-booking'); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Booking Status Summary -->
                        <div class="vandel-card">
                            <div class="vandel-card-header vandel-flex-header">
                                <h3><?php _e('Booking Status Summary', 'vandel-booking'); ?></h3>
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="vandel-view-all"><?php _e('View All Bookings', 'vandel-booking'); ?></a>
                            </div>
                            <div class="vandel-card-body">
                                <div class="vandel-status-summary">
                                    <div class="vandel-status-item vandel-status-pending">
                                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['pending']); ?></div>
                                        <div class="vandel-status-label"><?php _e('Pending', 'vandel-booking'); ?></div>
                                        <div class="vandel-status-icon"><span class="dashicons dashicons-clock"></span></div>
                                    </div>
                                    
                                    <div class="vandel-status-item vandel-status-confirmed">
                                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['confirmed']); ?></div>
                                        <div class="vandel-status-label"><?php _e('Confirmed', 'vandel-booking'); ?></div>
                                        <div class="vandel-status-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                                    </div>
                                    
                                    <div class="vandel-status-item vandel-status-completed">
                                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['completed']); ?></div>
                                        <div class="vandel-status-label"><?php _e('Completed', 'vandel-booking'); ?></div>
                                        <div class="vandel-status-icon"><span class="dashicons dashicons-saved"></span></div>
                                    </div>
                                    
                                    <div class="vandel-status-item vandel-status-canceled">
                                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['canceled']); ?></div>
                                        <div class="vandel-status-label"><?php _e('Canceled', 'vandel-booking'); ?></div>
                                        <div class="vandel-status-icon"><span class="dashicons dashicons-dismiss"></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Upcoming Bookings -->
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Upcoming Bookings', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" class="vandel-view-all"><?php _e('View Calendar', 'vandel-booking'); ?></a>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($upcoming_bookings)): ?>
                                <div class="vandel-empty-state">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <p><?php _e('No upcoming bookings.', 'vandel-booking'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="vandel-bookings-table-wrapper">
                                    <table class="vandel-bookings-table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('ID', 'vandel-booking'); ?></th>
                                                <th><?php _e('Client', 'vandel-booking'); ?></th>
                                                <th><?php _e('Service', 'vandel-booking'); ?></th>
                                                <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                                                <th><?php _e('Status', 'vandel-booking'); ?></th>
                                                <th><?php _e('Actions', 'vandel-booking'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_bookings as $booking): 
                                                $service = get_post($booking->service);
                                                $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                                                
                                                $status_classes = [
                                                    'pending' => 'vandel-status-badge-warning',
                                                    'confirmed' => 'vandel-status-badge-success',
                                                    'completed' => 'vandel-status-badge-info',
                                                    'canceled' => 'vandel-status-badge-danger'
                                                ];
                                                
                                                $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
                                            ?>
                                                <tr>
                                                    <td>#<?php echo $booking->id; ?></td>
                                                    <td><?php echo esc_html($booking->customer_name); ?></td>
                                                    <td><?php echo esc_html($service_name); ?></td>
                                                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?></td>
                                                    <td><span class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span></td>
                                                    <td>
                                                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" class="button button-small">
                                                            <?php _e('View', 'vandel-booking'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="vandel-dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Quick Actions', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-quick-action-buttons">
                                <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <span class="vandel-quick-action-label"><?php _e('Add Service', 'vandel-booking'); ?></span>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-edit"></span>
                                    <span class="vandel-quick-action-label"><?php _e('Create Booking', 'vandel-booking'); ?></span>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <span class="vandel-quick-action-label"><?php _e('Add Client', 'vandel-booking'); ?></span>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=settings'); ?>" class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <span class="vandel-quick-action-label"><?php _e('Settings', 'vandel-booking'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="vandel-empty-state vandel-empty-state-small">
                                    <p><?php _e('No bookings yet.', 'vandel-booking'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="vandel-recent-bookings">
                                    <?php foreach ($recent_bookings as $booking): 
                                        $service = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                                    ?>
                                        <div class="vandel-recent-booking-item">
                                            <div class="vandel-booking-info">
                                                <div class="vandel-booking-client">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    <?php echo esc_html($booking->customer_name); ?>
                                                </div>
                                                <div class="vandel-booking-service">
                                                    <?php echo esc_html($service_name); ?>
                                                </div>
                                            </div>
                                            <div class="vandel-booking-meta">
                                                <div class="vandel-booking-time">
                                                    <?php echo \VandelBooking\Helpers::formatDate($booking->booking_date); ?>
                                                </div>
                                                <div class="vandel-booking-status">
                                                    <span class="vandel-status-dot vandel-status-<?php echo $booking->status; ?>"></span>
                                                    <?php echo ucfirst($booking->status); ?>
                                                </div>
                                            </div>
                                            <div class="vandel-booking-action">
                                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" class="vandel-view-booking">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($recent_bookings)): ?>
                            <div class="vandel-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="vandel-link-btn">
                                    <?php _e('View All Bookings', 'vandel-booking'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Popular Services -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Your Services', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($services)): ?>
                                <div class="vandel-empty-state vandel-empty-state-small">
                                    <p><?php _e('No services created yet.', 'vandel-booking'); ?></p>
                                    <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="button button-primary">
                                        <?php _e('Add First Service', 'vandel-booking'); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="vandel-services-list">
                                    <?php foreach ($services as $service): 
                                        $price = get_post_meta($service->ID, '_vandel_service_base_price', true);
                                        $formatted_price = \VandelBooking\Helpers::formatPrice($price);
                                        $is_popular = get_post_meta($service->ID, '_vandel_service_is_popular', true) === 'yes';
                                    ?>
                                        <div class="vandel-service-item">
                                            <div class="vandel-service-icon">
                                                <?php if (has_post_thumbnail($service->ID)): ?>
                                                    <?php echo get_the_post_thumbnail($service->ID, [40, 40]); ?>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-admin-generic"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="vandel-service-details">
                                                <div class="vandel-service-name">
                                                    <?php echo esc_html($service->post_title); ?>
                                                    <?php if ($is_popular): ?>
                                                        <span class="vandel-popular-tag"><?php _e('Popular', 'vandel-booking'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="vandel-service-price">
                                                    <?php echo $formatted_price; ?>
                                                </div>
                                            </div>
                                            <div class="vandel-service-actions">
                                                <a href="<?php echo get_edit_post_link($service->ID); ?>" class="vandel-edit-service">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($services)): ?>
                            <div class="vandel-card-footer">
                                <a href="<?php echo admin_url('edit.php?post_type=vandel_service'); ?>" class="vandel-link-btn">
                                    <?php _e('Manage Services', 'vandel-booking'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render analytics tab content
     */
    private function renderAnalyticsTab() {
        global $wpdb;
        
        // Get booking data for charts
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        // Initialize empty datasets
        $monthly_bookings = [];
        $revenue_data = [];
        $service_breakdown = [];
        
        if ($bookings_table_exists) {
            // Get monthly bookings data (last 6 months)
            $monthly_results = $wpdb->get_results(
                "SELECT 
                    DATE_FORMAT(booking_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as revenue
                 FROM $bookings_table
                 WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
                 ORDER BY month ASC"
            );
            
            if ($monthly_results) {
                foreach ($monthly_results as $row) {
                    $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                    $monthly_bookings[$month_name] = intval($row->count);
                    $revenue_data[$month_name] = floatval($row->revenue);
                }
            }
            
            // Get service breakdown
            $service_results = $wpdb->get_results(
                "SELECT 
                    service,
                    COUNT(*) as count
                 FROM $bookings_table
                 WHERE status != 'canceled'
                 GROUP BY service
                 ORDER BY count DESC
                 LIMIT 5"
            );
            
            if ($service_results) {
                foreach ($service_results as $row) {
                    $service = get_post($row->service);
                    $service_name = $service ? $service->post_title : __('Unknown', 'vandel-booking') . ' (#' . $row->service . ')';
                    $service_breakdown[$service_name] = intval($row->count);
                }
            }
        }
        
        ?>
        <div id="analytics" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Booking Analytics', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($monthly_bookings)): ?>
                        <div class="vandel-empty-state">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <p><?php _e('Not enough data yet to display analytics. Start adding bookings to see insights here.', 'vandel-booking'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="vandel-analytics-grid">
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Bookings by Month', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="bookingsChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                            
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Revenue by Month', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="revenueChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vandel-analytics-grid">
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Popular Services', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="servicesChart" width="400" height="250"></canvas>
                                </div>
                            </div>
                            
                            <div class="vandel-analytics-stats">
                                <h4><?php _e('Booking Statistics', 'vandel-booking'); ?></h4>
                                
                                <?php 
                                // Calculate some statistics
                                $total_bookings = array_sum($monthly_bookings);
                                $total_revenue = array_sum($revenue_data);
                                $avg_booking_value = $total_bookings > 0 ? $total_revenue / $total_bookings : 0;
                                
                                // Determine growth if we have enough data
                                $months = array_keys($monthly_bookings);
                                $growth_text = '';
                                
                                if (count($months) >= 2) {
                                    $current_month = end($monthly_bookings);
                                    reset($monthly_bookings);
                                    $prev_month = prev($monthly_bookings);
                                    
                                    if ($prev_month > 0) {
                                        $growth_percent = (($current_month - $prev_month) / $prev_month) * 100;
                                        $growth_text = sprintf(
                                            __('%+.1f%% from previous month', 'vandel-booking'),
                                            $growth_percent
                                        );
                                    }
                                }
                                ?>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Average Booking Value', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($avg_booking_value); ?></div>
                                </div>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Total Revenue (6 months)', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($total_revenue); ?></div>
                                </div>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Current Month Bookings', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo number_format_i18n(end($monthly_bookings)); ?></div>
                                    <?php if ($growth_text): ?>
                                        <div class="vandel-stat-growth <?php echo strpos($growth_text, '+') === 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $growth_text; ?>
                                        </div>
                                    <?php endif; ?>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_business_name"><?php _e('Business Name', 'vandel-booking'); ?></label>
                            <input 
                                type="text" 
                                id="vandel_business_name" 
                                name="vandel_business_name" 
                                value="<?php echo esc_attr(get_option('vandel_business_name', get_bloginfo('name'))); ?>" 
                                class="regular-text"
                            >
                            <p class="description"><?php _e('Your business name for receipts and emails', 'vandel-booking'); ?></p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_primary_color"><?php _e('Brand Color', 'vandel-booking'); ?></label>
                            <input 
                                type="color" 
                                id="vandel_primary_color" 
                                name="vandel_primary_color" 
                                value="<?php echo esc_attr(get_option('vandel_primary_color', '#286cd6')); ?>"
                            >
                            <p class="description"><?php _e('Main color for buttons and accents on the booking form', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Regional Settings', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-setting-row">
                            <label for="vandel_currency"><?php _e('Currency', 'vandel-booking'); ?></label>
                            <select 
                                id="vandel_currency" 
                                name="vandel_currency"
                                class="regular-text"
                            >
                                <?php 
                                $currencies = [
                                    'USD' => __('US Dollar ($)', 'vandel-booking'),
                                    'EUR' => __('Euro (€)', 'vandel-booking'),
                                    'GBP' => __('British Pound (£)', 'vandel-booking'),
                                    'CAD' => __('Canadian Dollar (C$)', 'vandel-booking'),
                                    'AUD' => __('Australian Dollar (A$)', 'vandel-booking'),
                                    'SEK' => __('Swedish Krona (kr)', 'vandel-booking')
                                ];
                                $current_currency = get_option('vandel_currency', 'USD');
                                
                                foreach ($currencies as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '" ' . 
                                         selected($current_currency, $code, false) . '>' . 
                                         esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('Currency for prices and payments', 'vandel-booking'); ?></p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_default_timezone"><?php _e('Timezone', 'vandel-booking'); ?></label>
                            <select 
                                id="vandel_default_timezone" 
                                name="vandel_default_timezone"
                                class="regular-text"
                            >
                                <?php 
                                $current_timezone = get_option('vandel_default_timezone', wp_timezone_string());
                                $common_timezones = [
                                    'America/New_York' => 'Eastern Time (US & Canada)',
                                    'America/Chicago' => 'Central Time (US & Canada)',
                                    'America/Denver' => 'Mountain Time (US & Canada)',
                                    'America/Los_Angeles' => 'Pacific Time (US & Canada)',
                                    'America/Anchorage' => 'Alaska',
                                    'America/Honolulu' => 'Hawaii',
                                    'Europe/London' => 'London',
                                    'Europe/Paris' => 'Paris, Berlin, Rome, Madrid',
                                    'Asia/Tokyo' => 'Tokyo',
                                    'Australia/Sydney' => 'Sydney'
                                ];
                                
                                // First show common timezones
                                echo '<optgroup label="' . __('Common Timezones', 'vandel-booking') . '">';
                                foreach ($common_timezones as $zone => $label) {
                                    echo '<option value="' . esc_attr($zone) . '" ' . 
                                         selected($current_timezone, $zone, false) . '>' . 
                                         esc_html($label) . '</option>';
                                }
                                echo '</optgroup>';
                                
                                // Then show all timezones
                                echo '<optgroup label="' . __('All Timezones', 'vandel-booking') . '">';
                                $timezones = timezone_identifiers_list();
                                foreach ($timezones as $timezone) {
                                    if (!isset($common_timezones[$timezone])) {
                                        echo '<option value="' . esc_attr($timezone) . '" ' . 
                                             selected($current_timezone, $timezone, false) . '>' . 
                                             esc_html($timezone) . '</option>';
                                    }
                                }
                                echo '</optgroup>';
                                ?>
                            </select>
                            <p class="description"><?php _e('Timezone for booking calculations', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>


</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Load chart.js for analytics
            wp_enqueue_script('vandel-chartjs', VANDEL_PLUGIN_URL . 'assets/js/chart.min.js', [], '3.7.0', true);
            
            // Prepare chart data for JavaScript
            $chart_data = [
                'months' => array_keys($monthly_bookings),
                'bookings' => array_values($monthly_bookings),
                'revenue' => array_values($revenue_data),
                'serviceLabels' => array_keys($service_breakdown),
                'serviceData' => array_values($service_breakdown)
            ];
            
            // Add inline script for charts
            wp_add_inline_script('vandel-chartjs', '
                document.addEventListener("DOMContentLoaded", function() {
                    const chartColors = {
                        primary: "#286cd6",
                        secondary: "#6c757d",
                        success: "#28a745",
                        info: "#17a2b8",
                        warning: "#ffc107",
                        danger: "#dc3545",
                        light: "#f8f9fa",
                        dark: "#343a40"
                    };
                    
                    // Chart data
                    const chartData = ' . json_encode($chart_data) . ';
                    
                    // Bookings Chart
                    if (document.getElementById("bookingsChart")) {
                        const bookingsCtx = document.getElementById("bookingsChart").getContext("2d");
                        new Chart(bookingsCtx, {
                            type: "bar",
                            data: {
                                labels: chartData.months,
                                datasets: [{
                                    label: "' . __('Number of Bookings', 'vandel-booking') . '",
                                    data: chartData.bookings,
                                    backgroundColor: chartColors.primary,
                                    borderColor: chartColors.primary,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        precision: 0
                                    }
                                }
                            }
                        });
                    }
                    
                    // Revenue Chart
                    if (document.getElementById("revenueChart")) {
                        const revenueCtx = document.getElementById("revenueChart").getContext("2d");
                        new Chart(revenueCtx, {
                            type: "line",
                            data: {
                                labels: chartData.months,
                                datasets: [{
                                    label: "' . __('Revenue', 'vandel-booking') . '",
                                    data: chartData.revenue,
                                    backgroundColor: "rgba(40, 167, 69, 0.2)",
                                    borderColor: chartColors.success,
                                    borderWidth: 2,
                                    pointBackgroundColor: chartColors.success,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return "' . \VandelBooking\Helpers::getCurrencySymbol() . '" + value;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                    
                    // Services Chart
                    if (document.getElementById("servicesChart")) {
                        const servicesCtx = document.getElementById("servicesChart").getContext("2d");
                        new Chart(servicesCtx, {
                            type: "doughnut",
                            data: {
                                labels: chartData.serviceLabels,
                                datasets: [{
                                    data: chartData.serviceData,
                                    backgroundColor: [
                                        chartColors.primary,
                                        chartColors.success,
                                        chartColors.warning,
                                        chartColors.info,
                                        chartColors.danger
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: "right"
                                    }
                                }
                            }
                        });
                    }
                });
            ');
            ?>
        </div>
        <?php
    }
    
    /**
     * Render bookings tab content
     */
    private function renderBookingsTab() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        // Check if we need to handle an action
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        
        if ($action === 'add') {
            $this->renderAddBookingForm();
            return;
        }
        
        // Get booking data for list
        $bookings = [];
        if ($bookings_table_exists) {
            // Handle paging
            $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            
            // Handle filters
            $where = "1=1";
            $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
            if ($status_filter) {
                $where .= $wpdb->prepare(" AND status = %s", $status_filter);
            }
            
            $date_filter = isset($_GET['date_range']) ? sanitize_key($_GET['date_range']) : '';
            if ($date_filter) {
                switch ($date_filter) {
                    case 'today':
                        $where .= " AND DATE(booking_date) = CURDATE()";
                        break;
                    case 'tomorrow':
                        $where .= " AND DATE(booking_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                        break;
                    case 'this_week':
                        $where .= " AND YEARWEEK(booking_date, 1) = YEARWEEK(CURDATE(), 1)";
                        break;
                    case 'next_week':
                        $where .= " AND YEARWEEK(booking_date, 1) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1)";
                        break;
                    case 'this_month':
                        $where .= " AND YEAR(booking_date) = YEAR(CURDATE()) AND MONTH(booking_date) = MONTH(CURDATE())";
                        break;
                }
            }
            
            $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            if ($search_query) {
                $where .= $wpdb->prepare(
                    " AND (id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)",
                    "%{$search_query}%",
                    "%{$search_query}%",
                    "%{$search_query}%",
                    "%{$search_query}%"
                );
            }
            
            // Get total count for pagination
            $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE $where");
            
            // Get bookings
            $bookings = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $bookings_table 
                     WHERE $where
                     ORDER BY booking_date DESC
                     LIMIT %d OFFSET %d",
                    $per_page, $offset
                )
            );
            
            // Calculate pagination
            $total_pages = ceil($total_bookings / $per_page);
        }
        
        ?>
        <div id="bookings" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header vandel-flex-header">
                    <h3><?php _e('All Bookings', 'vandel-booking'); ?></h3>
                    <div class="vandel-header-actions">
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Booking', 'vandel-booking'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="vandel-card-body">
                    <!-- Filters -->
                    <div class="vandel-filters-toolbar">
                        <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="vandel-filter-form">
                            <input type="hidden" name="page" value="vandel-dashboard">
                            <input type="hidden" name="tab" value="bookings">
                            
                            <div class="vandel-filter-group">
                                <select name="status" class="vandel-filter-select">
                                    <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'vandel-booking'); ?></option>
                                    <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php _e('Confirmed', 'vandel-booking'); ?></option>
                                    <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'vandel-booking'); ?></option>
                                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>><?php _e('Canceled', 'vandel-booking'); ?></option>
                                </select>
                                
                                <select name="date_range" class="vandel-filter-select">
                                    <option value=""><?php _e('All Dates', 'vandel-booking'); ?></option>
                                    <option value="today" <?php selected($date_filter, 'today'); ?>><?php _e('Today', 'vandel-booking'); ?></option>
                                    <option value="tomorrow" <?php selected($date_filter, 'tomorrow'); ?>><?php _e('Tomorrow', 'vandel-booking'); ?></option>
                                    <option value="this_week" <?php selected($date_filter, 'this_week'); ?>><?php _e('This Week', 'vandel-booking'); ?></option>
                                    <option value="next_week" <?php selected($date_filter, 'next_week'); ?>><?php _e('Next Week', 'vandel-booking'); ?></option>
                                    <option value="this_month" <?php selected($date_filter, 'this_month'); ?>><?php _e('This Month', 'vandel-booking'); ?></option>
                                </select>
                                
                                <div class="vandel-search-field">
                                    <input type="text" name="s" placeholder="<?php _e('Search bookings...', 'vandel-booking'); ?>" value="<?php echo esc_attr($search_query); ?>">
                                    <button type="submit" class="vandel-search-button">
                                        <span class="dashicons dashicons-search"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="vandel-filter-actions">
                                <button type="submit" class="button"><?php _e('Apply Filters', 'vandel-booking'); ?></button>
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="button vandel-reset-btn"><?php _e('Reset', 'vandel-booking'); ?></a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Bookings Table -->
                    <?php if (!$bookings_table_exists || empty($bookings)): ?>
                        <div class="vandel-empty-state">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <p><?php _e('No bookings found. Create your first booking to get started.', 'vandel-booking'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="button button-primary">
                                <?php _e('Create First Booking', 'vandel-booking'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="vandel-bookings-table-wrapper">
                            <table class="vandel-bookings-table vandel-data-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('ID', 'vandel-booking'); ?></th>
                                        <th><?php _e('Client', 'vandel-booking'); ?></th>
                                        <th><?php _e('Service', 'vandel-booking'); ?></th>
                                        <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                                        <th><?php _e('Total', 'vandel-booking'); ?></th>
                                        <th><?php _e('Status', 'vandel-booking'); ?></th>
                                        <th><?php _e('Actions', 'vandel-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): 
                                        $service = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                                        
                                        $status_classes = [
                                            'pending' => 'vandel-status-badge-warning',
                                            'confirmed' => 'vandel-status-badge-success',
                                            'completed' => 'vandel-status-badge-info',
                                            'canceled' => 'vandel-status-badge-danger'
                                        ];
                                        
                                        $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
                                    ?>
                                        <tr>
                                            <td>#<?php echo $booking->id; ?></td>
                                            <td>
                                                <div class="vandel-client-info">
                                                    <strong><?php echo esc_html($booking->customer_name); ?></strong>
                                                    <?php if (!empty($booking->customer_email)): ?>
                                                        <span class="vandel-client-email"><?php echo esc_html($booking->customer_email); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo esc_html($service_name); ?></td>
                                            <td>
                                                <?php echo \VandelBooking\Helpers::formatDate($booking->booking_date); ?>
                                            </td>
                                            <td><?php echo \VandelBooking\Helpers::formatPrice($booking->total_price); ?></td>
                                            <td><span class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span></td>
                                            <td>
                                                <div class="vandel-row-actions">
                                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" class="button button-small">
                                                        <?php _e('View', 'vandel-booking'); ?>
                                                    </a>
                                                    <button class="button button-small vandel-toggle-status-btn" data-booking-id="<?php echo $booking->id; ?>">
                                                        <?php _e('Change Status', 'vandel-booking'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="vandel-pagination">
                                <?php
                                // Build pagination links
                                $current_url = add_query_arg(array_filter([
                                    'page' => 'vandel-dashboard',
                                    'tab' => 'bookings',
                                    'status' => $status_filter,
                                    'date_range' => $date_filter,
                                    's' => $search_query
                                ]));
                                
                                // Previous page
                                if ($page > 1) {
                                    echo '<a href="' . esc_url(add_query_arg('paged', $page - 1, $current_url)) . '" class="vandel-pagination-btn">&laquo; ' . __('Previous', 'vandel-booking') . '</a>';
                                }
                                
                                // Page numbers
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<a href="' . esc_url(add_query_arg('paged', 1, $current_url)) . '" class="vandel-pagination-btn">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="vandel-pagination-ellipsis">...</span>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="vandel-pagination-btn vandel-pagination-current">' . $i . '</span>';
                                    } else {
                                        echo '<a href="' . esc_url(add_query_arg('paged', $i, $current_url)) . '" class="vandel-pagination-btn">' . $i . '</a>';
                                    }
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="vandel-pagination-ellipsis">...</span>';
                                    }
                                    echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $current_url)) . '" class="vandel-pagination-btn">' . $total_pages . '</a>';
                                }
                                
                                // Next page
                                if ($page < $total_pages) {
                                    echo '<a href="' . esc_url(add_query_arg('paged', $page + 1, $current_url)) . '" class="vandel-pagination-btn">' . __('Next', 'vandel-booking') . ' &raquo;</a>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add booking form
     */
    private function renderAddBookingForm() {
        // Get services
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h3><?php _e('Create New Booking', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <form method="post" id="vandel-add-booking-form">
                    <?php wp_nonce_field('vandel_add_booking', 'vandel_booking_nonce'); ?>
                    
                    <div class="vandel-form-section">
                        <h4><?php _e('Service Details', 'vandel-booking'); ?></h4>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="service_id"><?php _e('Service', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <select name="service_id" id="service_id" required class="vandel-select">
                                    <option value=""><?php _e('Select a service', 'vandel-booking'); ?></option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service->ID; ?>" data-price="<?php echo esc_attr(get_post_meta($service->ID, '_vandel_service_base_price', true)); ?>">
                                            <?php echo esc_html($service->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="vandel-col">
                                <label for="booking_date"><?php _e('Date & Time', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="datetime-local" name="booking_date" id="booking_date" required class="vandel-datetime-field">
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="booking_status"><?php _e('Status', 'vandel-booking'); ?></label>
                                <select name="booking_status" id="booking_status" class="vandel-select">
                                    <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                                    <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                                    <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                                    <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                                </select>
                            </div>
                            <div class="vandel-col">
                                <label for="booking_price"><?php _e('Total Price', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <div class="vandel-input-group">
                                    <span class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="booking_price" id="booking_price" step="0.01" min="0" required class="vandel-price-field">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-form-section">
                        <h4><?php _e('Client Information', 'vandel-booking'); ?></h4>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="customer_name"><?php _e('Name', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" name="customer_name" id="customer_name" required class="vandel-text-field">
                            </div>
                            <div class="vandel-col">
                                <label for="customer_email"><?php _e('Email', 'vandel-booking'); ?></label>
                                <input type="email" name="customer_email" id="customer_email" class="vandel-text-field">
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="customer_phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                                <input type="tel" name="customer_phone" id="customer_phone" class="vandel-text-field">
                            </div>
                            <div class="vandel-col">
                                <label for="customer_address"><?php _e('Address', 'vandel-booking'); ?></label>
                                <input type="text" name="customer_address" id="customer_address" class="vandel-text-field">
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-form-section">
                        <h4><?php _e('Additional Information', 'vandel-booking'); ?></h4>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="booking_notes"><?php _e('Notes', 'vandel-booking'); ?></label>
                                <textarea name="booking_notes" id="booking_notes" rows="4" class="vandel-textarea"></textarea>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label class="vandel-checkbox-label">
                                    <input type="checkbox" name="send_notification" value="yes" checked> 
                                    <?php _e('Send notification email to customer', 'vandel-booking'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-form-actions">
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="button button-secondary"><?php _e('Cancel', 'vandel-booking'); ?></a>
                        <button type="submit" name="vandel_add_booking_submit" class="button button-primary"><?php _e('Create Booking', 'vandel-booking'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render clients tab content
     */
    private function renderClientsTab() {
        // Implementation for clients tab
        ?>
        <div id="clients" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Client Management', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <p><?php _e('Client management functionality will be available in the next update.', 'vandel-booking'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render calendar tab content
     */
    private function renderCalendarTab() {
        // Implementation for calendar tab
        ?>
        <div id="calendar" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Booking Calendar', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <p><?php _e('Calendar view will be available in the next update.', 'vandel-booking'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}