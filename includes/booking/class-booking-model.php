<?php
namespace VandelBooking\Booking;

/**
 * Booking Model
 */
class BookingModel {
    /**
     * @var string Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_bookings';
    }
    
    /**
     * Create booking
     * 
     * @param array $data Booking data
     * @return int|false Booking ID or false if failed
     */
    public function create($data) {
        global $wpdb;
        
        $defaults = [
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Format sub_services as JSON
        if (isset($data['sub_services']) && is_array($data['sub_services'])) {
            $data['sub_services'] = json_encode($data['sub_services']);
        }
        
        $format = [
            '%d', // client_id
            '%s', // service
            '%s', // sub_services
            '%s', // booking_date
            '%s', // customer_name
            '%s', // customer_email
            '%s', // access_info
            '%f', // total_price
            '%s', // status
            '%s'  // created_at
        ];
        
        $result = $wpdb->insert($this->table, $data, $format);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Failed to create booking: " . $wpdb->last_error);
            }
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get booking by ID
     * 
     * @param int $booking_id Booking ID
     * @return object|false Booking object or false if not found
     */
    public function get($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return false;
        }
        
        // Decode JSON data
        if (!empty($booking->sub_services)) {
            $booking->sub_services = json_decode($booking->sub_services);
        }
        
        return $booking;
    }
    
    /**
     * Get all bookings with filters
     * 
     * @param array $args Filter arguments
     * @return array Bookings
     */
    public function getAll($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'client_id' => 0,
            'service' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'booking_date',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = [];
        $values = [];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['client_id'])) {
            $where[] = 'client_id = %d';
            $values[] = $args['client_id'];
        }
        
        if (!empty($args['service'])) {
            $where[] = 'service = %s';
            $values[] = $args['service'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'booking_date >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'booking_date <= %s';
            $values[] = $args['date_to'];
        }
        
        // Build query
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        // Order
        $query .= $wpdb->prepare(' ORDER BY %s %s', $args['orderby'], $args['order']);
        
        // Limit
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if (!empty($args['offset'])) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare final query
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        // Get results
        $bookings = $wpdb->get_results($query);
        
        // Decode JSON data
        foreach ($bookings as &$booking) {
            if (!empty($booking->sub_services)) {
                $booking->sub_services = json_decode($booking->sub_services);
            }
        }
        
        return $bookings;
    }
    
    /**
     * Update booking
     * 
     * @param int $booking_id Booking ID
     * @param array $data Booking data
     * @return bool Whether the booking was updated
     */
    public function update($booking_id, $data) {
        global $wpdb;
        
        // Format sub_services as JSON
        if (isset($data['sub_services']) && is_array($data['sub_services'])) {
            $data['sub_services'] = json_encode($data['sub_services']);
        }
        
        $format = [];
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $format[] = is_float($value + 0) ? '%f' : '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $this->table,
            $data,
            ['id' => $booking_id],
            $format,
            ['%d']
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Failed to update booking: " . $wpdb->last_error);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Update booking status
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Whether the status was updated
     */
    public function updateStatus($booking_id, $status) {
        return $this->update($booking_id, ['status' => $status]);
    }
    
    /**
     * Delete booking
     * 
     * @param int $booking_id Booking ID
     * @return bool Whether the booking was deleted
     */
    public function delete($booking_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $booking_id],
            ['%d']
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Failed to delete booking: " . $wpdb->last_error);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Get booking counts by status
     * 
     * @return array Booking counts
     */
    public function getStatusCounts() {
        global $wpdb;
        
        $query = "SELECT status, COUNT(*) as count 
                  FROM {$this->table} 
                  GROUP BY status";
        
        $results = $wpdb->get_results($query);
        $counts = [];
        
        foreach (['pending', 'confirmed', 'completed', 'canceled'] as $status) {
            $counts[$status] = 0;
        }
        
        foreach ($results as $result) {
            $counts[$result->status] = (int) $result->count;
        }
        
        $counts['total'] = array_sum($counts);
        
        return $counts;
    }
    
    /**
     * Get bookings for calendar
     * 
     * @param int $year Year
     * @param int $month Month
     * @return array Bookings
     */
    public function getCalendarBookings($year, $month) {
        global $wpdb;
        
        $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                    s.post_title as service_name,
                    TIME(b.booking_date) as booking_time
             FROM {$this->table} b
             LEFT JOIN {$wpdb->posts} s ON b.service = s.ID
             WHERE b.booking_date BETWEEN %s AND %s
             ORDER BY b.booking_date ASC",
            $start_date,
            $end_date
        ));
        
        // Add detail URLs
        foreach ($bookings as &$booking) {
            $booking->details_url = admin_url("admin.php?page=vandel-dashboard&tab=booking-details&booking_id={$booking->id}");
            $booking->approve_url = wp_nonce_url(
                admin_url("admin.php?page=vandel-dashboard&tab=booking-details&booking_id={$booking->id}&action=approve"),
                'approve_booking_' . $booking->id
            );
            $booking->cancel_url = wp_nonce_url(
                admin_url("admin.php?page=vandel-dashboard&tab=booking-details&booking_id={$booking->id}&action=cancel"),
                'cancel_booking_' . $booking->id
            );
        }
        
        return $bookings;
    }
}