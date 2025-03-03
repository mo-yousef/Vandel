<?php
namespace VandelBooking\Database;

/**
 * Handles database installation and upgrades
 */
class Installer {
    /**
     * Run the installer
     */
    public function install() {
        $this->createTables();
        $this->setVersion();
    }
    
    /**
     * Create database tables
     */
    private function createTables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create clients table
        $this->createClientsTable($charset_collate);
        
        // Create bookings table
        $this->createBookingsTable($charset_collate);
        
        // Create notes table
        $this->createNotesTable($charset_collate);
    }
    
    /**
     * Create clients table
     * 
     * @param string $charset_collate Database charset
     */
    private function createClientsTable($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_clients';
        
        $sql = "CREATE TABLE $table_name (
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
    
    /**
     * Create bookings table
     * 
     * @param string $charset_collate Database charset
     */
    private function createBookingsTable($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        $clients_table = $wpdb->prefix . 'vandel_clients';
        
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            client_id MEDIUMINT(9) NOT NULL,
            service VARCHAR(255) NOT NULL,
            sub_services TEXT NULL,
            access_info TEXT NULL,
            booking_date DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (status),
            INDEX (booking_date),
            FOREIGN KEY (client_id) 
                REFERENCES {$clients_table}(id)
                ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create notes table
     * 
     * @param string $charset_collate Database charset
     */
    private function createNotesTable($charset_collate) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_booking_notes';
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        $sql = "CREATE TABLE $table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            booking_id MEDIUMINT(9) NOT NULL,
            note_content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT(20) NOT NULL,
            PRIMARY KEY (id),
            INDEX (booking_id),
            FOREIGN KEY (booking_id) 
                REFERENCES {$bookings_table}(id)
                ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Set plugin version
     */
    private function setVersion() {
        update_option('vandel_booking_version', VANDEL_VERSION);
    }
}