<?php
namespace VandelBooking\Booking;

/**
 * Booking Manager
 */
class BookingManager {
    /**
     * Constructor
     */
    public function __construct() {
        $this->initHooks();
    }
    
    /**
     * Initialize hooks
     */
    private function initHooks() {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_ajax_get_sub_services', [$this, 'handleGetSubServices']);
        add_action('wp_ajax_nopriv_get_sub_services', [$this, 'handleGetSubServices']);
        add_action('wp_ajax_update_booking_status', [$this, 'handleUpdateBookingStatus']);
    }
    
    /**
     * Register shortcodes
     */
    public function registerShortcodes() {
        add_shortcode('vandel_booking_form', [$this, 'renderBookingForm']);
    }
    
    /**
     * Render booking form
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered booking form
     */
    public function renderBookingForm($atts) {
        $form = new \VandelBooking\Frontend\BookingForm();
        return $form->render($atts);
    }
    
    /**
     * Handle AJAX request for sub-services
     */
    public function handleGetSubServices() {
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        if (!$service_id) {
            wp_send_json_error(['message' => 'Invalid service ID']);
            return;
        }
        
        $assigned_sub_services = get_post_meta($service_id, '_vandel_assigned_sub_services', true);
        if (!is_array($assigned_sub_services)) {
            $assigned_sub_services = [];
        }
        
        $sub_services = [];
        foreach ($assigned_sub_services as $sub_service_id) {
            $sub_service = get_post($sub_service_id);
            if ($sub_service && $sub_service->post_type === 'vandel_sub_service') {
                $sub_services[] = $sub_service;
            }
        }
        
        ob_start();
        $form = new \VandelBooking\Frontend\BookingForm();
        $form->renderSubServices($sub_services);
        
        wp_send_json_success(['html' => ob_get_clean()]);
    }
    
    /**
     * Handle AJAX request to update booking status
     */
    public function handleUpdateBookingStatus() {
        check_ajax_referer('vandel_booking_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $status = sanitize_text_field($_POST['status']);
        
        $valid_statuses = ['pending', 'confirmed', 'completed', 'canceled'];
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        $booking_model = new BookingModel();
        $result = $booking_model->updateStatus($booking_id, $status);
        
        if ($result) {
            // Send notification if enabled
            $notification = new BookingNotification();
            $notification->sendStatusUpdateNotification($booking_id, $status);
            
            wp_send_json_success([
                'message' => 'Status updated successfully',
                'status' => $status
            ]);
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    /**
     * Create new booking
     * 
     * @param array $booking_data Booking data
     * @return int|WP_Error Booking ID or error
     */
    public function createBooking($booking_data) {
        // Validate booking data
        $validator = new BookingValidator();
        $validation = $validator->validate($booking_data);
        
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Create or update client
        $client_model = new \VandelBooking\Client\ClientModel();
        $client_id = $client_model->getOrCreateClient([
            'email' => $booking_data['email'],
            'name' => $booking_data['name'],
            'phone' => $booking_data['phone'] ?? ''
        ]);
        
        // Create booking
        $booking_model = new BookingModel();
        $booking_id = $booking_model->create([
            'client_id' => $client_id,
            'service' => $booking_data['service'],
            'sub_services' => isset($booking_data['options']) ? $booking_data['options'] : [],
            'booking_date' => $booking_data['date'],
            'customer_name' => $booking_data['name'],
            'customer_email' => $booking_data['email'],
            'phone' => $booking_data['phone'] ?? '',
            'access_info' => $booking_data['access_info'] ?? '',
            'access_type' => $booking_data['access_type'] ?? '',
            'total_price' => $booking_data['total'],
            'comments' => $booking_data['comments'] ?? ''
        ]);
        
        if ($booking_id) {
            // Send notifications
            $notification = new BookingNotification();
            $notification->sendClientConfirmation($booking_id);
            $notification->sendAdminNotification($booking_id);
        }
        
        return $booking_id;
    }
    
    /**
     * Get booking by ID
     * 
     * @param int $booking_id Booking ID
     * @return object|false Booking object or false if not found
     */
    public function getBooking($booking_id) {
        $booking_model = new BookingModel();
        return $booking_model->get($booking_id);
    }
    
    /**
     * Get bookings with filters
     * 
     * @param array $args Filter arguments
     * @return array Bookings
     */
    public function getBookings($args = []) {
        $booking_model = new BookingModel();
        return $booking_model->getAll($args);
    }
    
    /**
     * Add note to booking
     * 
     * @param int $booking_id Booking ID
     * @param string $note_content Note content
     * @param int $user_id User ID
     * @return int|false Note ID or false if failed
     */
    public function addBookingNote($booking_id, $note_content, $user_id = 0) {
        $note_model = new \VandelBooking\Booking\NoteModel();
        return $note_model->addNote($booking_id, $note_content, $user_id);
    }
    
    /**
     * Delete booking note
     * 
     * @param int $note_id Note ID
     * @param int $booking_id Booking ID
     * @param int $user_id User ID
     * @return bool Whether the note was deleted
     */
    public function deleteBookingNote($note_id, $booking_id, $user_id) {
        $note_model = new \VandelBooking\Booking\NoteModel();
        return $note_model->deleteNote($note_id, $booking_id, $user_id);
    }
}