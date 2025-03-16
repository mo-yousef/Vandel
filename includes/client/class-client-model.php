<?php
namespace VandelBooking\Client;

/**
 * Client Model
 */
class ClientModel {
    /**
     * @var string Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_clients';
    }
    
    /**
     * Get or create client
     * 
     * @param array $data Client data
     * @return int Client ID
     */
    public function getOrCreateClient($data) {
        global $wpdb;
        
        // Check if client already exists
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE email = %s",
            $data['email']
        ));
        
        if ($client) {
            // Update name and phone if provided
            $update_data = [];
            $update_format = [];
            
            if (!empty($data['name']) && $data['name'] !== $client->name) {
                $update_data['name'] = $data['name'];
                $update_format[] = '%s';
            }
            
            if (isset($data['phone']) && $data['phone'] !== $client->phone) {
                $update_data['phone'] = $data['phone'];
                $update_format[] = '%s';
            }
            
            if (!empty($update_data)) {
                $wpdb->update(
                    $this->table,
                    $update_data,
                    ['id' => $client->id],
                    $update_format,
                    ['%d']
                );
            }
            
            return $client->id;
        }
        
        // Create new client
        $insert_data = [
            'email' => $data['email'],
            'name' => $data['name'],
            'phone' => isset($data['phone']) ? $data['phone'] : '',
            'total_spent' => 0,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%f', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get client by ID
     * 
     * @param int $client_id Client ID
     * @return object|false Client object or false if not found
     */
    public function get($client_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $client_id
        ));
    }
    
    /**
     * Get client by email
     * 
     * @param string $email Email address
     * @return object|false Client object or false if not found
     */
    public function getByEmail($email) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Get all clients
     * 
     * @param array $args Query arguments
     * @return array Clients
     */
    public function getAll($args = []) {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->table}";
        
        // Order
        $query .= $wpdb->prepare(' ORDER BY %s %s', $args['orderby'], $args['order']);
        
        // Limit
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update client total spent
     * 
     * @param int $client_id Client ID
     * @param float $amount Amount to add
     * @return bool Whether the client was updated
     */
    public function updateTotalSpent($client_id, $amount) {
        global $wpdb;
        
        $client = $this->get($client_id);
        if (!$client) {
            return false;
        }
        
        $new_total = $client->total_spent + $amount;
        
        $result = $wpdb->update(
            $this->table,
            ['total_spent' => $new_total],
            ['id' => $client_id],
            ['%f'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete client
     * 
     * @param int $client_id Client ID
     * @return bool Whether the client was deleted
     */
    public function delete($client_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $client_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Search clients
     * 
     * @param string $term Search term
     * @param int $limit Maximum number of results
     * @return array Matching clients
     */
    public function search($term, $limit = 10) {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} 
             WHERE name LIKE %s OR email LIKE %s 
             ORDER BY name ASC 
             LIMIT %d",
            $like, $like, $limit
        ));
    }
}