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
                                value="<?