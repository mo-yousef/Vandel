<?php
namespace VandelBooking\Admin;

use VandelBooking\Client\ClientModel;
use VandelBooking\Helpers;

/**
 * Enhanced Client Details Management Class
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
<div class="vandel-card vandel-modern-ui">
    <div class="vandel-card-header">
        <h2><?php echo esc_html($form_title); ?></h2>
    </div>
    <div class="vandel-card-body">
        <form method="post" action="" class="vandel-client-form">
            <?php wp_nonce_field('vandel_update_client', 'vandel_client_nonce'); ?>

            <?php if ($is_edit): ?>
            <input type="hidden" name="client_id" value="<?php echo esc_attr($client->id); ?>">
            <?php endif; ?>

            <div class="vandel-form-row vandel-form-row-grid">
                <div class="vandel-form-group">
                    <label for="client_name"><?php _e('Name', 'vandel-booking'); ?> <span
                            class="required">*</span></label>
                    <input type="text" name="client_name" id="client_name"
                        value="<?php echo $is_edit ? esc_attr($client->name) : ''; ?>" required class="regular-text">
                </div>
                <div class="vandel-form-group">
                    <label for="client_email"><?php _e('Email', 'vandel-booking'); ?> <span
                            class="required">*</span></label>
                    <input type="email" name="client_email" id="client_email"
                        value="<?php echo $is_edit ? esc_attr($client->email) : ''; ?>" required class="regular-text">
                </div>
            </div>

            <div class="vandel-form-row vandel-form-row-grid">
                <div class="vandel-form-group">
                    <label for="client_phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                    <input type="tel" name="client_phone" id="client_phone"
                        value="<?php echo $is_edit ? esc_attr($client->phone) : ''; ?>" class="regular-text">
                </div>
                <div class="vandel-form-group">
                    <label for="client_address"><?php _e('Address', 'vandel-booking'); ?></label>
                    <textarea name="client_address" id="client_address" rows="3"
                        class="regular-text"><?php echo $is_edit ? esc_textarea($client->address) : ''; ?></textarea>
                </div>
            </div>

            <div class="vandel-form-row">
                <div class="vandel-form-group">
                    <label for="client_notes"><?php _e('Notes', 'vandel-booking'); ?></label>
                    <textarea name="client_notes" id="client_notes" rows="5"
                        class="regular-text"><?php echo $is_edit ? esc_textarea($client->notes) : ''; ?></textarea>
                    <p class="description">
                        <?php _e('Add any notes or additional information about this client.', 'vandel-booking'); ?></p>
                </div>
            </div>

            <div class="vandel-form-actions">
                <a href="<?php echo $is_edit ? esc_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id)) : esc_url(admin_url('admin.php?page=vandel-dashboard&tab=clients')); ?>"
                    class="button button-secondary">
                    <?php _e('Cancel', 'vandel-booking'); ?>
                </a>
                <button type="submit" name="vandel_update_client" class="button button-primary">
                    <?php echo $is_edit ? __('Update Client', 'vandel-booking') : __('Create Client', 'vandel-booking'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Client Form Styling */
.vandel-client-form {
    max-width: 800px;
}

.vandel-form-row {
    margin-bottom: 20px;
}

.vandel-form-row-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 782px) {
    .vandel-form-row-grid {
        grid-template-columns: 1fr;
    }
}

.vandel-form-group {
    display: flex;
    flex-direction: column;
}

.vandel-form-group label {
    margin-bottom: 8px;
    font-weight: 500;
}

.vandel-form-group span.required {
    color: #d63638;
}

.vandel-form-group .description {
    margin-top: 8px;
    font-size: 12px;
    color: #606a73;
}

.vandel-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f1;
}
</style>
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
     * Render client details view with modern UI
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
        
        // Determine client status - new or returning
        $client_status = $client_stats->bookings_count <= 1 ? 'new' : 'returning';
        $client_status_label = $client_status === 'new' ? __('New Client', 'vandel-booking') : __('Returning Client', 'vandel-booking');
        $client_status_color = $client_status === 'new' ? '#2ea2cc' : '#46b450';
        
        // Calculate days since first booking
        $days_as_client = $client_stats->days_as_client;
        $member_since = '';
        if (!empty($client->created_at)) {
            $created_date = date_create($client->created_at);
            if ($created_date) {
                $member_since = date_i18n(get_option('date_format'), $created_date->getTimestamp());
            }
        }
        
        // Display status messages
        $this->displayStatusMessage();
        
        ?>
