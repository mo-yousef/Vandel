<?php
namespace VandelBooking\Location;

/**
 * ZIP Code Model
 */
class ZipCodeModel {
    /**
     * @var string Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_zip_codes';
    }
    
    /**
     * Add new ZIP code
     * 
     * @param array $data ZIP code data
     * @return int|false ZIP code ID or false if failed
     */
    public function add($data) {
        global $wpdb;
        
        // Check if ZIP code already exists
        $existing = $this->get($data['zip_code']);
        if ($existing) {
            // Update existing ZIP code
            return $this->update($existing->id, $data);
        }
        
        // Set defaults
        $defaults = [
            'price_adjustment' => 0,
            'service_fee' => 0,
            'is_serviceable' => 'yes',
            'created_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Insert new ZIP code
        $result = $wpdb->insert(
            $this->table,
            [
                'zip_code' => $data['zip_code'],
                'city' => $data['city'],
                'state' => isset($data['state']) ? $data['state'] : '',
                'country' => $data['country'],
                'price_adjustment' => floatval($data['price_adjustment']),
                'service_fee' => floatval($data['service_fee']),
                'is_serviceable' => $data['is_serviceable'],
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
     * Get ZIP code by code
     * 
     * @param string $zip_code ZIP code
     * @return object|false ZIP code object or false if not found
     */
    public function get($zip_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE zip_code = %s",
            $zip_code
        ));
    }
    
    /**
     * Get ZIP code by ID
     * 
     * @param int $id ZIP code ID
     * @return object|false ZIP code object or false if not found
     */
    public function getById($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Update ZIP code
     * 
     * @param int $id ZIP code ID
     * @param array $data ZIP code data
     * @return bool Whether the update was successful
     */
    public function update($id, $data) {
        global $wpdb;
        
        // Only update specified fields
        $update_data = [];
        $format = [];
        
        if (isset($data['city'])) {
            $update_data['city'] = $data['city'];
            $format[] = '%s';
        }
        
        if (isset($data['state'])) {
            $update_data['state'] = $data['state'];
            $format[] = '%s';
        }
        
        if (isset($data['country'])) {
            $update_data['country'] = $data['country'];
            $format[] = '%s';
        }
        
        if (isset($data['price_adjustment'])) {
            $update_data['price_adjustment'] = floatval($data['price_adjustment']);
            $format[] = '%f';
        }
        
        if (isset($data['service_fee'])) {
            $update_data['service_fee'] = floatval($data['service_fee']);
            $format[] = '%f';
        }
        
        if (isset($data['is_serviceable'])) {
            $update_data['is_serviceable'] = $data['is_serviceable'];
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete ZIP code
     * 
     * @param int $id ZIP code ID
     * @return bool Whether the ZIP code was deleted
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
     * Get all ZIP codes with optional filtering
     * 
     * @param array $args Query arguments
     * @return array ZIP codes
     */
    public function getAll($args = []) {
        global $wpdb;
        
        // Default arguments
        $defaults = [
            'country' => '',
            'state' => '',
            'city' => '',
            'is_serviceable' => '',
            'search' => '',
            'orderby' => 'zip_code',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $query = "SELECT * FROM {$this->table}";
        $where = [];
        $values = [];
        
        // Apply filters
        if (!empty($args['country'])) {
            $where[] = "country = %s";
            $values[] = $args['country'];
        }
        
        if (!empty($args['state'])) {
            $where[] = "state = %s";
            $values[] = $args['state'];
        }
        
        if (!empty($args['city'])) {
            $where[] = "city = %s";
            $values[] = $args['city'];
        }
        
        if (!empty($args['is_serviceable'])) {
            $where[] = "is_serviceable = %s";
            $values[] = $args['is_serviceable'];
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(zip_code LIKE %s OR city LIKE %s OR state LIKE %s OR country LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        // Combine where clauses
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Add order clause
        if (in_array($args['orderby'], ['zip_code', 'city', 'state', 'country', 'id', 'price_adjustment', 'service_fee'])) {
            $orderby = $args['orderby'];
        } else {
            $orderby = 'zip_code';
        }
        
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add limit clause
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            
            if ($args['offset'] > 0) {
                $query .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        // Prepare final query
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Get results
        return $wpdb->get_results($query);
    }
    
    /**
     * Get unique countries from ZIP codes
     * 
     * @return array Country list
     */
    public function getCountries() {
        global $wpdb;
        
        return $wpdb->get_col(
            "SELECT DISTINCT country FROM {$this->table} ORDER BY country ASC"
        );
    }
    
    /**
     * Get unique states for a country
     * 
     * @param string $country Country name
     * @return array State list
     */
    public function getStates($country) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT state FROM {$this->table} WHERE country = %s ORDER BY state ASC",
            $country
        ));
    }
    
    /**
     * Get unique cities for a country and state
     * 
     * @param string $country Country name
     * @param string $state State name
     * @return array City list
     */
    public function getCities($country, $state = '') {
        global $wpdb;
        
        if (empty($state)) {
            return $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT city FROM {$this->table} WHERE country = %s ORDER BY city ASC",
                $country
            ));
        } else {
            return $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT city FROM {$this->table} WHERE country = %s AND state = %s ORDER BY city ASC",
                $country, $state
            ));
        }
    }
    
    /**
     * Count ZIP codes with optional filtering
     * 
     * @param array $args Query arguments
     * @return int ZIP code count
     */
    public function count($args = []) {
        global $wpdb;
        
        // Default arguments
        $defaults = [
            'country' => '',
            'state' => '',
            'city' => '',
            'is_serviceable' => '',
            'search' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $query = "SELECT COUNT(*) FROM {$this->table}";
        $where = [];
        $values = [];
        
        // Apply filters
        if (!empty($args['country'])) {
            $where[] = "country = %s";
            $values[] = $args['country'];
        }
        
        if (!empty($args['state'])) {
            $where[] = "state = %s";
            $values[] = $args['state'];
        }
        
        if (!empty($args['city'])) {
            $where[] = "city = %s";
            $values[] = $args['city'];
        }
        
        if (!empty($args['is_serviceable'])) {
            $where[] = "is_serviceable = %s";
            $values[] = $args['is_serviceable'];
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(zip_code LIKE %s OR city LIKE %s OR state LIKE %s OR country LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        // Combine where clauses
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        // Prepare final query
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Get result
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Bulk import ZIP codes
     * 
     * @param array $zip_codes Array of ZIP code data
     * @return array Import results
     */
    public function bulkImport($zip_codes) {
        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($zip_codes as $data) {
            // Validate required fields
            if (empty($data['zip_code']) || empty($data['city']) || empty($data['country'])) {
                $failed++;
                $errors[] = sprintf(
                    __('Missing required fields for ZIP code: %s', 'vandel-booking'),
                    isset($data['zip_code']) ? $data['zip_code'] : 'Unknown'
                );
                continue;
            }
            
            // Check if ZIP code already exists
            $existing = $this->get($data['zip_code']);
            
            if ($existing) {
                // Update existing ZIP code
                $result = $this->update($existing->id, $data);
                if ($result) {
                    $updated++;
                } else {
                    $failed++;
                    $errors[] = sprintf(
                        __('Failed to update ZIP code: %s', 'vandel-booking'),
                        $data['zip_code']
                    );
                }
            } else {
                // Add new ZIP code
                $result = $this->add($data);
                if ($result) {
                    $imported++;
                } else {
                    $failed++;
                    $errors[] = sprintf(
                        __('Failed to add ZIP code: %s', 'vandel-booking'),
                        $data['zip_code']
                    );
                }
            }
        }
        
        return [
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
            'total' => count($zip_codes)
        ];
    }
    
    /**
     * Check if the table exists
     * 
     * @return bool Whether the table exists
     */
    public function tableExists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
    }
}