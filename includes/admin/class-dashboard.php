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
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Register plugin settings
     */
    public function registerSettings() {
        // General Settings
        register_setting('vandel_general_settings', 'vandel_base_price');
        register_setting('vandel_general_settings', 'vandel_default_timezone');
        register_setting('vandel_general_settings', 'vandel_business_hours_start');
        register_setting('vandel_general_settings', 'vandel_business_hours_end');
        
        // Booking Settings
        register_setting('vandel_booking_settings', 'vandel_min_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_max_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_booking_cancellation_policy');
        
        // Notification Settings
        register_setting('vandel_notification_settings', 'vandel_enable_email_notifications');
        register_setting('vandel_notification_settings', 'vandel_notification_email');
        register_setting('vandel_notification_settings', 'vandel_sms_notifications');
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
                    <form method="post" action="options.php">
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
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render General Settings
     */
    private function renderGeneralSettings() {
        settings_fields('vandel_general_settings');
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('General Settings', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-group">
                <div class="vandel-setting-row">
                    <label for="vandel_base_price"><?php _e('Base Service Price', 'vandel-booking'); ?></label>
                    <div class="vandel-input-group">
                        <span class="vandel-currency-symbol"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
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
        </div>
        <?php
    }

    /**
     * Render Booking Settings
     */
    private function renderBookingSettings() {
        settings_fields('vandel_booking_settings');
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Booking Settings', 'vandel-booking'); ?></h2>
            
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
                    <label for="vandel_booking_cancellation_policy"><?php _e('Cancellation Policy', 'vandel-booking'); ?></label>
                    <textarea 
                        id="vandel_booking_cancellation_policy" 
                        name="vandel_booking_cancellation_policy" 
                        rows="4" 
                        class="widefat"
                    ><?php echo esc_textarea(get_option('vandel_booking_cancellation_policy', '')); ?></textarea>
                    <p class="description"><?php _e('Enter your booking cancellation policy details', 'vandel-booking'); ?></p>
                </div>
            </div>

            <?php submit_button(); ?>
        </div>
        <?php
    }

    /**
     * Render Notification Settings
     */
    private function renderNotificationSettings() {
        settings_fields('vandel_notification_settings');
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Notification Settings', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-group">
                <div class="vandel-setting-row">
                    <label>
                        <input 
                            type="checkbox" 
                            name="vandel_enable_email_notifications" 
                            value="1" 
                            <?php checked(get_option('vandel_enable_email_notifications', 1), 1); ?>
                        >
                        <?php _e('Enable Email Notifications', 'vandel-booking'); ?>
                    </label>
                    <p class="description"><?php _e('Send email notifications for bookings', 'vandel-booking'); ?></p>
                </div>

                <div class="vandel-setting-row">
                    <label for="vandel_notification_email"><?php _e('Notification Email', 'vandel-booking'); ?></label>
                    <input 
                        type="email" 
                        id="vandel_notification_email" 
                        name="vandel_notification_email" 
                        value="<?php echo esc_attr(get_option('vandel_notification_email', get_option('admin_email'))); ?>" 
                        class="widefat"
                    >
                    <p class="description"><?php _e('Email address to receive booking notifications', 'vandel-booking'); ?></p>
                </div>

                <div class="vandel-setting-row">
                    <label>
                        <input 
                            type="checkbox" 
                            name="vandel_sms_notifications" 
                            value="1" 
                            <?php checked(get_option('vandel_sms_notifications', 0), 1); ?>
                        >
                        <?php _e('Enable SMS Notifications', 'vandel-booking'); ?>
                    </label>
                    <p class="description"><?php _e('Send SMS notifications for bookings (requires additional setup)', 'vandel-booking'); ?></p>
                </div>
            </div>

            <?php submit_button(); ?>
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
            
            <div class="vandel-settings-group">
                <div class="vandel-setting-row">
                    <h3><?php _e('Payment Gateways', 'vandel-booking'); ?></h3>
                    <div class="vandel-integration-list">
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('PayPal', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
                        </div>
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('Stripe', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="vandel-setting-row">
                    <h3><?php _e('Calendar Sync', 'vandel-booking'); ?></h3>
                    <div class="vandel-integration-list">
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('Google Calendar', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
                        </div>
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('Outlook Calendar', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <div class="vandel-setting-row">
                    <h3><?php _e('Marketing & CRM', 'vandel-booking'); ?></h3>
                    <div class="vandel-integration-list">
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('MailChimp', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
                        </div>
                        <div class="vandel-integration-item">
                            <label>
                                <input type="checkbox" disabled>
                                <?php _e('HubSpot', 'vandel-booking'); ?>
                            </label>
                            <span class="vandel-badge vandel-badge-warning"><?php _e('Coming Soon', 'vandel-booking'); ?></span>
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
        </div>
        <?php
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
}