<?php
namespace VandelBooking\Admin;

/**
 * Enhanced Dashboard Class using the WordPress Settings API
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
     * Render the dashboard
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
     * Render dashboard tabs navigation
     * 
     * @param string $active_tab Currently active tab
     */
    private function renderTabs($active_tab) {
        ?>
        <nav class="vandel-tabs-navigation">
            <ul>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=overview')); ?>" 
                       class="<?php echo $active_tab === 'overview' ? 'active' : ''; ?>" 
                       data-tab="overview">
                        <?php _e('Overview', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings')); ?>" 
                       class="<?php echo $active_tab === 'bookings' ? 'active' : ''; ?>" 
                       data-tab="bookings">
                        <?php _e('Bookings', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=clients')); ?>" 
                       class="<?php echo $active_tab === 'clients' ? 'active' : ''; ?>" 
                       data-tab="clients">
                        <?php _e('Clients', 'vandel-booking'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings')); ?>" 
                       class="<?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
                       data-tab="settings">
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
     * Render General Settings
     */
    private function renderGeneralSettings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('General Settings', 'vandel-booking'); ?></h2>

            <!-- Use WP Settings API -->
            <form method="post" action="options.php">
                <?php 
                    // This loads hidden fields + nonce for "vandel_general_settings"
                    settings_fields('vandel_general_settings'); 
                ?>
                
                <div class="vandel-settings-group">
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
                        <label for="vandel_primary_color"><?php _e('Primary Color', 'vandel-booking'); ?></label>
                        <input 
                            type="color" 
                            id="vandel_primary_color" 
                            name="vandel_primary_color" 
                            value="<?php echo esc_attr(get_option('vandel_primary_color', '#286cd6')); ?>"
                        >
                        <p class="description"><?php _e('Main color for buttons and accents', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_base_price"><?php _e('Base Service Price', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span class="vandel-currency-symbol">
                                <?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?>
                            </span>
                            <input 
                                type="number" 
                                id="vandel_base_price" 
                                name="vandel_base_price" 
                                value="<?php echo esc_attr(get_option('vandel_base_price', 0)); ?>" 
                                step="0.01" 
                                min="0"
                            >
                        </div>
                        <p class="description"><?php _e('Default base price for services', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_currency"><?php _e('Currency', 'vandel-booking'); ?></label>
                        <select 
                            id="vandel_currency" 
                            name="vandel_currency"
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
                        <label for="vandel_default_timezone"><?php _e('Default Timezone', 'vandel-booking'); ?></label>
                        <select 
                            id="vandel_default_timezone" 
                            name="vandel_default_timezone"
                        >
                            <?php 
                            $current_timezone = get_option('vandel_default_timezone', wp_timezone_string());
                            $timezones = timezone_identifiers_list();
                            foreach ($timezones as $timezone) {
                                echo '<option value="' . esc_attr($timezone) . '" ' . 
                                     selected($current_timezone, $timezone, false) . '>' . 
                                     esc_html($timezone) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e('Timezone for booking calculations', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label><?php _e('Business Hours', 'vandel-booking'); ?></label>
                        <div class="vandel-business-hours">
                            <div class="vandel-input-group">
                                <label><?php _e('Start Time', 'vandel-booking'); ?></label>
                                <input 
                                    type="time" 
                                    name="vandel_business_hours_start" 
                                    value="<?php echo esc_attr(get_option('vandel_business_hours_start', '09:00')); ?>"
                                >
                            </div>
                            <div class="vandel-input-group">
                                <label><?php _e('End Time', 'vandel-booking'); ?></label>
                                <input 
                                    type="time" 
                                    name="vandel_business_hours_end" 
                                    value="<?php echo esc_attr(get_option('vandel_business_hours_end', '17:00')); ?>"
                                >
                            </div>
                        </div>
                        <p class="description"><?php _e('Default operating hours for booking', 'vandel-booking'); ?></p>
                    </div>
                </div>

                <?php submit_button(); ?>
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
            
            <form method="post" action="options.php">
                <?php 
                    // This loads hidden fields + nonce for "vandel_booking_settings"
                    settings_fields('vandel_booking_settings'); 
                ?>
                
                <div class="vandel-settings-group">
                    <div class="vandel-setting-row">
                        <label for="vandel_min_advance_booking"><?php _e('Minimum Advance Booking', 'vandel-booking'); ?></label>
                        <input 
                            type="number" 
                            id="vandel_min_advance_booking" 
                            name="vandel_min_advance_booking" 
                            value="<?php echo esc_attr(get_option('vandel_min_advance_booking', 1)); ?>" 
                            min="0"
                        > <?php _e('hours', 'vandel-booking'); ?>
                        <p class="description"><?php _e('Minimum hours in advance for booking', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_max_advance_booking"><?php _e('Maximum Advance Booking', 'vandel-booking'); ?></label>
                        <input 
                            type="number" 
                            id="vandel_max_advance_booking" 
                            name="vandel_max_advance_booking" 
                            value="<?php echo esc_attr(get_option('vandel_max_advance_booking', 90)); ?>" 
                            min="1"
                        > <?php _e('days', 'vandel-booking'); ?>
                        <p class="description"><?php _e('Maximum days in advance for booking', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_booking_slots_interval"><?php _e('Booking Slots Interval', 'vandel-booking'); ?></label>
                        <input 
                            type="number" 
                            id="vandel_booking_slots_interval" 
                            name="vandel_booking_slots_interval" 
                            value="<?php echo esc_attr(get_option('vandel_booking_slots_interval', 30)); ?>" 
                            min="15"
                            step="15"
                        > <?php _e('minutes', 'vandel-booking'); ?>
                        <p class="description"><?php _e('Time interval between available booking slots', 'vandel-booking'); ?></p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_booking_cancellation_window"><?php _e('Cancellation Window', 'vandel-booking'); ?></label>
                        <input 
                            type="number" 
                            id="vandel_booking_cancellation_window" 
                            name="vandel_booking_cancellation_window" 
                            value="<?php echo esc_attr(get_option('vandel_booking_cancellation_window', 24)); ?>" 
                            min="0"
                        > <?php _e('hours before appointment', 'vandel-booking'); ?>
                        <p class="description">
                            <?php _e('How many hours before the appointment customers can cancel', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_default_booking_status">
                            <?php _e('Default Booking Status', 'vandel-booking'); ?>
                        </label>
                        <select 
                            id="vandel_default_booking_status" 
                            name="vandel_default_booking_status"
                        >
                            <?php 
                            $statuses = [
                                'pending'   => __('Pending', 'vandel-booking'),
                                'confirmed' => __('Confirmed', 'vandel-booking')
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
                            <?php _e('Status assigned to new bookings', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_booking_cancellation_policy">
                            <?php _e('Cancellation Policy', 'vandel-booking'); ?>
                        </label>
                        <textarea 
                            id="vandel_booking_cancellation_policy" 
                            name="vandel_booking_cancellation_policy" 
                            rows="4" 
                            class="widefat"
                        ><?php echo esc_textarea(get_option('vandel_booking_cancellation_policy', '')); ?></textarea>
                        <p class="description">
                            <?php _e('Your booking cancellation policy (displayed to customers)', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label>
                            <input 
                                type="checkbox" 
                                name="vandel_enable_multiple_bookings" 
                                value="yes" 
                                <?php checked(get_option('vandel_enable_multiple_bookings', 'no'), 'yes'); ?>
                            >
                            <?php _e('Enable Multiple Bookings per Time Slot', 'vandel-booking'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Allow multiple bookings for the same time slot', 'vandel-booking'); ?>
                        </p>
                    </div>
                </div>

                <?php submit_button(); ?>
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
            
            <form method="post" action="options.php">
                <?php settings_fields('vandel_notification_settings'); ?>
                
                <div class="vandel-settings-group">
                    <div class="vandel-setting-row">
                        <label>
                            <input 
                                type="checkbox" 
                                name="vandel_enable_email_notifications" 
                                value="yes" 
                                <?php checked(get_option('vandel_enable_email_notifications', 'yes'), 'yes'); ?>
                            >
                            <?php _e('Enable Email Notifications', 'vandel-booking'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Send email notifications for bookings', 'vandel-booking'); ?>
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
                            <?php _e('Name that appears in the "From" field', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_email_sender_address">
                            <?php _e('Email Sender Address', 'vandel-booking'); ?>
                        </label>
                        <input 
                            type="email" 
                            id="vandel_email_sender_address" 
                            name="vandel_email_sender_address" 
                            value="<?php echo esc_attr(get_option('vandel_email_sender_address', get_option('admin_email'))); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php _e('Email address that appears in the "From" field', 'vandel-booking'); ?>
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
                            <?php _e('Email address to receive booking notifications', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_email_subject">
                            <?php _e('Email Subject', 'vandel-booking'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="vandel_email_subject" 
                            name="vandel_email_subject" 
                            value="<?php echo esc_attr(get_option('vandel_email_subject', __('Your Booking Confirmation', 'vandel-booking'))); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php _e('Subject line for confirmation emails', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label for="vandel_email_message">
                            <?php _e('Email Message', 'vandel-booking'); ?>
                        </label>
                        <textarea 
                            id="vandel_email_message" 
                            name="vandel_email_message" 
                            rows="6" 
                            class="widefat"
                        ><?php echo esc_textarea(get_option('vandel_email_message', __('Thank you for your booking. We look forward to serving you.', 'vandel-booking'))); ?></textarea>
                        <p class="description">
                            <?php _e('Custom message to include in confirmation emails', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <label>
                            <input 
                                type="checkbox" 
                                name="vandel_sms_notifications" 
                                value="yes" 
                                <?php checked(get_option('vandel_sms_notifications', 'no'), 'yes'); ?>
                                disabled
                            >
                            <?php _e('Enable SMS Notifications', 'vandel-booking'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Send SMS notifications for bookings (Coming soon)', 'vandel-booking'); ?>
                        </p>
                    </div>
                </div>

                <?php submit_button(); ?>
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
            
            <form method="post" action="options.php">
                <?php settings_fields('vandel_integration_settings'); ?>
                
                <div class="vandel-settings-group">
                    <div class="vandel-setting-row">
                        <h3><?php _e('ZIP Code Feature', 'vandel-booking'); ?></h3>
                        <label>
                            <input 
                                type="checkbox" 
                                name="vandel_enable_zip_code_feature" 
                                value="yes" 
                                <?php checked(get_option('vandel_enable_zip_code_feature', 'no'), 'yes'); ?>
                            >
                            <?php _e('Enable ZIP Code Location Services', 'vandel-booking'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Enable location-based pricing and service area restrictions', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-setting-row">
                        <h3><?php _e('Payment Gateways', 'vandel-booking'); ?></h3>
                        <div class="vandel-integration-list">
                            <div class="vandel-integration-item">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="vandel_enable_paypal" 
                                        value="yes" 
                                        <?php checked(get_option('vandel_enable_paypal', 'no'), 'yes'); ?>
                                        disabled
                                    >
                                    <?php _e('PayPal', 'vandel-booking'); ?>
                                </label>
                                <span class="vandel-badge vandel-badge-warning">
                                    <?php _e('Coming Soon', 'vandel-booking'); ?>
                                </span>
                            </div>
                            <div class="vandel-integration-item">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="vandel_enable_stripe" 
                                        value="yes" 
                                        <?php checked(get_option('vandel_enable_stripe', 'no'), 'yes'); ?>
                                        disabled
                                    >
                                    <?php _e('Stripe', 'vandel-booking'); ?>
                                </label>
                                <span class="vandel-badge vandel-badge-warning">
                                    <?php _e('Coming Soon', 'vandel-booking'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="vandel-setting-row">
                        <h3><?php _e('Calendar Sync', 'vandel-booking'); ?></h3>
                        <div class="vandel-integration-list">
                            <div class="vandel-integration-item">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="vandel_enable_gcal" 
                                        value="yes" 
                                        <?php checked(get_option('vandel_enable_gcal', 'no'), 'yes'); ?>
                                        disabled
                                    >
                                    <?php _e('Google Calendar', 'vandel-booking'); ?>
                                </label>
                                <span class="vandel-badge vandel-badge-warning">
                                    <?php _e('Coming Soon', 'vandel-booking'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="vandel-setting-row">
                        <h3><?php _e('Marketing & CRM', 'vandel-booking'); ?></h3>
                        <div class="vandel-integration-list">
                            <div class="vandel-integration-item">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="vandel_enable_mailchimp" 
                                        value="yes" 
                                        <?php checked(get_option('vandel_enable_mailchimp', 'no'), 'yes'); ?>
                                        disabled
                                    >
                                    <?php _e('MailChimp', 'vandel-booking'); ?>
                                </label>
                                <span class="vandel-badge vandel-badge-warning">
                                    <?php _e('Coming Soon', 'vandel-booking'); ?>
                                </span>
                            </div>
                            <div class="vandel-integration-item">
                                <label>
                                    <input 
                                        type="checkbox" 
                                        name="vandel_enable_hubspot" 
                                        value="yes" 
                                        <?php checked(get_option('vandel_enable_hubspot', 'no'), 'yes'); ?>
                                        disabled
                                    >
                                    <?php _e('HubSpot', 'vandel-booking'); ?>
                                </label>
                                <span class="vandel-badge vandel-badge-warning">
                                    <?php _e('Coming Soon', 'vandel-booking'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="vandel-integration-disclaimer">
                    <p>
                        <strong><?php _e('Note:', 'vandel-booking'); ?></strong> 
                        <?php _e('Integrations are currently in development. Stay tuned for future updates!', 'vandel-booking'); ?>
                    </p>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render overview tab content
     */
/**
 * Render overview tab content
 */
private function renderOverviewTab() {
    ?>
    <div id="overview" class="vandel-tab-content">
        <div class="vandel-stats-grid">
            <?php
            // Check if BookingModel exists before trying to use it
            if (class_exists('\\VandelBooking\\Booking\\BookingModel')) {
                // Get booking statistics
                $booking_model = new \VandelBooking\Booking\BookingModel();
                $booking_stats = $booking_model->getStatusCounts();
            } else {
                // Create placeholder stats if the class doesn't exist
                $booking_stats = [
                    'total' => 0,
                    'pending' => 0,
                    'confirmed' => 0,
                    'completed' => 0
                ];
            }
            
            // Render cards for different booking statistics
            $stat_cards = [
                'total' => [
                    'label' => __('Total Bookings', 'vandel-booking'),
                    'value' => $booking_stats['total']
                ],
                'pending' => [
                    'label' => __('Pending Bookings', 'vandel-booking'),
                    'value' => $booking_stats['pending']
                ],
                'confirmed' => [
                    'label' => __('Confirmed Bookings', 'vandel-booking'),
                    'value' => $booking_stats['confirmed']
                ],
                'completed' => [
                    'label' => __('Completed Bookings', 'vandel-booking'),
                    'value' => $booking_stats['completed']
                ]
            ];
            
            foreach ($stat_cards as $key => $card):
            ?>
                <div class="vandel-stat-card">
                    <div class="vandel-stat-label"><?php echo esc_html($card['label']); ?></div>
                    <div class="vandel-stat-value"><?php echo esc_html($card['value']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Bookings Section -->
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h3 class="vandel-card-title"><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <?php
                if (class_exists('\\VandelBooking\\Booking\\BookingModel')) {
                    // Get booking model
                    $booking_model = new \VandelBooking\Booking\BookingModel();
                    
                    // Fetch recent bookings
                    $recent_bookings = $booking_model->getAll([
                        'limit' => 5,
                        'orderby' => 'booking_date',
                        'order' => 'DESC'
                    ]);
                    
                    if (!empty($recent_bookings)):
                    ?>
                        <table class="wp-list-table widefat fixed">
                            <thead>
                                <tr>
                                    <th><?php _e('Booking ID', 'vandel-booking'); ?></th>
                                    <th><?php _e('Service', 'vandel-booking'); ?></th>
                                    <th><?php _e('Client', 'vandel-booking'); ?></th>
                                    <th><?php _e('Date', 'vandel-booking'); ?></th>
                                    <th><?php _e('Status', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): 
                                    $service = get_post($booking->service);
                                    $client = $this->getBookingClientDetails($booking->client_id);
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($booking->id); ?></td>
                                        <td><?php echo esc_html($service ? $service->post_title : 'N/A'); ?></td>
                                        <td><?php echo esc_html($client ? $client->name : 'N/A'); ?></td>
                                        <td><?php echo \VandelBooking\Helpers::formatDate($booking->booking_date); ?></td>
                                        <td>
                                            <span class="vandel-status 
                                                <?php 
                                                switch ($booking->status) {
                                                    case 'pending':
                                                        echo 'vandel-status-inactive';
                                                        break;
                                                    case 'confirmed':
                                                        echo 'vandel-status-active';
                                                        break;
                                                    case 'completed':
                                                        echo 'vandel-status-featured';
                                                        break;
                                                    default:
                                                        echo '';
                                                }
                                                ?>
                                            ">
                                                <?php echo esc_html(ucfirst($booking->status)); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No recent bookings found.', 'vandel-booking'); ?></p>
                    <?php endif;
                } else {
                    // Show a placeholder message if BookingModel doesn't exist
                    ?>
                    <p><?php _e('Booking functionality is not fully configured. Please set up the booking system first.', 'vandel-booking'); ?></p>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

    /**
     * Render bookings tab content
     */
    private function renderBookingsTab() {
        ?>
        <div id="bookings" class="vandel-tab-content">
            <h2><?php _e('Bookings', 'vandel-booking'); ?></h2>
            
            <!-- Booking Filters -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3 class="vandel-card-title"><?php _e('Filter Bookings', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="vandel-dashboard">
                        <input type="hidden" name="tab" value="bookings">
                        
                        <div class="vandel-row">
                            <div class="vandel-col">
                                <label for="booking_status"><?php _e('Status', 'vandel-booking'); ?></label>
                                <select name="status" id="booking_status">
                                    <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                                    <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                                    <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                                    <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                                    <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                                </select>
                            </div>
                            
                            <div class="vandel-col">
                                <label for="booking_service"><?php _e('Service', 'vandel-booking'); ?></label>
                                <select name="service" id="booking_service">
                                    <option value=""><?php _e('All Services', 'vandel-booking'); ?></option>
                                    <?php
                                    $services = get_posts([
                                        'post_type' => 'vandel_service',
                                        'numberposts' => -1,
                                        'orderby' => 'title',
                                        'order' => 'ASC'
                                    ]);
                                    
                                    foreach ($services as $service) {
                                        echo '<option value="' . esc_attr($service->ID) . '">' 
                                             . esc_html($service->post_title) 
                                             . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="vandel-col">
                                <label for="booking_date_from">
                                    <?php _e('Date From', 'vandel-booking'); ?>
                                </label>
                                <input type="date" name="date_from" id="booking_date_from">
                            </div>
                            
                            <div class="vandel-col">
                                <label for="booking_date_to">
                                    <?php _e('Date To', 'vandel-booking'); ?>
                                </label>
                                <input type="date" name="date_to" id="booking_date_to">
                            </div>
                        </div>
                        
                        <div class="vandel-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Filter', 'vandel-booking'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings')); ?>" 
                               class="button">
                                <?php _e('Reset', 'vandel-booking'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Bookings List -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3 class="vandel-card-title"><?php _e('All Bookings', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php
                    // Use your BookingModel to get all bookings
                    $booking_model = new \VandelBooking\Booking\BookingModel();
                    
                    $status     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
                    $service    = isset($_GET['service']) ? intval($_GET['service']) : '';
                    $date_from  = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
                    $date_to    = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
                    
                    $args = [
                        'status'    => $status,
                        'service'   => $service,
                        'date_from' => $date_from,
                        'date_to'   => $date_to,
                        'orderby'   => 'booking_date',
                        'order'     => 'DESC'
                    ];
                    
                    $bookings = $booking_model->getAll($args);
                    
                    if (!empty($bookings)):
                    ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'vandel-booking'); ?></th>
                                    <th><?php _e('Service', 'vandel-booking'); ?></th>
                                    <th><?php _e('Client', 'vandel-booking'); ?></th>
                                    <th><?php _e('Email', 'vandel-booking'); ?></th>
                                    <th><?php _e('Date', 'vandel-booking'); ?></th>
                                    <th><?php _e('Status', 'vandel-booking'); ?></th>
                                    <th><?php _e('Total', 'vandel-booking'); ?></th>
                                    <th><?php _e('Actions', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking):
                                    $service_post = get_post($booking->service);
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($booking->id); ?></td>
                                        <td><?php echo esc_html($service_post ? $service_post->post_title : 'N/A'); ?></td>
                                        <td><?php echo esc_html($booking->customer_name); ?></td>
                                        <td><?php echo esc_html($booking->customer_email); ?></td>
                                        <td><?php echo \VandelBooking\Helpers::formatDate($booking->booking_date); ?></td>
                                        <td>
                                            <span class="vandel-status 
                                                <?php 
                                                switch ($booking->status) {
                                                    case 'pending':
                                                        echo 'vandel-status-inactive';
                                                        break;
                                                    case 'confirmed':
                                                        echo 'vandel-status-active';
                                                        break;
                                                    case 'completed':
                                                        echo 'vandel-status-featured';
                                                        break;
                                                    case 'canceled':
                                                        echo 'vandel-status-danger';
                                                        break;
                                                    default:
                                                        echo '';
                                                }
                                                ?>
                                            ">
                                                <?php echo esc_html(ucfirst($booking->status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo \VandelBooking\Helpers::formatPrice($booking->total_price); ?>
                                        </td>
                                        <td>
                                            <a href="<?php 
                                                echo esc_url(
                                                    admin_url(
                                                        'admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id
                                                    )
                                                ); 
                                            ?>" class="button button-small">
                                                <?php _e('View', 'vandel-booking'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No bookings found.', 'vandel-booking'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render clients tab content
     */
    private function renderClientsTab() {
        ?>
        <div id="clients" class="vandel-tab-content">
            <h2><?php _e('Clients', 'vandel-booking'); ?></h2>
            
            <!-- Client Search -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3 class="vandel-card-title"><?php _e('Search Clients', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="vandel-dashboard">
                        <input type="hidden" name="tab" value="clients">
                        
                        <div class="vandel-row">
                            <div class="vandel-col">
                                <label for="client_search"><?php _e('Search', 'vandel-booking'); ?></label>
                                <input type="text" name="s" id="client_search" 
                                       placeholder="<?php _e('Search by name or email', 'vandel-booking'); ?>" 
                                       class="regular-text">
                            </div>
                            
                            <div class="vandel-col">
                                <label>&nbsp;</label>
                                <button type="submit" class="button button-primary">
                                    <?php _e('Search', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Clients List -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3 class="vandel-card-title"><?php _e('Client List', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php
                    // Use your ClientModel
                    $client_model = new \VandelBooking\Client\ClientModel();
                    
                    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                    
                    $clients = empty($search_term) 
                        ? $client_model->getAll(['orderby' => 'name']) 
                        : $client_model->search($search_term);
                    
                    if (!empty($clients)):
                    ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'vandel-booking'); ?></th>
                                    <th><?php _e('Name', 'vandel-booking'); ?></th>
                                    <th><?php _e('Email', 'vandel-booking'); ?></th>
                                    <th><?php _e('Phone', 'vandel-booking'); ?></th>
                                    <th><?php _e('Total Spent', 'vandel-booking'); ?></th>
                                    <th><?php _e('Actions', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?php echo esc_html($client->id); ?></td>
                                        <td><?php echo esc_html($client->name); ?></td>
                                        <td><?php echo esc_html($client->email); ?></td>
                                        <td><?php echo esc_html($client->phone ?: 'N/A'); ?></td>
                                        <td><?php echo \VandelBooking\Helpers::formatPrice($client->total_spent); ?></td>
                                        <td>
                                            <a href="<?php 
                                                echo esc_url(
                                                    admin_url(
                                                        'admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id
                                                    )
                                                ); 
                                            ?>" class="button button-small">
                                                <?php _e('View', 'vandel-booking'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <?php if (empty($search_term)): ?>
                            <p><?php _e('No clients found.', 'vandel-booking'); ?></p>
                        <?php else: ?>
                            <p><?php _e('No clients matching your search criteria.', 'vandel-booking'); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Helper method to get a client object for display
     */
    private function getBookingClientDetails($client_id) {
        if (!class_exists('\\VandelBooking\\Client\\ClientModel')) {
            // Try to include it directly
            $file_path = VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Check alternative location
                $alt_path = VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-client-model.php';
                if (file_exists($alt_path)) {
                    require_once $alt_path;
                }
            }
        }
        
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $client_model = new \VandelBooking\Client\ClientModel();
            return $client_model->get($client_id);
        }
        
        // Return empty object if class isn't available
        return (object) ['name' => 'Unknown', 'email' => '', 'phone' => ''];
    }

}
