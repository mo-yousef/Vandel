<?php
namespace VandelBooking\API;

use VandelBooking\Location\ZipCodeModel;

/**
 * ZIP Code API Endpoint
 */
class ZipCodeAPI {
    /**
     * @var ZipCodeModel
     */
    private $zip_code_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $this->zip_code_model = new ZipCodeModel();
        }
        $this->registerRoutes();
    }
    
    /**
     * Register API routes
     */
    private function registerRoutes() {
        add_action('rest_api_init', function() {
            register_rest_route('vandel/v1', '/validate-zip-code', [
                'methods' => 'POST',
                'callback' => [$this, 'validateZipCode'],
                'permission_callback' => '__return_true'
            ]);
            
            register_rest_route('vandel/v1', '/get-zip-codes', [
                'methods' => 'GET',
                'callback' => [$this, 'getZipCodes'],
                'permission_callback' => '__return_true'
            ]);
        });
    }
    
    /**
     * Validate ZIP Code
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function validateZipCode($request) {
        $zip_code = sanitize_text_field($request->get_param('zip_code'));
        
        // Check if ZIP Code feature is enabled
        $zip_feature_enabled = get_option('vandel_enable_zip_code_feature', 'no');
        if ($zip_feature_enabled !== 'yes') {
            return new \WP_REST_Response([
                'valid' => false,
                'message' => __('ZIP Code feature is currently disabled.', 'vandel-booking')
            ], 400);
        }
        
        // Validate ZIP Code
        if (empty($zip_code)) {
            return new \WP_REST_Response([
                'valid' => false,
                'message' => __('ZIP Code cannot be empty.', 'vandel-booking')
            ], 400);
        }
        
        // Retrieve ZIP Code details
        if ($this->zip_code_model) {
            $zip_details = $this->zip_code_model->get($zip_code);
            
            if (!$zip_details) {
                return new \WP_REST_Response([
                    'valid' => false,
                    'message' => __('Invalid or unsupported ZIP Code.', 'vandel-booking')
                ], 404);
            }
            
            // Check if area is serviceable
            if ($zip_details->is_serviceable !== 'yes') {
                return new \WP_REST_Response([
                    'valid' => false,
                    'message' => __('Sorry, we do not serve this area.', 'vandel-booking')
                ], 400);
            }
            
            // Calculate price adjustment
            $base_price = get_option('vandel_base_service_price', 0);
            $adjusted_price = $base_price + $zip_details->price_adjustment;
            
            return new \WP_REST_Response([
                'valid' => true,
                'details' => [
                    'city' => $zip_details->city,
                    'state' => $zip_details->state,
                    'country' => $zip_details->country,
                    'base_price' => $base_price,
                    'price_adjustment' => $zip_details->price_adjustment,
                    'service_fee' => $zip_details->service_fee,
                    'adjusted_price' => $adjusted_price
                ]
            ], 200);
        } else {
            // Fall back to default response if model isn't available
            return new \WP_REST_Response([
                'valid' => true,
                'details' => [
                    'city' => 'Sample City',
                    'state' => 'ST',
                    'country' => 'Sample Country',
                    'base_price' => 0,
                    'price_adjustment' => 0,
                    'service_fee' => 0,
                    'adjusted_price' => 0
                ]
            ], 200);
        }
    }
    
    /**
     * Get ZIP Codes
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getZipCodes($request) {
        $country = $request->get_param('country');
        $state = $request->get_param('state');
        $city = $request->get_param('city');
        
        $args = [
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'limit' => 100
        ];
        
        if ($this->zip_code_model) {
            $zip_codes = $this->zip_code_model->getServiceableZipCodes($args);
            return new \WP_REST_Response($zip_codes, 200);
        } else {
            // Return empty array if model isn't available
            return new \WP_REST_Response([], 200);
        }
    }
}