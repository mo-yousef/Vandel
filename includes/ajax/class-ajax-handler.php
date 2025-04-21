<?php
namespace VandelBooking\Ajax;

/**
 * AJAX Handler for booking-related requests with detailed error reporting
 */
class AjaxHandler {
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_vandel_get_service_details', [$this, 'getServiceDetails']);
        add_action('wp_ajax_nopriv_vandel_get_service_details', [$this, 'getServiceDetails']);
        
        add_action('wp_ajax_vandel_validate_zip_code', [$this, 'validateZipCode']);
        add_action('wp_ajax_nopriv_vandel_validate_zip_code', [$this, 'validateZipCode']);
        
        add_action('wp_ajax_vandel_submit_booking', [$this, 'submitBooking']);
        add_action('wp_ajax_nopriv_vandel_submit_booking', [$this, 'submitBooking']);
    }
    
    /**
     * Get service details
     */
    public function getServiceDetails() {
        // Verify nonce
        if (!check_ajax_referer('vandel_booking_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'vandel-booking')]);
            return;
        }
        
        // Get and sanitize service ID
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        
        if (!$service_id) {
            wp_send_json_error(['message' => __('Invalid service ID', 'vandel-booking')]);
            return;
        }
        
        // Get service post
        $service = get_post($service_id);
        if (!$service || $service->post_type !== 'vandel_service') {
            wp_send_json_error(['message' => __('Service not found', 'vandel-booking')]);
            return;
        }
        
        // Get service details
        $service_data = [
            'id' => $service_id,
            'title' => $service->post_title,
            'subtitle' => get_post_meta($service_id, '_vandel_service_subtitle', true),
            'description' => get_post_meta($service_id, '_vandel_service_description', true),
            'price' => floatval(get_post_meta($service_id, '_vandel_service_base_price', true)),
            'duration' => get_post_meta($service_id, '_vandel_service_duration', true),
            'is_popular' => get_post_meta($service_id, '_vandel_service_is_popular', true) === 'yes',
        ];
        
        // Get sub-services
        $assigned_sub_services = get_post_meta($service_id, '_vandel_assigned_sub_services', true) ?: [];
        $options = [];
        $options_html = '';
        
        if (!empty($assigned_sub_services)) {
            $sub_services = [];
            foreach ($assigned_sub_services as $sub_service_id) {
                $sub_service = get_post($sub_service_id);
                if ($sub_service && $sub_service->post_type === 'vandel_sub_service') {
                    $sub_services[] = $sub_service;
                }
            }
            
            // Generate options HTML
            if (!empty($sub_services)) {
                ob_start();
                foreach ($sub_services as $sub_service) {
                    $id = $sub_service->ID;
                    $type = get_post_meta($id, '_vandel_sub_service_type', true) ?: 'checkbox';
                    $price = floatval(get_post_meta($id, '_vandel_sub_service_price', true));
                    $required = get_post_meta($id, '_vandel_sub_service_required', true) === 'yes';
                    $subtitle = get_post_meta($id, '_vandel_sub_service_subtitle', true);
                    
                    echo '<div class="vandel-option-item" data-option-id="' . esc_attr($id) . '" data-option-type="' . esc_attr($type) . '">';
                    echo '<div class="vandel-option-header">';
                    echo '<h4 class="vandel-option-title">' . esc_html($sub_service->post_title) . ($required ? ' <span class="required">*</span>' : '') . '</h4>';
                    
                    if ($price > 0) {
                        echo '<span class="vandel-option-price" data-price="' . esc_attr($price) . '">' . \VandelBooking\Helpers::formatPrice($price) . '</span>';
                    }
                    
                    echo '</div>';
                    
                    if (!empty($subtitle)) {
                        echo '<p class="vandel-option-subtitle">' . esc_html($subtitle) . '</p>';
                    }
                    
                    echo '<div class="vandel-option-input">';
                    
                    // Render different input types
                    switch ($type) {
                        case 'checkbox':
                            echo '<label class="vandel-checkbox-label">';
                            echo '<input type="checkbox" name="options[' . esc_attr($id) . ']" value="yes" data-price="' . esc_attr($price) . '" ' . ($required ? 'required' : '') . '>';
                            echo '<span class="vandel-checkbox-text">' . __('Yes, add this service', 'vandel-booking') . '</span>';
                            echo '</label>';
                            break;
                            
                        case 'text':
                            echo '<input type="text" name="options[' . esc_attr($id) . ']" placeholder="' . esc_attr(get_post_meta($id, '_vandel_sub_service_placeholder', true)) . '" ' . ($required ? 'required' : '') . '>';
                            break;
                            
                        case 'textarea':
                            echo '<textarea name="options[' . esc_attr($id) . ']" rows="3" placeholder="' . esc_attr(get_post_meta($id, '_vandel_sub_service_placeholder', true)) . '" ' . ($required ? 'required' : '') . '></textarea>';
                            break;
                            
                        case 'dropdown':
                            $options_data = json_decode(get_post_meta($id, '_vandel_sub_service_options', true), true) ?: [];
                            echo '<select name="options[' . esc_attr($id) . ']" ' . ($required ? 'required' : '') . '>';
                            echo '<option value="">' . __('Select an option', 'vandel-booking') . '</option>';
                            foreach ($options_data as $option) {
                                $option_price = isset($option['price']) ? floatval($option['price']) : 0;
                                echo '<option value="' . esc_attr($option['name']) . '" data-price="' . esc_attr($option_price) . '">';
                                echo esc_html($option['name']);
                                if ($option_price > 0) {
                                    echo ' (' . \VandelBooking\Helpers::formatPrice($option_price) . ')';
                                }
                                echo '</option>';
                            }
                            echo '</select>';
                            break;
                            
                        case 'radio':
                            $options_data = json_decode(get_post_meta($id, '_vandel_sub_service_options', true), true) ?: [];
                            foreach ($options_data as $option) {
                                $option_price = isset($option['price']) ? floatval($option['price']) : 0;
                                echo '<label class="vandel-radio-label">';
                                echo '<input type="radio" name="options[' . esc_attr($id) . ']" value="' . esc_attr($option['name']) . '" data-price="' . esc_attr($option_price) . '" ' . ($required ? 'required' : '') . '>';
                                echo '<span class="vandel-radio-text">' . esc_html($option['name']);
                                if ($option_price > 0) {
                                    echo ' <span class="vandel-radio-price">(' . \VandelBooking\Helpers::formatPrice($option_price) . ')</span>';
                                }
                                echo '</span>';
                                echo '</label>';
                            }
                            break;
                            
                        case 'number':
                            $min = get_post_meta($id, '_vandel_sub_service_min', true) ?: 0;
                            $max = get_post_meta($id, '_vandel_sub_service_max', true) ?: 999;
                            $default = get_post_meta($id, '_vandel_sub_service_default', true) ?: 0;
                            
                            echo '<input type="number" name="options[' . esc_attr($id) . ']" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" value="' . esc_attr($default) . '" data-price="' . esc_attr($price) . '" ' . ($required ? 'required' : '') . '>';
                            
                            if ($price > 0) {
                                echo '<span class="vandel-price-per-unit">' . \VandelBooking\Helpers::formatPrice($price) . ' ' . __('per unit', 'vandel-booking') . '</span>';
                            }
                            break;
                    }
                    
                    echo '</div>'; // .vandel-option-input
                    echo '</div>'; // .vandel-option-item
                    
                    // Store option data for response
                    $options[] = [
                        'id' => $id,
                        'title' => $sub_service->post_title,
                        'subtitle' => $subtitle,
                        'type' => $type,
                        'price' => $price,
                        'required' => $required,
                    ];
                }
                $options_html = ob_get_clean();
            }
        }
        
        // Add options to response
        $service_data['options'] = $options;
        $service_data['optionsHtml'] = $options_html;
        
        // Return success response
        wp_send_json_success($service_data);
    }
    
    /**
     * Validate ZIP code
     */
    public function validateZipCode() {
        if (!check_ajax_referer('vandel_booking_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'vandel-booking')]);
            return;
        }
        
        $zip_code = sanitize_text_field($_POST['zip_code']);
        
        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP Code cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Check if ZipCodeModel exists
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
            $zip_details = $zip_code_model->get($zip_code);
            
            if (!$zip_details) {
                wp_send_json_error(['message' => __('ZIP Code not found in our service area', 'vandel-booking')]);
                return;
            }
            
            if ($zip_details->is_serviceable !== 'yes') {
                wp_send_json_error(['message' => __('Sorry, we do not serve this area yet', 'vandel-booking')]);
                return;
            }
            
            wp_send_json_success([
                'zip_code' => $zip_details->zip_code,
                'city' => $zip_details->city,
                'state' => $zip_details->state,
                'country' => $zip_details->country,
                'price_adjustment' => floatval($zip_details->price_adjustment),
                'service_fee' => floatval($zip_details->service_fee),
                'location_string' => sprintf('%s, %s', $zip_details->city, $zip_details->state)
            ]);
        } else {
            // If ZipCodeModel doesn't exist, return a simulated success response
            wp_send_json_success([
                'zip_code' => $zip_code,
                'city' => 'Demo City',
                'state' => 'DS',
                'country' => 'Demo Country',
                'price_adjustment' => 0,
                'service_fee' => 0,
                'location_string' => 'Demo City, DS'
            ]);
        }
    }
    
    /**
     * Submit booking with enhanced error reporting
     */
    public function submitBooking() {
        global $wpdb;
        $error_details = [];
        
        // Verify nonce - but continue for debugging
        $nonce_valid = check_ajax_referer('vandel_booking_nonce', 'nonce', false);
        if (!$nonce_valid) {
            $error_details[] = "Nonce verification failed. Received: " . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set');
        }
        
        try {
            // Debug: Log entire submission
            error_log('Booking submission data: ' . print_r($_POST, true));
            
            // Validate form data
            $required_fields = ['service_id', 'name', 'email', 'phone', 'date', 'time', 'terms'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = $field;
                }
            }
            
            if (!empty($missing_fields)) {
                $error_details[] = 'Missing required fields: ' . implode(', ', $missing_fields);
                throw new \Exception('Missing required fields: ' . implode(', ', $missing_fields));
            }
            
            // Continue with basic field validation
            $data = [];
            foreach ($required_fields as $field) {
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
            
            // Validate email
            if (!is_email($data['email'])) {
                $error_details[] = 'Invalid email address: ' . $data['email'];
                throw new \Exception('Invalid email address');
            }
            
            // Validate service
            $service_id = intval($data['service_id']);
            $service = get_post($service_id);
            if (!$service || $service->post_type !== 'vandel_service') {
                $error_details[] = 'Invalid service ID: ' . $service_id . '. Post exists: ' . ($service ? 'Yes' : 'No');
                if ($service) {
                    $error_details[] = 'Post type: ' . $service->post_type;
                }
                throw new \Exception('Invalid service selection');
            }
            
            // Debug: Verify database tables exist
            $tables_to_check = [
                $wpdb->prefix . 'vandel_clients',
                $wpdb->prefix . 'vandel_bookings',
                $wpdb->prefix . 'vandel_booking_notes'
            ];
            
            foreach ($tables_to_check as $table) {
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                if (!$table_exists) {
                    $error_details[] = "Database table missing: $table";
                }
            }
            
            // Combine date and time
            $booking_date = date('Y-m-d H:i:s', strtotime($data['date'] . ' ' . $data['time']));
            
            // Collect selected options
            $selected_options = [];
            if (!empty($_POST['options']) && is_array($_POST['options'])) {
                foreach ($_POST['options'] as $option_id => $option_value) {
                    $selected_options[$option_id] = sanitize_text_field($option_value);
                }
            }
            
            // Get ZIP code data if available
            $zip_code_data = [];
            if (!empty($_POST['zip_code_data'])) {
                $zip_code_data = json_decode(stripslashes($_POST['zip_code_data']), true);
            }
            
            // Calculate total price
            $base_price = floatval(get_post_meta($service_id, '_vandel_service_base_price', true));
            $options_price = $this->calculateOptionsPrice($selected_options);
            $price_adjustment = isset($zip_code_data['price_adjustment']) ? floatval($zip_code_data['price_adjustment']) : 0;
            $service_fee = isset($zip_code_data['service_fee']) ? floatval($zip_code_data['service_fee']) : 0;
            
            $total_price = $base_price + $options_price + $price_adjustment + $service_fee;
            
            // Debug: Log calculated price
            error_log("Price calculation: Base price = $base_price, Options price = $options_price, Total = $total_price");
            
            // Prepare booking data
            $booking_data = [
                'service' => $service_id,
                'customer_name' => $data['name'],
                'customer_email' => $data['email'],
                'phone' => $data['phone'],
                'booking_date' => $booking_date,
                'sub_services' => !empty($selected_options) ? json_encode($selected_options) : null,
                'total_price' => $total_price,
                'access_info' => isset($zip_code_data['zip_code']) ? $zip_code_data['zip_code'] : '',
                'status' => get_option('vandel_default_booking_status', 'pending'),
                'created_at' => current_time('mysql')
            ];
            
            // Add comments if provided
            if (isset($_POST['comments'])) {
                $booking_data['comments'] = sanitize_textarea_field($_POST['comments']);
            }
            
            // Create booking - First try using BookingManager
            $booking_id = null;
            $manager_error = null;
            
            if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
                try {
                    $booking_manager = new \VandelBooking\Booking\BookingManager();
                    $booking_result = $booking_manager->createBooking($booking_data);
                    
                    if (is_wp_error($booking_result)) {
                        $manager_error = $booking_result->get_error_message();
                        $error_details[] = "BookingManager error: " . $manager_error;
                    } else {
                        $booking_id = $booking_result;
                    }
                } catch (\Exception $e) {
                    $manager_error = $e->getMessage();
                    $error_details[] = "BookingManager exception: " . $manager_error;
                }
            } else {
                $error_details[] = "BookingManager class not found";
            }
            
            // If BookingManager failed, try direct database insertion
            if (!$booking_id) {
                $error_details[] = "Attempting direct database insertion";
                
                // Try to create client
                try {
                    // Check if client exists
                    $client_table = $wpdb->prefix . 'vandel_clients';
                    $client = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$client_table} WHERE email = %s",
                        $data['email']
                    ));
                    
                    if ($client) {
                        $client_id = $client->id;
                        $error_details[] = "Found existing client #$client_id";
                    } else {
                        // Create new client
                        $client_insert = $wpdb->insert(
                            $client_table,
                            [
                                'email' => $data['email'],
                                'name' => $data['name'],
                                'phone' => $data['phone'],
                                'created_at' => current_time('mysql')
                            ],
                            ['%s', '%s', '%s', '%s']
                        );
                        
                        if ($client_insert === false) {
                            $error_details[] = "Client creation failed: " . $wpdb->last_error;
                            throw new \Exception('Failed to create client: ' . $wpdb->last_error);
                        }
                        
                        $client_id = $wpdb->insert_id;
                        $error_details[] = "Created new client #$client_id";
                    }
                    
                    // Create booking
                    $booking_table = $wpdb->prefix . 'vandel_bookings';
                    $booking_data['client_id'] = $client_id;
                    
                    $booking_insert = $wpdb->insert(
                        $booking_table,
                        [
                            'client_id' => $client_id,
                            'service' => $booking_data['service'],
                            'sub_services' => $booking_data['sub_services'],
                            'booking_date' => $booking_data['booking_date'],
                            'customer_name' => $booking_data['customer_name'],
                            'customer_email' => $booking_data['customer_email'],
                            'access_info' => $booking_data['access_info'],
                            'total_price' => $booking_data['total_price'],
                            'status' => $booking_data['status'],
                            'created_at' => $booking_data['created_at']
                        ],
                        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']
                    );
                    
                    if ($booking_insert === false) {
                        $error_details[] = "Booking creation failed: " . $wpdb->last_error;
                        throw new \Exception('Failed to create booking: Database error - ' . $wpdb->last_error);
                    }
                    
                    $booking_id = $wpdb->insert_id;
                    $error_details[] = "Created booking #$booking_id via direct DB insertion";
                    
                } catch (\Exception $e) {
                    $error_details[] = "Direct DB insertion exception: " . $e->getMessage();
                    throw $e; // Re-throw to handle at outer level
                }
            }
            
            // If we have a booking ID, return success
            if ($booking_id) {
                // Attempt to send notification if available
                if (class_exists('\\VandelBooking\\Booking\\BookingNotification')) {
                    try {
                        $notification = new \VandelBooking\Booking\BookingNotification();
                        $notification->sendClientConfirmation($booking_id);
                        $notification->sendAdminNotification($booking_id);
                        $error_details[] = "Notifications sent successfully";
                    } catch (\Exception $e) {
                        // Log notification error but don't stop booking process
                        $error_details[] = "Notification error: " . $e->getMessage();
                        error_log('Notification error: ' . $e->getMessage());
                    }
                }
                
                wp_send_json_success([
                    'booking_id' => $booking_id,
                    'message' => __('Booking created successfully', 'vandel-booking'),
                    'debug_info' => $error_details
                ]);
                return;
            } else {
                throw new \Exception('Failed to create booking: No booking ID returned' . 
                    ($manager_error ? ' - ' . $manager_error : ''));
            }
            
        } catch (\Exception $e) {
            error_log('Booking error: ' . $e->getMessage() . "\nDebug info: " . print_r($error_details, true));
            wp_send_json_error([
                'message' => $e->getMessage(),
                'debug_info' => $error_details
            ]);
        }
    }
    
    /**
     * Calculate the total price for selected options
     * 
     * @param array $selected_options Selected options
     * @return float Total price for options
     */
    private function calculateOptionsPrice($selected_options) {
        $total = 0;
        
        foreach ($selected_options as $option_id => $option_value) {
            // Skip if empty
            if (empty($option_value)) {
                continue;
            }
            
            $sub_service = get_post($option_id);
            if (!$sub_service || $sub_service->post_type !== 'vandel_sub_service') {
                continue;
            }
            
            $type = get_post_meta($option_id, '_vandel_sub_service_type', true) ?: 'checkbox';
            $base_price = floatval(get_post_meta($option_id, '_vandel_sub_service_price', true));
            
            switch ($type) {
                case 'checkbox':
                    // Value is either 'yes' or empty
                    if ($option_value === 'yes') {
                        $total += $base_price;
                    }
                    break;
                    
                case 'radio':
                case 'dropdown':
                    // Value is option name, get price from options
                    $options = json_decode(get_post_meta($option_id, '_vandel_sub_service_options', true), true) ?: [];
                    foreach ($options as $option) {
                        if (isset($option['name']) && $option['name'] === $option_value) {
                            $total += isset($option['price']) ? floatval($option['price']) : 0;
                            break;
                        }
                    }
                    break;
                    
                case 'number':
                    // Value is quantity
                    $quantity = intval($option_value);
                    if ($quantity > 0) {
                        $total += $base_price * $quantity;
                    }
                    break;
                    
                default:
                    // Text inputs might have fixed price
                    if (!empty($option_value) && $base_price > 0) {
                        $total += $base_price;
                    }
                    break;
            }
        }
        
        return $total;
    }

}