<div class="vandel-client-details vandel-modern-ui">
    <!-- Client Header with Quick Stats -->
    <div class="vandel-client-header">
        <div class="vandel-client-header-content">
            <div class="vandel-client-avatar-container">
                <div class="vandel-client-avatar">
                    <?php echo strtoupper(substr($client->name, 0, 1)); ?>
                </div>
                <span class="vandel-client-status"
                    style="background-color: <?php echo esc_attr($client_status_color); ?>">
                    <?php echo esc_html($client_status_label); ?>
                </span>
            </div>
            <div class="vandel-client-info">
                <h2 class="vandel-client-name"><?php echo esc_html($client->name); ?></h2>
                <div class="vandel-client-details">
                    <div class="vandel-client-contact">
                        <div class="vandel-client-detail">
                            <span class="dashicons dashicons-email-alt"></span>
                            <a
                                href="mailto:<?php echo esc_attr($client->email); ?>"><?php echo esc_html($client->email); ?></a>
                        </div>
                        <?php if (!empty($client->phone)): ?>
                        <div class="vandel-client-detail">
                            <span class="dashicons dashicons-phone"></span>
                            <a
                                href="tel:<?php echo esc_attr($client->phone); ?>"><?php echo esc_html($client->phone); ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($member_since)): ?>
                        <div class="vandel-client-detail">
                            <span class="dashicons dashicons-calendar"></span>
                            <?php echo sprintf(__('Member since: %s', 'vandel-booking'), $member_since); ?>
                            <?php if ($days_as_client > 0): ?>
                            <span
                                class="vandel-days-count">(<?php echo sprintf(_n('%d day', '%d days', $days_as_client, 'vandel-booking'), $days_as_client); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="vandel-client-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id . '&action=edit')); ?>"
                class="button vandel-button-secondary">
                <span class="dashicons dashicons-edit"></span> <?php _e('Edit Client', 'vandel-booking'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add&client_id=' . $client->id); ?>"
                class="button vandel-button-primary">
                <span class="dashicons dashicons-plus"></span> <?php _e('New Booking', 'vandel-booking'); ?>
            </a>
            <div class="vandel-dropdown">
                <button class="button vandel-dropdown-trigger">
                    <span class="dashicons dashicons-admin-tools"></span> <?php _e('More Actions', 'vandel-booking'); ?>
                </button>
                <div class="vandel-dropdown-content">
                    <a href="#" class="vandel-recalculate-stats" data-client-id="<?php echo esc_attr($client->id); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Recalculate Stats', 'vandel-booking'); ?>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id . '&action=delete_client'), 'delete_client_' . $client->id); ?>"
                        class="vandel-delete-link"
                        onclick="return confirm('<?php _e('Are you sure you want to delete this client? This action cannot be undone.', 'vandel-booking'); ?>')">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Delete Client', 'vandel-booking'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Summary Stats -->
    <div class="vandel-client-stats-cards">
        <div class="vandel-stat-card">
            <div class="vandel-stat-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="vandel-stat-content">
                <div class="vandel-stat-value"><?php echo Helpers::formatPrice($client_stats->total_spent); ?></div>
                <div class="vandel-stat-label"><?php _e('Total Spent', 'vandel-booking'); ?></div>
            </div>
        </div>

        <div class="vandel-stat-card">
            <div class="vandel-stat-icon">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="vandel-stat-content">
                <div class="vandel-stat-value"><?php echo number_format_i18n($client_stats->bookings_count); ?></div>
                <div class="vandel-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></div>
            </div>
        </div>

        <div class="vandel-stat-card">
            <div class="vandel-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="vandel-stat-content">
                <div class="vandel-stat-value"><?php echo Helpers::formatPrice($client_stats->average_booking); ?></div>
                <div class="vandel-stat-label"><?php _e('Average Order', 'vandel-booking'); ?></div>
            </div>
        </div>

        <div class="vandel-stat-card">
            <div class="vandel-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="vandel-stat-content">
                <div class="vandel-stat-value">
                    <?php 
                                                    if (!empty($client_stats->last_booking)) {
                                                        echo date_i18n(get_option('date_format'), strtotime($client_stats->last_booking));
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                </div>
                <div class="vandel-stat-label"><?php _e('Last Booking', 'vandel-booking'); ?></div>
            </div>
        </div>
    </div>

    <div class="vandel-client-details-grid">
        <!-- Booking History and Timeline -->
        <div class="vandel-grid-col vandel-col-wide">
            <div class="vandel-card">
                <div class="vandel-card-header vandel-flex-header">
                    <h3><?php _e('Booking History', 'vandel-booking'); ?></h3>
                    <?php if (!empty($client_bookings)): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&client_id=' . $client->id)); ?>"
                        class="vandel-view-all">
                        <?php _e('View All', 'vandel-booking'); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($client_bookings)): ?>
                    <div class="vandel-empty-state">
                        <div class="vandel-empty-state-icon">
                            <span class="dashicons dashicons-calendar-alt"></span>
                        </div>
                        <p><?php _e('No bookings found for this client.', 'vandel-booking'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add&client_id=' . $client->id); ?>"
                            class="button vandel-button-primary">
                            <?php _e('Create First Booking', 'vandel-booking'); ?>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="vandel-client-bookings-list">
                        <div class="vandel-bookings-timeline">
                            <?php foreach ($client_bookings as $index => $booking): 
                                                                    $service = get_post($booking->service);
                                                                    $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                                                                
                                                                    $status_classes = [
                                                                        'pending' => 'vandel-status-pending',
                                                                        'confirmed' => 'vandel-status-confirmed',
                                                                        'completed' => 'vandel-status-completed',
                                                                        'canceled' => 'vandel-status-canceled'
                                                                    ];
                                                                    
                                                                    $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
                                                                ?>
                            <div class="vandel-timeline-item">
                                <div
                                    class="vandel-timeline-connection <?php echo $index === 0 ? 'vandel-timeline-first' : ''; ?>">
                                </div>
                                <div class="vandel-timeline-dot <?php echo esc_attr($status_class); ?>"></div>
                                <div class="vandel-timeline-content">
                                    <div class="vandel-timeline-header">
                                        <span class="vandel-timeline-date">
                                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?>
                                        </span>
                                        <span class="vandel-timeline-id">
                                            <?php echo sprintf(__('Booking #%s', 'vandel-booking'), $booking->id); ?>
                                        </span>
                                        <span class="vandel-status-badge <?php echo esc_attr($status_class); ?>">
                                            <?php echo ucfirst($booking->status); ?>
                                        </span>
                                    </div>
                                    <div class="vandel-timeline-service">
                                        <?php echo esc_html($service_name); ?>
                                    </div>
                                    <div class="vandel-timeline-footer">
                                        <span class="vandel-timeline-price">
                                            <?php echo Helpers::formatPrice($booking->total_price); ?>
                                        </span>
                                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>"
                                            class="vandel-timeline-link">
                                            <?php _e('View Details', 'vandel-booking'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($client->address)): ?>
            <!-- Client Address -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Address Information', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <div class="vandel-address">
                        <?php echo nl2br(esc_html($client->address)); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="vandel-grid-col">
            <!-- Client Status Section -->
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Client Information', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php if ($client_stats->bookings_count > 0): ?>
                    <!-- Booking Status Breakdown -->
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
                                                                        'completed' => '#46b450',
                                                                        'canceled' => '#d9534f',
                                                                    ];
                                                                    
                                                                    $color = isset($status_colors[$status]) ? $status_colors[$status] : '#777';
                                                                    ?>
                            <div class="vandel-status-bar-container">
                                <div class="vandel-status-bar-label">
                                    <span class="vandel-status-name"><?php echo ucfirst($status); ?></span>
                                    <span class="vandel-status-count"><?php echo number_format_i18n($count); ?></span>
                                </div>
                                <div class="vandel-status-bar">
                                    <div class="vandel-status-bar-fill"
                                        style="width: <?php echo esc_attr($percentage); ?>%; background-color: <?php echo esc_attr($color); ?>">
                                    </div>
                                </div>
                                <div class="vandel-status-bar-percent"><?php echo $percentage; ?>%</div>
                            </div>
                            <?php
                                                                }
                                                                ?>
                        </div>
                    </div>

                    <?php if (!empty($client_stats->most_booked_service)): ?>
                    <div class="vandel-most-booked">
                        <h4><?php _e('Most Booked Service', 'vandel-booking'); ?></h4>
                        <div class="vandel-most-booked-service">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span
                                class="vandel-most-booked-name"><?php echo esc_html($client_stats->most_booked_service); ?></span>
                            <span
                                class="vandel-most-booked-count">(<?php echo sprintf(_n('%d booking', '%d bookings', $client_stats->most_booked_service_count, 'vandel-booking'), $client_stats->most_booked_service_count); ?>)</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="vandel-notice vandel-notice-info">
                        <p><?php _e('This client has not made any bookings yet.', 'vandel-booking'); ?></p>
                    </div>
                    <?php endif; ?>
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
                            <textarea name="client_note" rows="3" class="widefat"
                                placeholder="<?php _e('Add a note about this client...', 'vandel-booking'); ?>"></textarea>
                        </div>
                        <div class="vandel-form-row">
                            <button type="submit" name="vandel_add_client_note" class="button vandel-button-primary">
                                <span class="dashicons dashicons-plus"></span>
                                <?php _e('Add Note', 'vandel-booking'); ?>
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
    </div>
</div>

<style>
/* Modern Client Details Styling */
.vandel-modern-ui {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

/* Client Header */
.vandel-client-header {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.vandel-client-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.vandel-client-avatar-container {
    position: relative;
}

.vandel-client-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background-color: #5c3896;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
}

.vandel-client-status {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #2ea2cc;
    color: white;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    font-weight: bold;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.vandel-client-name {
    margin: 0 0 8px 0;
    font-size: 24px;
    color: #23282d;
}

.vandel-client-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.vandel-client-contact {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.vandel-client-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #606a73;
    font-size: 14px;
}

.vandel-client-detail a {
    text-decoration: none;
    color: #2271b1;
}

.vandel-client-detail a:hover {
    color: #135e96;
    text-decoration: underline;
}

.vandel-client-detail .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #606a73;
}

.vandel-days-count {
    font-style: italic;
    color: #606a73;
    margin-left: 5px;
    font-size: 12px;
}

.vandel-client-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 782px) {
    .vandel-client-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .vandel-client-actions {
        margin-top: 20px;
        align-self: flex-start;
        flex-wrap: wrap;
    }
}

/* Button Styling */
.vandel-button-primary {
    background: #5c3896;
    border-color: #5c3896;
    color: white;
}

.vandel-button-primary:hover {
    background: #4a2d78;
    border-color: #4a2d78;
    color: white;
}

.vandel-button-secondary {
    background: #f0f0f1;
    border-color: #e2e4e7;
    color: #1d2327;
}

.vandel-button-secondary:hover {
    background: #e2e4e7;
    border-color: #cfd4d9;
    color: #1d2327;
}

/* Dropdown Styling */
.vandel-dropdown {
    position: relative;
    display: inline-block;
}

.vandel-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    min-width: 180px;
    background: white;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
    border-radius: 4px;
    z-index: 100;
    margin-top: 5px;
}

