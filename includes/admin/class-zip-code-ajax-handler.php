<?php
namespace VandelBooking\Admin;

use VandelBooking\Location\ZipCodeModel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ZIP Code AJAX Handlers
 */
class ZipCodeAjaxHandler {
    /**
     * @var ZipCodeModel
     */
    private $zip_code_model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->zip_code_model = new ZipCodeModel();
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        add_action('wp_ajax_vandel_import_zip_codes', [$this, 'importZipCodes']);
        add_action('wp_ajax_vandel_export_zip_codes', [$this, 'exportZipCodes']);
        add_action('wp_ajax_vandel_validate_zip_code', [$this, 'validateZipCode']);
    }

    /**
     * Import ZIP Codes from CSV/Excel
     */
    public function importZipCodes() {
        // Security check
        check_ajax_referer('vandel_zip_code_nonce', 'nonce') {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'vandel-booking')]);
        }

        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'vandel-booking')]);
        }

        $file = $_FILES['file'];

        // Validate file type
        $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload a CSV or Excel file.', 'vandel-booking')]);
        }

        // Temporarily move the file
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . basename($file['name']);
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error(['message' => __('Failed to move uploaded file', 'vandel-booking')]);
        }

        try {
            // Determine file type and read
            $file_type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file_path);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($file_type);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Remove header row
            array_shift($data);

            $imported_count = 0;
            $failed_count = 0;

            foreach ($data as $row) {
                // Validate row has minimum required fields
                if (count($row) < 4) continue;

                $zip_code_data = [
                    'zip_code' => sanitize_text_field($row[0]),
                    'city' => sanitize_text_field($row[1]),
                    'state' => sanitize_text_field($row[2] ?? ''),
                    'country' => sanitize_text_field($row[3]),
                    'price_adjustment' => floatval($row[4] ?? 0),
                    'service_fee' => floatval($row[5] ?? 0),
                    'is_serviceable' => isset($row[6]) && $row[6] === 'yes' ? 'yes' : 'no'
                ];

                // Attempt to add ZIP code
                $result = $this->zip_code_model->add($zip_code_data);

                if ($result) {
                    $imported_count++;
                } else {
                    $failed_count++;
                }
            }

            // Clean up uploaded file
            unlink($file_path);

            wp_send_json_success([
                'message' => sprintf(
                    __('Import completed. %d ZIP codes imported, %d failed.', 'vandel-booking'), 
                    $imported_count, 
                    $failed_count
                )
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
     * Export ZIP Codes to CSV
     */
    public function exportZipCodes() {
        // Security check
        check_ajax_referer('vandel_zip_code_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'vandel-booking'));
        }

        // Fetch all ZIP Codes
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_zip_codes';
        $zip_codes = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        // Prepare CSV
        $csv_data = [
            ['ZIP Code', 'City', 'State', 'Country', 'Price Adjustment', 'Service Fee', 'Serviceable']
        ];

        foreach ($zip_codes as $zip_code) {
            $csv_data[] = [
                $zip_code['zip_code'],
                $zip_code['city'],
                $zip_code['state'],
                $zip_code['country'],
                $zip_code['price_adjustment'],
                $zip_code['service_fee'],
                $zip_code['is_serviceable']
            ];
        }

        // Output CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vandel_zip_codes_' . date('Y-m-d') . '.csv"');
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
     * Validate ZIP Code via AJAX
     */
    public function validateZipCode() {
        // Security check
        check_ajax_referer('vandel_zip_code_nonce', 'nonce');

        // Get ZIP Code
        $zip_code = sanitize_text_field($_POST['zip_code']);

        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP Code cannot be empty', 'vandel-booking')]);
        }

        // Check ZIP Code details
        $details = $this->zip_code_model->get($zip_code);

        if (!$details) {
            wp_send_json_error(['message' => __('ZIP Code not found', 'vandel-booking')]);
        }

        wp_send_json_success([
            'zip_code' => $details->zip_code,
            'city' => $details->city,
            'state' => $details->state,
            'country' => $details->country,
            'price_adjustment' => $details->price_adjustment,
            'service_fee' => $details->service_fee,
            'is_serviceable' => $details->is_serviceable
        ]);
    }
}