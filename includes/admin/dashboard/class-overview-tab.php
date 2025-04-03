<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Overview Tab
 * Handles the main dashboard overview tab
 */
class Overview_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // No specific hooks for overview tab
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // No actions to process for overview tab
    }
    
    /**
     * Render tab content
     */
    public function render() {
        global $wpdb;
        
        // Get statistics
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $clients_table = $wpdb->prefix . 'vandel_clients';
        
        // Check if tables exist
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        $clients_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table;
        
        // Get counts
        $total_bookings = $bookings_table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table") : 0;
        $total_clients = $clients_table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $clients_table") : 0;
        
        // Get booking status counts
        $booking_stats = [
            'total' => intval($total_bookings),
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'canceled' => 0
        ];
        
        if ($bookings_table_exists) {
            $status_counts = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $bookings_table GROUP BY status");
            if ($status_counts) {
                foreach ($status_counts as $status) {
                    if (isset($booking_stats[$status->status])) {
                        $booking_stats[$status->status] = intval($status->count);
                    }
                }
            }
        }
        
        // Get total revenue
        $total_revenue = $bookings_table_exists ? $wpdb->get_var("SELECT SUM(total_price) FROM $bookings_table WHERE status != 'canceled'") : 0;
        $total_revenue = floatval($total_revenue);
        
        // Get upcoming bookings
        $upcoming_bookings = [];
        if ($bookings_table_exists) {
            $upcoming_bookings = $wpdb->get_results(
                "SELECT * FROM $bookings_table 
                 WHERE booking_date > NOW() 
                 AND status IN ('pending', 'confirmed') 
                 ORDER BY booking_date ASC 
                 LIMIT 5"
            );
        }
        
        // Get recent bookings
        $recent_bookings = [];
        if ($bookings_table_exists) {
            $recent_bookings = $wpdb->get_results(
                "SELECT * FROM $bookings_table 
                 ORDER BY created_at DESC 
                 LIMIT 5"
            );
        }
        
        // Get services
        $services_count = 0;
        $services = [];
        if (post_type_exists('vandel_service')) {
            $services_query = new \WP_Query([
                'post_type' => 'vandel_service',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            $services_count = $services_query->found_posts;
            
            // Get top services
            $services = get_posts([
                'post_type' => 'vandel_service',
                'posts_per_page' => 5,
                'orderby' => 'meta_value_num',
                'meta_key' => '_vandel_service_booking_count', // This meta key might need to be created
                'order' => 'DESC'
            ]);
        }
        
        // Render the overview tab content
        $this->render_content($booking_stats, $total_clients, $services_count, $total_revenue, $upcoming_bookings, $recent_bookings, $services);
    }
    
    /**
     * Render the overview content
     */
    private function render_content($booking_stats, $total_clients, $services_count, $total_revenue, $upcoming_bookings, $recent_bookings, $services) {
        ?>
        <div id="overview" class="vandel-tab-content">
            
            <div class="vandel-dashboard-welcome">
                <div class="vandel-welcome-content">
                    <h2><?php _e('Welcome to Your Booking Dashboard', 'vandel-booking'); ?></h2>
                    <p><?php _e('Manage your bookings, services, and clients all in one place. Here\'s a snapshot of your business.', 'vandel-booking'); ?></p>
                </div>
                <div class="vandel-quick-stats">
                    <div class="vandel-stat-cards">
                        <div class="vandel-stat-card vandel-stat-bookings">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($booking_stats['total']); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Bookings', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-clients">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-groups"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($total_clients); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Clients', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-services">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-admin-generic"></span></span>
                                <span class="vandel-stat-value"><?php echo number_format_i18n($services_count); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Active Services', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-revenue">
                            <div class="vandel-stat-header">
                                <span class="vandel-stat-icon"><span class="dashicons dashicons-chart-line"></span></span>
                                <span class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($total_revenue); ?></span>
                            </div>
                            <div class="vandel-stat-footer">
                                <span class="vandel-stat-label"><?php _e('Total Revenue', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vandel-dashboard-grid">
                <div class="vandel-dashboard-main">
                    <?php if ($booking_stats['total'] === 0): ?>
                        <!-- First Time Setup Section if no bookings -->
                        <?php $this->render_getting_started($services_count); ?>
                    <?php else: ?>
                        <!-- Booking Status Summary -->
                        <?php $this->render_booking_status_summary($booking_stats); ?>
                    <?php endif; ?>
                    
                    <!-- Upcoming Bookings -->
                    <?php $this->render_upcoming_bookings($upcoming_bookings); ?>
                </div>
                
                <div class="vandel-dashboard-sidebar">
                    <!-- Quick Actions -->
                    <?php $this->render_quick_actions(); ?>
                    
                    <!-- Recent Bookings -->
                    <?php $this->render_recent_bookings($recent_bookings); ?>
                    
                    <!-- Popular Services -->
                    <?php $this->render_services_list($services); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render getting started section
     */
    private function render_getting_started($services_count) {
        ?>
        <div class="vandel-card vandel-setup-card">
            <div class="vandel-card-header">
                <h3><?php _e('Getting Started with Vandel Booking', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <div class="vandel-setup-steps">
                    <div class="vandel-setup-step <?php echo $services_count > 0 ? 'completed' : ''; ?>">
                        <div class="vandel-setup-step-number">1</div>
                        <div class="vandel-setup-step-content">
                            <h4><?php _e('Create Services', 'vandel-booking'); ?></h4>
                            <p><?php _e('Start by setting up the services you offer to your customers.', 'vandel-booking'); ?></p>
                            <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="button button-primary"><?php _e('Add a Service', 'vandel-booking'); ?></a>
                        </div>
                    </div>
                    
                    <div class="vandel-setup-step">
                        <div class="vandel-setup-step-number">2</div>
                        <div class="vandel-setup-step-content">
                            <h4><?php _e('Add the Booking Form to Your Website', 'vandel-booking'); ?></h4>
                            <p><?php _e('Use the shortcode to add the booking form to any page on your website.', 'vandel-booking'); ?></p>
                            <div class="vandel-shortcode-display">
                                <code>[vandel_booking_form]</code>
                                <button class="vandel-copy-shortcode" data-shortcode="[vandel_booking_form]">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-setup-step">
                        <div class="vandel-setup-step-number">3</div>
                        <div class="vandel-setup-step-content">
                            <h4><?php _e('Customize Your Settings', 'vandel-booking'); ?></h4>
                            <p><?php _e('Configure your business hours, notification emails, and other settings.', 'vandel-booking'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=settings'); ?>" class="button button-secondary"><?php _e('Go to Settings', 'vandel-booking'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render booking status summary
     */
    private function render_booking_status_summary($booking_stats) {
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header vandel-flex-header">
                <h3><?php _e('Booking Status Summary', 'vandel-booking'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="vandel-view-all"><?php _e('View All Bookings', 'vandel-booking'); ?></a>
            </div>
            <div class="vandel-card-body">
                <div class="vandel-status-summary">
                    <div class="vandel-status-item vandel-status-pending">
                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['pending']); ?></div>
                        <div class="vandel-status-label"><?php _e('Pending', 'vandel-booking'); ?></div>
                        <div class="vandel-status-icon"><span class="dashicons dashicons-clock"></span></div>
                    </div>
                    
                    <div class="vandel-status-item vandel-status-confirmed">
                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['confirmed']); ?></div>
                        <div class="vandel-status-label"><?php _e('Confirmed', 'vandel-booking'); ?></div>
                        <div class="vandel-status-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                    </div>
                    
                    <div class="vandel-status-item vandel-status-completed">
                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['completed']); ?></div>
                        <div class="vandel-status-label"><?php _e('Completed', 'vandel-booking'); ?></div>
                        <div class="vandel-status-icon"><span class="dashicons dashicons-saved"></span></div>
                    </div>
                    
                    <div class="vandel-status-item vandel-status-canceled">
                        <div class="vandel-status-count"><?php echo number_format_i18n($booking_stats['canceled']); ?></div>
                        <div class="vandel-status-label"><?php _e('Canceled', 'vandel-booking'); ?></div>
                        <div class="vandel-status-icon"><span class="dashicons dashicons-dismiss"></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render upcoming bookings
     */
    private function render_upcoming_bookings($upcoming_bookings) {
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header vandel-flex-header">
                <h3><?php _e('Upcoming Bookings', 'vandel-booking'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" class="vandel-view-all"><?php _e('View Calendar', 'vandel-booking'); ?></a>
            </div>
            <div class="vandel-card-body">
                <?php if (empty($upcoming_bookings)): ?>
                    <div class="vandel-empty-state">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <p><?php _e('No upcoming bookings.', 'vandel-booking'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vandel-bookings-table-wrapper">
                        <table class="vandel-bookings-table">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'vandel-booking'); ?></th>
                                    <th><?php _e('Client', 'vandel-booking'); ?></th>
                                    <th><?php _e('Service', 'vandel-booking'); ?></th>
                                    <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                                    <th><?php _e('Status', 'vandel-booking'); ?></th>
                                    <th><?php _e('Actions', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_bookings as $booking): 
                                    $service = get_post($booking->service);
                                    $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                                    
                                    $status_classes = [
                                        'pending' => 'vandel-status-badge-warning',
                                        'confirmed' => 'vandel-status-badge-success',
                                        'completed' => 'vandel-status-badge-info',
                                        'canceled' => 'vandel-status-badge-danger'
                                    ];
                                    
                                    $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
                                ?>
                                    <tr>
                                        <td>#<?php echo $booking->id; ?></td>
                                        <td><?php echo esc_html($booking->customer_name); ?></td>
                                        <td><?php echo esc_html($service_name); ?></td>
                                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?></td>
                                        <td><span class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span></td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" class="button button-small">
                                                <?php _e('View', 'vandel-booking'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render quick actions section
     */
    private function render_quick_actions() {
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h3><?php _e('Quick Actions', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <div class="vandel-quick-action-buttons">
                    <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="vandel-quick-action-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span class="vandel-quick-action-label"><?php _e('Add Service', 'vandel-booking'); ?></span>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="vandel-quick-action-btn">
                        <span class="dashicons dashicons-edit"></span>
                        <span class="vandel-quick-action-label"><?php _e('Create Booking', 'vandel-booking'); ?></span>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="vandel-quick-action-btn">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span class="vandel-quick-action-label"><?php _e('Add Client', 'vandel-booking'); ?></span>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=settings'); ?>" class="vandel-quick-action-btn">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span class="vandel-quick-action-label"><?php _e('Settings', 'vandel-booking'); ?></span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent bookings
     */
    private function render_recent_bookings($recent_bookings) {
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h3><?php _e('Recent Bookings', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <?php if (empty($recent_bookings)): ?>
                    <div class="vandel-empty-state vandel-empty-state-small">
                        <p><?php _e('No bookings yet.', 'vandel-booking'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="vandel-recent-bookings">
                        <?php foreach ($recent_bookings as $booking): 
                            $service = get_post($booking->service);
                            $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                        ?>
                            <div class="vandel-recent-booking-item">
                                <div class="vandel-booking-info">
                                    <div class="vandel-booking-client">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <?php echo esc_html($booking->customer_name); ?>
                                    </div>
                                    <div class="vandel-booking-service">
                                        <?php echo esc_html($service_name); ?>
                                    </div>
                                </div>
                                <div class="vandel-booking-meta">
                                    <div class="vandel-booking-time">
                                        <?php echo \VandelBooking\Helpers::formatDate($booking->booking_date); ?>
                                    </div>
                                    <div class="vandel-booking-status">
                                        <span class="vandel-status-dot vandel-status-<?php echo $booking->status; ?>"></span>
                                        <?php echo ucfirst($booking->status); ?>
                                    </div>
                                </div>
                                <div class="vandel-booking-action">
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" class="vandel-view-booking">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($recent_bookings)): ?>
                <div class="vandel-card-footer">
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="vandel-link-btn">
                        <?php _e('View All Bookings', 'vandel-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render services list
     */
    private function render_services_list($services) {
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h3><?php _e('Your Services', 'vandel-booking'); ?></h3>
            </div>
            <div class="vandel-card-body">
                <?php if (empty($services)): ?>
                    <div class="vandel-empty-state vandel-empty-state-small">
                        <p><?php _e('No services created yet.', 'vandel-booking'); ?></p>
                        <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" class="button button-primary">
                            <?php _e('Add First Service', 'vandel-booking'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="vandel-services-list">
                        <?php foreach ($services as $service): 
                            $price = get_post_meta($service->ID, '_vandel_service_base_price', true);
                            $formatted_price = \VandelBooking\Helpers::formatPrice($price);
                            $is_popular = get_post_meta($service->ID, '_vandel_service_is_popular', true) === 'yes';
                        ?>
                            <div class="vandel-service-item">
                                <div class="vandel-service-icon">
                                    <?php if (has_post_thumbnail($service->ID)): ?>
                                        <?php echo get_the_post_thumbnail($service->ID, [40, 40]); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="vandel-service-details">
                                    <div class="vandel-service-name">
                                        <?php echo esc_html($service->post_title); ?>
                                        <?php if ($is_popular): ?>
                                            <span class="vandel-popular-tag"><?php _e('Popular', 'vandel-booking'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vandel-service-price">
                                        <?php echo $formatted_price; ?>
                                    </div>
                                </div>
                                <div class="vandel-service-actions">
                                    <a href="<?php echo get_edit_post_link($service->ID); ?>" class="vandel-edit-service">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($services)): ?>
                <div class="vandel-card-footer">
                    <a href="<?php echo admin_url('edit.php?post_type=vandel_service'); ?>" class="vandel-link-btn">
                        <?php _e('Manage Services', 'vandel-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}