.vandel-dropdown:hover .vandel-dropdown-content {
    display: block;
}

.vandel-dropdown-content a {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: #1d2327;
    border-bottom: 1px solid #f0f0f1;
}

.vandel-dropdown-content a:last-child {
    border-bottom: none;
}

.vandel-dropdown-content a:hover {
    background: #f6f7f7;
}

.vandel-dropdown-content a.vandel-delete-link {
    color: #d63638;
}

.vandel-dropdown-content a.vandel-delete-link:hover {
    background: #fcf0f1;
}

.vandel-dropdown-content a .dashicons {
    margin-right: 8px;
}

/* Stats Cards */
.vandel-client-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.vandel-stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.2s ease;
}

.vandel-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

.vandel-stat-icon {
    background: rgba(92, 56, 150, 0.1);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vandel-stat-icon .dashicons {
    color: #5c3896;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.vandel-stat-content {
    flex: 1;
}

.vandel-stat-value {
    font-size: 20px;
    font-weight: 600;
    color: #23282d;
    margin-bottom: 5px;
}

.vandel-stat-label {
    font-size: 13px;
    color: #606a73;
}

/* Grid Layout */
.vandel-client-details-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

@media (max-width: 1024px) {
    .vandel-client-details-grid {
        grid-template-columns: 1fr;
    }
}

/* Cards */
.vandel-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
    overflow: hidden;
}

