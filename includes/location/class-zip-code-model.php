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
        
        // Ensure the table exists
        $this->ensureTableExists();
    }
    
    /**
     * Ensure the ZIP codes table exists
     */
    private function ensureTableExists() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
        
        if (!$table_exists) {
            error_log('VandelBooking: ZIP codes table does not exist, attempting to create it');
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $this->table (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                zip_code VARCHAR(20) NOT NULL,
                city VARCHAR(100) NOT NULL,
                state VARCHAR(100) NULL,
                country VARCHAR(100) NOT NULL,
                price_adjustment DECIMAL(10, 2) DEFAULT 0,
                service_fee DECIMAL(10, 2) DEFAULT 0,
                is_serviceable ENUM('yes', 'no') DEFAULT 'yes',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY zip_code (zip_code)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Add ZIP Code
     * 
     * @param array $data ZIP Code data
     * @return int|false ZIP Code ID or false if failed
     */
    public function add($data) {
        global $wpdb;
        
        $insert_data = [
            'zip_code' => $data['zip_code'],
            'city' => $data['city'],
            'state' => isset($data['state']) ? $data['state'] : '',
            'country' => $data['country'],
            'price_adjustment' => isset($data['price_adjustment']) ? $data['price_adjustment'] : 0,
            'service_fee' => isset($data['service_fee']) ? $data['service_fee'] : 0,
            'is_serviceable' => isset($data['is_serviceable']) ? $data['is_serviceable'] : 'yes',
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get ZIP Code details
     * 
     * @param string $zip_code ZIP Code
     * @return object|null ZIP Code details
     */
    public function get($zip_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE zip_code = %s",
            $zip_code
        ));
    }
    
    /**
     * Update ZIP Code
     * 
     * @param string $zip_code ZIP Code
     * @param array $data Updated data
     * @return bool Whether update was successful
     */
    public function update($zip_code, $data) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table,
            $data,
            ['zip_code' => $zip_code],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete ZIP Code
     * 
     * @param string $zip_code ZIP Code
     * @return bool Whether deletion was successful
     */
    public function delete($zip_code) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['zip_code' => $zip_code],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Search ZIP Codes
     * 
     * @param string $term Search term
     * @param int $limit Maximum number of results
     * @return array Matching ZIP Codes
     */
    public function search($term, $limit = 10) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE zip_code LIKE %s OR city LIKE %s OR state LIKE %s 
             LIMIT %d",
            $like, $like, $like, $limit
        ));
    }
    
    /**
     * Get serviceable ZIP Codes
     * 
     * @param array $args Filter arguments
     * @return array Serviceable ZIP Codes
     */
    public function getServiceableZipCodes($args = []) {
        global $wpdb;
        
        $defaults = [
            'country' => '',
            'state' => '',
            'city' => '',
            'orderby' => 'zip_code',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["is_serviceable = 'yes'"];
        $values = [];
        
        if (!empty($args['country'])) {
            $where[] = 'country = %s';
            $values[] = $args['country'];
        }
        
        if (!empty($args['state'])) {
            $where[] = 'state = %s';
            $values[] = $args['state'];
        }
        
        if (!empty($args['city'])) {
            $where[] = 'city = %s';
            $values[] = $args['city'];
        }
        
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Validate orderby field against column list
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->table}");
        $args['orderby'] = in_array($args['orderby'], $columns) ? $args['orderby'] : 'zip_code';
        
        // Validate order direction
        $args['order'] = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare final query with placeholder values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
}