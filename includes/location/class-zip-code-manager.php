<?php
namespace VandelBooking\Location;

/**
 * ZIP Code Manager
 * 
 * Handles all ZIP code operations including validation, pricing calculations,
 * and integration with the booking form and admin interfaces.
 */
class ZipCodeManager {
    /**
     * @var ZipCodeModel
     */
    private $model;
    
    /**
     * @var bool
     */
    private $is_enabled;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize the model if it exists
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $this->model = new ZipCodeModel();
        }
        
        // Check if the ZIP code feature is enabled in settings
        $this->is_enabled = get_option('vandel_enable_zip_code_feature', 'no') === 'yes';
        
        // Initialize hooks if feature is enabled
        if ($this->is_enabled) {
            $this->init_hooks();
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into the booking form to add ZIP code validation
        add_filter('vandel_booking_price_adjustment', [$this, 'apply_zip_code_adjustment'], 10, 2);
        add_filter('vandel_booking_service_fee', [$this, 'apply_zip_code_service_fee'], 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_vandel_validate_zip_code', [$this, 'ajax_validate_zip_code']);
        add_action('wp_ajax_nopriv_vandel_validate_zip_code', [$this, 'ajax_validate_zip_code']);
    }
    
    /**
     * Validate a ZIP code and return its details
     * 
     * @param string $zip_code ZIP code to validate
     * @return object|bool ZIP code details or false if invalid
     */
    public function validate($zip_code) {
        if (!$this->is_enabled || !$this->model) {
            return false;
        }
        
        $zip_details = $this->model->get($zip_code);
        
        if (!$zip_details || $zip_details->is_serviceable !== 'yes') {
            return false;
        }
        
        return $zip_details;
    }
    
    /**
     * Apply ZIP code price adjustment to booking total
     * 
     * @param float $price Current price
     * @param array $booking_data Booking data including ZIP code
     * @return float Adjusted price
     */
    public function apply_zip_code_adjustment($price, $booking_data) {
        if (!$this->is_enabled || !$this->model || empty($booking_data['zip_code'])) {
            return $price;
        }
        
        $zip_details = $this->validate($booking_data['zip_code']);
        
        if ($zip_details && isset($zip_details->price_adjustment)) {
            return $price + floatval($zip_details->price_adjustment);
        }
        
        return $price;
    }
    
    /**
     * Apply ZIP code service fee to booking total
     * 
     * @param float $fee Current service fee
     * @param array $booking_data Booking data including ZIP code
     * @return float Adjusted service fee
     */
    public function apply_zip_code_service_fee($fee, $booking_data) {
        if (!$this->is_enabled || !$this->model || empty($booking_data['zip_code'])) {
            return $fee;
        }
        
        $zip_details = $this->validate($booking_data['zip_code']);
        
        if ($zip_details && isset($zip_details->service_fee)) {
            return $fee + floatval($zip_details->service_fee);
        }
        
        return $fee;
    }
    
    /**
     * AJAX handler for ZIP code validation
     */
    public function ajax_validate_zip_code() {
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        $zip_code = sanitize_text_field($_POST['zip_code']);
        
        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP Code cannot be empty', 'vandel-booking')]);
            return;
        }
        
        $zip_details = $this->validate($zip_code);
        
        if (!$zip_details) {
            wp_send_json_error(['message' => __('ZIP Code not found in our service area', 'vandel-booking')]);
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
    }
    
    /**
     * Get all serviceable ZIP codes
     * 
     * @param array $args Filter arguments
     * @return array List of ZIP codes
     */
    public function get_serviceable_zip_codes($args = []) {
        if (!$this->is_enabled || !$this->model) {
            return [];
        }
        
        return $this->model->getServiceableZipCodes($args);
    }
    
    /**
     * Check if ZIP code feature is enabled
     * 
     * @return bool
     */
    public function is_enabled() {
        return $this->is_enabled;
    }
}