.vandel-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f1;
    background: #fafafa;
}

.vandel-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
}

.vandel-flex-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vandel-card-body {
    padding: 20px;
}

.vandel-view-all {
    font-size: 13px;
    text-decoration: none;
    color: #2271b1;
}

.vandel-view-all:hover {
    color: #135e96;
    text-decoration: underline;
}

/* Timeline Styling */
.vandel-bookings-timeline {
    position: relative;
    padding-left: 30px;
}

.vandel-timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.vandel-timeline-item:last-child {
    margin-bottom: 0;
}

.vandel-timeline-connection {
    position: absolute;
    left: -22px;
    top: 24px;
    bottom: -35px;
    width: 2px;
    background: #e2e4e7;
}

.vandel-timeline-item:last-child .vandel-timeline-connection {
    display: none;
}

.vandel-timeline-first {
    top: 0;
}

.vandel-timeline-dot {
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #e2e4e7;
    border: 2px solid white;
    box-shadow: 0 0 0 1px #e2e4e7;
}

.vandel-timeline-dot.vandel-status-pending {
    background: #f0ad4e;
}

.vandel-timeline-dot.vandel-status-confirmed {
    background: #5bc0de;
}

.vandel-timeline-dot.vandel-status-completed {
    background: #46b450;
}

