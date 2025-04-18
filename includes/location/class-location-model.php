<?php
namespace VandelBooking\Location;

/**
 * Location Model
 * Handles database operations for areas and locations
 */
class LocationModel {
    /**
     * @var string Areas table name
     */
    private $areas_table;
    
    /**
     * @var string Locations table name
     */
    private $locations_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->areas_table = $wpdb->prefix . 'vandel_areas';
        $this->locations_table = $wpdb->prefix . 'vandel_locations';
        
        // Ensure tables exist
        $this->createTables();
    }
    
    /**
     * Create required tables if they don't exist
     */
    private function createTables() {
        global $wpdb;
        
        // Get WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create areas table if it doesn't exist
        $areas_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->areas_table}'") === $this->areas_table;
        
        if (!$areas_table_exists) {
            $sql = "CREATE TABLE {$this->areas_table} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                country VARCHAR(100) NOT NULL,
                admin_area VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY name (name),
                KEY country (country)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
        
        // Create locations table if it doesn't exist
        $locations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->locations_table}'") === $this->locations_table;
        
        if (!$locations_table_exists) {
            $sql = "CREATE TABLE {$this->locations_table} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                area_id MEDIUMINT(9) NOT NULL,
                price_adjustment DECIMAL(10, 2) DEFAULT 0,
                service_fee DECIMAL(10, 2) DEFAULT 0,
                is_active ENUM('yes', 'no') DEFAULT 'yes',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY area_id (area_id),
                KEY name (name)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Get all areas
     * 
     * @return array List of area objects
     */
    public function getAreas() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$this->areas_table} ORDER BY name ASC");
    }
    
    /**
     * Get area by ID
     * 
     * @param int $area_id Area ID
     * @return object|null Area object or null if not found
     */
    public function getAreaById($area_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->areas_table} WHERE id = %d",
            $area_id
        ));
    }
    
    /**
     * Get area by name
     * 
     * @param string $name Area name
     * @return object|null Area object or null if not found
     */
    public function getAreaByName($name) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->areas_table} WHERE name = %s",
            $name
        ));
    }
    
    /**
     * Add new area
     * 
     * @param array $data Area data (name, country, admin_area)
     * @return int|false Area ID or false on failure
     */
    public function addArea($data) {
        global $wpdb;
        
        // Check if area with this name already exists
        $existing = $this->getAreaByName($data['name']);
        if ($existing) {
            return false;
        }
        
        $result = $wpdb->insert(
            $this->areas_table,
            [
                'name' => $data['name'],
                'country' => $data['country'],
                'admin_area' => $data['admin_area'] ?? $data['name'],
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update area
     * 
     * @param int $area_id Area ID
     * @param array $data Area data to update
     * @return bool Success status
     */
    public function updateArea($area_id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $formats[] = '%s';
        }
        
        if (isset($data['country'])) {
            $update_data['country'] = $data['country'];
            $formats[] = '%s';
        }
        
        if (isset($data['admin_area'])) {
            $update_data['admin_area'] = $data['admin_area'];
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->areas_table,
            $update_data,
            ['id' => $area_id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete area and all associated locations
     * 
     * @param int $area_id Area ID
     * @return bool Success status
     */
    public function deleteArea($area_id) {
        global $wpdb;
        
        // First delete all locations in this area
        $wpdb->delete(
            $this->locations_table,
            ['area_id' => $area_id],
            ['%d']
        );
        
        // Then delete the area
        $result = $wpdb->delete(
            $this->areas_table,
            ['id' => $area_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get locations by area
     * 
     * @param int $area_id Area ID
     * @return array List of location objects
     */
    public function getLocationsByArea($area_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->locations_table} WHERE area_id = %d ORDER BY name ASC",
            $area_id
        ));
    }
    
    /**
     * Count locations in an area
     * 
     * @param int $area_id Area ID
     * @return int Number of locations
     */
    public function countLocationsByArea($area_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->locations_table} WHERE area_id = %d",
            $area_id
        ));
    }
    
    /**
     * Get location by ID
     * 
     * @param int $location_id Location ID
     * @return object|null Location object or null if not found
     */
    public function getLocationById($location_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->locations_table} WHERE id = %d",
            $location_id
        ));
    }
    
    /**
     * Add new location
     * 
     * @param array $data Location data (name, area_id, price_adjustment, service_fee, is_active)
     * @return int|false Location ID or false on failure
     */
    public function addLocation($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->locations_table,
            [
                'name' => $data['name'],
                'area_id' => $data['area_id'],
                'price_adjustment' => $data['price_adjustment'] ?? 0,
                'service_fee' => $data['service_fee'] ?? 0,
                'is_active' => $data['is_active'] ?? 'yes',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%f', '%f', '%s', '%s']
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update location
     * 
     * @param int $location_id Location ID
     * @param array $data Location data to update
     * @return bool Success status
     */
    public function updateLocation($location_id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $formats[] = '%s';
        }
        
        if (isset($data['area_id'])) {
            $update_data['area_id'] = $data['area_id'];
            $formats[] = '%d';
        }
        
        if (isset($data['price_adjustment'])) {
            $update_data['price_adjustment'] = $data['price_adjustment'];
            $formats[] = '%f';
        }
        
        if (isset($data['service_fee'])) {
            $update_data['service_fee'] = $data['service_fee'];
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
            $this->locations_table,
            $update_data,
            ['id' => $location_id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete location
     * 
     * @param int $location_id Location ID
     * @return bool Success status
     */
    public function deleteLocation($location_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->locations_table,
            ['id' => $location_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Initialize Sweden areas and locations
     * 
     * @return bool Success status
     */
    public function initializeSweden() {
        // Check if we already have areas
        $existing_areas = $this->getAreas();
        if (!empty($existing_areas)) {
            return false;
        }
        
        // Sample Sweden areas
        $areas = [
            [
                'name' => 'Stockholm',
                'country' => 'Sweden',
                'locations' => ['Södermalm', 'Östermalm', 'Vasastan', 'Kungsholmen', 'Gamla Stan']
            ],
            [
                'name' => 'Uppsala',
                'country' => 'Sweden',
                'locations' => ['Uppsala', 'Almunge', 'Björklinge', 'Vattholma', 'Storvreta']
            ],
            [
                'name' => 'Gothenburg',
                'country' => 'Sweden',
                'locations' => ['Majorna', 'Lindholmen', 'Hisingen', 'Askim', 'Frölunda']
            ]
        ];
        
        $success = true;
        
        foreach ($areas as $area_data) {
            $area_id = $this->addArea([
                'name' => $area_data['name'],
                'country' => $area_data['country'],
                'admin_area' => $area_data['name']
            ]);
            
            if (!$area_id) {
                $success = false;
                continue;
            }
            
            // Add locations for this area
            foreach ($area_data['locations'] as $location_name) {
                $location_added = $this->addLocation([
                    'name' => $location_name,
                    'area_id' => $area_id
                ]);
                
                if (!$location_added) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
}