<?php
namespace VandelBooking\Ajax;

/**
 * AJAX Handler for booking-related requests
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
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
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
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
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
     * Submit booking
     */
    public function submitBooking() {
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        try {
            // Validate form data
            $required_fields = ['service_id', 'name', 'email', 'phone', 'date', 'time', 'terms'];
            
            $data = [];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new \Exception(sprintf(__('Missing required field: %s', 'vandel-booking'), $field));
                }
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
            
            // Validate email
            if (!is_email($data['email'])) {
                throw new \Exception(__('Invalid email address', 'vandel-booking'));
            }
            
            // Validate service
            $service_id = intval($data['service_id']);
            $service = get_post($service_id);
            if (!$service || $service->post_type !== 'vandel_service') {
                throw new \Exception(__('Invalid service selection', 'vandel-booking'));
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
            
            // Prepare booking data
            $booking_data = [
                'service' => $service_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date' => $booking_date,
                'options' => $selected_options,
                'total' => $total_price,
                'comments' => isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '',
                'access_info' => isset($zip_code_data['zip_code']) ? sanitize_text_field($zip_code_data['zip_code']) : '',
                'access_type' => 'zip_code'
            ];
            
            // Create booking
            if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
                $booking_manager = new \VandelBooking\Booking\BookingManager();
                $booking_id = $booking_manager->createBooking($booking_data);
                
                if (is_wp_error($booking_id)) {
                    throw new \Exception($booking_id->get_error_message());
                }
                
                wp_send_json_success([
                    'booking_id' => $booking_id,
                    'message' => __('Booking created successfully', 'vandel-booking')
                ]);
            } else {
                // For demo purposes when BookingManager doesn't exist
                wp_send_json_success([
                    'booking_id' => rand(1000, 9999),
                    'message' => __('Booking created successfully (demo mode)', 'vandel-booking'),
                    'debug_data' => $booking_data
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
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