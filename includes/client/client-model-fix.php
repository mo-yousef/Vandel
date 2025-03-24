<?php
namespace VandelBooking\Client;

/**
 * Fix for Client Model
 * Ensures the table has the required columns
 */
function vandel_upgrade_client_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vandel_clients';
    
    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        // If the table doesn't exist, create it
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
        
        return; // Table created, no need to check columns
    }
    
    // Check if bookings_count column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'bookings_count'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN bookings_count INT DEFAULT 0 AFTER total_spent");
    }
    
    // Check if last_booking column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'last_booking'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN last_booking DATETIME NULL AFTER bookings_count");
    }
    
    // Check if address column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'address'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN address TEXT NULL AFTER phone");
    }
    
    // Check if notes column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'notes'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN notes TEXT NULL AFTER address");
    }
    
    // Check if updated_at column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'updated_at'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at DATETIME NULL AFTER created_at");
    }
    
    // Check if custom_fields column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'custom_fields'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN custom_fields TEXT NULL AFTER last_booking");
    }
}

// Call the function to update the table
add_action('admin_init', 'VandelBooking\\Client\\vandel_upgrade_client_table');
