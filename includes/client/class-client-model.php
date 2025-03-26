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
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NULL,
                address TEXT NULL,
                notes TEXT NULL,
                total_spent DECIMAL(10, 2) DEFAULT 0,
                bookings_count INT DEFAULT 0,
                last_booking DATETIME NULL,
                custom_fields TEXT NULL, 
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY email (email)
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
            // Update client data if provided
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
            
            if (isset($data['address']) && isset($client->address) && $data['address'] !== $client->address) {
                $update_data['address'] = $data['address'];
                $update_format[] = '%s';
            }
            
            if (isset($data['notes']) && isset($client->notes) && $data['notes'] !== $client->notes) {
                $update_data['notes'] = $data['notes'];
                $update_format[] = '%s';
            }
            
            if (!empty($update_data)) {
                $update_data['updated_at'] = current_time('mysql');
                $update_format[] = '%s';
                
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
            'address' => isset($data['address']) ? $data['address'] : '',
            'notes' => isset($data['notes']) ? $data['notes'] : '',
            'total_spent' => 0,
            'bookings_count' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert(
            $this->table,
            $insert_data,
            ['%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s']
        );
        
        if ($result) {
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
     * Get all clients with filtering and sorting options
     * 
     * @param array $args Query arguments
     * @return array Clients
     */
    public function getAll($args = []) {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'name',
            'order' => 'ASC',
            'search' => '',
            'limit' => 20,
            'offset' => 0,
            'min_spent' => null,
            'max_spent' => null,
            'min_bookings' => null,
            'last_booking_after' => null,
            'created_after' => null,
            'created_before' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = [];
        $values = [];
        
        // Search in name, email, phone, and address
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
            
            // Only include address in search if the column exists
            $columns = $wpdb->get_col("DESCRIBE {$this->table}");
            if (in_array('address', $columns)) {
                $where[count($where) - 1] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR address LIKE %s)';
                $values[] = $search_term;
            }
        }
        
        // Filter by total spent
        if (is_numeric($args['min_spent'])) {
            $where[] = 'total_spent >= %f';
            $values[] = floatval($args['min_spent']);
        }
        
        if (is_numeric($args['max_spent'])) {
            $where[] = 'total_spent <= %f';
            $values[] = floatval($args['max_spent']);
        }
        
        // Filter by bookings count
        if (is_numeric($args['min_bookings'])) {
            $where[] = 'bookings_count >= %d';
            $values[] = intval($args['min_bookings']);
        }
        
        // Filter by last booking date
        if (!empty($args['last_booking_after'])) {
            $where[] = 'last_booking >= %s';
            $values[] = $args['last_booking_after'];
        }
        
        // Filter by creation date
        if (!empty($args['created_after'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['created_after'];
        }
        
        if (!empty($args['created_before'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['created_before'];
        }
        
        // Build the query
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        // Check if orderby column exists in table
        $columns = $wpdb->get_col("DESCRIBE {$this->table}");
        if (!in_array($args['orderby'], $columns)) {
            $args['orderby'] = 'name'; // Default to name if column doesn't exist
        }
        
        // Add ORDER BY clause
        $query .= " ORDER BY {$args['orderby']} " . ($args['order'] === 'DESC' ? 'DESC' : 'ASC');
        
        // Add LIMIT clause
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d, %d", $args['offset'], $args['limit']);
        }
        
        // Prepare the full query with all values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Count total clients matching the filters
     * 
     * @param array $args Filter arguments
     * @return int Number of matching clients
     */
    public function count($args = []) {
        global $wpdb;
        
        $defaults = [
            'search' => '',
            'min_spent' => null,
            'max_spent' => null,
            'min_bookings' => null,
            'last_booking_after' => null,
            'created_after' => null,
            'created_before' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = [];
        $values = [];
        
        // Search in name, email, phone, and address
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
            
            // Only include address in search if the column exists
            $columns = $wpdb->get_col("DESCRIBE {$this->table}");
            if (in_array('address', $columns)) {
                $where[count($where) - 1] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR address LIKE %s)';
                $values[] = $search_term;
            }
        }
        
        // Filter by total spent
        if (is_numeric($args['min_spent'])) {
            $where[] = 'total_spent >= %f';
            $values[] = floatval($args['min_spent']);
        }
        
        if (is_numeric($args['max_spent'])) {
            $where[] = 'total_spent <= %f';
            $values[] = floatval($args['max_spent']);
        }
        
        // Filter by bookings count
        if (is_numeric($args['min_bookings'])) {
            $where[] = 'bookings_count >= %d';
            $values[] = intval($args['min_bookings']);
        }
        
        // Filter by last booking date
        if (!empty($args['last_booking_after'])) {
            $where[] = 'last_booking >= %s';
            $values[] = $args['last_booking_after'];
        }
        
        // Filter by creation date
        if (!empty($args['created_after'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['created_after'];
        }
        
        if (!empty($args['created_before'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['created_before'];
        }
        
        // Build the query
        $query = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        // Prepare the full query with all values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Update client data
     * 
     * @param int $client_id Client ID
     * @param array $data Client data
     * @return bool Whether the update was successful
     */
    public function update($client_id, $data) {
        global $wpdb;
        
        $update_data = [];
        $formats = [];
        
        // Add fields that can be updated
        $allowed_fields = [
            'name' => '%s',
            'email' => '%s',
            'phone' => '%s',
            'address' => '%s',
            'notes' => '%s',
            'custom_fields' => '%s',
            'total_spent' => '%f',
            'bookings_count' => '%d',
            'last_booking' => '%s'
        ];
        
        // Check which columns actually exist in the table
        $columns = $wpdb->get_col("DESCRIBE {$this->table}");
        
        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field]) && in_array($field, $columns)) {
                $update_data[$field] = $data[$field];
                $formats[] = $format;
            }
        }
        
        // Add updated_at timestamp
        if (!empty($update_data) && in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        } else if (empty($update_data)) {
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
     * Delete client by ID
     * 
     * @param int $client_id Client ID
     * @return bool Whether the client was deleted
     */
    public function delete($client_id) {
        global $wpdb;
        
        // Check if client has bookings and if bookings table exists
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table) {
            $has_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE client_id = %d",
                $client_id
            ));
            
            if ($has_bookings) {
                // Update bookings to remove client association
                $wpdb->update(
                    $bookings_table, 
                    ['client_id' => 0], 
                    ['client_id' => $client_id],
                    ['%d'],
                    ['%d']
                );
            }
        }
        
        // Delete the client
        $result = $wpdb->delete(
            $this->table,
            ['id' => $client_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Update total spent
     * 
     * @param int $client_id Client ID
     * @param float $amount Amount to add
     * @return bool Success
     */
    public function updateTotalSpent($client_id, $amount) {
        global $wpdb;
        
        // Check which columns actually exist in the table
        $columns = $wpdb->get_col("DESCRIBE {$this->table}");
        $update_data = [];
        $formats = [];
        
        if (in_array('total_spent', $columns)) {
            $update_data['total_spent'] = $wpdb->get_var($wpdb->prepare(
                "SELECT total_spent FROM {$this->table} WHERE id = %d",
                $client_id
            ));
            
            if ($update_data['total_spent'] === null) {
                $update_data['total_spent'] = 0;
            }
            
            $update_data['total_spent'] = floatval($update_data['total_spent']) + floatval($amount);
            $formats[] = '%f';
        }
        
        if (in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
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
     * Add booking to client stats
     * 
     * @param int $client_id Client ID
     * @param float $amount Booking amount
     * @param string $booking_date Booking date
     * @return bool Success
     */
    public function addBooking($client_id, $amount, $booking_date = null) {
        global $wpdb;
        
        if (empty($booking_date)) {
            $booking_date = current_time('mysql');
        }
        
        // Check which columns actually exist in the table
        $columns = $wpdb->get_col("DESCRIBE {$this->table}");
        $update_data = [];
        $formats = [];
        
        if (in_array('total_spent', $columns)) {
            $update_data['total_spent'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(total_spent, 0) FROM {$this->table} WHERE id = %d",
                $client_id
            ));
            
            $update_data['total_spent'] = floatval($update_data['total_spent']) + floatval($amount);
            $formats[] = '%f';
        }
        
        if (in_array('bookings_count', $columns)) {
            $update_data['bookings_count'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(bookings_count, 0) FROM {$this->table} WHERE id = %d",
                $client_id
            ));
            
            $update_data['bookings_count'] = intval($update_data['bookings_count']) + 1;
            $formats[] = '%d';
        }
        
        if (in_array('last_booking', $columns)) {
            $update_data['last_booking'] = $booking_date;
            $formats[] = '%s';
        }
        
        if (in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
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
     * Get client bookings
     * 
     * @param int $client_id Client ID
     * @param array $args Optional arguments
     * @return array Client bookings
     */
    public function getClientBookings($client_id, $args = []) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return [];
        }
        
        $defaults = [
            'orderby' => 'booking_date',
            'order' => 'DESC',
            'limit' => 10,
            'offset' => 0,
            'status' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $query = "SELECT * FROM $bookings_table WHERE client_id = %d";
        $values = [$client_id];
        
        // Add status filter if provided
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $values[] = $args['status'];
        }
        
        // Add order
        $columns = $wpdb->get_col("DESCRIBE $bookings_table");
        if (!in_array($args['orderby'], $columns)) {
            $args['orderby'] = 'booking_date'; // Default to booking_date if column doesn't exist
        }
            
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Add limit
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d, %d";
            $values[] = $args['offset'];
            $values[] = $args['limit'];
        }
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, $values);
        $bookings = $wpdb->get_results($prepared_query);
        
        // Add service names
        foreach ($bookings as &$booking) {
            $service = get_post($booking->service);
            $booking->service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        }
        
        return $bookings;
    }
    
    /**
     * Get client stats summary
     * 
     * @param int $client_id Client ID
     * @return object Stats summary
     */
    public function getClientStats($client_id) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        $stats = new \stdClass();
        $stats->total_spent = 0;
        $stats->bookings_count = 0;
        $stats->last_booking = null;
        $stats->average_booking = 0;
        $stats->days_as_client = 0;
        $stats->status_counts = [
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'canceled' => 0
        ];
        
        // Get client data
        $client = $this->get($client_id);
        if (!$client) {
            return $stats;
        }
        
        // If the bookings table doesn't exist, return basic stats from client record
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            $client_columns = $wpdb->get_col("DESCRIBE {$this->table}");
            
            if (in_array('total_spent', $client_columns) && isset($client->total_spent)) {
                $stats->total_spent = floatval($client->total_spent);
            }
            
            if (in_array('bookings_count', $client_columns) && isset($client->bookings_count)) {
                $stats->bookings_count = intval($client->bookings_count);
            }
            
            if (in_array('last_booking', $client_columns) && isset($client->last_booking)) {
                $stats->last_booking = $client->last_booking;
            }
            
            if ($stats->bookings_count > 0 && $stats->total_spent > 0) {
                $stats->average_booking = $stats->total_spent / $stats->bookings_count;
            }
            
            return $stats;
        }
        
        // Get status breakdown
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM $bookings_table 
             WHERE client_id = %d 
             GROUP BY status",
            $client_id
        ));
        
        foreach ($status_counts as $status) {
            if (isset($stats->status_counts[$status->status])) {
                $stats->status_counts[$status->status] = intval($status->count);
            }
            
            // Update total bookings count
            $stats->bookings_count += intval($status->count);
        }
        
        // Get total spent from completed bookings
        $stats->total_spent = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $bookings_table 
             WHERE client_id = %d AND status = 'completed'",
            $client_id
        )) ?: 0);
        
        // Get average booking value
        if ($stats->bookings_count > 0) {
            $stats->average_booking = $stats->total_spent / $stats->bookings_count;
        }
        
        // Get time as client (days since first booking)
        $first_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(created_at) FROM $bookings_table WHERE client_id = %d",
            $client_id
        ));
        
        if ($first_booking) {
            $stats->days_as_client = ceil((time() - strtotime($first_booking)) / DAY_IN_SECONDS);
        }
        
        // Get most recent booking
        $stats->last_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT booking_date FROM $bookings_table 
             WHERE client_id = %d 
             ORDER BY booking_date DESC LIMIT 1",
            $client_id
        ));
        
        // Get most booked service
        $most_booked = $wpdb->get_row($wpdb->prepare(
            "SELECT service, COUNT(*) as count 
             FROM $bookings_table 
             WHERE client_id = %d 
             GROUP BY service 
             ORDER BY count DESC 
             LIMIT 1",
            $client_id
        ));
        
        if ($most_booked) {
            $service = get_post($most_booked->service);
            $stats->most_booked_service = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
            $stats->most_booked_service_count = intval($most_booked->count);
        } else {
            $stats->most_booked_service = '';
            $stats->most_booked_service_count = 0;
        }
        
        return $stats;
    }
    
    /**
     * Recalculate client stats
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    public function recalculateStats($client_id) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return false;
        }
        
        // Get total spent from all completed bookings
        $total_spent = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $bookings_table 
             WHERE client_id = %d AND status = 'completed'",
            $client_id
        ));
        
        // Get total bookings count
        $bookings_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE client_id = %d",
            $client_id
        ));
        
        // Get last booking date
        $last_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT booking_date FROM $bookings_table 
             WHERE client_id = %d 
             ORDER BY booking_date DESC LIMIT 1",
            $client_id
        ));
        
        // Get columns that actually exist in the table
        $columns = $wpdb->get_col("DESCRIBE {$this->table}");
        $update_data = [];
        $formats = [];
        
        if (in_array('total_spent', $columns)) {
            $update_data['total_spent'] = floatval($total_spent ?: 0);
            $formats[] = '%f';
        }
        
        if (in_array('bookings_count', $columns)) {
            $update_data['bookings_count'] = intval($bookings_count ?: 0);
            $formats[] = '%d';
        }
        
        if (in_array('last_booking', $columns)) {
            $update_data['last_booking'] = $last_booking ?: null;
            $formats[] = '%s';
        }
        
        if (in_array('updated_at', $columns)) {
            $update_data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        // Update client stats
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
     * Import clients from CSV
     * 
     * @param string $csv_data CSV data
     * @return array Result stats
     */
    public function importFromCSV($csv_data) {
        $lines = explode("\n", $csv_data);
        $headers = str_getcsv(array_shift($lines));
        
        $required_headers = ['email', 'name'];
        $missing_headers = array_diff($required_headers, $headers);
        
        if (!empty($missing_headers)) {
            throw new \Exception(sprintf(
                __('Missing required headers: %s', 'vandel-booking'),
                implode(', ', $missing_headers)
            ));
        }
        
        $stats = [
            'total' => count($lines),
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $data = array_combine($headers, str_getcsv($line));
            
            if (empty($data['email']) || empty($data['name'])) {
                $stats['failed']++;
                $stats['errors'][] = sprintf(
                    __('Line %d: Missing required fields', 'vandel-booking'),
                    $stats['imported'] + $stats['updated'] + $stats['failed']
                );
                continue;
            }
            
            try {
                $existing_client = $this->getByEmail($data['email']);
                
                if ($existing_client) {
                    // Update existing client
                    $this->update($existing_client->id, $data);
                    $stats['updated']++;
                } else {
                    // Create new client
                    $this->getOrCreateClient($data);
                    $stats['imported']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = $e->getMessage();
            }
        }
        
        return $stats;
    }
}