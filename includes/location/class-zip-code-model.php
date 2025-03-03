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
            'state' => $data['state'],
            'country' => $data['country'],
            'price_adjustment' => isset($data['price_adjustment']) ? $data['price_adjustment'] : 0,
            'service_fee' => isset($data['service_fee']) ? $data['service_fee'] : 0,
            'is_serviceable' => isset($data['is_serviceable']) ? $data['is_serviceable'] : 'yes',
        ];
        
        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s']
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
            ['%s', '%s', '%s', '%f', '%f', '%s'],
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
        
        $where = ['is_serviceable = "yes"'];
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
        
        $query .= $wpdb->prepare(' ORDER BY %s %s', $args['orderby'], $args['order']);
        
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
}
