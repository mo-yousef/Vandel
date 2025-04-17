<?php
namespace VandelBooking\Location;

/**
 * Location CSV Importer
 * 
 * Handles importing location data from CSV files for different countries
 */
class LocationImporter {
    /**
     * @var LocationModel Location model instance
     */
    private $location_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new LocationModel();
        }
    }
    
    /**
     * Import locations from CSV file
     * 
     * @param string $file Path to CSV file
     * @param string $country Country code or name
     * @param array $mapping Column mapping (keys: zip_code, city, area, state)
     * @return array Results with counts
     */
    public function importFromCsv($file, $country, $mapping = []) {
        $results = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        // Check if file exists
        if (!file_exists($file)) {
            $results['errors'][] = "File not found: $file";
            return $results;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            $results['errors'][] = "Location model not available";
            return $results;
        }
        
        // Set default column mapping if not provided
        if (empty($mapping)) {
            $mapping = [
                'zip_code' => 0,  // First column is postal code
                'city' => 1,      // Second column is city
                'area' => 2,      // Third column is area/district
                'state' => 3      // Fourth column is state/province
            ];
        }
        
        // Open CSV file
        $handle = fopen($file, 'r');
        if (!$handle) {
            $results['errors'][] = "Failed to open file: $file";
            return $results;
        }
        
        // Skip header row if exists
        $header = fgetcsv($handle);
        
        // Process rows
        while (($data = fgetcsv($handle)) !== false) {
            $results['total']++;
            
            // Skip empty rows
            if (empty($data) || count($data) < 2) {
                continue;
            }
            
            // Extract data based on mapping
            $zip_code = isset($data[$mapping['zip_code']]) ? $data[$mapping['zip_code']] : '';
            $city = isset($data[$mapping['city']]) ? $data[$mapping['city']] : '';
            $area = isset($data[$mapping['area']]) ? $data[$mapping['area']] : '';
            $state = isset($data[$mapping['state']]) ? $data[$mapping['state']] : '';
            
            // Skip if missing required fields
            if (empty($zip_code) || empty($city)) {
                $results['failed']++;
                $results['errors'][] = "Missing required fields in row: " . implode(',', $data);
                continue;
            }
            
            // Prepare location data
            $location_data = [
                'country' => $country,
                'city' => $city,
                'area_name' => $area ?: $city,
                'zip_code' => $zip_code,
                'state' => $state,
                'price_adjustment' => 0,
                'service_fee' => 0,
                'is_active' => 'yes'
            ];
            
            // Check if location already exists
            $existing = $this->location_model->getByZipCode($zip_code, $country, $city);
            
            if ($existing) {
                // Update existing record
                $update_result = $this->location_model->update($existing->id, $location_data);
                
                if ($update_result) {
                    $results['updated']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update location: $zip_code, $city";
                }
            } else {
                // Add new record
                $add_result = $this->location_model->add($location_data);
                
                if ($add_result) {
                    $results['imported']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to add location: $zip_code, $city";
                }
            }
        }
        
        fclose($handle);
        
        return $results;
    }
    
    /**
     * Get a list of available country import files
     * 
     * @return array List of available countries with file info
     */
    public function getAvailableCountries() {
        $countries = [];
        $import_dir = VANDEL_PLUGIN_DIR . 'data/locations/';
        
        // Ensure directory exists
        if (!is_dir($import_dir)) {
            if (!mkdir($import_dir, 0755, true)) {
                return $countries;
            }
        }
        
        // Scan directory for CSV files
        $files = glob($import_dir . '*.csv');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $country_code = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
            
            // Extract info from first row to determine the format
            $handle = fopen($file, 'r');
            if ($handle) {
                $header = fgetcsv($handle);
                $sample_row = fgetcsv($handle);
                fclose($handle);
                
                $countries[$country_code] = [
                    'name' => $this->getCountryName($country_code),
                    'file' => $file,
                    'header' => $header,
                    'sample' => $sample_row,
                    'count' => $this->countCsvRows($file) - 1 // Subtract header row
                ];
            }
        }
        
        return $countries;
    }
    
    /**
     * Count rows in a CSV file
     * 
     * @param string $file CSV file path
     * @return int Number of rows
     */
    private function countCsvRows($file) {
        $count = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (fgetcsv($handle) !== false) {
                $count++;
            }
            fclose($handle);
        }
        
        return $count;
    }
    
    /**
     * Get country name from country code
     * 
     * @param string $country_code Country code
     * @return string Country name
     */
    private function getCountryName($country_code) {
        $countries = [
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'US' => 'United States',
            'UK' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy'
        ];
        
        return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }
    
    /**
     * Generate sample CSV file
     * 
     * @param string $country Country code
     * @return string Path to generated sample file
     */
    public function generateSampleCsv($country) {
        $sample_data = [];
        
        // Add sample header
        $sample_data[] = ['postal_code', 'city', 'area', 'state'];
        
        // Add sample rows based on country
        if ($country === 'SE') {
            // Sample data for Sweden
            $sample_data[] = ['11152', 'Stockholm', 'Norrmalm', 'Stockholm County'];
            $sample_data[] = ['11346', 'Stockholm', 'Östermalm', 'Stockholm County'];
            $sample_data[] = ['11553', 'Stockholm', 'Södermalm', 'Stockholm County'];
            $sample_data[] = ['16440', 'Stockholm', 'Kista', 'Stockholm County'];
            $sample_data[] = ['41115', 'Göteborg', 'Centrum', 'Västra Götaland County'];
            $sample_data[] = ['41451', 'Göteborg', 'Majorna', 'Västra Götaland County'];
            $sample_data[] = ['21119', 'Malmö', 'Centrum', 'Skåne County'];
            $sample_data[] = ['75236', 'Uppsala', 'Centrum', 'Uppsala County'];
        } else {
            // Generic sample data for other countries
            $sample_data[] = ['10001', 'Sample City', 'Downtown', 'Sample State'];
            $sample_data[] = ['10002', 'Sample City', 'Uptown', 'Sample State'];
            $sample_data[] = ['20001', 'Another City', 'West End', 'Another State'];
            $sample_data[] = ['20002', 'Another City', 'East End', 'Another State'];
        }
        
        // Ensure directory exists
        $import_dir = VANDEL_PLUGIN_DIR . 'data/locations/';
        if (!is_dir($import_dir)) {
            mkdir($import_dir, 0755, true);
        }
        
        // Create file path
        $file_path = $import_dir . strtolower($country) . '.csv';
        
        // Write CSV file
        $handle = fopen($file_path, 'w');
        if ($handle) {
            foreach ($sample_data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }
        
        return $file_path;
    }
}
