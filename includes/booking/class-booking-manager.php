<?php
namespace VandelBooking\Booking;

/**
 * Enhanced Booking Manager
 */
class BookingManager {
    /**
     * @var BookingModel
     */
    private $booking_model;
    
    /**
     * @var object Client Model
     */
    private $client_model;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize booking model
        if (class_exists('\\VandelBooking\\Booking\\BookingModel')) {
            $this->booking_model = new BookingModel();
        } else {
            error_log('BookingModel class not found');
            throw new \Exception('BookingModel class not found');
        }
        
        // Initialize client model with proper error handling
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $this->client_model = new \VandelBooking\Client\ClientModel();
        } else {
            // Try to include it directly
            $client_path = VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
            if (file_exists($client_path)) {
                require_once $client_path;
                if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
                    $this->client_model = new \VandelBooking\Client\ClientModel();
                } else {
                    error_log('ClientModel class not found after inclusion');
                }
            } else {
                error_log('ClientModel file not found at: ' . $client_path);
            }
        }
    }
    
    /**
     * Create new booking
     * 
     * @param array $booking_data Booking data
     * @return int|WP_Error Booking ID or error
     */
    public function createBooking($booking_data) {
        global $wpdb;
        try {
            // Log the booking data for debugging
            error_log('Creating booking with data: ' . print_r($booking_data, true));
            
            // Validate required fields
            $required_fields = ['service', 'customer_name', 'customer_email', 'booking_date', 'total_price'];
            foreach ($required_fields as $field) {
                if (empty($booking_data[$field])) {
                    error_log('Missing required field: ' . $field);
                    return new \WP_Error(
                        'missing_field',
                        sprintf(__('Missing required field: %s', 'vandel-booking'), $field)
                    );
                }
            }
            
            // Create client record directly if client model is not available
            if (!isset($this->client_model) || !is_object($this->client_model)) {
                error_log('Client model not available, creating client record directly');
                
                // Check if client exists by email
                $client = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}vandel_clients WHERE email = %s",
                    $booking_data['customer_email']
                ));
                
                if ($client) {
                    $client_id = $client->id;
                } else {
                    // Create new client
                    $wpdb->insert(
                        $wpdb->prefix . 'vandel_clients',
                        [
                            'email' => $booking_data['customer_email'],
                            'name' => $booking_data['customer_name'],
                            'phone' => isset($booking_data['phone']) ? $booking_data['phone'] : '',
                            'created_at' => current_time('mysql')
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                    $client_id = $wpdb->insert_id;
                }
            } else {
                // Use client model
                $client_id = $this->client_model->getOrCreateClient([
                    'email' => $booking_data['customer_email'],
                    'name' => $booking_data['customer_name'],
                    'phone' => isset($booking_data['phone']) ? $booking_data['phone'] : ''
                ]);
            }
            
            if (!$client_id) {
                error_log('Failed to create client');
                return new \WP_Error(
                    'client_creation_failed',
                    __('Failed to create client', 'vandel-booking')
                );
            }
            
            // Add client_id to booking data
            $booking_data['client_id'] = $client_id;
            
            // Create booking using model or direct insertion
            if (isset($this->booking_model) && is_object($this->booking_model)) {
                error_log('Using booking model to create booking');
                $booking_id = $this->booking_model->create($booking_data);
            } else {
                error_log('Booking model not available, creating booking record directly');
                
                // Prepare booking data for direct insertion
                $db_data = [
                    'client_id' => $client_id,
                    'service' => $booking_data['service'],
                    'sub_services' => isset($booking_data['sub_services']) ? $booking_data['sub_services'] : null,
                    'booking_date' => $booking_data['booking_date'],
                    'customer_name' => $booking_data['customer_name'],
                    'customer_email' => $booking_data['customer_email'],
                    'access_info' => isset($booking_data['access_info']) ? $booking_data['access_info'] : '',
                    'total_price' => $booking_data['total_price'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ];
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'vandel_bookings',
                    $db_data,
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']
                );
                
                if ($result === false) {
                    error_log('Database insertion error: ' . $wpdb->last_error);
                    return new \WP_Error(
                        'booking_creation_failed',
                        __('Failed to create booking: Database error', 'vandel-booking')
                    );
                }
                
                $booking_id = $wpdb->insert_id;
            }
            
            if (!$booking_id) {
                error_log('Failed to create booking: ' . $wpdb->last_error);
                return new \WP_Error(
                    'booking_creation_failed',
                    __('Failed to create booking', 'vandel-booking')
                );
            }
            
            error_log('Successfully created booking with ID: ' . $booking_id);
            return $booking_id;
            
        } catch (\Exception $e) {
            error_log('Exception in createBooking: ' . $e->getMessage());
            return new \WP_Error(
                'booking_exception',
                __('Error creating booking: ', 'vandel-booking') . $e->getMessage()
            );
        }
    }
}