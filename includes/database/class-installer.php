<?php
namespace VandelBooking\Database;

/**
 * Database Installer
 * Handles database table creation and updates
 */
class Installer {
    /**
     * Database version
     * Increment this when changing table structure to trigger updates
     * @var string
     */
    private $db_version = '1.0.3';
    
    /**
     * Tables to be installed
     * @var array
     */
    private $tables = [
        'bookings',
        'clients',
        'booking_notes',
        'zip_codes',
        'locations',
        'areas',               // Add this
        'location_spots'       // Add this
    ];

    /**
     * Constructor
     */
    public function __construct() {
        // Allow hooks to modify tables if needed
        $this->tables = apply_filters('vandel_database_tables', $this->tables);
    }

    /**
     * Install database tables
     * 
     * @return bool Whether installation was successful
     */
    public function install() {
        $success = true;
        
        // Create or update tables
        foreach ($this->tables as $table) {
            $method = "create_{$table}_table";
            if (method_exists($this, $method)) {
                $result = $this->$method();
                if (!$result) {
                    error_log("VandelBooking: Failed to create {$table} table");
                    $success = false;
                }
            }
        }
        
        // Update version in options
        if ($success) {
            update_option('vandel_db_version', $this->db_version);
        }
        
        return $success;
    }

    /**
     * Check if tables need to be updated
     * 
     * @return bool Whether tables need update
     */
    public function needsUpdate() {
        $current_version = get_option('vandel_db_version', '0');
        return version_compare($current_version, $this->db_version, '<');
    }

    /**
     * Create bookings table
     * 
     * @return bool Whether table was created
     */
    private function create_bookings_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Get WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            client_id MEDIUMINT(9) NOT NULL,
            service VARCHAR(255) NOT NULL,
            sub_services TEXT NULL,
            booking_date DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            phone VARCHAR(100) NULL,
            access_info TEXT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY status (status),
            KEY booking_date (booking_date)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Check if table exists after creation attempt
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Create clients table
     * 
     * @return bool Whether table was created
     */
    private function create_clients_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_clients';
        
        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Get WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        
        // Check if table exists after creation attempt
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Create booking notes table
     * 
     * @return bool Whether table was created
     */
    private function create_booking_notes_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Get WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            booking_id MEDIUMINT(9) NOT NULL,
            note_content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT(20) NOT NULL,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Check if table exists after creation attempt
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }




    /**
     * Create ZIP codes table
     * 
     * @return bool Whether table was created
     */
    private function create_zip_codes_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_zip_codes';
        
        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        // Get WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
        
        // Check if table exists after creation attempt
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }


// Add this method to the Installer class
private function create_locations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vandel_locations';
    
    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    // Get WordPress dbDelta function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
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
    
    // Check if table exists after creation attempt
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}


/**
 * Create areas table
 * 
 * @return bool Whether table was created successfully
 */
private function create_areas_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vandel_areas';
    
    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    // Get WordPress dbDelta function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
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
    
    // Check if table exists after creation attempt
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}


/**
 * Create location spots table
 * 
 * @return bool Whether table was created successfully
 */
private function create_location_spots_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vandel_location_spots';
    
    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    // Get WordPress dbDelta function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
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
    
    // Check if table exists after creation attempt
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

 /**
     * Update tables if needed
     * This method handles database migrations when structure changes
     * 
     * @return bool Whether updates were successfully applied
     */
    public function update() {
        global $wpdb;
        $current_version = get_option('vandel_db_version', '0');
        
        // Don't do anything if we're on the latest version
        if (version_compare($current_version, $this->db_version, '>=')) {
            return true;
        }
        
        // Run version-specific updates
        $versions = [
            '1.0.1' => function() {
                // Update to add client address and notes columns
                global $wpdb;
                $clients_table = $wpdb->prefix . 'vandel_clients';
                
                // Check if address column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $clients_table LIKE 'address'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $clients_table ADD COLUMN address TEXT NULL AFTER phone");
                }
                
                // Check if notes column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $clients_table LIKE 'notes'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $clients_table ADD COLUMN notes TEXT NULL AFTER address");
                }
            },
            '1.0.2' => function() {
                // Update to add booking statistics to clients table
                global $wpdb;
                $clients_table = $wpdb->prefix . 'vandel_clients';
                
                // Check if bookings_count column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $clients_table LIKE 'bookings_count'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $clients_table ADD COLUMN bookings_count INT DEFAULT 0 AFTER total_spent");
                }
                
                // Check if last_booking column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $clients_table LIKE 'last_booking'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $clients_table ADD COLUMN last_booking DATETIME NULL AFTER bookings_count");
                }
                
                // Update clients with booking statistics
                if (function_exists('vandel_update_client_statistics')) {
                    vandel_update_client_statistics();
                }
            },
            '1.0.3' => function() {
                // Update to add phone column to bookings table
                global $wpdb;
                $bookings_table = $wpdb->prefix . 'vandel_bookings';
                
                // Check if phone column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'phone'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN phone VARCHAR(100) NULL AFTER customer_email");
                }
                
                // Check if updated_at column exists
                $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'updated_at'");
                if (empty($column_exists)) {
                    $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN updated_at DATETIME NULL AFTER created_at");
                }
            },
            '1.0.4' => function() {
                // Create locations table
                $this->create_locations_table();
            }
        ];
        




        
        // Loop through versions and apply updates
        foreach ($versions as $version => $update_function) {
            if (version_compare($current_version, $version, '<')) {
                call_user_func($update_function);
            }
        }
        
        // Update version in options
        update_option('vandel_db_version', $this->db_version);
        
        return true;
    }
}