.vandel-timeline-dot.vandel-status-canceled {
    background: #d9534f;
}

.vandel-timeline-content {
    background: #f6f7f7;
    border-radius: 6px;
    padding: 15px;
    border-left: 3px solid #e2e4e7;
}

.vandel-timeline-content.vandel-status-pending {
    border-left-color: #f0ad4e;
}

.vandel-timeline-content.vandel-status-confirmed {
    border-left-color: #5bc0de;
}

.vandel-timeline-content.vandel-status-completed {
    border-left-color: #46b450;
}

.vandel-timeline-content.vandel-status-canceled {
    border-left-color: #d9534f;
}

.vandel-timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 10px;
    gap: 10px;
}

.vandel-timeline-date {
    font-weight: 500;
    color: #23282d;
}

.vandel-timeline-id {
    color: #606a73;
    font-size: 13px;
}

.vandel-timeline-service {
    font-weight: 500;
    margin-bottom: 10px;
}

.vandel-timeline-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vandel-timeline-price {
    font-weight: 500;
    color: #5c3896;
}

.vandel-timeline-link {
    font-size: 13px;
    text-decoration: none;
    color: #2271b1;
}

.vandel-timeline-link:hover {
    text-decoration: underline;
}

.vandel-status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 500;
    color: white;
}

.vandel-status-badge.vandel-status-pending {
    background: #f0ad4e;
}

.vandel-status-badge.vandel-status-confirmed {
    background: #5bc0de;
}

.vandel-status-badge.vandel-status-completed {
    background: #46b450;
}

.vandel-status-badge.vandel-status-canceled {
    background: #d9534f;
}

/* Empty States */
.vandel-empty-state {
    text-align: center;
    padding: 30px 20px;
}

.vandel-empty-state-icon {
    margin-bottom: 15px;
}

.vandel-empty-state-icon .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #e2e4e7;
}

.vandel-empty-state p {
    color: #606a73;
    margin-bottom: 20px;
}

.vandel-empty-state-small {
    color: #606a73;
    font-style: italic;
    text-align: center;
    margin: 10px 0;
}

/* Status Breakdown */
.vandel-status-breakdown {
    margin-bottom: 24px;
}

.vandel-status-breakdown h4 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 14px;
}

