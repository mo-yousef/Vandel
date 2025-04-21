<?php
namespace VandelBooking\Ajax;

/**
 * Handles AJAX actions for booking management
 */
class BookingAjaxHandler {
    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_vandel_booking_bulk_action', [$this, 'handle_bulk_action']);
    }

    /**
     * Handle bulk actions for bookings
     */
    public function handle_bulk_action() {
        // Verify nonce
        if (!isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'vandel_bookings_tab_nonce')) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'vandel-booking')
            ]);
            exit;
        }

        // Check user permissions with multiple checks
        if (!current_user_can('manage_options') && 
            !current_user_can('edit_posts') && 
            !current_user_can('manage_vandel_bookings')) {
            
            // Log unauthorized access attempt
            error_log(sprintf(
                'Unauthorized booking bulk action attempt by user ID %d', 
                get_current_user_id()
            ));

            wp_send_json_error([
                'message' => __('You do not have permission to perform this action', 'vandel-booking')
            ]);
            exit;
        }

        // Validate input
        $action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
        $booking_ids = isset($_POST['booking_ids']) ? array_map('intval', $_POST['booking_ids']) : [];

        // Ensure we have an action and booking IDs
        if (empty($action) || empty($booking_ids)) {
            wp_send_json_error([
                'message' => __('No action or bookings selected', 'vandel-booking')
            ]);
            exit;
        }

        // Ensure BookingManager is loaded
        if (!class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-manager.php';
        }

        $booking_manager = new \VandelBooking\Booking\BookingManager();
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        // Allowed bulk actions
        $allowed_actions = apply_filters('vandel_allowed_bulk_actions', [
            'confirm' => 'confirmed',
            'complete' => 'completed', 
            'cancel' => 'canceled', 
            'delete' => 'delete'
        ]);

        // Verify the action is allowed
        if (!isset($allowed_actions[$action])) {
            wp_send_json_error([
                'message' => __('Invalid bulk action', 'vandel-booking')
            ]);
            exit;
        }

        // Process each booking
        foreach ($booking_ids as $booking_id) {
            try {
                // Additional per-booking permission check
                if (!current_user_can('edit_post', $booking_id)) {
                    $error_count++;
                    $errors[] = sprintf(
                        __('Permission denied for booking #%d', 'vandel-booking'), 
                        $booking_id
                    );
                    continue;
                }

                // Perform action based on allowed actions
                switch ($action) {
                    case 'confirm':
                    case 'complete':
                    case 'cancel':
                        $result = $booking_manager->updateBookingStatus($booking_id, $allowed_actions[$action]);
                        break;
                    case 'delete':
                        $result = $booking_manager->deleteBooking($booking_id);
                        break;
                    default:
                        // Allow custom bulk actions via filter
                        $result = apply_filters('vandel_booking_bulk_action', true, $booking_id, $action);
                        break;
                }

                // Track success/failure
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = sprintf(
                        __('Failed to process booking #%d', 'vandel-booking'), 
                        $booking_id
                    );
                }
            } catch (\Exception $e) {
                $error_count++;
                $errors[] = sprintf(
                    __('Error processing booking #%d: %s', 'vandel-booking'), 
                    $booking_id, 
                    $e->getMessage()
                );

                // Log the error for debugging
                error_log(sprintf(
                    'Vandel Booking Bulk Action Error: %s - Booking #%d', 
                    $e->getMessage(), 
                    $booking_id
                ));
            }
        }

        // Prepare response
        if ($error_count > 0) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Processed %d bookings. %d bookings encountered errors.', 'vandel-booking'), 
                    $success_count, 
                    $error_count
                ),
                'details' => $errors
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully processed %d bookings.', 'vandel-booking'), 
                    $success_count
                )
            ]);
        }
    }
}