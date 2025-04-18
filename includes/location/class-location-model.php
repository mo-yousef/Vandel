<?php
namespace VandelBooking\Location;

/**
 * Location Model
 * Handles hierarchical location data (country -> city -> area)
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
    }
    
    /**
     * Add a new location
     * 
     * @param array $data Location data
     * @return int|false Location ID or false if failed
     */
    public function add($data) {
        global $wpdb;
        
        // Required fields validation
        if (empty($data['country']) || empty($data['city']) || empty($data['area_name']) || empty($data['zip_code'])) {
            return false;
        }
        
        // Check if location already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} 
             WHERE country = %s AND city = %s AND area_name = %s AND zip_code = %s",
            $data['country'], $data['city'], $data['area_name'], $data['zip_code']
        ));
        
        if ($existing) {
            return $this->update($existing, $data);
        }
        
        // Set defaults
        $data = wp_parse_args($data, [
            'price_adjustment' => 0,
            'service_fee' => 0,
            'is_active' => 'yes',
            'created_at' => current_time('mysql')
        ]);
        
        // Insert location
        $result = $wpdb->insert(
            $this->table,
            [
                'country' => $data['country'],
                'city' => $data['city'],
                'area_name' => $data['area_name'],
                'zip_code' => $data['zip_code'],
                'price_adjustment' => floatval($data['price_adjustment']),
                'service_fee' => floatval($data['service_fee']),
                'is_active' => $data['is_active'],
                'created_at' => $data['created_at']
            ],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update location
     * 
     * @param int $id Location ID
     * @param array $data Updated data
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
     * Delete location
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
     * @return object|false Location data or false if not found
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
     * @param string $country Optional country filter
     * @param string $city Optional city filter
     * @return object|false Location data or false if not found
     */
    public function getByZipCode($zip_code, $country = null, $city = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table} WHERE zip_code = %s";
        $params = [$zip_code];
        
        if ($country) {
            $query .= " AND country = %s";
            $params[] = $country;
        }
        
        if ($city) {
            $query .= " AND city = %s";
            $params[] = $city;
        }
        
        return $wpdb->get_row($wpdb->prepare($query, $params));
    }
    
    /**
     * Get all countries
     * 
     * @return array List of countries
     */
    public function getCountries() {
        global $wpdb;
        
        return $wpdb->get_col(
            "SELECT DISTINCT country FROM {$this->table} ORDER BY country ASC"
        );
    }
    
    /**
     * Get cities for a country
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
     * Get areas for a city
     * 
     * @param string $country Country name
     * @param string $city City name
     * @return array List of areas with their IDs
     */
    public function getAreas($country, $city) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, area_name, zip_code, price_adjustment, service_fee, is_active 
             FROM {$this->table} 
             WHERE country = %s AND city = %s 
             ORDER BY area_name ASC",
            $country, $city
        ));
    }
    
    /**
     * Get all locations with optional filtering
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
            'orderby' => 'country',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = '1=1';
        $values = [];
        
        if (!empty($args['country'])) {
            $where .= ' AND country = %s';
            $values[] = $args['country'];
        }
        
        if (!empty($args['city'])) {
            $where .= ' AND city = %s';
            $values[] = $args['city'];
        }
        
        if ($args['is_active'] === 'yes' || $args['is_active'] === 'no') {
            $where .= ' AND is_active = %s';
            $values[] = $args['is_active'];
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= ' AND (country LIKE %s OR city LIKE %s OR area_name LIKE %s OR zip_code LIKE %s)';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        // Build query
        $query = "SELECT * FROM {$this->table} WHERE {$where}";
        
        // Add ORDER BY
        $allowed_columns = ['id', 'country', 'city', 'area_name', 'zip_code', 'created_at'];
        if (!in_array($args['orderby'], $allowed_columns)) {
            $args['orderby'] = 'country';
        }
        
        $query .= " ORDER BY {$args['orderby']} " . ($args['order'] === 'DESC' ? 'DESC' : 'ASC');
        
        // Add LIMIT
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare query
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Bulk import locations
     * 
     * @param array $locations Array of location data
     * @return array Import stats
     */
    public function bulkImport($locations) {
        $stats = [
            'total' => count($locations),
            'imported' => 0,
            'updated' => 0,
            'failed' => 0
        ];
        
        foreach ($locations as $location) {
            // Check if location already exists
            global $wpdb;
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table} 
                 WHERE country = %s AND city = %s AND area_name = %s AND zip_code = %s",
                $location['country'], $location['city'], $location['area_name'], $location['zip_code']
            ));
            
            if ($existing_id) {
                // Update existing location
                $result = $this->update($existing_id, $location);
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
     * Initialize with sample Sweden locations
     * 
     * @return bool Success
     */
    public function initializeSweden() {
        // Check if there are already locations in the database
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        
        if ($count > 0) {
            return false; // Already initialized
        }
        
        // Sample Sweden locations
        $locations = [
            // Stockholm
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Södermalm', 'zip_code' => '11853', 'price_adjustment' => 0, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Norrmalm', 'zip_code' => '11121', 'price_adjustment' => 0, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Östermalm', 'zip_code' => '11431', 'price_adjustment' => 10, 'service_fee' => 5],
            ['country' => 'Sweden', 'city' => 'Stockholm', 'area_name' => 'Kungsholmen', 'zip_code' => '11227', 'price_adjustment' => 5, 'service_fee' => 0],
            
            // Gothenburg
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Centrum', 'zip_code' => '41108', 'price_adjustment' => 0, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Majorna-Linné', 'zip_code' => '41453', 'price_adjustment' => 0, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Gothenburg', 'area_name' => 'Örgryte-Härlanda', 'zip_code' => '41676', 'price_adjustment' => 5, 'service_fee' => 0],
            
            // Malmö
            ['country' => 'Sweden', 'city' => 'Malmö', 'area_name' => 'Centrum', 'zip_code' => '21119', 'price_adjustment' => 0, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Malmö', 'area_name' => 'Limhamn-Bunkeflo', 'zip_code' => '21612', 'price_adjustment' => 10, 'service_fee' => 0],
            ['country' => 'Sweden', 'city' => 'Malmö', 'area_name' => 'Västra Innerstaden', 'zip_code' => '21450', 'price_adjustment' => 5, 'service_fee' => 0],
        ];
        
        return $this->bulkImport($locations);
    }
}