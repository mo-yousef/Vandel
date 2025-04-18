<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Settings Tab
 * Handles the settings tab functionality in the dashboard
 */
class Settings_Tab implements Tab_Interface {
    /**
     * Settings sections
     * @var array
     */
    private $sections = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->sections = $this->get_sections();
    }
    
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Process settings form submission
        if (isset($_POST['vandel_save_settings']) && isset($_POST['vandel_settings_nonce'])) {
            $this->save_settings();
        }
    }
    
    /**
     * Render tab content
     */
    public function render() {
        $current_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        if (!array_key_exists($current_section, $this->sections)) {
            $current_section = 'general';
        }
        ?>
        <div class="wrap vandel-settings-wrap">
            <h1 class="wp-heading-inline"><?php _e('Settings', 'vandel-booking'); ?></h1>
            
            <div class="vandel-settings-container">
                <div class="vandel-settings-sidebar">
                    <ul class="vandel-settings-nav">
                        <?php foreach ($this->sections as $section_id => $section_name): ?>
                            <li class="<?php echo $current_section === $section_id ? 'active' : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg(['section' => $section_id])); ?>">
                                    <?php echo esc_html($section_name); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="vandel-settings-content">
                    <?php
                    // Display settings saved message if applicable
                    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved.', 'vandel-booking') . '</p></div>';
                    }
                    
                    // Render current section
                    $this->render_section($current_section);
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get available settings sections
     * 
     * @return array Section ID => Name mapping
     */
    public function get_sections() {
        $sections = [
            'general' => __('General', 'vandel-booking'),
            'booking' => __('Booking', 'vandel-booking'),
            'notification' => __('Notifications', 'vandel-booking'),
            'integration' => __('Integrations', 'vandel-booking'),
            'zip-codes' => __('ZIP Codes', 'vandel-booking'),
            'locations' => __('Locations', 'vandel-booking')
        ];
        
        return apply_filters('vandel_settings_sections', $sections);
    }
    
    /**
     * Render settings section content
     * 
     * @param string $section Current section
     */
    public function render_section($section) {
        switch ($section) {
            case 'general':
                $this->render_general_settings();
                break;
                
            case 'booking':
                $this->render_booking_settings();
                break;
                
            case 'notification':
                $this->render_notification_settings();
                break;
                
            case 'integration':
                $this->render_integration_settings();
                break;
                
            case 'zip-codes':
                $this->render_zip_code_settings();
                break;
                
            case 'locations':
                // Check if our location settings class exists, then use it to render the UI
                if (class_exists('\\VandelBooking\\Admin\\LocationSettingsTab')) {
                    $location_settings = new \VandelBooking\Admin\LocationSettingsTab();
                    $location_settings->renderSettingsSection();
                } else {
                    echo '<div class="notice notice-warning"><p>' . __('Location management is not available.', 'vandel-booking') . '</p></div>';
                }
                break;
                
            default:
                $this->render_general_settings();
                break;
        }
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General Settings
        register_setting('vandel_general_settings', 'vandel_business_name');
        register_setting('vandel_general_settings', 'vandel_primary_color');
        register_setting('vandel_general_settings', 'vandel_default_timezone');
        register_setting('vandel_general_settings', 'vandel_date_format');
        register_setting('vandel_general_settings', 'vandel_time_format');
        register_setting('vandel_general_settings', 'vandel_currency');
        register_setting('vandel_general_settings', 'vandel_language');
        
        // Booking Settings
        register_setting('vandel_booking_settings', 'vandel_min_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_max_advance_booking');
        register_setting('vandel_booking_settings', 'vandel_booking_cancellation_window');
        register_setting('vandel_booking_settings', 'vandel_default_booking_status');
        register_setting('vandel_booking_settings', 'vandel_enable_multiple_bookings');
        register_setting('vandel_booking_settings', 'vandel_booking_slots_interval');
        
        // Notification Settings
        register_setting('vandel_notification_settings', 'vandel_enable_email_notifications');
        register_setting('vandel_notification_settings', 'vandel_email_sender_name');
        register_setting('vandel_notification_settings', 'vandel_email_sender_address');
        register_setting('vandel_notification_settings', 'vandel_notification_email');
        register_setting('vandel_notification_settings', 'vandel_email_subject');
        register_setting('vandel_notification_settings', 'vandel_email_message');
        
        // Integration Settings
        register_setting('vandel_integration_settings', 'vandel_enable_paypal');
        register_setting('vandel_integration_settings', 'vandel_enable_stripe');
        register_setting('vandel_integration_settings', 'vandel_enable_gcal');
        register_setting('vandel_integration_settings', 'vandel_enable_zip_code_feature');
        
        // Apply filters for additional settings
        do_action('vandel_register_settings');
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['vandel_settings_nonce'], 'save_vandel_settings')) {
            add_settings_error(
                'vandel_settings',
                'nonce_error',
                __('Security check failed.', 'vandel-booking'),
                'error'
            );
            return;
        }
        
        // Get current section
        $current_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        // Save settings based on section
        switch ($current_section) {
            case 'general':
                $this->save_general_settings();
                break;
                
            case 'booking':
                $this->save_booking_settings();
                break;
                
            case 'notification':
                $this->save_notification_settings();
                break;
                
            case 'integration':
                $this->save_integration_settings();
                break;
                
            default:
                // Allow other sections to save their settings
                do_action('vandel_save_settings_' . $current_section);
                break;
        }
        
        // Redirect to show success message
        wp_redirect(add_query_arg(['settings-updated' => 'true']));
        exit;
    }
    
    /**
     * Save general settings
     */
    private function save_general_settings() {
        $fields = [
            'vandel_business_name',
            'vandel_primary_color',
            'vandel_default_timezone',
            'vandel_date_format',
            'vandel_time_format',
            'vandel_currency',
            'vandel_language'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Save booking settings
     */
    private function save_booking_settings() {
        // Numeric fields
        $numeric_fields = [
            'vandel_min_advance_booking',
            'vandel_max_advance_booking',
            'vandel_booking_cancellation_window',
            'vandel_booking_slots_interval'
        ];
        
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, intval($_POST[$field]));
            }
        }
        
        // Select fields
        $select_fields = [
            'vandel_default_booking_status'
        ];
        
        foreach ($select_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_key($_POST[$field]));
            }
        }
        
        // Checkbox fields
        $checkbox_fields = [
            'vandel_enable_multiple_bookings'
        ];
        
        foreach ($checkbox_fields as $field) {
            update_option($field, isset($_POST[$field]) ? 'yes' : 'no');
        }
    }
    
    /**
     * Save notification settings
     */
    private function save_notification_settings() {
        // Text fields
        $text_fields = [
            'vandel_email_sender_name',
            'vandel_email_sender_address',
            'vandel_notification_email',
            'vandel_email_subject'
        ];
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Textarea fields
        $textarea_fields = [
            'vandel_email_message'
        ];
        
        foreach ($textarea_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, wp_kses_post($_POST[$field]));
            }
        }
        
        // Checkbox fields
        $checkbox_fields = [
            'vandel_enable_email_notifications'
        ];
        
        foreach ($checkbox_fields as $field) {
            update_option($field, isset($_POST[$field]) ? 'yes' : 'no');
        }
    }
    
    /**
     * Save integration settings
     */
    private function save_integration_settings() {
        // Checkbox fields
        $checkbox_fields = [
            'vandel_enable_paypal',
            'vandel_enable_stripe',
            'vandel_enable_gcal',
            'vandel_enable_zip_code_feature'
        ];
        
        foreach ($checkbox_fields as $field) {
            update_option($field, isset($_POST[$field]) ? 'yes' : 'no');
        }
        
        // API Keys and other fields can be added here
    }
    
    /**
     * Render general settings form
     */
    private function render_general_settings() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('save_vandel_settings', 'vandel_settings_nonce'); ?>
            
            <div class="vandel-settings-section">
                <h2 class="vandel-settings-title"><?php _e('General Settings', 'vandel-booking'); ?></h2>
                <p class="vandel-settings-description"><?php _e('Basic settings for your booking system.', 'vandel-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vandel_business_name"><?php _e('Business Name', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="vandel_business_name" id="vandel_business_name" 
                                   value="<?php echo esc_attr(get_option('vandel_business_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('The name of your business that will appear in emails and the booking form.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_primary_color"><?php _e('Primary Color', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="color" name="vandel_primary_color" id="vandel_primary_color" 
                                   value="<?php echo esc_attr(get_option('vandel_primary_color', '#3182ce')); ?>">
                            <p class="description"><?php _e('The primary color used in the booking form and emails.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_default_timezone"><?php _e('Default Timezone', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_default_timezone" id="vandel_default_timezone" class="regular-text">
                                <?php
                                $current_timezone = get_option('vandel_default_timezone', wp_timezone_string());
                                $timezone_identifiers = DateTimeZone::listIdentifiers();
                                
                                foreach ($timezone_identifiers as $timezone) {
                                    echo '<option value="' . esc_attr($timezone) . '" ' . selected($current_timezone, $timezone, false) . '>' . esc_html($timezone) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The default timezone for the booking system.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_date_format"><?php _e('Date Format', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_date_format" id="vandel_date_format">
                                <?php
                                $date_formats = [
                                    'F j, Y' => date_i18n('F j, Y'),
                                    'Y-m-d' => date_i18n('Y-m-d'),
                                    'm/d/Y' => date_i18n('m/d/Y'),
                                    'd/m/Y' => date_i18n('d/m/Y')
                                ];
                                
                                $current_format = get_option('vandel_date_format', get_option('date_format'));
                                
                                foreach ($date_formats as $format => $display) {
                                    echo '<option value="' . esc_attr($format) . '" ' . selected($current_format, $format, false) . '>' . esc_html($display) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The format to display dates.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_time_format"><?php _e('Time Format', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_time_format" id="vandel_time_format">
                                <?php
                                $time_formats = [
                                    'g:i a' => date_i18n('g:i a'),
                                    'g:i A' => date_i18n('g:i A'),
                                    'H:i' => date_i18n('H:i')
                                ];
                                
                                $current_format = get_option('vandel_time_format', get_option('time_format'));
                                
                                foreach ($time_formats as $format => $display) {
                                    echo '<option value="' . esc_attr($format) . '" ' . selected($current_format, $format, false) . '>' . esc_html($display) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The format to display times.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_currency"><?php _e('Currency', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_currency" id="vandel_currency">
                                <?php
                                $currencies = [
                                    'USD' => __('US Dollar ($)', 'vandel-booking'),
                                    'EUR' => __('Euro (€)', 'vandel-booking'),
                                    'GBP' => __('British Pound (£)', 'vandel-booking'),
                                    'SEK' => __('Swedish Krona (kr)', 'vandel-booking'),
                                    'CAD' => __('Canadian Dollar (C$)', 'vandel-booking'),
                                    'AUD' => __('Australian Dollar (A$)', 'vandel-booking')
                                ];
                                
                                $current_currency = get_option('vandel_currency', 'USD');
                                
                                foreach ($currencies as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '" ' . selected($current_currency, $code, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The currency used for prices and payments.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_language"><?php _e('Language', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_language" id="vandel_language">
                                <?php
                                $languages = [
                                    'en_US' => __('English (United States)', 'vandel-booking'),
                                    'sv_SE' => __('Swedish', 'vandel-booking'),
                                    'fr_FR' => __('French', 'vandel-booking'),
                                    'de_DE' => __('German', 'vandel-booking'),
                                    'es_ES' => __('Spanish', 'vandel-booking')
                                ];
                                
                                $current_language = get_option('vandel_language', get_locale());
                                
                                foreach ($languages as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '" ' . selected($current_language, $code, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The language for the booking form and emails.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="vandel_save_settings" class="button-primary" value="<?php esc_attr_e('Save Settings', 'vandel-booking'); ?>">
                </p>
            </div>
        </form>
        <?php
    }
    
    /**
     * Render booking settings form
     */
    private function render_booking_settings() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('save_vandel_settings', 'vandel_settings_nonce'); ?>
            
            <div class="vandel-settings-section">
                <h2 class="vandel-settings-title"><?php _e('Booking Settings', 'vandel-booking'); ?></h2>
                <p class="vandel-settings-description"><?php _e('Configure the behavior of the booking system.', 'vandel-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vandel_min_advance_booking"><?php _e('Minimum Advance Booking', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="vandel_min_advance_booking" id="vandel_min_advance_booking" 
                                   value="<?php echo esc_attr(get_option('vandel_min_advance_booking', 1)); ?>" 
                                   class="small-text" min="0">
                            <span class="description"><?php _e('hours', 'vandel-booking'); ?></span>
                            <p class="description"><?php _e('Minimum time before a booking can be made (in hours). Set to 0 for no minimum.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_max_advance_booking"><?php _e('Maximum Advance Booking', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="vandel_max_advance_booking" id="vandel_max_advance_booking" 
                                   value="<?php echo esc_attr(get_option('vandel_max_advance_booking', 90)); ?>" 
                                   class="small-text" min="1">
                            <span class="description"><?php _e('days', 'vandel-booking'); ?></span>
                            <p class="description"><?php _e('Maximum time in advance a booking can be made (in days).', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_booking_cancellation_window"><?php _e('Cancellation Window', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="vandel_booking_cancellation_window" id="vandel_booking_cancellation_window" 
                                   value="<?php echo esc_attr(get_option('vandel_booking_cancellation_window', 24)); ?>" 
                                   class="small-text" min="0">
                            <span class="description"><?php _e('hours', 'vandel-booking'); ?></span>
                            <p class="description"><?php _e('Time before a booking when it can no longer be canceled (in hours). Set to 0 to allow cancellations anytime.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_default_booking_status"><?php _e('Default Booking Status', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select name="vandel_default_booking_status" id="vandel_default_booking_status">
                                <?php
                                $statuses = [
                                    'pending' => __('Pending', 'vandel-booking'),
                                    'confirmed' => __('Confirmed', 'vandel-booking')
                                ];
                                
                                $current_status = get_option('vandel_default_booking_status', 'pending');
                                
                                foreach ($statuses as $status => $name) {
                                    echo '<option value="' . esc_attr($status) . '" ' . selected($current_status, $status, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('The default status for new bookings.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_enable_multiple_bookings"><?php _e('Allow Multiple Bookings', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <label for="vandel_enable_multiple_bookings">
                                <input type="checkbox" name="vandel_enable_multiple_bookings" id="vandel_enable_multiple_bookings" 
                                       value="yes" <?php checked(get_option('vandel_enable_multiple_bookings', 'no'), 'yes'); ?>>
                                <?php _e('Allow multiple bookings for the same time slot', 'vandel-booking'); ?>
                            </label>
                            <p class="description"><?php _e('If enabled, multiple bookings can be made for the same time slot.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="vandel_booking_slots_interval"><?php _e('Time Slot Interval', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="vandel_booking_slots_interval" id="vandel_booking_slots_interval" 
                                   value="<?php echo esc_attr(get_option('vandel_booking_slots_interval', 30)); ?>" 
                                   class="small-text" min="5" step="5">
                            <span class="description"><?php _e('minutes', 'vandel-booking'); ?></span>
                            <p class="description"><?php _e('The interval between available time slots (in minutes).', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="vandel_save_settings" class="button-primary" value="<?php esc_attr_e('Save Settings', 'vandel-booking'); ?>">
                </p>
            </div>
        </form>
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
                <p><?php _e('Configure how you and your customers receive notifications about bookings, including email templates and settings.', 'vandel-booking'); ?>
                </p>
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
                                <input type="checkbox" name="vandel_enable_email_notifications" value="yes"
                                    <?php checked(get_option('vandel_enable_email_notifications', 'yes'), 'yes'); ?>
                                    class="vandel-switch-input">
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
                            <input type="text" id="vandel_email_sender_name" name="vandel_email_sender_name"
                                value="<?php echo esc_attr(get_option('vandel_email_sender_name', get_bloginfo('name'))); ?>"
                                class="regular-text">
                            <p class="description">
                                <?php _e('Name that appears in the "From" field of notification emails', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_email_sender_address">
                                <?php _e('Sender Email Address', 'vandel-booking'); ?>
                            </label>
                            <input type="email" id="vandel_email_sender_address" name="vandel_email_sender_address"
                                value="<?php echo esc_attr(get_option('vandel_email_sender_address', get_option('admin_email'))); ?>"
                                class="regular-text">
                            <p class="description">
                                <?php _e('Email address that appears in the "From" field of notification emails', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_notification_email">
                                <?php _e('Admin Notification Email', 'vandel-booking'); ?>
                            </label>
                            <input type="email" id="vandel_notification_email" name="vandel_notification_email"
                                value="<?php echo esc_attr(get_option('vandel_notification_email', get_option('admin_email'))); ?>"
                                class="regular-text">
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
                            <input type="text" id="vandel_email_subject" name="vandel_email_subject"
                                value="<?php echo esc_attr(get_option('vandel_email_subject', __('Your Booking Confirmation - {booking_id}', 'vandel-booking'))); ?>"
                                class="regular-text">
                            <p class="description">
                                <?php _e('Subject line for customer confirmation emails. You can use {booking_id} as a placeholder.', 'vandel-booking'); ?>
                            </p>
                        </div>

                        <div class="vandel-setting-row">
                            <label for="vandel_email_message">
                                <?php _e('Email Message Template', 'vandel-booking'); ?>
                            </label>
                            <textarea id="vandel_email_message" name="vandel_email_message" rows="8"
                                class="widefat code"><?php echo esc_textarea(get_option('vandel_email_message', __("Dear {customer_name},\n\nThank you for your booking. Your booking details are as follows:\n\nService: {service_name}\nDate: {booking_date}\nBooking Reference: {booking_id}\n\nIf you need to make any changes to your booking, please contact us.\n\nWe look forward to serving you!\n\nRegards,\n{site_name} Team", 'vandel-booking'))); ?></textarea>
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
                                <input type="checkbox" name="vandel_sms_notifications" value="yes"
                                    <?php checked(get_option('vandel_sms_notifications', 'no'), 'yes'); ?> disabled
                                    class="vandel-switch-input">
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
                <p><?php _e('Connect your booking system with popular services and apps for payments, marketing, and calendars.', 'vandel-booking'); ?>
                </p>
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
                                <input type="checkbox" name="vandel_enable_zip_code_feature" value="yes"
                                    <?php checked(get_option('vandel_enable_zip_code_feature', 'no'), 'yes'); ?>
                                    class="vandel-switch-input">
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
                                <p><?php _e('With this feature enabled, you can set up service areas by ZIP code, adjust pricing for different locations, and restrict bookings to your service area.', 'vandel-booking'); ?>
                                </p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes')); ?>"
                                    class="button vandel-feature-btn">
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
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/paypal.svg" alt="PayPal"
                                            width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>PayPal</h4>
                                        <p><?php _e('Accept payments via PayPal', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input type="checkbox" name="vandel_enable_paypal" value="yes"
                                            <?php checked(get_option('vandel_enable_paypal', 'no'), 'yes'); ?> disabled>
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
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/stripe.svg" alt="Stripe"
                                            width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Stripe</h4>
                                        <p><?php _e('Accept credit card payments', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input type="checkbox" name="vandel_enable_stripe" value="yes"
                                            <?php checked(get_option('vandel_enable_stripe', 'no'), 'yes'); ?> disabled>
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
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/google-calendar.svg"
                                            alt="Google Calendar" width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Google Calendar</h4>
                                        <p><?php _e('Sync bookings with Google Calendar', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input type="checkbox" name="vandel_enable_gcal" value="yes"
                                            <?php checked(get_option('vandel_enable_gcal', 'no'), 'yes'); ?> disabled>
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
                                        <img src="<?php echo VANDEL_PLUGIN_URL; ?>assets/images/mailchimp.svg" alt="Mailchimp"
                                            width="80">
                                    </div>
                                    <div class="vandel-integration-info">
                                        <h4>Mailchimp</h4>
                                        <p><?php _e('Add clients to your email marketing lists', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-integration-action">
                                    <label class="vandel-switch-label">
                                        <input type="checkbox" name="vandel_enable_mailchimp" value="yes"
                                            <?php checked(get_option('vandel_enable_mailchimp', 'no'), 'yes'); ?> disabled>
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
                <p><?php _e('Manage the areas you service with ZIP code-based pricing. Set up service areas, adjust pricing, and manage locations.', 'vandel-booking'); ?>
                </p>
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
                                        <label><?php _e('ZIP Code', 'vandel-booking'); ?> <span
                                                class="required">*</span></label>
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
                                            <span
                                                class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                            <input type="number" name="price_adjustment" step="0.01" min="-100" max="100"
                                                class="widefat" value="0">
                                        </div>
                                        <p class="description">
                                            <?php _e('Amount to add or subtract from base price for this area', 'vandel-booking'); ?>
                                        </p>
                                    </div>
                                    <div class="vandel-col">
                                        <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                        <div class="vandel-input-group">
                                            <span
                                                class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                            <input type="number" name="service_fee" step="0.01" min="0" class="widefat"
                                                value="0">
                                        </div>
                                        <p class="description">
                                            <?php _e('Additional fee for servicing this area', 'vandel-booking'); ?></p>
                                    </div>
                                </div>
                                <div class="vandel-toggle-controls">
                                    <div class="vandel-toggle-field">
                                        <label class="vandel-toggle">
                                            <input type="checkbox" name="is_serviceable" value="yes" checked>
                                            <span class="vandel-toggle-slider"></span>
                                        </label>
                                        <span
                                            class="vandel-toggle-label"><?php _e('Serviceable Area', 'vandel-booking'); ?></span>
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
                                            <input type="file" name="zip_codes_file" id="vandel-zip-codes-file"
                                                accept=".csv,.xlsx,.xls">
                                        </div>
                                        <div class="vandel-col">
                                            <button type="button" id="vandel-import-zip-codes" class="button button-secondary">
                                                <span class="dashicons dashicons-upload"></span>
                                                <?php _e('Import', 'vandel-booking'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <p class="description">
                                    <?php _e('CSV format: ZIP Code, City, State, Country, Price Adjustment, Service Fee, Serviceable (yes/no)', 'vandel-booking'); ?>
                                </p>
                            </div>
                            <div class="vandel-export-section">
                                <h4><?php _e('Export ZIP Codes', 'vandel-booking'); ?></h4>
                                <p><?php _e('Download all your service areas as a CSV file.', 'vandel-booking'); ?></p>
                                <button type="button" id="vandel-export-zip-codes" class="button button-secondary">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php _e('Export CSV', 'vandel-booking'); ?>
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
                        <input type="text" id="vandel-zip-search"
                            placeholder="<?php _e('Search ZIP codes...', 'vandel-booking'); ?>" class="regular-text">
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
     * Render location settings section
     */
    private function render_location_settings() {
        ?>
        <h2><?php _e('Location Settings', 'vandel-booking'); ?></h2>
        <p><?php _e('Configure location-based settings for your booking system.', 'vandel-booking'); ?></p>
        
        <form method="post" action="options.php">
            <?php settings_fields('vandel_location_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Location System', 'vandel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="vandel_enable_location_system" value="yes" <?php checked(get_option('vandel_enable_location_system', 'yes'), 'yes'); ?>>
                            <?php _e('Enable the hierarchical location system (Country > City > Area)', 'vandel-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Location Validation', 'vandel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="vandel_require_location_validation" value="yes" <?php checked(get_option('vandel_require_location_validation', 'yes'), 'yes'); ?>>
                            <?php _e('Require valid location for booking', 'vandel-booking'); ?>
                        </label>
                        <p class="description"><?php _e('If enabled, customers will need to enter a valid ZIP code that matches an area in your location database.', 'vandel-booking'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Location Management', 'vandel-booking'); ?></th>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=vandel-locations'); ?>" class="button">
                            <?php _e('Manage Locations', 'vandel-booking'); ?>
                        </a>
                        <p class="description"><?php _e('Add, edit, and manage your service locations.', 'vandel-booking'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render advanced settings section
     */
    private function render_advanced_settings() {
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Advanced Settings', 'vandel-booking'); ?></h2>
            <p><?php _e('Configure advanced options for the booking system.', 'vandel-booking'); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('vandel_advanced_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Debug Mode', 'vandel-booking'); ?></th>
                        <td>
                            <label for="vandel_enable_debug">
                                <input type="checkbox" id="vandel_enable_debug" name="vandel_enable_debug" value="yes" <?php checked(get_option('vandel_enable_debug'), 'yes'); ?>>
                                <?php _e('Enable debug logging', 'vandel-booking'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, debug information will be logged to the WordPress debug log.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache Settings', 'vandel-booking'); ?></th>
                        <td>
                            <label for="vandel_enable_cache">
                                <input type="checkbox" id="vandel_enable_cache" name="vandel_enable_cache" value="yes" <?php checked(get_option('vandel_enable_cache'), 'yes'); ?>>
                                <?php _e('Enable data caching', 'vandel-booking'); ?>
                            </label>
                            <p class="description"><?php _e('Cache service and booking data to improve performance.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache Expiration', 'vandel-booking'); ?></th>
                        <td>
                            <input type="number" name="vandel_cache_expiration" value="<?php echo esc_attr(get_option('vandel_cache_expiration', 3600)); ?>" min="60" step="60" class="small-text">
                            <?php _e('seconds', 'vandel-booking'); ?>
                            <p class="description"><?php _e('Time in seconds before cached data expires. Default is 1 hour (3600 seconds).', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Database Cleanup', 'vandel-booking'); ?></th>
                        <td>
                            <label for="vandel_auto_cleanup">
                                <input type="checkbox" id="vandel_auto_cleanup" name="vandel_auto_cleanup" value="yes" <?php checked(get_option('vandel_auto_cleanup'), 'yes'); ?>>
                                <?php _e('Enable automatic cleanup', 'vandel-booking'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically remove old, canceled bookings after a certain period.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cleanup After', 'vandel-booking'); ?></th>
                        <td>
                            <input type="number" name="vandel_cleanup_days" value="<?php echo esc_attr(get_option('vandel_cleanup_days', 90)); ?>" min="30" class="small-text">
                            <?php _e('days', 'vandel-booking'); ?>
                            <p class="description"><?php _e('Number of days after which old canceled bookings will be removed. Minimum 30 days.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Custom CSS', 'vandel-booking'); ?></th>
                        <td>
                            <textarea name="vandel_custom_css" rows="8" class="large-text code"><?php echo esc_textarea(get_option('vandel_custom_css', '')); ?></textarea>
                            <p class="description"><?php _e('Add custom CSS to style the booking form. This will be added to both admin and frontend.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Custom JavaScript', 'vandel-booking'); ?></th>
                        <td>
                            <textarea name="vandel_custom_js" rows="8" class="large-text code"><?php echo esc_textarea(get_option('vandel_custom_js', '')); ?></textarea>
                            <p class="description"><?php _e('Add custom JavaScript. This will be added to both admin and frontend.', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}