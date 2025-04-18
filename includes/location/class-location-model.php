<?php
namespace VandelBooking\Location;

/**
 * Location Model
 * Handles hierarchical location data (Country > City > Area with ZIP code)
 */
class LocationModel {
    /**
     * @var string Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_locations';
        
        // Ensure the table exists
        $this->ensureTableExists();
    }
    
    /**
     * Ensure the locations table exists
     */
    private function ensureTableExists() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
        
        if (!$table_exists) {
            error_log('VandelBooking: Locations table does not exist, attempting to create it');
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $this->table (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                country VARCHAR(100) NOT NULL,
                city VARCHAR(100) NOT NULL,
                area_name VARCHAR(255) NOT NULL,
                zip_code VARCHAR(20) NOT NULL,
                price_adjustment DECIMAL(10, 2) DEFAULT 0,
                service_fee DECIMAL(10, 2) DEFAULT 0,
                is_active ENUM('yes', 'no') DEFAULT 'yes',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY location_zip (country, city, area_name, zip_code)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Add a new location
     * 
     * @param array $data Location data
     * @return int|false Location ID or false if failed
     */
    public function add($data) {
        global $wpdb;
        
        // Log the data being added
        error_log('Adding location with data: ' . print_r($data, true));
        
        // Make sure required fields are present
        if (empty($data['country']) || empty($data['city']) || empty($data['area_name']) || empty($data['zip_code'])) {
            error_log('Location add failed: required fields missing');
            return false;
        }
        
        $insert_data = [
            'country'          => $data['country'],
            'city'             => $data['city'],
            'area_name'        => $data['area_name'],
            'zip_code'         => $data['zip_code'],
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee'      => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_active'        => isset($data['is_active']) ? $data['is_active'] : 'yes',
            'created_at'       => current_time('mysql')
        ];
        
        // Make sure table exists before attempting insert
        $this->ensureTableExists();
        
        // Check if location already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE country = %s AND city = %s AND area_name = %s AND zip_code = %s",
                $data['country'],
                $data['city'],
                $data['area_name'],
                $data['zip_code']
            )
        );
        
        if ($exists) {
            error_log('Location already exists: ' . $data['country'] . ' > ' . $data['city'] . ' > ' . $data['area_name'] . ' (' . $data['zip_code'] . ')');
            return false;
        }
        
        // Perform the insert
        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            error_log('Error adding location: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('Location added successfully: ' . $data['country'] . ' > ' . $data['city'] . ' > ' . $data['area_name'] . ' (' . $data['zip_code'] . ')');
        return $wpdb->insert_id;
    }
    
    /**
     * Get location details by ID
     * 
     * @param int $id Location ID
     * @return object|null Location details
     */
    public function getById($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }
    
/**
 * Get location by ZIP code
 * 
 * @param string $zip_code ZIP code
 * @param string $country Country (optional)
 * @param string $city City (optional)
 * @return object|false Location object or false if not found
 */
public function getByZipCode($zip_code, $country = '', $city = '') {
    global $wpdb;
    
    $query = "SELECT * FROM {$this->table} WHERE zip_code = %s";
    $values = [$zip_code];
    
    if (!empty($country)) {
        $query .= " AND country = %s";
        $values[] = $country;
    }
    
    if (!empty($city)) {
        $query .= " AND city = %s";
        $values[] = $city;
    }
    
    $query .= " LIMIT 1";
    
    return $wpdb->get_row($wpdb->prepare($query, $values));
}
    
    /**
     * Update a location
     * 
     * @param int $id Location ID
     * @param array $data Updated data
     * @return bool Whether update was successful
     */
    public function update($id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
        // Only update provided fields
        if (isset($data['country'])) {
            $update_data['country'] = $data['country'];
            $formats[] = '%s';
        }
        
        if (isset($data['city'])) {
            $update_data['city'] = $data['city'];
            $formats[] = '%s';
        }
        
        if (isset($data['area_name'])) {
            $update_data['area_name'] = $data['area_name'];
            $formats[] = '%s';
        }
        
        if (isset($data['zip_code'])) {
            $update_data['zip_code'] = $data['zip_code'];
            $formats[] = '%s';
        }
        
        if (isset($data['price_adjustment'])) {
            $update_data['price_adjustment'] = floatval($data['price_adjustment']);
            $formats[] = '%f';
        }
        
        if (isset($data['service_fee'])) {
            $update_data['service_fee'] = floatval($data['service_fee']);
            $formats[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'];
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a location
     * 
     * @param int $id Location ID
     * @return bool Whether deletion was successful
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get available countries
     * 
     * @return array List of countries
     */
    public function getCountries() {
        global $wpdb;
        
        $results = $wpdb->get_results("SELECT DISTINCT country FROM {$this->table} WHERE is_active = 'yes' ORDER BY country ASC");
        
        return array_map(function($item) {
            return $item->country;
        }, $results);
    }
    
    /**
     * Get cities for a specific country
     * 
     * @param string $country Country name
     * @return array List of cities
     */
    public function getCities($country) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT city FROM {$this->table} WHERE country = %s AND is_active = 'yes' ORDER BY city ASC",
            $country
        ));
        
        return array_map(function($item) {
            return $item->city;
        }, $results);
    }
    
    /**
     * Get areas for a specific city
     * 
     * @param string $country Country name
     * @param string $city City name
     * @return array List of areas with zip codes
     */
    public function getAreas($country, $city) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, area_name, zip_code, price_adjustment, service_fee 
             FROM {$this->table} 
             WHERE country = %s AND city = %s AND is_active = 'yes' 
             ORDER BY area_name ASC",
            $country,
            $city
        ));
    }
    
    /**
     * Search locations
     * 
     * @param string $term Search term
     * @param int $limit Maximum number of results
     * @return array Matching locations
     */
    public function search($term, $limit = 10) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE zip_code LIKE %s OR area_name LIKE %s OR city LIKE %s 
             ORDER BY country, city, area_name
             LIMIT %d",
            $like, $like, $like, $limit
        ));
    }
    
    /**
     * Import locations from array
     * 
     * @param array $locations Array of location data
     * @return array Results with counts of success/failure
     */
    public function bulkImport($locations) {
        $results = [
            'total' => count($locations),
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($locations as $location) {
            // Check if location already exists
            global $wpdb;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE country = %s AND city = %s AND area_name = %s AND zip_code = %s",
                $location['country'],
                $location['city'],
                $location['area_name'],
                $location['zip_code']
            ));
            
            if ($existing) {
                // Update existing record
                $success = $this->update($existing->id, $location);
                if ($success) {
                    $results['updated']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update: {$location['country']} > {$location['city']} > {$location['area_name']} ({$location['zip_code']})";
                }
            } else {
                // Add new record
                $result = $this->add($location);
                if ($result) {
                    $results['imported']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to import: {$location['country']} > {$location['city']} > {$location['area_name']} ({$location['zip_code']})";
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Initialize Sweden locations
     * 
     * @return bool Whether initialization was successful
     */
    public function initializeSweden() {
        // Check if Sweden locations already exist
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE country = %s",
            'Sweden'
        ));
        
        if ($count > 0) {
            return false; // Already initialized
        }
        
        // Sample data for Sweden locations
        $locations = [
            // Uppsala areas
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Almunge', 'zip_code' => '74012', 'price_adjustment' => 10, 'service_fee' => 5],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Alder', 'zip_code' => '75592', 'price_adjustment' => 5, 'service_fee' => 2.5],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Älvkarleby', 'zip_code' => '81421', 'price_adjustment' => 15, 'service_fee' => 7.5],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Balinge', 'zip_code' => '75594', 'price_adjustment' => 8, 'service_fee' => 4],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Bälsta', 'zip_code' => '74791', 'price_adjustment' => 12, 'service_fee' => 6],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Björklinge', 'zip_code' => '74395', 'price_adjustment' => 7, 'service_fee' => 3.5],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Edsbro', 'zip_code' => '76010', 'price_adjustment' => 20, 'service_fee' => 10],
            ['country' => 'Sweden', 'city' => 'Uppsala', 'area_name' => 'Enköping', 'zip_code' => '74532', 'price_adjustment' => 15, 'service_fee' => 7.5],
            
            // Stockholm areas
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Bromma', 'zip_code' => '16733', 'price_adjustment' => 25, 'service_fee' => 12.5],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Gamla Stan', 'zip_code' => '11131', 'price_adjustment' => 30, 'service_fee' => 15],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Kista', 'zip_code' => '16440', 'price_adjustment' => 20, 'service_fee' => 10],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Södermalm', 'zip_code' => '11826', 'price_adjustment' => 35, 'service_fee' => 17.5],
            
            // Gothenburg areas
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Askim', 'zip_code' => '43631', 'price_adjustment' => 18, 'service_fee' => 9],
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Frölunda', 'zip_code' => '42132', 'price_adjustment' => 15, 'service_fee' => 7.5],
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Hisingen', 'zip_code' => '41702', 'price_adjustment' => 20, 'service_fee' => 10],
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Majorna', 'zip_code' => '41451', 'price_adjustment' => 22, 'service_fee' => 11]
        ];
        
        $results = $this->bulkImport($locations);
        
        return $results['imported'] > 0;
    }
}