<?php
/**
 * Vandel Booking Plugin Database Diagnostic and Fix Tool
 * 
 * This script checks and repairs database issues with the Vandel Booking plugin.
 * Add this to the includes/ directory of your plugin and run it by visiting:
 * ?page=vandel-dashboard&tab=settings&run_diagnostic=1
 */

// Security check - only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

class VandelDiagnosticTool {
    private $tables = [
        'vandel_bookings',
        'vandel_clients',
        'vandel_booking_notes',
        'vandel_zip_codes'
    ];
    
    private $issues = [];
    private $fixed = [];
    
    /**
     * Run the diagnostic and fix
     */
    public function run() {
        global $wpdb;
        
        echo '<div class="wrap">';
        echo '<h1>Vandel Booking Plugin Diagnostic Tool</h1>';
        
        // Check if tables exist
        echo '<h2>Checking Database Tables</h2>';
        
        $missing_tables = [];
        foreach ($this->tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            echo "<p>Table <code>$table_name</code>: " . ($table_exists ? '✅ Exists' : '❌ Missing') . "</p>";
            
            if (!$table_exists) {
                $missing_tables[] = $table;
                $this->issues[] = "Missing table: $table_name";
            }
        }
        
        // Fix missing tables if needed
        if (!empty($missing_tables)) {
            if (isset($_POST['fix_tables']) && wp_verify_nonce($_POST['vandel_diagnostic_nonce'], 'vandel_fix_tables')) {
                echo '<h3>Creating Missing Tables...</h3>';
                $this->createMissingTables($missing_tables);
            } else {
                echo '<form method="post">';
                wp_nonce_field('vandel_fix_tables', 'vandel_diagnostic_nonce');
                echo '<input type="submit" name="fix_tables" value="Create Missing Tables" class="button button-primary">';
                echo '</form>';
            }
        }
        
        // Check client model files
        echo '<h2>Checking Client Model Files</h2>';
        
        $client_files = [
            VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php',
            VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-client-model.php',
        ];
        
        foreach ($client_files as $file) {
            echo "<p>File <code>" . basename($file) . "</code>: " . (file_exists($file) ? '✅ Exists' : '❌ Missing') . "</p>";
            
            if (!file_exists($file)) {
                $this->issues[] = "Missing file: " . basename($file);
            }
        }
        
        // Fix client model file if needed
        if (!file_exists($client_files[0]) && file_exists($client_files[1])) {
            if (isset($_POST['fix_client_model']) && wp_verify_nonce($_POST['vandel_diagnostic_nonce'], 'vandel_fix_tables')) {
                echo '<h3>Fixing Client Model...</h3>';
                $this->fixClientModel($client_files[1], $client_files[0]);
            } else {
                echo '<form method="post">';
                wp_nonce_field('vandel_fix_tables', 'vandel_diagnostic_nonce');
                echo '<input type="submit" name="fix_client_model" value="Fix Client Model" class="button button-primary">';
                echo '</form>';
            }
        }
        
        // Check AJAX handler files
        echo '<h2>Checking AJAX Handler Files</h2>';
        
        $ajax_file = VANDEL_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
        echo "<p>File <code>class-ajax-handler.php</code>: " . (file_exists($ajax_file) ? '✅ Exists' : '❌ Missing') . "</p>";
        
        if (!file_exists($ajax_file)) {
            $this->issues[] = "Missing file: class-ajax-handler.php";
            
            if (isset($_POST['fix_ajax_handler']) && wp_verify_nonce($_POST['vandel_diagnostic_nonce'], 'vandel_fix_tables')) {
                echo '<h3>Creating AJAX Handler...</h3>';
                $this->createAjaxHandler($ajax_file);
            } else {
                echo '<form method="post">';
                wp_nonce_field('vandel_fix_tables', 'vandel_diagnostic_nonce');
                echo '<input type="submit" name="fix_ajax_handler" value="Create AJAX Handler" class="button button-primary">';
                echo '</form>';
            }
        }
        
        // Sample booking data
        echo '<h2>Test Booking Data</h2>';
        
        // Count existing bookings
        $booking_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vandel_bookings'") === $wpdb->prefix . 'vandel_bookings') {
            $booking_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}vandel_bookings");
        }
        
        echo "<p>Current bookings in database: $booking_count</p>";
        
        if (isset($_POST['create_test_booking']) && wp_verify_nonce($_POST['vandel_diagnostic_nonce'], 'vandel_fix_tables')) {
            echo '<h3>Creating Test Booking...</h3>';
            $this->createTestBooking();
        } else {
            echo '<form method="post">';
            wp_nonce_field('vandel_fix_tables', 'vandel_diagnostic_nonce');
            echo '<input type="submit" name="create_test_booking" value="Create Test Booking" class="button button-primary">';
            echo '</form>';
        }
        
        // Summary
        echo '<h2>Diagnostic Summary</h2>';
        
        if (empty($this->issues) && empty($this->fixed)) {
            echo '<p>✅ No issues found. Your database setup appears to be working correctly.</p>';
        } else {
            if (!empty($this->issues)) {
                echo '<h3>Outstanding Issues:</h3>';
                echo '<ul>';
                foreach ($this->issues as $issue) {
                    echo "<li>$issue</li>";
                }
                echo '</ul>';
            }
            
            if (!empty($this->fixed)) {
                echo '<h3>Fixed Issues:</h3>';
                echo '<ul>';
                foreach ($this->fixed as $fix) {
                    echo "<li>$fix</li>";
                }
                echo '</ul>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Create missing database tables
     */
    private function createMissingTables($missing_tables) {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($missing_tables as $table) {
            switch ($table) {
                case 'vandel_clients':
                    $sql = "CREATE TABLE {$wpdb->prefix}vandel_clients (
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
                    
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vandel_clients'") === $wpdb->prefix . 'vandel_clients') {
                        echo "<p>✅ Created table: {$wpdb->prefix}vandel_clients</p>";
                        $this->fixed[] = "Created table: {$wpdb->prefix}vandel_clients";
                    } else {
                        echo "<p>❌ Failed to create table: {$wpdb->prefix}vandel_clients</p>";
                    }
                    break;
                    
                case 'vandel_bookings':
                    $sql = "CREATE TABLE {$wpdb->prefix}vandel_bookings (
                        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                        client_id MEDIUMINT(9) NOT NULL,
                        service VARCHAR(255) NOT NULL,
                        sub_services TEXT NULL,
                        booking_date DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
                        customer_name VARCHAR(255) NOT NULL,
                        customer_email VARCHAR(255) NOT NULL,
                        phone VARCHAR(20) NULL,
                        access_info TEXT NULL,
                        total_price DECIMAL(10, 2) NOT NULL,
                        status VARCHAR(50) DEFAULT 'pending',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        INDEX (status),
                        INDEX (booking_date)
                    ) $charset_collate;";
                    
                    dbDelta($sql);
                    
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vandel_bookings'") === $wpdb->prefix . 'vandel_bookings') {
                        echo "<p>✅ Created table: {$wpdb->prefix}vandel_bookings</p>";
                        $this->fixed[] = "Created table: {$wpdb->prefix}vandel_bookings";
                    } else {
                        echo "<p>❌ Failed to create table: {$wpdb->prefix}vandel_bookings</p>";
                    }
                    break;
                    
                case 'vandel_booking_notes':
                    $sql = "CREATE TABLE {$wpdb->prefix}vandel_booking_notes (
                        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                        booking_id MEDIUMINT(9) NOT NULL,
                        note_content TEXT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        created_by BIGINT(20) NOT NULL,
                        PRIMARY KEY (id),
                        INDEX (booking_id)
                    ) $charset_collate;";
                    
                    dbDelta($sql);
                    
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vandel_booking_notes'") === $wpdb->prefix . 'vandel_booking_notes') {
                        echo "<p>✅ Created table: {$wpdb->prefix}vandel_booking_notes</p>";
                        $this->fixed[] = "Created table: {$wpdb->prefix}vandel_booking_notes";
                    } else {
                        echo "<p>❌ Failed to create table: {$wpdb->prefix}vandel_booking_notes</p>";
                    }
                    break;
                    
                case 'vandel_zip_codes':
                    $sql = "CREATE TABLE {$wpdb->prefix}vandel_zip_codes (
                        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                        zip_code VARCHAR(20) NOT NULL UNIQUE,
                        city VARCHAR(100) NOT NULL,
                        state VARCHAR(100) NULL,
                        country VARCHAR(100) NOT NULL,
                        price_adjustment DECIMAL(10, 2) DEFAULT 0,
                        service_fee DECIMAL(10, 2) DEFAULT 0,
                        is_serviceable ENUM('yes', 'no') DEFAULT 'yes',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        INDEX (zip_code)
                    ) $charset_collate;";
                    
                    dbDelta($sql);
                    
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}vandel_zip_codes'") === $wpdb->prefix . 'vandel_zip_codes') {
                        echo "<p>✅ Created table: {$wpdb->prefix}vandel_zip_codes</p>";
                        $this->fixed[] = "Created table: {$wpdb->prefix}vandel_zip_codes";
                    } else {
                        echo "<p>❌ Failed to create table: {$wpdb->prefix}vandel_zip_codes</p>";
                    }
                    break;
            }
        }
        
        // Remove fixed issues from issues list
        foreach ($this->fixed as $fix) {
            $issue_text = str_replace('Created', 'Missing', $fix);
            $key = array_search($issue_text, $this->issues);
            if ($key !== false) {
                unset($this->issues[$key]);
            }
        }
    }
    
    /**
     * Fix client model file
     */
    private function fixClientModel($source, $destination) {
        // Create directory if it doesn't exist
        $dir = dirname($destination);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_exists($source)) {
            // Copy the file
            $content = file_get_contents($source);
            $content = str_replace('namespace VandelBooking\\Booking;', 'namespace VandelBooking\\Client;', $content);
            $content = str_replace('class BookingClientModel', 'class ClientModel', $content);
            
            $result = file_put_contents($destination, $content);
            
            if ($result !== false) {
                echo "<p>✅ Created file: " . basename($destination) . "</p>";
                $this->fixed[] = "Created file: " . basename($destination);
                
                // Remove from issues list
                $key = array_search("Missing file: " . basename($destination), $this->issues);
                if ($key !== false) {
                    unset($this->issues[$key]);
                }
            } else {
                echo "<p>❌ Failed to create file: " . basename($destination) . "</p>";
            }
        } else {
            echo "<p>❌ Source file not found: " . basename($source) . "</p>";
        }
    }
    
    /**
     * Create AJAX handler file
     */
    private function createAjaxHandler($destination) {
        // Create directory if it doesn't exist
        $dir = dirname($destination);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Create AJAX handler content
        $content = <<<'EOT'
