<?php
namespace VandelBooking\API;

/**
 * API Loader
 */
class APILoader {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    
    /**
     * Register API routes
     */
    public function registerRoutes() {
        register_rest_route('vandel/v1', '/submit-booking', [
            'methods' => 'POST',
            'callback' => [$this, 'handleBookingSubmission'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('vandel/v1', '/get-services', [
            'methods' => 'GET',
            'callback' => [$this, 'getServices'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Handle booking submission
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handleBookingSubmission($request) {
        try {
            // Get and validate parameters
            $params = $request->get_params();
            
            // Log incoming data in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Booking submission params: " . print_r($params, true));
            }
            
            // Validate required fields
            $required_fields = ['service', 'name', 'email', 'date'];
            foreach ($required_fields as $field) {
                if (empty($params[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }
            
            // Create booking
            $booking_manager = new \VandelBooking\Booking\BookingManager();
            $booking_id = $booking_manager->createBooking($params);
            
            if (is_wp_error($booking_id)) {
                throw new \Exception($booking_id->get_error_message());
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => __('Booking submitted successfully', 'vandel-booking'),
                'booking_id' => $booking_id
            ], 200);
            
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Booking submission error: " . $e->getMessage());
            }
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Get services
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function getServices($request) {
        $services = get_posts([
            'post_type' => 'vandel_service',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        $formatted_services = [];
        
        foreach ($services as $service) {
            $formatted_services[] = [
                'id' => $service->ID,
                'title' => $service->post_title,
                'subtitle' => get_post_meta($service->ID, '_vandel_service_subtitle', true),
                'description' => get_post_meta($service->ID, '_vandel_service_description', true),
                'price' => get_post_meta($service->ID, '_vandel_service_base_price', true),
                'icon' => get_the_post_thumbnail_url($service->ID, 'thumbnail')
            ];
        }
        
        return new \WP_REST_Response($formatted_services, 200);
    }
}