<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Settings Tab
 * Handles plugin settings interface
 */
class Settings_Tab implements Tab_Interface {
    /**
     * @var array Settings sections
     */
    private $sections = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_sections();
    }
    
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_settings_actions']);
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Start output buffering to prevent "headers already sent" errors
        ob_start();
        
        // Handle settings form submissions
        if (isset($_POST['vandel_save_settings']) && isset($_POST['_wpnonce'])) {
            $this->save_settings();
        }
        
        // Handle ZIP code form submissions
        if (isset($_POST['vandel_add_zip_code']) && isset($_POST['_wpnonce'])) {
            $this->add_zip_code();
        }

        // Handle location form submissions
        if (isset($_POST['vandel_add_location']) && isset($_POST['_wpnonce'])) {
            $this->add_location();
        }
    }

    
    /**
     * Initialize settings sections
     */
    private function initialize_sections() {
        $this->sections = [
            'general' => __('General Settings', 'vandel-booking'),
            'booking' => __('Booking Settings', 'vandel-booking'),
            'notifications' => __('Notifications', 'vandel-booking'),
            'integrations' => __('Integrations', 'vandel-booking'),
            'zip-codes' => __('ZIP Codes', 'vandel-booking'),
            'locations' => __('Locations', 'vandel-booking')  // Make sure this is included
        ];
    }

    
    /**
     * Register plugin settings
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
        register_setting('vandel_integration_settings', 'vandel_enable_location_system');
    }
    
    /**
     * Handle settings-related actions
     */
    public function handle_settings_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || !isset($_GET['tab']) || $_GET['tab'] !== 'settings') {
            return;
        }
        
        // Handle ZIP code deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete_zip_code' && isset($_GET['zip_code'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_zip_code_' . $_GET['zip_code'])) {
                wp_die(__('Security check failed', 'vandel-booking'));
                return;
            }
            
            $this->delete_zip_code($_GET['zip_code']);
            wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes&message=zip_deleted'));
            exit;
        }
        
        // Handle location deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete_location' && isset($_GET['location_id'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_location_' . $_GET['location_id'])) {
                wp_die(__('Security check failed', 'vandel-booking'));
                return;
            }
            
            $this->delete_location($_GET['location_id']);
            wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=locations&message=location_deleted'));
            exit;
        }
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        check_admin_referer('vandel_save_settings');
        
        // Process settings based on section
        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        switch ($section) {
            case 'general':
                // Update general settings
                if (isset($_POST['vandel_business_name'])) {
                    update_option('vandel_business_name', sanitize_text_field($_POST['vandel_business_name']));
                }
                if (isset($_POST['vandel_primary_color'])) {
                    update_option('vandel_primary_color', sanitize_hex_color($_POST['vandel_primary_color']));
                }
                // Add other general settings here
                break;
                
            case 'booking':
                // Update booking settings
                if (isset($_POST['vandel_min_advance_booking'])) {
                    update_option('vandel_min_advance_booking', intval($_POST['vandel_min_advance_booking']));
                }
                if (isset($_POST['vandel_max_advance_booking'])) {
                    update_option('vandel_max_advance_booking', intval($_POST['vandel_max_advance_booking']));
                }
                // Add other booking settings here
                break;
                
            case 'notifications':
                // Update notification settings
                $enable_email = isset($_POST['vandel_enable_email_notifications']) ? 'yes' : 'no';
                update_option('vandel_enable_email_notifications', $enable_email);
                
                if (isset($_POST['vandel_email_sender_name'])) {
                    update_option('vandel_email_sender_name', sanitize_text_field($_POST['vandel_email_sender_name']));
                }
                // Add other notification settings here
                break;
                
            case 'integrations':
                // Update integration settings
                $enable_zip_code = isset($_POST['vandel_enable_zip_code_feature']) ? 'yes' : 'no';
                update_option('vandel_enable_zip_code_feature', $enable_zip_code);
                
                $enable_location = isset($_POST['vandel_enable_location_system']) ? 'yes' : 'no';
                update_option('vandel_enable_location_system', $enable_location);
                
                // Add other integration settings here
                break;
        }
        
        // Clean output buffer before redirect
        ob_clean();
        
        // Redirect back to settings page with success message
        wp_redirect(add_query_arg(['message' => 'settings_saved'], wp_get_referer()));
        exit;
    }


    /**
     * Add ZIP code
     */
    private function add_zip_code() {
        check_admin_referer('vandel_zip_code_actions');
        
        // Validate and sanitize inputs
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $price_adjustment = isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0;
        $service_fee = isset($_POST['service_fee']) ? floatval($_POST['service_fee']) : 0;
        $is_serviceable = isset($_POST['is_serviceable']) ? 'yes' : 'no';
        
        if (empty($zip_code) || empty($city) || empty($country)) {
            wp_redirect(add_query_arg(['message' => 'zip_fields_required'], wp_get_referer()));
            exit;
        }
        
        // Check if ZIP code already exists
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
            $existing = $zip_code_model->get($zip_code);
            
            if ($existing) {
                wp_redirect(add_query_arg(['message' => 'zip_exists'], wp_get_referer()));
                exit;
            }
            
            // Add ZIP code
            $result = $zip_code_model->add([
                'zip_code' => $zip_code,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'price_adjustment' => $price_adjustment,
                'service_fee' => $service_fee,
                'is_serviceable' => $is_serviceable
            ]);
            
            if ($result) {
                wp_redirect(add_query_arg(['message' => 'zip_added'], wp_get_referer()));
                exit;
            } else {
                wp_redirect(add_query_arg(['message' => 'zip_error'], wp_get_referer()));
                exit;
            }
        } else {
            wp_redirect(add_query_arg(['message' => 'zip_model_missing'], wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Delete ZIP code
     */
    private function delete_zip_code($zip_code) {
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
            return $zip_code_model->delete($zip_code);
        }
        return false;
    }
    
    /**
     * Add location
     */
    private function add_location() {
        check_admin_referer('vandel_location_actions');
        
        // Validate and sanitize inputs
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $area_name = isset($_POST['area_name']) ? sanitize_text_field($_POST['area_name']) : '';
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        $price_adjustment = isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0;
        $service_fee = isset($_POST['service_fee']) ? floatval($_POST['service_fee']) : 0;
        $is_active = isset($_POST['is_active']) ? 'yes' : 'no';
        
        if (empty($country) || empty($city) || empty($area_name) || empty($zip_code)) {
            wp_redirect(add_query_arg(['message' => 'location_fields_required'], wp_get_referer()));
            exit;
        }
        
        // Check if location model exists
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $location_model = new \VandelBooking\Location\LocationModel();
            
            // Add location
            $result = $location_model->add([
                'country' => $country,
                'city' => $city,
                'area_name' => $area_name,
                'zip_code' => $zip_code,
                'price_adjustment' => $price_adjustment,
                'service_fee' => $service_fee,
                'is_active' => $is_active
            ]);
            
            if ($result) {
                wp_redirect(add_query_arg(['message' => 'location_added'], wp_get_referer()));
                exit;
            } else {
                wp_redirect(add_query_arg(['message' => 'location_error'], wp_get_referer()));
                exit;
            }
        } else {
            wp_redirect(add_query_arg(['message' => 'location_model_missing'], wp_get_referer()));
            exit;
        }
    }
    
    /**
     * Delete location
     */
    private function delete_location($location_id) {
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $location_model = new \VandelBooking\Location\LocationModel();
            return $location_model->delete($location_id);
        }
        return false;
    }
    
        // In includes/admin/dashboard/class-settings-tab.php
        // Inside the Settings_Tab class

        /**
         * Render tab content
         */
        public function render() {
            // Get current section
            $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
            
            // Display status messages
            $this->display_status_messages();
            
            ?>
            <div class="vandel-settings-wrap">
                <div class="vandel-settings-header">
                    <h2><?php _e('Settings', 'vandel-booking'); ?></h2>
                </div>
                
                <div class="vandel-settings-container">
                    <div class="vandel-settings-nav">
                        <ul class="vandel-settings-nav-items">
                            <?php foreach ($this->sections as $section_id => $section_title): ?>
                                <li class="vandel-settings-nav-item <?php echo $section === $section_id ? 'active' : ''; ?>">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=' . $section_id)); ?>">
                                        <?php echo esc_html($section_title); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="vandel-settings-content">
                        <?php
                        // Load section content
                        switch ($section) {
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
                            case 'locations':
                                $this->render_location_settings();
                                break;
                            default:
                                $this->render_general_settings();
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php
        }

/**
 * Render location settings
 */
private function render_location_settings() {
    // Check if LocationService class exists
    if (class_exists('\\VandelBooking\\Location\\LocationService')) {
        // Initialize LocationService
        $location_service = new \VandelBooking\Location\LocationService();
        
        // Check if render_admin_page method exists
        if (method_exists($location_service, 'render_admin_page')) {
            // Call the render method from LocationService
            $location_service->render_admin_page();
            return;
        }
    }
    
    // Fallback if LocationService is not available
    ?>
    <div class="vandel-settings-section">
        <h3><?php _e('Location Management', 'vandel-booking'); ?></h3>
        
        <div class="notice notice-warning">
            <p><?php _e('The Location Management System is not available. Please make sure the LocationService class is properly installed.', 'vandel-booking'); ?></p>
        </div>
        
        <p><?php _e('The Location Management feature allows you to manage service areas by country, city, and ZIP code.', 'vandel-booking'); ?></p>
        
        <p><?php _e('Benefits include:', 'vandel-booking'); ?></p>
        <ul>
            <li><?php _e('Geographically organize your service areas', 'vandel-booking'); ?></li>
            <li><?php _e('Set different pricing for different locations', 'vandel-booking'); ?></li>
            <li><?php _e('Control which areas you service', 'vandel-booking'); ?></li>
            <li><?php _e('Import ZIP codes for quick setup', 'vandel-booking'); ?></li>
        </ul>
    </div>
    <?php
}
    
    /**
     * Display status messages
     */
    private function display_status_messages() {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message_type = 'success';
        $message = '';
        
        switch ($_GET['message']) {
            case 'settings_saved':
                $message = __('Settings saved successfully.', 'vandel-booking');
                break;
            case 'zip_added':
                $message = __('ZIP code added successfully.', 'vandel-booking');
                break;
            case 'zip_deleted':
                $message = __('ZIP code deleted successfully.', 'vandel-booking');
                break;
            case 'zip_exists':
                $message = __('ZIP code already exists.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'zip_fields_required':
                $message = __('Please fill all required fields for ZIP code.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'zip_model_missing':
                $message = __('ZIP code model is not available.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'zip_error':
                $message = __('Failed to add ZIP code.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'location_added':
                $message = __('Location added successfully.', 'vandel-booking');
                break;
            case 'location_deleted':
                $message = __('Location deleted successfully.', 'vandel-booking');
                break;
            case 'location_fields_required':
                $message = __('Please fill all required fields for location.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'location_model_missing':
                $message = __('Location model is not available.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'location_error':
                $message = __('Failed to add location.', 'vandel-booking');
                $message_type = 'error';
                break;
        }
        
        if (!empty($message)) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message_type),
                esc_html($message)
            );
        }
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        ?>
        <div class="vandel-settings-section">
            <h3><?php _e('General Settings', 'vandel-booking'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('vandel_save_settings'); ?>
                <input type="hidden" name="vandel_save_settings" value="1">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vandel_business_name"><?php _e('Business Name', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="vandel_business_name" name="vandel_business_name" 
                                   value="<?php echo esc_attr(get_option('vandel_business_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_primary_color"><?php _e('Primary Color', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="vandel_primary_color" name="vandel_primary_color" 
                                   value="<?php echo esc_attr(get_option('vandel_primary_color', '#286cd6')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_base_price"><?php _e('Base Price', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="vandel_base_price" name="vandel_base_price" 
                                   value="<?php echo esc_attr(get_option('vandel_base_price', '0')); ?>" 
                                   step="0.01" min="0" class="small-text">
                            <p class="description"><?php _e('Default base price for services', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_default_timezone"><?php _e('Default Timezone', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select id="vandel_default_timezone" name="vandel_default_timezone" class="regular-text">
                                <?php
                                $current_timezone = get_option('vandel_default_timezone', wp_timezone_string());
                                $timezones = timezone_identifiers_list();
                                
                                foreach ($timezones as $timezone) {
                                    echo '<option value="' . esc_attr($timezone) . '"' . selected($current_timezone, $timezone, false) . '>' . esc_html($timezone) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_business_hours_start"><?php _e('Business Hours Start', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="time" id="vandel_business_hours_start" name="vandel_business_hours_start" 
                                   value="<?php echo esc_attr(get_option('vandel_business_hours_start', '09:00')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_business_hours_end"><?php _e('Business Hours End', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="time" id="vandel_business_hours_end" name="vandel_business_hours_end" 
                                   value="<?php echo esc_attr(get_option('vandel_business_hours_end', '17:00')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_currency"><?php _e('Currency', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select id="vandel_currency" name="vandel_currency">
                                <?php
                                $current_currency = get_option('vandel_currency', 'USD');
                                $currencies = [
                                    'USD' => __('US Dollar ($)', 'vandel-booking'),
                                    'EUR' => __('Euro (€)', 'vandel-booking'),
                                    'GBP' => __('British Pound (£)', 'vandel-booking'),
                                    'SEK' => __('Swedish Krona (kr)', 'vandel-booking'),
                                    'NOK' => __('Norwegian Krone (kr)', 'vandel-booking'),
                                    'DKK' => __('Danish Krone (kr)', 'vandel-booking')
                                ];
                                
                                foreach ($currencies as $code => $name) {
                                    echo '<option value="' . esc_attr($code) . '"' . selected($current_currency, $code, false) . '>' . esc_html($name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'vandel-booking'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render booking settings
     */
    private function render_booking_settings() {
        ?>
        <div class="vandel-settings-section">
            <h3><?php _e('Booking Settings', 'vandel-booking'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('vandel_save_settings'); ?>
                <input type="hidden" name="vandel_save_settings" value="1">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vandel_min_advance_booking"><?php _e('Minimum Advance Booking', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="vandel_min_advance_booking" name="vandel_min_advance_booking" 
                                   value="<?php echo esc_attr(get_option('vandel_min_advance_booking', '1')); ?>" 
                                   min="0" class="small-text">
                            <p class="description"><?php _e('Minimum hours in advance to book (0 = no limit)', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_max_advance_booking"><?php _e('Maximum Advance Booking', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="vandel_max_advance_booking" name="vandel_max_advance_booking" 
                                   value="<?php echo esc_attr(get_option('vandel_max_advance_booking', '90')); ?>" 
                                   min="1" class="small-text">
                            <p class="description"><?php _e('Maximum days in advance to allow booking', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_booking_cancellation_window"><?php _e('Cancellation Window', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="vandel_booking_cancellation_window" name="vandel_booking_cancellation_window" 
                                   value="<?php echo esc_attr(get_option('vandel_booking_cancellation_window', '24')); ?>" 
                                   min="0" class="small-text">
                            <p class="description"><?php _e('Hours before booking when cancellation is allowed (0 = anytime)', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_booking_cancellation_policy"><?php _e('Cancellation Policy', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <textarea id="vandel_booking_cancellation_policy" name="vandel_booking_cancellation_policy" 
                                      rows="5" class="large-text"><?php echo esc_textarea(get_option('vandel_booking_cancellation_policy', '')); ?></textarea>
                            <p class="description"><?php _e('Your cancellation policy text', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_default_booking_status"><?php _e('Default Booking Status', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select id="vandel_default_booking_status" name="vandel_default_booking_status">
                                <?php
                                $current_status = get_option('vandel_default_booking_status', 'pending');
                                $statuses = [
                                    'pending' => __('Pending', 'vandel-booking'),
                                    'confirmed' => __('Confirmed', 'vandel-booking')
                                ];
                                
                                foreach ($statuses as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($current_status, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Multiple Bookings', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_multiple_bookings">
                                <input type="checkbox" id="vandel_enable_multiple_bookings" name="vandel_enable_multiple_bookings" 
                                       value="yes" <?php checked(get_option('vandel_enable_multiple_bookings', 'no'), 'yes'); ?>>
                                <?php _e('Allow multiple bookings for the same time slot', 'vandel-booking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vandel_booking_slots_interval"><?php _e('Booking Slots Interval', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <select id="vandel_booking_slots_interval" name="vandel_booking_slots_interval">
                                <?php
                                $current_interval = get_option('vandel_booking_slots_interval', '30');
                                $intervals = [
                                    '15' => __('15 minutes', 'vandel-booking'),
                                    '30' => __('30 minutes', 'vandel-booking'),
                                    '60' => __('1 hour', 'vandel-booking'),
                                    '120' => __('2 hours', 'vandel-booking')
                                ];
                                
                                foreach ($intervals as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '"' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
</select>
                            <p class="description"><?php _e('Time interval between available booking slots', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'vandel-booking'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render notification settings
     */
    private function render_notification_settings() {
        ?>
        <div class="vandel-settings-section">
            <h3><?php _e('Notification Settings', 'vandel-booking'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('vandel_save_settings'); ?>
                <input type="hidden" name="vandel_save_settings" value="1">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Email Notifications', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_email_notifications">
                                <input type="checkbox" id="vandel_enable_email_notifications" name="vandel_enable_email_notifications" 
                                       value="yes" <?php checked(get_option('vandel_enable_email_notifications', 'yes'), 'yes'); ?>>
                                <?php _e('Enable email notifications', 'vandel-booking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="vandel-email-fields">
                        <th scope="row">
                            <label for="vandel_email_sender_name"><?php _e('Sender Name', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="vandel_email_sender_name" name="vandel_email_sender_name" 
                                   value="<?php echo esc_attr(get_option('vandel_email_sender_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr class="vandel-email-fields">
                        <th scope="row">
                            <label for="vandel_email_sender_address"><?php _e('Sender Email Address', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="vandel_email_sender_address" name="vandel_email_sender_address" 
                                   value="<?php echo esc_attr(get_option('vandel_email_sender_address', get_option('admin_email'))); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr class="vandel-email-fields">
                        <th scope="row">
                            <label for="vandel_notification_email"><?php _e('Admin Notification Email', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="vandel_notification_email" name="vandel_notification_email" 
                                   value="<?php echo esc_attr(get_option('vandel_notification_email', get_option('admin_email'))); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Email address to receive booking notifications', 'vandel-booking'); ?></p>
                        </td>
                    </tr>
                    <tr class="vandel-email-fields">
                        <th scope="row">
                            <label for="vandel_email_subject"><?php _e('Email Subject', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="vandel_email_subject" name="vandel_email_subject" 
                                   value="<?php echo esc_attr(get_option('vandel_email_subject', __('Booking Confirmation', 'vandel-booking'))); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr class="vandel-email-fields">
                        <th scope="row">
                            <label for="vandel_email_message"><?php _e('Email Message', 'vandel-booking'); ?></label>
                        </th>
                        <td>
                            <textarea id="vandel_email_message" name="vandel_email_message" 
                                      rows="6" class="large-text"><?php echo esc_textarea(get_option('vandel_email_message', __('Thank you for your booking. We look forward to serving you.', 'vandel-booking'))); ?></textarea>
                            <p class="description">
                                <?php _e('Available placeholders: {customer_name}, {service_name}, {booking_date}, {booking_id}, {total_price}', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('SMS Notifications', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_sms_notifications">
                                <input type="checkbox" id="vandel_sms_notifications" name="vandel_sms_notifications" 
                                       value="yes" <?php checked(get_option('vandel_sms_notifications', 'no'), 'yes'); ?>>
                                <?php _e('Enable SMS notifications (requires additional setup)', 'vandel-booking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'vandel-booking'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render integration settings
     */
    private function render_integration_settings() {
        ?>
        <div class="vandel-settings-section">
            <h3><?php _e('Integration Settings', 'vandel-booking'); ?></h3>
            
            <form method="post" action="">
                <?php wp_nonce_field('vandel_save_settings'); ?>
                <input type="hidden" name="vandel_save_settings" value="1">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php _e('Location Management', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_location_system">
                                <input type="checkbox" id="vandel_enable_location_system" name="vandel_enable_location_system" 
                                       value="yes" <?php checked(get_option('vandel_enable_location_system', 'yes'), 'yes'); ?>>
                                <?php _e('Enable Location Management System', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Enhanced location management with hierarchical locations (country, city, area)', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('ZIP Code Management', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_zip_code_feature">
                                <input type="checkbox" id="vandel_enable_zip_code_feature" name="vandel_enable_zip_code_feature" 
                                       value="yes" <?php checked(get_option('vandel_enable_zip_code_feature', 'no'), 'yes'); ?>>
                                <?php _e('Enable ZIP Code Management', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Manage service availability and pricing based on ZIP codes', 'vandel-booking'); ?>
                            </p>
                            <div class="vandel-zip-code-actions" style="<?php echo get_option('vandel_enable_zip_code_feature', 'no') === 'yes' ? '' : 'display: none;'; ?>">
                                <p>
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes'); ?>" class="button">
                                        <?php _e('Manage ZIP Codes', 'vandel-booking'); ?>
                                    </a>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('PayPal Integration', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_paypal">
                                <input type="checkbox" id="vandel_enable_paypal" name="vandel_enable_paypal" 
                                       value="yes" <?php checked(get_option('vandel_enable_paypal', 'no'), 'yes'); ?>>
                                <?php _e('Enable PayPal Integration', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Coming soon - Accept payments via PayPal', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Stripe Integration', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_stripe">
                                <input type="checkbox" id="vandel_enable_stripe" name="vandel_enable_stripe" 
                                       value="yes" <?php checked(get_option('vandel_enable_stripe', 'no'), 'yes'); ?>>
                                <?php _e('Enable Stripe Integration', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Coming soon - Accept payments via Stripe', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Google Calendar Integration', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_gcal">
                                <input type="checkbox" id="vandel_enable_gcal" name="vandel_enable_gcal" 
                                       value="yes" <?php checked(get_option('vandel_enable_gcal', 'no'), 'yes'); ?>>
                                <?php _e('Enable Google Calendar Integration', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Coming soon - Sync bookings with Google Calendar', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Mailchimp Integration', 'vandel-booking'); ?>
                        </th>
                        <td>
                            <label for="vandel_enable_mailchimp">
                                <input type="checkbox" id="vandel_enable_mailchimp" name="vandel_enable_mailchimp" 
                                       value="yes" <?php checked(get_option('vandel_enable_mailchimp', 'no'), 'yes'); ?>>
                                <?php _e('Enable Mailchimp Integration', 'vandel-booking'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Coming soon - Add clients to Mailchimp lists', 'vandel-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'vandel-booking'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render ZIP code settings
     */
    private function render_zip_code_settings() {
        $zip_code_feature_enabled = get_option('vandel_enable_zip_code_feature', 'no') === 'yes';
        
        if (!$zip_code_feature_enabled) {
            echo '<div class="notice notice-warning"><p>' . 
                sprintf(
                    __('ZIP Code feature is disabled. <a href="%s">Enable it here</a>.', 'vandel-booking'),
                    admin_url('admin.php?page=vandel-dashboard&tab=settings&section=integrations')
                ) . 
                '</p></div>';
        }
        
        // Get ZIP codes
        $zip_codes = [];
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
            $zip_codes = $zip_code_model->getAll();
        }
        ?>
        <div class="vandel-settings-section">
            <h3><?php _e('ZIP Code Management', 'vandel-booking'); ?></h3>
            
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h4><?php _e('Add New ZIP Code', 'vandel-booking'); ?></h4>
                </div>
                <div class="vandel-card-body">
                    <form method="post" action="">
                        <?php wp_nonce_field('vandel_zip_code_actions'); ?>
                        <input type="hidden" name="vandel_add_zip_code" value="1">
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="zip_code"><?php _e('ZIP Code', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" id="zip_code" name="zip_code" required>
                            </div>
                            <div class="vandel-form-group">
                                <label for="city"><?php _e('City', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" id="city" name="city" required>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="state"><?php _e('State/Province', 'vandel-booking'); ?></label>
                                <input type="text" id="state" name="state">
                            </div>
                            <div class="vandel-form-group">
                                <label for="country"><?php _e('Country', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" id="country" name="country" required>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="price_adjustment"><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                <input type="number" id="price_adjustment" name="price_adjustment" step="0.01" value="0">
                                <p class="description"><?php _e('Additional price adjustment for this ZIP code (can be negative)', 'vandel-booking'); ?></p>
                            </div>
                            <div class="vandel-form-group">
                                <label for="service_fee"><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                <input type="number" id="service_fee" name="service_fee" step="0.01" min="0" value="0">
                                <p class="description"><?php _e('Additional service fee for this ZIP code', 'vandel-booking'); ?></p>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label>
                                    <input type="checkbox" id="is_serviceable" name="is_serviceable" checked>
                                    <?php _e('This ZIP code is available for service', 'vandel-booking'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="vandel-form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Add ZIP Code', 'vandel-booking'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h4><?php _e('Import/Export ZIP Codes', 'vandel-booking'); ?></h4>
                </div>
                <div class="vandel-card-body">
                    <div class="vandel-import-export-actions">
                        <div class="vandel-action-group">
                            <h5><?php _e('Import ZIP Codes', 'vandel-booking'); ?></h5>
                            <p><?php _e('Upload a CSV file with ZIP codes.', 'vandel-booking'); ?></p>
                            <form method="post" enctype="multipart/form-data">
                                <div class="vandel-form-row">
                                    <div class="vandel-form-group">
                                        <input type="file" id="vandel-zip-codes-file" name="vandel-zip-codes-file" accept=".csv">
                                    </div>
                                </div>
                                <div class="vandel-form-actions">
                                    <button type="button" id="vandel-import-zip-codes" class="button"><?php _e('Import', 'vandel-booking'); ?></button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="vandel-action-group">
                            <h5><?php _e('Export ZIP Codes', 'vandel-booking'); ?></h5>
                            <p><?php _e('Download all ZIP codes as a CSV file.', 'vandel-booking'); ?></p>
                            <button type="button" id="vandel-export-zip-codes" class="button"><?php _e('Export', 'vandel-booking'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h4><?php _e('ZIP Codes', 'vandel-booking'); ?></h4>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($zip_codes)): ?>
                        <p><?php _e('No ZIP codes found.', 'vandel-booking'); ?></p>
                    <?php else: ?>
                        <div class="vandel-table-container">
                            <table class="wp-list-table widefat fixed striped vandel-data-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('City', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('State', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('Country', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('Price Adjustment', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('Service Fee', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('Status', 'vandel-booking'); ?></th>
                                        <th scope="col"><?php _e('Actions', 'vandel-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($zip_codes as $zip_code): ?>
                                        <tr>
                                            <td><?php echo esc_html($zip_code->zip_code); ?></td>
                                            <td><?php echo esc_html($zip_code->city); ?></td>
                                            <td><?php echo esc_html($zip_code->state); ?></td>
                                            <td><?php echo esc_html($zip_code->country); ?></td>
                                            <td><?php echo \VandelBooking\Helpers::formatPrice($zip_code->price_adjustment); ?></td>
                                            <td><?php echo \VandelBooking\Helpers::formatPrice($zip_code->service_fee); ?></td>
                                            <td>
                                                <?php if ($zip_code->is_serviceable === 'yes'): ?>
                                                    <span class="vandel-status-badge active"><?php _e('Active', 'vandel-booking'); ?></span>
                                                <?php else: ?>
                                                    <span class="vandel-status-badge inactive"><?php _e('Inactive', 'vandel-booking'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes&action=delete_zip_code&zip_code=' . urlencode($zip_code->zip_code)), 'delete_zip_code_' . $zip_code->zip_code); ?>" 
                                                   class="vandel-delete-zip-code"
                                                   data-id="<?php echo esc_attr($zip_code->zip_code); ?>">
                                                    <?php _e('Delete', 'vandel-booking'); ?>
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
        <?php
    }
    

}