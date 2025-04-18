<?php
namespace VandelBooking\Booking;

/**
 * Enhanced Booking Manager
 * Central class for managing all booking operations
 */
class BookingManager {
    /**
     * @var BookingModel
     */
    private $booking_model;
    
    /**
     * @var \VandelBooking\Client\ClientModel
     */
    private $client_model;
    
    /**
     * @var BookingNotification
     */
    private $notification;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initializeModels();
    }
    
    /**
     * Initialize required models
     */
    private function initializeModels() {
        // Initialize booking model
        if (class_exists('\\VandelBooking\\Booking\\BookingModel')) {
            $this->booking_model = new BookingModel();
        }
        
        // Try to initialize client model with various paths
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $this->client_model = new \VandelBooking\Client\ClientModel();
        } else {
            // Try to include it directly from various possible locations
            $possible_paths = [
                VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php',
                VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-client-model.php',
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
            
            // Try again after including
            if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
                $this->client_model = new \VandelBooking\Client\ClientModel();
            }
        }
        
        // Initialize notification if available
        if (class_exists('\\VandelBooking\\Booking\\BookingNotification')) {
            $this->notification = new BookingNotification();
        }
    }
    
    /**
     * Create new booking
     * 
     * @param array $booking_data Booking data
     * @return int|WP_Error Booking ID or error
     */

