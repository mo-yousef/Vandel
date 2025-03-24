<?php
namespace VandelBooking\Admin;

use VandelBooking\Client\ClientModel;
use VandelBooking\Helpers;

/**
 * Client Details Management Class
 */
class ClientDetails {
    /**
     * @var ClientModel
     */
    private $client_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize client model
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $this->client_model = new ClientModel();
        }
        
        // Handle client actions
        add_action('admin_init', [$this, 'handleClientActions']);
    }
    
    /**
     * Handle client actions
     */
    public function handleClientActions() {
        // Only process if we're on our dashboard page
        if (!isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard') {
            return;
        }
        
        // Check for client update
        if (isset($_POST['vandel_update_client']) && wp_verify_nonce($_POST['vandel_client_nonce'], 'vandel_update_client')) {
            $this->handleClientUpdate();
        }
        
        // Check for client deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete_client' && isset($_GET['client_id']) && isset($_GET['_wpnonce'])) {
            $client_id = intval($_GET['client_id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_client_' . $client_id)) {
                $this->handleClientDeletion($client_id);
            }
        }
        
        // Check for client notes update
        if (isset($_POST['vandel_add_client_note']) && wp_verify_nonce($_POST['vandel_client_note_nonce'], 'vandel_add_client_note')) {
            $this->handleAddClientNote();
        }
    }
    
    /**
     * Handle client update
     */
    private function handleClientUpdate() {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        if ($client_id === 0) {
            // Handle client creation
            try {
                $client_data = [
                    'name' => sanitize_text_field($_POST['client_name']),
                    'email' => sanitize_email($_POST['client_email']),
                    'phone' => sanitize_text_field($_POST['client_phone']),
                    'address' => sanitize_textarea_field($_POST['client_address']),
                    'notes' => sanitize_textarea_field($_POST['client_notes'])
                ];
                
                // Validate required fields
                if (empty($client_data['name']) || empty($client_data['email'])) {
                    wp_redirect(add_query_arg([
                        'page' => 'vandel-dashboard',
                        'tab' => 'clients',
                        'action' => 'add',
                        'message' => 'missing_fields'
                    ], admin_url('admin.php')));
                    exit;
                }
                
                // Create client
                $client_id = $this->client_model->getOrCreateClient($client_data);
                
                // Redirect to client details
                wp_redirect(add_query_arg([
                    'page' => 'vandel-dashboard',
                    'tab' => 'client-details',
                    'client_id' => $client_id,
                    'message' => 'client_created'
                ], admin_url('admin.php')));
                exit;
                
            } catch (\Exception $e) {
                wp_redirect(add_query_arg([
                    'page' => 'vandel-dashboard',
                    'tab' => 'clients',
                    'action' => 'add',
                    'message' => 'create_failed'
                ], admin_url('admin.php')));
                exit;
            }
        } else {
            // Handle client update
            $client_data = [
                'name' => sanitize_text_field($_POST['client_name']),
                'email' => sanitize_email($_POST['client_email']),
                'phone' => sanitize_text_field($_POST['client_phone']),
                'address' => sanitize_textarea_field($_POST['client_address']),
                'notes' => sanitize_textarea_field($_POST['client_notes'])
            ];
            
            // Validate required fields
            if (empty($client_data['name']) || empty($client_data['email'])) {
                wp_redirect(add_query_arg([
                    'page' => 'vandel-dashboard',
                    'tab' => 'client-details',
                    'client_id' => $client_id,
                    'message' => 'missing_fields'
                ], admin_url('admin.php')));
                exit;
            }
            
            // Update client
            $result = $this->client_model->update($client_id, $client_data);
            
            // Redirect back to client details page with message
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'client-details',
                'client_id' => $client_id,
                'message' => $result ? 'client_updated' : 'update_failed'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle client deletion
     * 
     * @param int $client_id Client ID
     */
    private function handleClientDeletion($client_id) {
        if (!$this->client_model) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                'message' => 'delete_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        $result = $this->client_model->delete($client_id);
        
        wp_redirect(add_query_arg([
            'page' => 'vandel-dashboard',
            'tab' => 'clients',
            'message' => $result ? 'client_deleted' : 'delete_failed'
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle adding client note
     */
    private function handleAddClientNote() {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $note_content = isset($_POST['client_note']) ? sanitize_textarea_field($_POST['client_note']) : '';
        
        if ($client_id === 0 || empty($note_content)) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'client-details',
                'client_id' => $client_id,
                'message' => 'note_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Get client data
        $client = $this->client_model->get($client_id);
        if (!$client) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                'message' => 'client_not_found'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Update client notes
        $current_notes = $client->notes ?: '';
        $new_notes = date_i18n(get_option('date_format') . ' ' . get_option('time_format')) . " - " . $note_content . "\n\n" . $current_notes;
        
        $result = $this->client_model->update($client_id, ['notes' => $new_notes]);
        
        wp_redirect(add_query_arg([
            'page' => 'vandel-dashboard',
            'tab' => 'client-details',
            'client_id' => $client_id,
            'message' => $result ? 'note_added' : 'note_failed'
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Render client details page
     * 
     * @param int $client_id Client ID
     */
    public function render($client_id = 0) {
        // Check if client exists
        $client = null;
        if ($client_id > 0 && $this->client_model) {
            $client = $this->client_model->get($client_id);
        }
        
        // Handle action to add new client
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'add' || (!$client && $client_id > 0)) {
            $this->renderAddOrEditClient($client);
            return;
        }
        
        // Display client details if client exists
        if ($client) {
            $this->renderClientDetails($client);
        } else {
            // Display error message
            echo '<div class="notice notice-error"><p>' . __('Client not found', 'vandel-booking') . '</p></div>';
            // Display client list as fallback
            echo '<p><a href="' . admin_url('admin.php?page=vandel-dashboard&tab=clients') . '" class="button">' . __('Back to Clients List', 'vandel-booking') . '</a></p>';
        }
    }
    
    /**
     * Render client details view
     * 
     * @param object $client Client data
     */
    private function renderClientDetails($client) {
        // Get client statistics
        $client_stats = $this->client_model->getClientStats($client->id);
        
        // Get client bookings
        $client_bookings = $this->client_model->getClientBookings($client->id, [
            'limit' => 10
        ]);
        
        // Display status messages
        $this->displayStatusMessage();
        
        ?>
        <div class="vandel-client-details">
            <div class="vandel-client-header">
                <h2><?php echo esc_html($client->name); ?></h2>
                <div class="vandel-client-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id . '&action=edit')); ?>" class="button">
                        <span class="dashicons dashicons-edit"></span> <?php _e('Edit Client', 'vandel-booking'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id . '&action=delete_client'), 'delete_client_' . $client->id); ?>" class="button button-link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this client? This action cannot be undone.', 'vandel-booking'); ?>')">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'vandel-booking'); ?>
                    </a>
                </div>
            </div>
            
            <div class="vandel-grid-row">
                <!-- Client Details -->
                <div class="vandel-grid-col">
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Client Information', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <table class="vandel-info-table">
                                <tr>
                                    <th><?php _e('Email:', 'vandel-booking'); ?></th>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr($client->email); ?>"><?php echo esc_html($client->email); ?></a>
                                    </td>
                                </tr>
                                <?php if (!empty($client->phone)): ?>
                                <tr>
                                    <th><?php _e('Phone:', 'vandel-booking'); ?></th>
                                    <td>
                                        <a href="tel:<?php echo esc_attr($client->phone); ?>"><?php echo esc_html($client->phone); ?></a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($client->address)): ?>
                                <tr>
                                    <th><?php _e('Address:', 'vandel-booking'); ?></th>
                                    <td><?php echo nl2br(esc_html($client->address)); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><?php _e('Client Since:', 'vandel-booking'); ?></th>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($client->created_at)); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Client Notes -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Client Notes', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <form method="post" action="">
                                <?php wp_nonce_field('vandel_add_client_note', 'vandel_client_note_nonce'); ?>
                                <input type="hidden" name="client_id" value="<?php echo esc_attr($client->id); ?>">
                                <div class="vandel-form-row">
                                    <textarea name="client_note" rows="3" class="widefat" placeholder="<?php _e('Add a note about this client...', 'vandel-booking'); ?>"></textarea>
                                </div>
                                <div class="vandel-form-row">
                                    <button type="submit" name="vandel_add_client_note" class="button button-primary">
                                        <span class="dashicons dashicons-plus"></span> <?php _e('Add Note', 'vandel-booking'); ?>
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($client->notes)): ?>
                                <div class="vandel-client-notes">
                                    <?php echo nl2br(esc_html($client->notes)); ?>
                                </div>
                            <?php else: ?>
                                <p class="vandel-empty-state-small"><?php _e('No notes yet.', 'vandel-booking'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Client Stats & Bookings -->
                <div class="vandel-grid-col">
                    <!-- Client Statistics -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Client Statistics', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-stats-grid">
                                <div class="vandel-stat-box">
                                    <div class="vandel-stat-value"><?php echo number_format_i18n($client_stats->bookings_count); ?></div>
                                    <div class="vandel-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></div>
                                </div>
                                
                                <div class="vandel-stat-box">
                                    <div class="vandel-stat-value"><?php echo Helpers::formatPrice($client_stats->total_spent); ?></div>
                                    <div class="vandel-stat-label"><?php _e('Total Spent', 'vandel-booking'); ?></div>
                                </div>
                                
                                <div class="vandel-stat-box">
                                    <div class="vandel-stat-value"><?php echo Helpers::formatPrice($client_stats->average_booking); ?></div>
                                    <div class="vandel-stat-label"><?php _e('Avg. Booking Value', 'vandel-booking'); ?></div>
                                </div>
                                
                                <div class="vandel-stat-box">
                                    <div class="vandel-stat-value">
                                        <?php if (!empty($client_stats->last_booking)): ?>
                                            <?php echo date_i18n(get_option('date_format'), strtotime($client_stats->last_booking)); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </div>
                                    <div class="vandel-stat-label"><?php _e('Last Booking', 'vandel-booking'); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($client_stats->bookings_count > 0): ?>
                                <!-- Status Breakdown -->
                                <div class="vandel-status-breakdown">
                                    <h4><?php _e('Booking Status Breakdown', 'vandel-booking'); ?></h4>
                                    <div class="vandel-status-bars">
                                        <?php 
                                        foreach ($client_stats->status_counts as $status => $count) {
                                            $percentage = $client_stats->bookings_count > 0 ? 
                                                round(($count / $client_stats->bookings_count) * 100) : 0;
                                            
                                            $status_colors = [
                                                'pending' => '#f0ad4e',
                                                'confirmed' => '#5bc0de',
                                                'completed' => '#5cb85c',
                                                'canceled' => '#d9534f',
                                            ];
                                            
                                            $color = isset($status_colors[$status]) ? $status_colors[$status] : '#777';
                                            ?>
                                            <div class="vandel-status-bar-container">
                                                <div class="vandel-status-bar-label">
                                                    <?php echo ucfirst($status); ?>
                                                    <span class="vandel-status-count"><?php echo number_format_i18n($count); ?></span>
                                                </div>
                                                <div class="vandel-status-bar">
                                                    <div class="vandel-status-bar-fill" style="width: <?php echo esc_attr($percentage); ?>%; background-color: <?php echo esc_attr($color); ?>"></div>
                                                </div>
                                                <div class="vandel-status-bar-percent"><?php echo $percentage; ?>%</div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&client_id=' . $client->id)); ?>" class="vandel-view-all"><?php _e('View All', 'vandel-booking'); ?></a>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($client_bookings)): ?>
                                <div class="vandel-empty-state-small">
                                    <p><?php _e('No bookings found for this client.', 'vandel-booking'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add&client_id=' . $client->id); ?>" class="button button-small">
                                        <?php _e('Create Booking', 'vandel-booking'); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="vandel-recent-bookings-list">
                                    <?php foreach ($client_bookings as $booking): ?>
                                        <div class="vandel-booking-item">
                                            <div class="vandel-booking-item-header">
                                                <div class="vandel-booking-id">
                                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>">
                                                        #<?php echo esc_html($booking->id); ?>
                                                    </a>
                                                </div>
                                                <div class="vandel-booking-status">
                                                    <span class="vandel-status-badge vandel-status-badge-<?php echo esc_attr($booking->status); ?>">
                                                        <?php echo ucfirst($booking->status); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="vandel-booking-details">
                                                <div class="vandel-booking-service">
                                                    <?php echo esc_html($booking->service_name); ?>
                                                </div>
                                                <div class="vandel-booking-date">
                                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?>
                                                </div>
                                                <div class="vandel-booking-price">
                                                    <?php echo Helpers::formatPrice($booking->total_price); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="vandel-card-footer">
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add&client_id=' . $client->id); ?>" class="button button-small">
                                        <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Booking', 'vandel-booking'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add or edit client form
     * 
     * @param object|null $client Client data
     */
    private function renderAddOrEditClient($client = null) {
        $is_edit = $client !== null;
        $form_title = $is_edit ? __('Edit Client', 'vandel-booking') : __('Add New Client', 'vandel-booking');
        
        // Display status messages
        $this->displayStatusMessage();
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h2><?php echo esc_html($form_title); ?></h2>
            </div>
            <div class="vandel-card-body">
                <form method="post" action="">
                    <?php wp_nonce_field('vandel_update_client', 'vandel_client_nonce'); ?>
                    
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="client_id" value="<?php echo esc_attr($client->id); ?>">
                    <?php endif; ?>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_name"><?php _e('Name', 'vandel-booking'); ?> <span class="required">*</span></label>
                            <input type="text" name="client_name" id="client_name" value="<?php echo $is_edit ? esc_attr($client->name) : ''; ?>" required class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label for="client_email"><?php _e('Email', 'vandel-booking'); ?> <span class="required">*</span></label>
                            <input type="email" name="client_email" id="client_email" value="<?php echo $is_edit ? esc_attr($client->email) : ''; ?>" required class="widefat">
                        </div>
                    </div>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                            <input type="tel" name="client_phone" id="client_phone" value="<?php echo $is_edit ? esc_attr($client->phone) : ''; ?>" class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label for="client_address"><?php _e('Address', 'vandel-booking'); ?></label>
                            <textarea name="client_address" id="client_address" rows="3" class="widefat"><?php echo $is_edit ? esc_textarea($client->address) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_notes"><?php _e('Notes', 'vandel-booking'); ?></label>
                            <textarea name="client_notes" id="client_notes" rows="5" class="widefat"><?php echo $is_edit ? esc_textarea($client->notes) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="vandel-form-actions">
                        <a href="<?php echo $is_edit ? esc_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id)) : esc_url(admin_url('admin.php?page=vandel-dashboard&tab=clients')); ?>" class="button button-secondary"><?php _e('Cancel', 'vandel-booking'); ?></a>
                        <button type="submit" name="vandel_update_client" class="button button-primary">
                            <?php echo $is_edit ? __('Update Client', 'vandel-booking') : __('Create Client', 'vandel-booking'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display status message if available
     */
    private function displayStatusMessage() {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message_type = 'success';
        $message = '';
        
        switch ($_GET['message']) {
            case 'client_created':
                $message = __('Client created successfully.', 'vandel-booking');
                break;
            case 'client_updated':
                $message = __('Client updated successfully.', 'vandel-booking');
                break;
            case 'client_deleted':
                $message = __('Client deleted successfully.', 'vandel-booking');
                break;
            case 'note_added':
                $message = __('Note added successfully.', 'vandel-booking');
                break;
            case 'missing_fields':
                $message = __('Please fill all required fields.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'create_failed':
            case 'update_failed':
            case 'delete_failed':
            case 'note_failed':
                $message = __('An error occurred. Please try again.', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'client_not_found':
                $message = __('Client not found.', 'vandel-booking');
                $message_type = 'error';
                break;
        }
        
        if (!empty($message)) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message_type),
                esc_html($message)
            );
        }
    }
}