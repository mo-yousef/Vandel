<?php
namespace VandelBooking\Admin;

/**
 * Location AJAX Handler
 */
class LocationAjaxHandler {
    /**
     * @var \VandelBooking\Location\LocationModel
     */
    private $location_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize location model
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new \VandelBooking\Location\LocationModel();
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_vandel_save_location', [$this, 'ajaxSaveLocation']);
        add_action('wp_ajax_vandel_delete_location', [$this, 'ajaxDeleteLocation']);
        add_action('wp_ajax_vandel_import_locations', [$this, 'ajaxImportLocations']);
        add_action('wp_ajax_vandel_get_cities', [$this, 'ajaxGetCities']);
        add_action('wp_ajax_vandel_get_areas', [$this, 'ajaxGetAreas']);
        add_action('wp_ajax_vandel_validate_location', [$this, 'ajaxValidateLocation']);
        add_action('wp_ajax_nopriv_vandel_validate_location', [$this, 'ajaxValidateLocation']);
    }
    
    /**
     * AJAX handler for saving location
     */
    public function ajaxSaveLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get form data
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $location_data = [
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'city' => isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '',
            'area_name' => isset($_POST['area_name']) ? sanitize_text_field($_POST['area_name']) : '',
            'zip_code' => isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '',
            'price_adjustment' => isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0,
            'service_fee' => isset($_POST['service_fee']) ? floatval($_POST['service_fee']) : 0,
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] === 'yes' ? 'yes' : 'no'
        ];
        
        // Validate required fields
        if (empty($location_data['country']) || empty($location_data['city']) || 
            empty($location_data['area_name']) || empty($location_data['zip_code'])) {
            wp_send_json_error(['message' => __('Please fill all required fields', 'vandel-booking')]);
            return;
        }
        
        // Update or add location
        if ($id > 0) {
            $result = $this->location_model->update($id, $location_data);
            $message = __('Location updated successfully', 'vandel-booking');
        } else {
            $result = $this->location_model->add($location_data);
            $message = __('Location added successfully', 'vandel-booking');
        }
        
        if ($result) {
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to save location', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for deleting location
     */
    public function ajaxDeleteLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get location ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid location ID', 'vandel-booking')]);
            return;
        }
        
        // Delete location
        $result = $this->location_model->delete($id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Location deleted successfully', 'vandel-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete location', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for importing locations
     */
    public function ajaxImportLocations() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'vandel-booking')]);
            return;
        }
        
        // Read CSV file
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$file) {
            wp_send_json_error(['message' => __('Unable to open file', 'vandel-booking')]);
            return;
        }
        
        // Get headers
        $headers = fgetcsv($file);
        
        // Check required headers
        $required_headers = ['country', 'city', 'area_name', 'zip_code'];
        $missing_headers = array_diff($required_headers, $headers);
        
        if (!empty($missing_headers)) {
            fclose($file);
            wp_send_json_error([
                'message' => sprintf(
                    __('Missing required headers: %s', 'vandel-booking'),
                    implode(', ', $missing_headers)
                )
            ]);
            return;
        }
        
        // Parse locations
        $locations = [];
        while (($row = fgetcsv($file)) !== false) {
            $location = array_combine($headers, $row);
            $locations[] = $location;
        }
        
        fclose($file);
        
        if (empty($locations)) {
            wp_send_json_error(['message' => __('No locations found in file', 'vandel-booking')]);
            return;
        }
        
        // Import locations
        $result = $this->location_model->bulkImport($locations);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Import completed. %d locations imported, %d updated, %d failed.', 'vandel-booking'),
                $result['imported'],
                $result['updated'],
                $result['failed']
            )
        ]);
    }
    
    /**
     * AJAX handler for getting cities
     */
    public function ajaxGetCities() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get country
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(['message' => __('Country is required', 'vandel-booking')]);
            return;
        }
        
        // Get cities
        $cities = $this->location_model->getCities($country);
        
        wp_send_json_success($cities);
    }
    
    /**
     * AJAX handler for getting areas
     */
    public function ajaxGetAreas() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get country and city
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        
        if (empty($country) || empty($city)) {
            wp_send_json_error(['message' => __('Country and city are required', 'vandel-booking')]);
            return;
        }
        
        // Get areas
        $areas = $this->location_model->getAreas($country, $city);
        
        wp_send_json_success($areas);
    }
    
    /**
     * AJAX handler for validating location
     */
    public function ajaxValidateLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_booking_nonce', 'nonce', false) && 
            !check_ajax_referer('vandel_location_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get ZIP code
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        
        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP code is required', 'vandel-booking')]);
            return;
        }
        
        // Get location by ZIP code
        $location = $this->location_model->getByZipCode($zip_code);
        
        if (!$location) {
            wp_send_json_error(['message' => __('Location not found for this ZIP code', 'vandel-booking')]);
            return;
        }
        
        if ($location->is_active !== 'yes') {
            wp_send_json_error(['message' => __('This location is not active for bookings', 'vandel-booking')]);
            return;
        }
        
        wp_send_json_success([
            'id' => $location->id,
            'country' => $location->country,
            'city' => $location->city,
            'area_name' => $location->area_name,
            'zip_code' => $location->zip_code,
            'price_adjustment' => floatval($location->price_adjustment),
            'service_fee' => floatval($location->service_fee),
            'location_string' => sprintf('%s, %s', $location->area_name, $location->city)
        ]);
    }
}