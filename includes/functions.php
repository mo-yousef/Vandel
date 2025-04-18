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



/**
 * Add custom inline styles for primary color
 */
function vandel_add_custom_dashboard_colors() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->base, 'vandel-dashboard') === false) {
        return;
    }
    
    $primary_color = get_option('vandel_primary_color', '#3182ce');
    
    // Generate darker and lighter variants
    $primary_dark = vandel_adjust_brightness($primary_color, -20);
    $primary_light = vandel_adjust_brightness($primary_color, 40);
    
    $custom_css = "
    :root {
      --vandel-primary-color: {$primary_color};
      --vandel-primary-dark: {$primary_dark};
      --vandel-primary-light: {$primary_light};
    }
    
    .vandel-dashboard-welcome {
      background: linear-gradient(135deg, {$primary_color} 0%, {$primary_dark} 100%);
    }";
    
    wp_add_inline_style('vandel-modern-dashboard', $custom_css);
}
add_action('admin_enqueue_scripts', 'vandel_add_custom_dashboard_colors', 20);

/**
 * Helper function to adjust color brightness
 */
function vandel_adjust_brightness($hex, $steps) {
    // Convert hex to rgb
    $hex = str_replace('#', '', $hex);
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    // Convert back to hex
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}



/**
 * Enqueue ZIP Code admin scripts 
 */
function vandel_enqueue_zip_code_admin_scripts($hook) {
    // Only load on the ZIP code settings page
    if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
        return;
    }
    
    // Check if we're on the ZIP codes settings page
    if (!isset($_GET['tab']) || $_GET['tab'] !== 'settings' || 
        !isset($_GET['section']) || $_GET['section'] !== 'zip-codes') {
        return;
    }
    
    // Enqueue the ZIP code admin script
    wp_enqueue_script(
        'vandel-zip-code-admin',
        VANDEL_PLUGIN_URL . 'assets/js/admin/zip-code-admin.js',
        ['jquery'],
        VANDEL_VERSION,
        true
    );
    
    // Localize the script
    wp_localize_script(
        'vandel-zip-code-admin',
        'vandelZipCodeAdmin',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_zip_code_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this ZIP code?', 'vandel-booking')
        ]
    );
}
add_action('admin_enqueue_scripts', 'vandel_enqueue_zip_code_admin_scripts');




/**
 * Client Statistics AJAX Handler
 * Add this to your functions.php file or includes/client/client-functions.php
 */

/**
 * AJAX handler for recalculating client stats
 */
function vandel_ajax_recalculate_client_stats() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vandel_client_admin')) {
        wp_send_json_error(['message' => __('Security verification failed', 'vandel-booking')]);
        return;
    }
    
    // Check for client ID
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    if ($client_id <= 0) {
        wp_send_json_error(['message' => __('Invalid client ID', 'vandel-booking')]);
        return;
    }
    
    // Get ClientModel instance
    if (!class_exists('\\VandelBooking\\Client\\ClientModel')) {
        wp_send_json_error(['message' => __('Client model not found', 'vandel-booking')]);
        return;
    }
    
    $client_model = new \VandelBooking\Client\ClientModel();
    
    // Check if recalculateStats method exists
    if (!method_exists($client_model, 'recalculateStats')) {
        wp_send_json_error(['message' => __('Recalculate method not available', 'vandel-booking')]);
        return;
    }
    
    $result = $client_model->recalculateStats($client_id);
    
    if ($result) {
        wp_send_json_success(['message' => __('Client statistics updated successfully', 'vandel-booking')]);
    } else {
        wp_send_json_error(['message' => __('Failed to update client statistics', 'vandel-booking')]);
    }
}

// Register AJAX handler
add_action('wp_ajax_vandel_recalculate_client_stats', 'vandel_ajax_recalculate_client_stats');



/**
 * Migrate legacy ZIP codes to Location Management
 */
function vandel_migrate_zip_codes_to_locations() {
    global $wpdb;
    
    // Check if both tables exist
    $zip_codes_table = $wpdb->prefix . 'vandel_zip_codes';
    $locations_table = $wpdb->prefix . 'vandel_locations';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$zip_codes_table'") !== $zip_codes_table ||
        $wpdb->get_var("SHOW TABLES LIKE '$locations_table'") !== $locations_table) {
        return false;
    }
    
    // Get all ZIP codes
    $zip_codes = $wpdb->get_results("SELECT * FROM $zip_codes_table");
    
    if (empty($zip_codes)) {
        return false;
    }
    
    $imported = 0;
    
    foreach ($zip_codes as $zip) {
        // Check if already exists in locations
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $locations_table WHERE zip_code = %s AND country = %s AND city = %s",
            $zip->zip_code, $zip->country, $zip->city
        ));
        
        if ($exists) {
            continue; // Skip if already exists
        }
        
        // Insert into locations
        $result = $wpdb->insert(
            $locations_table,
            [
                'country' => $zip->country,
                'city' => $zip->city,
                'area_name' => '', // No area name in legacy data
                'zip_code' => $zip->zip_code,
                'price_adjustment' => $zip->price_adjustment,
                'service_fee' => $zip->service_fee,
                'is_active' => $zip->is_serviceable,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result) {
            $imported++;
        }
    }
    
    return $imported;
}

// Add this to an admin action or run manually
add_action('admin_init', 'vandel_migrate_zip_codes_to_locations');