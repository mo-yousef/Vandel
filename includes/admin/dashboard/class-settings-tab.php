<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Settings Tab
 * Handles the settings management tab
 */
class Settings_Tab implements Tab_Interface {
    /**
     * Section classes
     *
     * @var array
     */
    private $section_classes = [];
    
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // No specific hooks for settings tab
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Process section-specific actions
        $this->initialize_section_classes();
        
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        if (isset($this->section_classes[$active_section]) && method_exists($this->section_classes[$active_section], 'process_actions')) {
            $this->section_classes[$active_section]->process_actions();
        }
    }
    
    /**
     * Initialize section classes
     */
    private function initialize_section_classes() {
        // Check if sections already initialized
        if (!empty($this->section_classes)) {
            return;
        }
        
        // Load section files
        $section_files = [
            'class-general-settings.php',
            'class-booking-settings.php',
            'class-notification-settings.php',
            'class-integration-settings.php',
            'class-zip-code-settings.php',
        ];
        
        foreach ($section_files as $file) {
            $file_path = VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/settings/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Initialize section classes if they exist
        $this->section_classes = [
            'general' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\General_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\General_Settings() : null,
                
            'booking' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Booking_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Booking_Settings() : null,
                
            'notifications' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Notification_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Notification_Settings() : null,
                
            'integrations' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Integration_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Integration_Settings() : null,
                
            'zip-codes' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\ZipCode_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\ZipCode_Settings() : null,
        ];
    }
    
    /**
     * Render tab content
     */
    public function render() {
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        ?>
        <div id="settings" class="vandel-tab-content">
            <div class="vandel-settings-container">
                <!-- Settings Navigation -->
                <?php $this->render_settings_navigation($active_section); ?>

                <!-- Settings Content -->
                <div class="vandel-settings-content">
                    <?php
                    // Initialize section classes if not already done
                    $this->initialize_section_classes();
                    
                    // Render the active section
                    if (isset($this->section_classes[$active_section]) && $this->section_classes[$active_section]) {
                        $this->section_classes[$active_section]->render();
                    } else {
                        // Fallback to built-in section renderers
                        switch ($active_section) {
                            case 'general':
                                $this->render_general_settings();
                                break;
                            case 'booking':
                                $this->render_booking_settings();
                                break;
                            case 'notifications':
                                $this->render_notification_settings();
                                break;
                            case 'integrations':
                                $this->render_integration_settings();
                                break;
                            case 'zip-codes':
                                $this->render_zip_code_settings();
                                break;
                            default:
                                $this->render_general_settings();
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings navigation
     */
    private function render_settings_navigation($active_section) {
        ?>
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
        <?php
    }
    
    /**
     * Render General Settings
     */
    private function render_general_settings() {
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
                        <h3><?php _e('Brand Settings', 'vandel-booking'); ?></h3>
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
    private function render_booking_settings() {
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
    private function render_notification_settings() {
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
    private function render_integration_settings() {
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
     * Render ZIP Code settings
     */
    private function render_zip_code_settings() {
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
}