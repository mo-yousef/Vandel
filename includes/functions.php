<?php

add_action('admin_init', function() {
    global $wpdb;
    error_log('Database tables: ' . print_r($wpdb->get_results("SHOW TABLES LIKE '%vandel%'"), true));
});





/**
 * Vandel Booking Debug and Fix Script
 * Add this to your theme's functions.php temporarily for debugging
 */

// Debug booking submissions
add_action('wp_ajax_vandel_submit_booking', 'debug_vandel_booking_submission', 1);
add_action('wp_ajax_nopriv_vandel_submit_booking', 'debug_vandel_booking_submission', 1);

function debug_vandel_booking_submission() {
    // Log the entire request
    error_log('Vandel Booking Submission - Full Request: ' . print_r($_REQUEST, true));
    
    // Continue with normal processing
}

// Force create database tables
function vandel_force_create_database_tables() {
    global $wpdb;
    
    $tables_exist = true;
    $tables_to_check = [
        $wpdb->prefix . 'vandel_clients',
        $wpdb->prefix . 'vandel_bookings',
        $wpdb->prefix . 'vandel_booking_notes'
    ];
    
    foreach ($tables_to_check as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            $tables_exist = false;
            error_log("Vandel Debug: Table $table does not exist");
        }
    }
    
    if (!$tables_exist) {
        error_log("Vandel Debug: Creating missing tables");
        
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
        } else {
            // Try to include the installer manually
            $installer_file = WP_PLUGIN_DIR . '/vandel-cleaning-booking/includes/database/class-installer.php';
            if (file_exists($installer_file)) {
                require_once $installer_file;
                if (class_exists('\\VandelBooking\\Database\\Installer')) {
                    $installer = new \VandelBooking\Database\Installer();
                    $installer->install();
                }
            }
        }
        
        // Verify tables were created
        foreach ($tables_to_check as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            error_log("Vandel Debug: Table $table exists after creation attempt: " . ($table_exists ? 'Yes' : 'No'));
        }
    }
}

// Run the table check and creation at plugin load time
add_action('plugins_loaded', 'vandel_force_create_database_tables', 20);

// Manually create tables function - run if needed
function vandel_create_tables_manually() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create clients table
    $clients_table = $wpdb->prefix . 'vandel_clients';
    $sql_clients = "CREATE TABLE $clients_table (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NULL,
        total_spent DECIMAL(10, 2) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (email)
    ) $charset_collate;";
    
    dbDelta($sql_clients);
    
    // Create bookings table
    $bookings_table = $wpdb->prefix . 'vandel_bookings';
    $sql_bookings = "CREATE TABLE $bookings_table (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        client_id MEDIUMINT(9) NOT NULL,
        service VARCHAR(255) NOT NULL,
        sub_services TEXT NULL,
        access_info TEXT NULL,
        booking_date DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (status),
        INDEX (booking_date)
    ) $charset_collate;";
    
    dbDelta($sql_bookings);
    
    // Create notes table
    $notes_table = $wpdb->prefix . 'vandel_booking_notes';
    $sql_notes = "CREATE TABLE $notes_table (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        booking_id MEDIUMINT(9) NOT NULL,
        note_content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_by BIGINT(20) NOT NULL,
        PRIMARY KEY (id),
        INDEX (booking_id)
    ) $charset_collate;";
    
    dbDelta($sql_notes);
    
    // Log table creation results
    error_log("Vandel Debug: Manual table creation attempted");
    
    // Check if tables exist now
    $tables_to_check = [
        $wpdb->prefix . 'vandel_clients',
        $wpdb->prefix . 'vandel_bookings',
        $wpdb->prefix . 'vandel_booking_notes'
    ];
    
    foreach ($tables_to_check as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        error_log("Vandel Debug: Table $table exists after manual creation: " . ($table_exists ? 'Yes' : 'No'));
    }
}

// Uncomment the line below to manually create tables
// add_action('init', 'vandel_create_tables_manually');

// Function to check for clients table column issues
function vandel_check_clients_table() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vandel_clients';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table;
    
    if (!$table_exists) {
        error_log("Vandel Debug: Clients table does not exist");
        return;
    }
    
    // Get columns
    $columns = $wpdb->get_results("DESCRIBE $clients_table");
    $column_names = [];
    
    foreach ($columns as $column) {
        $column_names[] = $column->Field;
    }
    
    error_log("Vandel Debug: Clients table columns: " . implode(', ', $column_names));
    
    // Check for required columns
    $required_columns = ['id', 'email', 'name', 'phone', 'total_spent', 'created_at'];
    $missing_columns = [];
    
    foreach ($required_columns as $required) {
        if (!in_array($required, $column_names)) {
            $missing_columns[] = $required;
        }
    }
    
    if (!empty($missing_columns)) {
        error_log("Vandel Debug: Missing columns in clients table: " . implode(', ', $missing_columns));
    } else {
        error_log("Vandel Debug: All required columns exist in clients table");
    }
}

// Check clients table on init
add_action('init', 'vandel_check_clients_table');