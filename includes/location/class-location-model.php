<?php
namespace VandelBooking\Location;

/**
 * Location Model
 * Handles all location-related database operations
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
     * @return int|false Location ID or false on failure
     */
    public function add($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['country']) || empty($data['city']) || 
            empty($data['area_name']) || empty($data['zip_code'])) {
            return false;
        }
        
        // Check if this location already exists
        $exists = $this->getByZipCode($data['zip_code']);
        if ($exists) {
            return $this->update($exists->id, $data);
        }
        
        // Prepare data for insertion
        $insert_data = [
            'country' => $data['country'],
            'city' => $data['city'],
            'area_name' => $data['area_name'],
            'zip_code' => $data['zip_code'],
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee' => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_active' => isset($data['is_active']) ? $data['is_active'] : 'yes',
            'created_at' => current_time('mysql')
        ];
        
        $formats = ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s'];
        
        $result = $wpdb->insert($this->table, $insert_data, $formats);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing location
     *
     * @param int $id Location ID
     * @param array $data Location data
     * @return bool Success
     */
    public function update($id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
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
     * @return bool Success
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
     * Get location by ID
     *
     * @param int $id Location ID
     * @return object|false Location object or false if not found
     */
    public function get($id) {
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
     * @return object|false Location object or false if not found
     */
    public function getByZipCode($zip_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE zip_code = %s",
            $zip_code
        ));
    }
    
    /**
     * Get all locations with filtering options
     *
     * @param array $args Filter arguments
     * @return array Locations
     */
    public function getAll($args = []) {
        global $wpdb;
        
        $defaults = [
            'country' => '',
            'city' => '',
            'is_active' => '',
            'search' => '',
            'orderby' => 'id',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = [];
        $values = [];
        
        if (!empty($args['country'])) {
            $where[] = 'country = %s';
            $values[] = $args['country'];
        }
        
        if (!empty($args['city'])) {
            $where[] = 'city = %s';
            $values[] = $args['city'];
        }
        
        if (!empty($args['is_active'])) {
            $where[] = 'is_active = %s';
            $values[] = $args['is_active'];
        }
        
        if (!empty($args['search'])) {
            $where[] = '(country LIKE %s OR city LIKE %s OR area_name LIKE %s OR zip_code LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        // Build query
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Sanitize orderby field
        $allowed_orderby = ['id', 'country', 'city', 'area_name', 'zip_code', 'created_at'];
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'id';
        }
        
        // Sanitize order
        $args['order'] = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add limit
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare final query with values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Get results
        return $wpdb->get_results($query);
    }
    
    /**
     * Get all unique countries from the database
     *
     * @return array List of countries
     */
    public function getCountries() {
        global $wpdb;
        
        return $wpdb->get_col("SELECT DISTINCT country FROM {$this->table} ORDER BY country ASC");
    }
    
    /**
     * Get all cities for a given country
     *
     * @param string $country Country name
     * @return array List of cities
     */
    public function getCities($country) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT city FROM {$this->table} WHERE country = %s ORDER BY city ASC",
            $country
        ));
    }
    
    /**
     * Get all areas for a given country and city
     *
     * @param string $country Country name
     * @param string $city City name
     * @return array List of areas
     */
    public function getAreas($country, $city) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT area_name FROM {$this->table} WHERE country = %s AND city = %s ORDER BY area_name ASC",
            $country, $city
        ));
    }
    
    /**
     * Bulk import locations from array
     *
     * @param array $locations Array of location data
     * @return array Result statistics
     */
    public function bulkImport($locations) {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'failed' => 0
        ];
        
        foreach ($locations as $location) {
            // Check if location exists
            $existing = $this->getByZipCode($location['zip_code']);
            
            if ($existing) {
                // Update existing location
                $result = $this->update($existing->id, $location);
                if ($result) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            } else {
                // Add new location
                $result = $this->add($location);
                if ($result) {
                    $stats['imported']++;
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Initialize Sweden sample locations
     *
     * @return bool Success
     */
    public function initializeSweden() {
        // Check if we already have Sweden locations
        $has_sweden = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE country = %s",
            'Sweden'
        ));
        
        if ($has_sweden > 0) {
            return false; // Already initialized
        }
        
        // Sample data for Sweden
        $locations = [
            // Stockholm
            [
                'country' => 'Sweden',
                'city' => 'Stockholm',
                'area_name' => 'Södermalm',
                'zip_code' => '11646',
                'price_adjustment' => 10,
                'service_fee' => 5,
                'is_active' => 'yes'
            ],
            [
                'country' => 'Sweden',
                'city' => 'Stockholm',
                'area_name' => 'Östermalm',
                'zip_code' => '11428',
                'price_adjustment' => 15,
                'service_fee' => 5,
                'is_active' => 'yes'
            ],
            [
                'country' => 'Sweden',
                'city' => 'Stockholm',
                'area_name' => 'Kungsholmen',
                'zip_code' => '11230',
                'price_adjustment' => 10,
                'service_fee' => 5,
                'is_active' => 'yes'
            ],
            
            // Gothenburg
            [
                'country' => 'Sweden',
                'city' => 'Gothenburg',
                'area_name' => 'Centrum',
                'zip_code' => '41116',
                'price_adjustment' => 5,
                'service_fee' => 3,
                'is_active' => 'yes'
            ],
            [
                'country' => 'Sweden',
                'city' => 'Gothenburg',
                'area_name' => 'Majorna',
                'zip_code' => '41465',
                'price_adjustment' => 0,
                'service_fee' => 3,
                'is_active' => 'yes'
            ],
            
            // Malmö
            [
                'country' => 'Sweden',
                'city' => 'Malmö',
                'area_name' => 'Centrum',
                'zip_code' => '21129',
                'price_adjustment' => 0,
                'service_fee' => 2,
                'is_active' => 'yes'
            ],
            [
                'country' => 'Sweden',
                'city' => 'Malmö',
                'area_name' => 'Limhamn',
                'zip_code' => '21616',
                'price_adjustment' => 5,
                'service_fee' => 2,
                'is_active' => 'yes'
            ]
        ];
        
        $result = $this->bulkImport($locations);
        
        return ($result['imported'] > 0 || $result['updated'] > 0);
    }
}