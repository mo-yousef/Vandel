<?php
namespace VandelBooking\Booking;

/**
 * Booking Workflow
 * Handles the complete booking process from submission to completion
 */
class BookingWorkflow {
    /**
     * @var BookingManager
     */
    private $booking_manager;
    
    /**
     * @var BookingNotification
     */
    private $notification;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize dependencies
        $this->initializeDependencies();
        
        // Register hooks
        $this->registerHooks();
    }
    
    /**
     * Initialize dependencies
     */
    private function initializeDependencies() {
        // Initialize booking manager
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $this->booking_manager = new BookingManager();
        }
        
        // Initialize notification class
        if (class_exists('\\VandelBooking\\Booking\\BookingNotification')) {
            $this->notification = new BookingNotification();
        }
    }
    
    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        // Handle booking submission via AJAX
        add_action('wp_ajax_vandel_submit_booking', [$this, 'handleBookingSubmission']);
        add_action('wp_ajax_nopriv_vandel_submit_booking', [$this, 'handleBookingSubmission']);
        
        // Handle booking status updates
        add_action('vandel_booking_status_changed', [$this, 'handleStatusChange'], 10, 3);
        
        // Handle booking cancellations
        add_action('wp_ajax_vandel_cancel_booking', [$this, 'handleCancellation']);
        add_action('wp_ajax_nopriv_vandel_cancel_booking', [$this, 'handleCancellation']);
        
        // Handle booking reminders (daily check)
        add_action('vandel_daily_booking_check', [$this, 'sendBookingReminders']);
    }
    
    /**
     * Handle booking submission
     */
    public function handleBookingSubmission() {
        // Verify nonce
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
            
            // Process location data
            $location_data = [];
            if (!empty($_POST['location_data'])) {
                $location_data = json_decode(stripslashes($_POST['location_data']), true);
            } else if (!empty($_POST['zip_code_data'])) {
                // Legacy format support
                $location_data = json_decode(stripslashes($_POST['zip_code_data']), true);
            } else if (!empty($_POST['zip_code'])) {
                // Try to get location data from ZIP code
                $zip_code = sanitize_text_field($_POST['zip_code']);
                
                if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
                    $location_model = new \VandelBooking\Location\LocationModel();
                    $location = $location_model->getByZipCode($zip_code);
                    
                    if ($location) {
                        $location_data = [
                            'id' => $location->id,
                            'country' => $location->country,
                            'city' => $location->city,
                            'area_name' => $location->area_name,
                            'zip_code' => $location->zip_code,
                            'price_adjustment' => floatval($location->price_adjustment),
                            'service_fee' => floatval($location->service_fee)
                        ];
                    } else {
                        throw new \Exception(__('ZIP Code not found in our service area', 'vandel-booking'));
                    }
                } else if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
                    // Legacy ZIP code system
                    $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
                    $zip_details = $zip_code_model->get($zip_code);
                    
                    if ($zip_details) {
                        $location_data = [
                            'zip_code' => $zip_details->zip_code,
                            'city' => $zip_details->city,
                            'state' => $zip_details->state,
                            'country' => $zip_details->country,
                            'price_adjustment' => floatval($zip_details->price_adjustment),
                            'service_fee' => floatval($zip_details->service_fee)
                        ];
                    } else {
                        throw new \Exception(__('ZIP Code not found in our service area', 'vandel-booking'));
                    }
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
            
            // Calculate total price
            $base_price = floatval(get_post_meta($service_id, '_vandel_service_base_price', true));
            $options_price = $this->calculateOptionsPrice($selected_options);
            $price_adjustment = isset($location_data['price_adjustment']) ? floatval($location_data['price_adjustment']) : 0;
            $service_fee = isset($location_data['service_fee']) ? floatval($location_data['service_fee']) : 0;
            
            $total_price = $base_price + $options_price + $price_adjustment + $service_fee;
            
            // Prepare booking data
            $booking_data = [
                'service' => $service_id,
                'customer_name' => $data['name'],
                'customer_email' => $data['email'],
                'phone' => $data['phone'],
                'booking_date' => $booking_date,
                'sub_services' => $selected_options,
                'total_price' => $total_price,
                'comments' => isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '',
                'status' => get_option('vandel_default_booking_status', 'pending'),
                'location_data' => $location_data
            ];
            
            // Add access info from location data
            if (!empty($location_data)) {
                $access_info = '';
                
                if (isset($location_data['zip_code'])) {
                    $access_info .= $location_data['zip_code'];
                }
                
                if (isset($location_data['city'])) {
                    if (!empty($access_info)) {
                        $access_info .= ', ';
                    }
                    $access_info .= $location_data['city'];
                }
                
                if (isset($location_data['area_name']) && !empty($location_data['area_name'])) {
                    if (!empty($access_info)) {
                        $access_info .= ', ';
                    }
                    $access_info .= $location_data['area_name'];
                }
                
                if (isset($location_data['country'])) {
                    if (!empty($access_info)) {
                        $access_info .= ', ';
                    }
                    $access_info .= $location_data['country'];
                }
                
                $booking_data['access_info'] = $access_info;
            }
            
            // Create booking
            if (!$this->booking_manager) {
                throw new \Exception(__('Booking system is not available', 'vandel-booking'));
            }
            
            $booking_id = $this->booking_manager->createBooking($booking_data);
            
            if (is_wp_error($booking_id)) {
                throw new \Exception($booking_id->get_error_message());
            }
            
            // Run action hook for successful booking
            do_action('vandel_booking_created', $booking_id, $booking_data);
            
            // Send booking created notification
            $this->sendBookingNotifications($booking_id, 'created');
            
            // Return success response
            wp_send_json_success([
                'booking_id' => $booking_id,
                'message' => __('Booking created successfully', 'vandel-booking')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Calculate options price
     * 
     * @param array $selected_options Selected service options
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
    
    /**
     * Handle booking status change
     * 
     * @param int $booking_id Booking ID
     * @param string $old_status Previous status
     * @param string $new_status New status
     */
    public function handleStatusChange($booking_id, $old_status, $new_status) {
        // Log status change
        $this->logBookingActivity($booking_id, sprintf(
            __('Status changed from %s to %s', 'vandel-booking'),
            $old_status,
            $new_status
        ));
        
        // Update client statistics if needed
        $this->updateClientStats($booking_id, $old_status, $new_status);
        
        // Send status update notification
        $this->sendBookingNotifications($booking_id, 'status_update', [
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
    }
    
    /**
     * Handle booking cancellation
     */
    public function handleCancellation() {
        // Verify nonce
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        try {
            // Get booking ID and validation code
            $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
            $validation_code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
            
            if (!$booking_id) {
                throw new \Exception(__('Invalid booking ID', 'vandel-booking'));
            }
            
            // Get booking details
            $booking = $this->booking_manager->getBooking($booking_id);
            
            if (!$booking) {
                throw new \Exception(__('Booking not found', 'vandel-booking'));
            }
            
            // Check if booking is already canceled
            if ($booking->status === 'canceled') {
                throw new \Exception(__('Booking is already canceled', 'vandel-booking'));
            }
            
            // Validate cancellation is allowed
            $can_cancel = $this->validateCancellation($booking);
            
            if (!$can_cancel) {
                throw new \Exception(__('Cancellation is not allowed for this booking', 'vandel-booking'));
            }
            
            // Check validation code (if not an admin)
            if (!current_user_can('manage_options')) {
                // Generate validation code from email and booking ID
                $expected_code = substr(md5($booking->customer_email . $booking_id), 0, 10);
                
                if ($validation_code !== $expected_code) {
                    throw new \Exception(__('Invalid cancellation code', 'vandel-booking'));
                }
            }
            
            // Update booking status
            $result = $this->booking_manager->updateBookingStatus($booking_id, 'canceled');
            
            if (!$result) {
                throw new \Exception(__('Failed to cancel booking', 'vandel-booking'));
            }
            
            // Log cancellation
            $this->logBookingActivity($booking_id, __('Booking canceled by client', 'vandel-booking'));
            
            // Send cancellation notification
            $this->sendBookingNotifications($booking_id, 'cancellation');
            
            // Return success response
            wp_send_json_success([
                'message' => __('Booking canceled successfully', 'vandel-booking')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send booking reminders
     */
    public function sendBookingReminders() {
        global $wpdb;
        
        // Check if we're using a dedicated BookingModel class
        if (class_exists('\\VandelBooking\\Booking\\BookingModel')) {
            $booking_model = new BookingModel();
            
            // Define parameters for upcoming bookings
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $tomorrow_end = date('Y-m-d 23:59:59', strtotime('+1 day'));
            
            // Get confirmed bookings for tomorrow
            $bookings = $booking_model->getAll([
                'status' => 'confirmed',
                'date_from' => $tomorrow,
                'date_to' => $tomorrow_end
            ]);
        } else {
            // Fallback to direct query
            $bookings_table = $wpdb->prefix . 'vandel_bookings';
            $tomorrow = date('Y-m-d 00:00:00', strtotime('+1 day'));
            $tomorrow_end = date('Y-m-d 23:59:59', strtotime('+1 day'));
            
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $bookings_table 
                 WHERE status = 'confirmed' 
                 AND booking_date BETWEEN %s AND %s",
                $tomorrow, $tomorrow_end
            ));
        }
        
        // Send reminder for each booking
        if (!empty($bookings)) {
            foreach ($bookings as $booking) {
                $this->sendBookingNotifications($booking->id, 'reminder');
            }
        }
    }
    
    /**
     * Send booking notifications
     * 
     * @param int $booking_id Booking ID
     * @param string $type Notification type (created, status_update, reminder, cancellation)
     * @param array $params Additional parameters
     * @return bool Whether notifications were sent
     */
    private function sendBookingNotifications($booking_id, $type = 'created', $params = []) {
        // Check if notifications are enabled
        if (get_option('vandel_enable_email_notifications', 'yes') !== 'yes') {
            return false;
        }
        
        // Make sure we have a notification object
        if (!$this->notification && class_exists('\\VandelBooking\\Booking\\BookingNotification')) {
            $this->notification = new BookingNotification();
        }
        
        if (!$this->notification) {
            return false;
        }
        
        $sent = false;
        
        switch ($type) {
            case 'created':
                // Send confirmation to client and notification to admin
                $client_sent = $this->notification->sendClientConfirmation($booking_id);
                $admin_sent = $this->notification->sendAdminNotification($booking_id);
                $sent = $client_sent || $admin_sent;
                break;
                
            case 'status_update':
                // Send status update notification
                if (isset($params['new_status'])) {
                    $sent = $this->notification->sendStatusUpdateNotification($booking_id, $params['new_status']);
                }
                break;
                
            case 'reminder':
                // Send booking reminder
                if (method_exists($this->notification, 'sendReminderNotification')) {
                    $sent = $this->notification->sendReminderNotification($booking_id);
                }
                break;
                
            case 'cancellation':
                // Send cancellation notification
                if (method_exists($this->notification, 'sendCancellationNotification')) {
                    $sent = $this->notification->sendCancellationNotification($booking_id);
                } else {
                    // Fallback to status update notification
                    $sent = $this->notification->sendStatusUpdateNotification($booking_id, 'canceled');
                }
                break;
        }
        
        return $sent;
    }
    
    /**
     * Log booking activity
     * 
     * @param int $booking_id Booking ID
     * @param string $activity Activity description
     * @param int $user_id User ID (0 for system)
     * @return int|false Note ID or false if failed
     */
    private function logBookingActivity($booking_id, $activity, $user_id = 0) {
        // Check if we have BookingManager with addBookingNote method
        if ($this->booking_manager && method_exists($this->booking_manager, 'addBookingNote')) {
            return $this->booking_manager->addBookingNote($booking_id, $activity, $user_id);
        }
        
        // Check if we have NoteModel class
        if (class_exists('\\VandelBooking\\Booking\\NoteModel')) {
            $note_model = new NoteModel();
            return $note_model->addNote($booking_id, $activity, $user_id);
        }
        
        // Fallback to direct database insertion
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_booking_notes';
        
        // Get current user if not specified
        $user_id = $user_id ?: get_current_user_id();
        
        $result = $wpdb->insert(
            $table_name,
            [
                'booking_id' => $booking_id,
                'note_content' => $activity,
                'created_at' => current_time('mysql'),
                'created_by' => $user_id
            ],
            ['%d', '%s', '%s', '%d']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update client statistics based on booking status change
     * 
     * @param int $booking_id Booking ID
     * @param string $old_status Old booking status
     * @param string $new_status New booking status
     * @return bool Whether update was successful
     */
    private function updateClientStats($booking_id, $old_status, $new_status) {
        // Only proceed if status changes between completed and other statuses
        $affects_total = ($old_status === 'completed' || $new_status === 'completed');
        if (!$affects_total) {
            return false;
        }
        
        // Get booking details
        $booking = $this->booking_manager->getBooking($booking_id);
        
        if (!$booking || !isset($booking->client_id) || $booking->client_id <= 0) {
            return false;
        }
        
        // Check if we have ClientModel class
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $client_model = new \VandelBooking\Client\ClientModel();
            
            if (method_exists($client_model, 'recalculateStats')) {
                return $client_model->recalculateStats($booking->client_id);
            }
        }
        
        return false;
    }
    
    /**
     * Validate if a booking can be canceled
     * 
     * @param object $booking Booking object
     * @return bool Whether cancellation is allowed
     */
    private function validateCancellation($booking) {
        // Admin can always cancel
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check booking status - only pending or confirmed bookings can be canceled
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return false;
        }
        
        // Check cancellation window
        $cancellation_window = intval(get_option('vandel_booking_cancellation_window', 24));
        
        if ($cancellation_window > 0) {
            $booking_time = strtotime($booking->booking_date);
            $current_time = current_time('timestamp');
            $time_diff = $booking_time - $current_time;
            $hours_diff = $time_diff / 3600; // Convert to hours
            
            // Check if we're within the cancellation window
            if ($hours_diff < $cancellation_window) {
                return false;
            }
        }
        
        return true;
    }
}