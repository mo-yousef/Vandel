<?php
namespace VandelBooking\Admin;

use VandelBooking\Location\LocationModel;

/**
 * Location AJAX Handlers
 */
class LocationAjaxHandler {
    /**
     * @var LocationModel
     */
    private $location_model;

    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new LocationModel();
        } else {
            error_log('LocationModel class not found');
        }
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Debug log
        error_log('LocationAjaxHandler: Initializing hooks');
        
        // Register all the hooks needed
        add_action('wp_ajax_vandel_get_cities', [$this, 'getCities']);
        add_action('wp_ajax_vandel_get_areas', [$this, 'getAreas']);
        add_action('wp_ajax_vandel_import_locations', [$this, 'importLocations']);
        add_action('wp_ajax_vandel_export_locations', [$this, 'exportLocations']);
        add_action('wp_ajax_vandel_validate_location', [$this, 'validateLocation']);
        add_action('wp_ajax_nopriv_vandel_validate_location', [$this, 'validateLocation']);
    }

    /**
     * Get cities for a country
     */
    public function getCities() {
        // Log the incoming request for debugging
        error_log('getCities AJAX called with: ' . print_r($_POST, true));
        
        // Security check
        $nonce_verified = wp_verify_nonce($_POST['nonce'] ?? '', 'vandel_location_nonce');
        if (!$nonce_verified) {
            error_log('Nonce verification failed in getCities');
            wp_send_json_error(['message' => __('Security verification failed', 'vandel-booking')]);
            return;
        }
        
        // Get country
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(['message' => __('Country is required', 'vandel-booking')]);
            return;
        }
        
        if (!$this->location_model) {
            // Return dummy data for testing if model isn't available
            error_log('LocationModel not available, returning dummy data for cities');
            wp_send_json_success(['Stockholm', 'Gothenburg', 'Malmö', 'Uppsala']);
            return;
        }
        
        // Get cities from the model
        $cities = $this->location_model->getCities($country);
        error_log('Cities found: ' . print_r($cities, true));
        
        wp_send_json_success($cities);
    }
    
    /**
     * Get areas for a city
     */
    public function getAreas() {
        // Security check
        check_ajax_referer('vandel_location_nonce', 'nonce');
        
        // Get country and city
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        
        if (empty($country) || empty($city)) {
            wp_send_json_error(['message' => __('Country and city are required', 'vandel-booking')]);
            return;
        }
        
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        $areas = $this->location_model->getAreas($country, $city);
        
        // Format areas for select dropdown
        $formatted_areas = [];
        foreach ($areas as $area) {
            $formatted_areas[] = [
                'id' => $area->id,
                'text' => $area->area_name . ' : ' . $area->zip_code,
                'zip_code' => $area->zip_code,
                'price_adjustment' => $area->price_adjustment,
                'service_fee' => $area->service_fee
            ];
        }
        
        wp_send_json_success($formatted_areas);
    }
    
    /**
     * Import locations from CSV/Excel
     */
    public function importLocations() {
        // Security check
        check_ajax_referer('vandel_location_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'vandel-booking')]);
            return;
        }

        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'vandel-booking')]);
            return;
        }

        $file = $_FILES['file'];

        // Validate file type
        $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV or Excel file.', 'vandel-booking')]);
            return;
        }

        // Temporarily move the file
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . basename($file['name']);
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error(['message' => __('Failed to move uploaded file', 'vandel-booking')]);
            return;
        }

        try {
            // Check if we have PhpSpreadsheet available
            if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                throw new \Exception(__('PhpSpreadsheet library is required for Excel import', 'vandel-booking'));
            }
            
            // Determine file type and read
            $file_type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file_path);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($file_type);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Remove header row
            array_shift($data);

            $locations = [];
            foreach ($data as $row) {
                // Validate row has minimum required fields
                if (count($row) < 4) continue;

                $locations[] = [
                    'country' => sanitize_text_field($row[0]),
                    'city' => sanitize_text_field($row[1]),
                    'area_name' => sanitize_text_field($row[2]),
                    'zip_code' => sanitize_text_field($row[3]),
                    'price_adjustment' => isset($row[4]) ? floatval($row[4]) : 0,
                    'service_fee' => isset($row[5]) ? floatval($row[5]) : 0,
                    'is_active' => isset($row[6]) && strtolower($row[6]) === 'yes' ? 'yes' : 'no'
                ];
            }

            // Bulk import
            $results = $this->location_model->bulkImport($locations);

            // Clean up uploaded file
            unlink($file_path);

            wp_send_json_success([
                'message' => sprintf(
                    __('Import completed. %d locations imported, %d updated, %d failed.', 'vandel-booking'), 
                    $results['imported'], 
                    $results['updated'],
                    $results['failed']
                ),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            // Clean up file in case of error
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Export locations to CSV
     */
    public function exportLocations() {
        // Security check
        check_ajax_referer('vandel_location_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vandel-booking'));
        }

        // Fetch all locations
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_locations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            wp_die(__('Locations table does not exist', 'vandel-booking'));
        }
        
        $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY country, city, area_name", ARRAY_A);

        // Prepare CSV
        $csv_data = [
            ['Country', 'City', 'Area Name', 'ZIP Code', 'Price Adjustment', 'Service Fee', 'Active']
        ];

        foreach ($locations as $location) {
            $csv_data[] = [
                $location['country'],
                $location['city'],
                $location['area_name'],
                $location['zip_code'],
                $location['price_adjustment'],
                $location['service_fee'],
                $location['is_active']
            ];
        }

        // Output CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vandel_locations_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'wb');
        foreach ($csv_data as $line) {
            fputcsv($fp, $line);
        }
        fclose($fp);
        exit;
    }

    /**
     * Validate location via AJAX
     */
    public function validateLocation() {
        // Security check - Accept either booking nonce or location nonce
        if (!check_ajax_referer('vandel_booking_nonce', 'nonce', false) && 
            !check_ajax_referer('vandel_location_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security verification failed', 'vandel-booking')]);
            return;
        }

        // Get location details
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP Code cannot be empty', 'vandel-booking')]);
            return;
        }

        // Check if we have a location model
        if (!$this->location_model) {
            // Return sample data for demo/testing
            wp_send_json_success([
                'zip_code' => $zip_code,
                'area_name' => 'Demo Area',
                'city' => $city ?: 'Demo City',
                'country' => $country ?: 'Sweden',
                'price_adjustment' => 0,
                'service_fee' => 0,
                'is_active' => 'yes',
                'location_string' => 'Demo Area, Demo City'
            ]);
            return;
        }

        // Check location details
        $details = $this->location_model->getByZipCode($zip_code, $country, $city);

        if (!$details) {
            wp_send_json_error(['message' => __('Location not found', 'vandel-booking')]);
            return;
        }

        // Check if location is active
        if ($details->is_active !== 'yes') {
            wp_send_json_error(['message' => __('Sorry, we do not serve this area', 'vandel-booking')]);
            return;
        }

        wp_send_json_success([
            'id' => $details->id,
            'zip_code' => $details->zip_code,
            'area_name' => $details->area_name,
            'city' => $details->city,
            'country' => $details->country,
            'price_adjustment' => floatval($details->price_adjustment),
            'service_fee' => floatval($details->service_fee),
            'is_active' => $details->is_active,
            'location_string' => sprintf('%s, %s', $details->area_name, $details->city)
        ]);
    }
}