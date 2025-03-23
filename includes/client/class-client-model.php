<?php
namespace VandelBooking\Client;

/**
 * Client Model
 * Handles all client-related database operations
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
        
        // Ensure the table exists
        $this->ensureTableExists();
    }
    
    /**
     * Ensure the clients table exists
     */
    private function ensureTableExists() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
        
        if (!$table_exists) {
            error_log('VandelBooking: Clients table does not exist, attempting to create it');
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $this->table (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NULL,
                total_spent DECIMAL(10, 2) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX (email)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Get or create client
     * 
     * @param array $data Client data
     * @return int Client ID
     */
    public function getOrCreateClient($data) {
        global $wpdb;
        
        if (empty($data['email'])) {
            throw new \Exception(__('Email is required for client', 'vandel-booking'));
        }
        
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
            'name' => $data['name'] ?? __('Guest', 'vandel-booking'),
            'phone' => isset($data['phone']) ? $data['phone'] : '',
            'total_spent' => 0,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%f', '%s']
        );
        
        if ($wpdb->insert_id) {
            return $wpdb->insert_id;
        } else {
            error_log('VandelBooking: Failed to create client: ' . $wpdb->last_error);
            throw new \Exception(__('Failed to create client', 'vandel-booking'));
        }
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
        
        // Sanitize orderby column
        $allowed_columns = ['id', 'name', 'email', 'total_spent', 'created_at'];
        if (!in_array($args['orderby'], $allowed_columns)) {
            $args['orderby'] = 'name';
        }
        
        // Sanitize order direction
        $args['order'] = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $query = "SELECT * FROM {$this->table}";
        
        // Add WHERE clause if needed
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= $wpdb->prepare(
                " WHERE name LIKE %s OR email LIKE %s",
                $search, $search
            );
        }
        
        // Add ORDER BY clause
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add LIMIT clause if needed
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update client total spent
     * 
     * @param int $client_id Client ID
     * @param float $amount Amount to add (can be negative for refunds)
     * @return bool Whether the client was updated
     */
    public function updateTotalSpent($client_id, $amount) {
        global $wpdb;
        
        $client = $this->get($client_id);
        if (!$client) {
            return false;
        }
        
        $new_total = $client->total_spent + $amount;
        if ($new_total < 0) {
            $new_total = 0; // Prevent negative total spent
        }
        
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
     * Update client data
     * 
     * @param int $client_id Client ID
     * @param array $data Client data to update
     * @return bool Whether the client was updated
     */
    public function update($client_id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
        // Only update fields that are provided
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $formats[] = '%s';
        }
        
        if (isset($data['email'])) {
            $update_data['email'] = $data['email'];
            $formats[] = '%s';
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = $data['phone'];
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false; // Nothing to update
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $client_id],
            $formats,
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
             WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s
             ORDER BY name ASC 
             LIMIT %d",
            $like, $like, $like, $limit
        ));
    }
    
    /**
     * Count all clients or filtered by criteria
     * 
     * @param array $args Optional filter arguments
     * @return int Number of clients
     */
    public function count($args = []) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table}";
        

        // Add WHERE clause if search term provided
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= $wpdb->prepare(
                " WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s",
                $search, $search, $search
            );
        }
        
        return (int) $wpdb->get_var($query);
    }
}