public function createBooking($booking_data) {
    try {
        // Validate required fields
        $required_fields = ['service', 'customer_name', 'customer_email', 'booking_date', 'total_price'];
        foreach ($required_fields as $field) {
            if (empty($booking_data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Missing required field: %s', 'vandel-booking'), $field)
                );
            }
        }
        
        // Create client record
        try {
            $client_id = $this->getOrCreateClient($booking_data);
        } catch (\Exception $e) {
            return new \WP_Error('client_error', $e->getMessage());
        }
        
        if (!$client_id) {
            return new \WP_Error(
                'client_creation_failed',
                __('Failed to create client', 'vandel-booking')
            );
        }
        
        // Add client_id to booking data
        $booking_data['client_id'] = $client_id;
        
        // Set default status if not provided
        if (!isset($booking_data['status'])) {
            $booking_data['status'] = get_option('vandel_default_booking_status', 'pending');
        }
        
        // Set created_at if not provided
        if (!isset($booking_data['created_at'])) {
            $booking_data['created_at'] = current_time('mysql');
        }
        
        // Process location data if available
        if (!empty($booking_data['zip_code_data'])) {
            if (is_string($booking_data['zip_code_data'])) {
                $booking_data['zip_code_data'] = json_decode($booking_data['zip_code_data'], true);
            }
            
            // Store location info in access_info field
            if (!isset($booking_data['access_info']) || empty($booking_data['access_info'])) {
                $location_info = [
                    'zip_code' => $booking_data['zip_code_data']['zip_code'] ?? '',
                    'city' => $booking_data['zip_code_data']['city'] ?? '',
                    'area_name' => $booking_data['zip_code_data']['area_name'] ?? '',
                    'state' => $booking_data['zip_code_data']['state'] ?? '',
                    'country' => $booking_data['zip_code_data']['country'] ?? '',
                ];
                $booking_data['access_info'] = json_encode($location_info);
            }
            
            // Add location price adjustments
            if (isset($booking_data['zip_code_data']['price_adjustment']) || 
                isset($booking_data['zip_code_data']['service_fee'])) {
                $price_adjustment = isset($booking_data['zip_code_data']['price_adjustment']) ? 
                    floatval($booking_data['zip_code_data']['price_adjustment']) : 0;
                $service_fee = isset($booking_data['zip_code_data']['service_fee']) ? 
                    floatval($booking_data['zip_code_data']['service_fee']) : 0;
                    
                // Store the location fees separately for reference
                $booking_data['location_adjustment'] = $price_adjustment;
                $booking_data['location_fee'] = $service_fee;
            }
        }
        
        // Create booking using model or direct insertion
        $booking_id = $this->createBookingRecord($booking_data);
        
        if (!$booking_id || is_wp_error($booking_id)) {
            return $booking_id ?: new \WP_Error(
                'booking_creation_failed',
                __('Failed to create booking', 'vandel-booking')
            );
        }
        
        // Store location fees as meta if needed
        if (!empty($booking_data['location_adjustment']) || !empty($booking_data['location_fee'])) {
            update_post_meta($booking_id, '_vandel_location_adjustment', $booking_data['location_adjustment'] ?? 0);
            update_post_meta($booking_id, '_vandel_location_fee', $booking_data['location_fee'] ?? 0);
        }
        
        // Send notifications if available
        $this->sendNotifications($booking_id);
        
        // Update client total spent
        $this->updateClientSpending($client_id, $booking_data['total_price']);
        
        // Return booking ID
        return $booking_id;
        
    } catch (\Exception $e) {
        error_log('Exception in createBooking: ' . $e->getMessage());
        return new \WP_Error(
            'booking_exception',
            __('Error creating booking: ', 'vandel-booking') . $e->getMessage()
        );
    }
}
    /**
     * Get or create client
     * 
     * @param array $data Booking data containing client info
     * @return int Client ID
     */
    private function getOrCreateClient($data) {
        // Create client data from booking data
        $client_data = [
            'email' => $data['customer_email'],
            'name' => $data['customer_name'],
            'phone' => isset($data['phone']) ? $data['phone'] : ''
        ];
        
        // Try using the client model
        if (isset($this->client_model) && method_exists($this->client_model, 'getOrCreateClient')) {
            return $this->client_model->getOrCreateClient($client_data);
        }
        
        // Fallback to direct database insertion
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_clients';
        
        // Check if client exists
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE email = %s",
            $client_data['email']
        ));
        
        if ($client) {
            return $client->id;
        }
        
        // Create new client
        $result = $wpdb->insert(
            $table_name,
            [
                'email' => $client_data['email'],
                'name' => $client_data['name'],
                'phone' => $client_data['phone'],
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            throw new \Exception('Database error creating client: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create booking record in database
     * 
     * @param array $data Booking data
     * @return int|false Booking ID or false on failure
     */
    private function createBookingRecord($data) {
        // Try using the booking model
        if (isset($this->booking_model) && method_exists($this->booking_model, 'create')) {
            return $this->booking_model->create($data);
        }
        
        // Fallback to direct database insertion
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        // Format sub_services as JSON if it's an array
        if (isset($data['sub_services']) && is_array($data['sub_services'])) {
            $data['sub_services'] = json_encode($data['sub_services']);
        }
        
        // Insert booking record
        $result = $wpdb->insert(
            $table_name,
            [
                'client_id' => $data['client_id'],
                'service' => $data['service'],
                'sub_services' => isset($data['sub_services']) ? $data['sub_services'] : null,
                'booking_date' => $data['booking_date'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'access_info' => isset($data['access_info']) ? $data['access_info'] : '',
                'total_price' => $data['total_price'],
                'status' => $data['status'],
                'created_at' => $data['created_at']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            error_log('Error creating booking: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Send booking notifications
     * 
     * @param int $booking_id Booking ID
     */
    private function sendNotifications($booking_id) {
        // Check if notifications are enabled
        if (get_option('vandel_enable_email_notifications', 'yes') !== 'yes') {
            return;
        }
        
        try {
            // Use notification class if available
            if (isset($this->notification)) {
                $this->notification->sendClientConfirmation($booking_id);
                $this->notification->sendAdminNotification($booking_id);
            } else if (class_exists('\\VandelBooking\\Booking\\BookingNotification')) {
                $notification = new BookingNotification();
                $notification->sendClientConfirmation($booking_id);
                $notification->sendAdminNotification($booking_id);
            }
        } catch (\Exception $e) {
            error_log('Error sending booking notifications: ' . $e->getMessage());
        }
    }
    
    /**
     * Update client's total spent
     * 
     * @param int $client_id Client ID
     * @param float $amount Amount to add
     */
    private function updateClientSpending($client_id, $amount) {
        try {
            // Use client model if available
            if (isset($this->client_model) && method_exists($this->client_model, 'updateTotalSpent')) {
                $this->client_model->updateTotalSpent($client_id, $amount);
            } else {
                // Fallback to direct database update
                global $wpdb;
                $table_name = $wpdb->prefix . 'vandel_clients';
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_name} SET total_spent = total_spent + %f WHERE id = %d",
                    $amount, $client_id
                ));
            }
        } catch (\Exception $e) {
            error_log('Error updating client spending: ' . $e->getMessage());
        }
    }
    
    /**
     * Update booking status
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Whether the status was updated
     */
    public function updateBookingStatus($booking_id, $status) {
        // Use booking model if available
        if (isset($this->booking_model) && method_exists($this->booking_model, 'updateStatus')) {
            $result = $this->booking_model->updateStatus($booking_id, $status);
        } else {
            // Fallback to direct database update
            global $wpdb;
            $table_name = $wpdb->prefix . 'vandel_bookings';
            
            $result = $wpdb->update(
                $table_name,
                ['status' => $status],
                ['id' => $booking_id],
                ['%s'],
                ['%d']
            );
        }
        
        // Send status update notification if available
        if ($result && isset($this->notification)) {
            try {
                $this->notification->sendStatusUpdateNotification($booking_id, $status);
            } catch (\Exception $e) {
                error_log('Error sending status notification: ' . $e->getMessage());
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Get booking by ID
     * 
     * @param int $booking_id Booking ID
     * @return object|false Booking object or false if not found
     */
    public function getBooking($booking_id) {
        // Use booking model if available
        if (isset($this->booking_model) && method_exists($this->booking_model, 'get')) {
            return $this->booking_model->get($booking_id);
        }
        
        // Fallback to direct database query
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get all bookings with filtering
     * 
     * @param array $args Filter arguments
     * @return array Bookings
     */
    public function getBookings($args = []) {
        // Use booking model if available
        if (isset($this->booking_model) && method_exists($this->booking_model, 'getAll')) {
            return $this->booking_model->getAll($args);
        }
        
        // Fallback to direct database query
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        $defaults = [
            'status' => '',
            'client_id' => 0,
            'service' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'booking_date',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = [];
        $values = [];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['client_id'])) {
            $where[] = 'client_id = %d';
            $values[] = $args['client_id'];
        }
        
        if (!empty($args['service'])) {
            $where[] = 'service = %s';
            $values[] = $args['service'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'booking_date >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'booking_date <= %s';
            $values[] = $args['date_to'];
        }
        
        // Build query
        $query = "SELECT * FROM {$table_name}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Sanitize orderby
        $allowed_orderby = ['id', 'booking_date', 'total_price', 'status', 'created_at'];
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'booking_date';
        }
        
        // Sanitize order
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Limit
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare final query
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Get results
        $bookings = $wpdb->get_results($query);
        
        // Decode JSON data
        foreach ($bookings as &$booking) {
            if (!empty($booking->sub_services)) {
                $booking->sub_services = json_decode($booking->sub_services);
            }
        }
        
        return $bookings;
    }
    
    /**
     * Add booking note
     * 
     * @param int $booking_id Booking ID
     * @param string $note_content Note content
     * @param int $user_id User ID (0 for system)
     * @return int|false Note ID or false if failed
     */
    public function addBookingNote($booking_id, $note_content, $user_id = 0) {
        // Check if NoteModel exists
        if (class_exists('\\VandelBooking\\Booking\\NoteModel')) {
            $note_model = new NoteModel();
            return $note_model->addNote($booking_id, $note_content, $user_id);
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
                'note_content' => $note_content,
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
     * Get booking notes
     * 
     * @param int $booking_id Booking ID
     * @return array Booking notes
     */
    public function getBookingNotes($booking_id) {
        // Check if NoteModel exists
        if (class_exists('\\VandelBooking\\Booking\\NoteModel')) {
            $note_model = new NoteModel();
            return $note_model->getNotes($booking_id);
        }
        
        // Fallback to direct database query
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_booking_notes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM {$table_name} n 
             LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID 
             WHERE booking_id = %d 
             ORDER BY created_at DESC",
            $booking_id
        ));
    }
}