.vandel-status-bars {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vandel-status-bar-container {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.vandel-status-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.vandel-status-name {
    color: #23282d;
}

.vandel-status-count {
    color: #606a73;
}

.vandel-status-bar {
    height: 8px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
}

.vandel-status-bar-fill {
    height: 100%;
    border-radius: 4px;
}

.vandel-status-bar-percent {
    font-size: 11px;
    color: #606a73;
    text-align: right;
}

/* Most Booked Service */
.vandel-most-booked {
    margin-top: 24px;
}

.vandel-most-booked h4 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 14px;
}

.vandel-most-booked-service {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f6f7f7;
    border-radius: 4px;
    padding: 12px;
}

.vandel-most-booked-service .dashicons {
    color: #f0ad4e;
}

.vandel-most-booked-name {
    font-weight: 500;
    flex: 1;
}

.vandel-most-booked-count {
    font-size: 12px;
    color: #606a73;
}

/* Notes */
.vandel-client-notes {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f1;
    white-space: pre-line;
    font-size: 14px;
    color: #50575e;
}

/* Address */
.vandel-address {
    white-space: pre-line;
    font-size: 14px;
    color: #50575e;
}

/* Form Styles */
.vandel-form-row {
    margin-bottom: 15px;
}

.vandel-form-row:last-child {
    margin-bottom: 0;
}

/* Notice Styles */
.vandel-notice {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.vandel-notice p {
    margin: 0;
}

.vandel-notice-info {
    background: #e5f5fa;
    border-left: 4px solid #00a0d2;
    color: #00a0d2;
}

.vandel-notice-warning {
    background: #fff8e5;
    border-left: 4px solid #ffb900;
    color: #b17300;
}

.vandel-notice-error {
    background: #fef1f1;
    border-left: 4px solid #d63638;
    color: #b32d2e;
}

.vandel-notice-success {
    background: #ecf7ed;
    border-left: 4px solid #46b450;
    color: #2a8636;
}

/* Responsive adjustments */
@media (max-width: 782px) {
    .vandel-client-stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .vandel-client-details-grid {
        grid-template-columns: 1fr;
    }

    .vandel-client-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .vandel-client-actions {
        margin-top: 15px;
        align-self: flex-start;
    }
}

@media (max-width: 480px) {
    .vandel-client-stats-cards {
        grid-template-columns: 1fr;
    }

    .vandel-client-actions {
        flex-wrap: wrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle recalculate stats button
    $('.vandel-recalculate-stats').on('click', function(e) {
        e.preventDefault();

        const $button = $(this);
        const clientId = $button.data('client-id');
        const originalText = $button.text();

        // Show loading state
        $button.html('<span class="dashicons dashicons-update vandel-spin"></span> ' +
            '<?php _e("Recalculating...", "vandel-booking"); ?>');

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vandel_recalculate_client_stats',
                client_id: clientId,
                nonce: '<?php echo wp_create_nonce("vandel_client_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const $notice = $(
                        '<div class="notice notice-success is-dismissible"><p>' +
                        response.data.message + '</p></div>');
                    $('.vandel-client-details').before($notice);

                    // Reload the page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    const $notice = $(
                        '<div class="notice notice-error is-dismissible"><p>' +
                        (response.data.message ||
                            '<?php _e("Failed to recalculate statistics.", "vandel-booking"); ?>'
                        ) +
                        '</p></div>');
                    $('.vandel-client-details').before($notice);

                    // Reset button
                    $button.text(originalText);
                }
            },
            error: function() {
                // Show error message
                const $notice = $('<div class="notice notice-error is-dismissible"><p>' +
                    '<?php _e("Failed to recalculate statistics.", "vandel-booking"); ?>' +
                    '</p></div>');
                $('.vandel-client-details').before($notice);

                // Reset button
                $button.text(originalText);
            }
        });
    });

    // Simple animation for stats cards
    $('.vandel-stat-card').each(function(index) {
        const $card = $(this);
        setTimeout(function() {
            $card.addClass('vandel-stat-card-visible');
        }, 100 * index);
    });
});

// Add this to your stylesheet or keep it inline
@keyframes vandel - spin {
        0 % {
            transform: rotate(0 deg);
        }
        100 % {
            transform: rotate(360 deg);
        }
    }

    .vandel - spin {
        animation: vandel - spin 1.5 s linear infinite;
    }

    .vandel - stat - card {
        opacity: 0;
        transform: translateY(10 px);
        transition: all 0.3 s ease - out;
    }

    .vandel - stat - card - visible {
        opacity: 1;
        transform: translateY(0);
    }
</script>

<?php }  } ?>