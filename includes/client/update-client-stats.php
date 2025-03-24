<?php
namespace VandelBooking\Client;

/**
 * Update client statistics from bookings
 * This function should be called after plugin update to populate client statistics
 */
function vandel_update_client_statistics() {
    global $wpdb;
    $clients_table = $wpdb->prefix . 'vandel_clients';
    $bookings_table = $wpdb->prefix . 'vandel_bookings';
    
    // Check if both tables exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$clients_table'") !== $clients_table || 
        $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
        return false;
    }
    
    // Get all clients
    $clients = $wpdb->get_results("SELECT id, email FROM $clients_table");
    
    if (empty($clients)) {
        return false;
    }
    
    foreach ($clients as $client) {
        // Get total spent from completed bookings
        $total_spent = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $bookings_table 
             WHERE client_id = %d AND status = 'completed'",
            $client->id
        ));
        
        // Get total bookings count
        $bookings_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE client_id = %d",
            $client->id
        ));
        
        // Get last booking date
        $last_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT booking_date FROM $bookings_table 
             WHERE client_id = %d 
             ORDER BY booking_date DESC LIMIT 1",
            $client->id
        ));
        
        // Update client stats
        $wpdb->update(
            $clients_table,
            [
                'total_spent' => floatval($total_spent ?: 0),
                'bookings_count' => intval($bookings_count ?: 0),
                'last_booking' => $last_booking ?: null,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $client->id],
            ['%f', '%d', '%s', '%s'],
            ['%d']
        );
    }
    
    return true;
}

/**
 * Add hook to update client statistics on plugin activation
 */
function vandel_maybe_update_client_statistics() {
    // Check if we've already run this update
    if (get_option('vandel_client_stats_updated') === 'yes') {
        return;
    }
    
    // Run the update
    if (vandel_update_client_statistics()) {
        // Mark as completed
        update_option('vandel_client_stats_updated', 'yes');
    }
}

// Register the function to run on admin init with a very low priority
add_action('admin_init', 'VandelBooking\\Client\\vandel_maybe_update_client_statistics', 999);

/**
 * Add AJAX handler for recalculating client stats
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
    
    $client_model = new ClientModel();
    $result = $client_model->recalculateStats($client_id);
    
    if ($result) {
        wp_send_json_success(['message' => __('Client statistics updated successfully', 'vandel-booking')]);
    } else {
        wp_send_json_error(['message' => __('Failed to update client statistics', 'vandel-booking')]);
    }
}

// Register AJAX handler
add_action('wp_ajax_vandel_recalculate_client_stats', 'VandelBooking\\Client\\vandel_ajax_recalculate_client_stats');
