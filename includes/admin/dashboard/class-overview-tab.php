<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Enhanced Overview Tab for Cleaning Business
 * Provides a comprehensive dashboard with key metrics and cleaning-specific analytics
 */
class Overview_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // No specific hooks needed for the overview tab
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
        
        // Get counts and stats
        $booking_stats = $this->get_booking_statistics($bookings_table, $bookings_table_exists);
        $revenue_stats = $this->get_revenue_statistics($bookings_table, $bookings_table_exists);
        $client_stats  = $this->get_client_statistics($clients_table, $clients_table_exists);
        $service_stats = $this->get_service_statistics();
        
        // Get upcoming bookings
        $upcoming_bookings = $this->get_upcoming_bookings($bookings_table, $bookings_table_exists);
        
        // Get recent bookings for activity feed
        $recent_bookings = $this->get_recent_bookings($bookings_table, $bookings_table_exists);
        
        // Get location data for service areas heatmap
        $location_data = $this->get_location_statistics($bookings_table, $bookings_table_exists);
        
        // Get repeat client statistics
        $repeat_client_stats = $this->get_repeat_client_statistics(
            $bookings_table, 
            $clients_table, 
            $bookings_table_exists && $clients_table_exists
        );
        
        // Render the dashboard
        $this->render_dashboard_view(
            $booking_stats,
            $revenue_stats, 
            $client_stats, 
            $service_stats,
            $upcoming_bookings,
            $recent_bookings,
            $location_data,
            $repeat_client_stats
        );
    }
    
    /**
     * Get booking statistics
     */
    private function get_booking_statistics($bookings_table, $table_exists) {
        global $wpdb;
        
        $stats = [
            'total'         => 0,
            'pending'       => 0,
            'confirmed'     => 0,
            'completed'     => 0,
            'canceled'      => 0,
            'today'         => 0,
            'this_week'     => 0,
            'this_month'    => 0,
            'monthly_trend' => []
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Get total booking count
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        
        // Get status counts
        $status_counts = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM $bookings_table 
            GROUP BY status
        ");
        if ($status_counts) {
            foreach ($status_counts as $status) {
                // Only set if our array key exists
                if (array_key_exists($status->status, $stats)) {
                    $stats[$status->status] = (int) $status->count;
                }
            }
        }
        
        // Today's bookings
        $stats['today'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $bookings_table
            WHERE DATE(booking_date) = %s
        ", current_time('Y-m-d')));
        
        // This week's bookings
        $stats['this_week'] = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM $bookings_table
            WHERE YEARWEEK(booking_date, 1) = YEARWEEK(NOW(), 1)
        ");
        
        // This month's bookings
        $stats['this_month'] = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $bookings_table 
            WHERE YEAR(booking_date) = YEAR(CURRENT_DATE()) 
              AND MONTH(booking_date) = MONTH(CURRENT_DATE())
        ");
        
        // Get monthly trend (last 6 months)
        $monthly_results = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(booking_date, '%Y-%m') as month,
                COUNT(*) as count
            FROM $bookings_table
            WHERE booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
            ORDER BY month ASC
        ");
        
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                // Convert the year-month to a localizable month name
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (int) $row->count;
            }
        }
        
        // Calculate month-over-month growth if possible
        if (count($stats['monthly_trend']) > 1) {
            $values   = array_values($stats['monthly_trend']);
            $current  = end($values);
            $previous = prev($values);
            
            if ($previous > 0) {
                $stats['growth_rate'] = round((($current - $previous) / $previous) * 100, 1);
            } else {
                $stats['growth_rate'] = 0;
            }
        } else {
            $stats['growth_rate'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Get revenue statistics
     */
    private function get_revenue_statistics($bookings_table, $table_exists) {
        global $wpdb;
        
        $stats = [
            'total'            => 0,
            'this_month'       => 0,
            'last_month'       => 0,
            'monthly_trend'    => [],
            'avg_booking_value'=> 0
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Total revenue (excluding canceled)
        $stats['total'] = (float) $wpdb->get_var("
            SELECT COALESCE(SUM(total_price), 0) 
            FROM $bookings_table 
            WHERE status != 'canceled'
        ");
        
        // This month's revenue
        $stats['this_month'] = (float) $wpdb->get_var("
            SELECT COALESCE(SUM(total_price), 0) 
            FROM $bookings_table 
            WHERE status != 'canceled'
              AND YEAR(booking_date) = YEAR(CURRENT_DATE())
              AND MONTH(booking_date) = MONTH(CURRENT_DATE())
        ");
        
        // Last month's revenue
        $stats['last_month'] = (float) $wpdb->get_var("
            SELECT COALESCE(SUM(total_price), 0) 
            FROM $bookings_table 
            WHERE status != 'canceled'
              AND YEAR(booking_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
              AND MONTH(booking_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");
        
        // Monthly revenue trend (last 6 months)
        $monthly_results = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(booking_date, '%Y-%m') as month,
                COALESCE(SUM(total_price), 0) as revenue
            FROM $bookings_table
            WHERE booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
              AND status != 'canceled'
            GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
            ORDER BY month ASC
        ");
        
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (float) $row->revenue;
            }
        }
        
        // Average booking value for completed or confirmed
        $total_completed_bookings = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $bookings_table 
            WHERE status IN ('completed', 'confirmed')
        ");
        
        if ($total_completed_bookings > 0) {
            $stats['avg_booking_value'] = $stats['total'] / $total_completed_bookings;
        }
        
        // Month-over-month growth
        if ($stats['last_month'] > 0) {
            $stats['growth_rate'] = round((($stats['this_month'] - $stats['last_month']) / $stats['last_month']) * 100, 1);
        } else {
            $stats['growth_rate'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Get client statistics
     */
    private function get_client_statistics($clients_table, $table_exists) {
        global $wpdb;
        
        $stats = [
            'total'          => 0,
            'new_this_month' => 0,
            'monthly_trend'  => []
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Total clients
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $clients_table");
        
        // New clients this month
        $stats['new_this_month'] = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $clients_table 
            WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
              AND MONTH(created_at) = MONTH(CURRENT_DATE())
        ");
        
        // Monthly new client trend (last 6 months)
        $monthly_results = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM $clients_table
            WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (int) $row->count;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get service statistics
     */
    private function get_service_statistics() {
        $stats = [
            'total'       => 0,
            'services'    => [],
            'most_popular'=> null
        ];
        
        // Retrieve all services
        $services = get_posts([
            'post_type'      => 'vandel_service',
            'posts_per_page' => -1
        ]);
        
        $stats['total'] = count($services);
        
        if ($stats['total'] === 0) {
            return $stats;
        }
        
        // Get booking counts for each service
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $table_exists   = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        if ($table_exists) {
            $service_counts = $wpdb->get_results("
                SELECT service, COUNT(*) as count, SUM(total_price) as revenue
                FROM $bookings_table
                WHERE status != 'canceled'
                GROUP BY service
                ORDER BY count DESC
            ");
            
            if ($service_counts) {
                $services_with_counts = [];
                $highest_count        = 0;
                $most_popular_id      = 0;
                
                foreach ($services as $service) {
                    $count   = 0;
                    $revenue = 0;
                    
                    // Match the service ID to the results
                    foreach ($service_counts as $sc) {
                        if ($sc->service == $service->ID) {
                            $count   = (int) $sc->count;
                            $revenue = (float) $sc->revenue;
                            break;
                        }
                    }
                    
                    // Track most popular
                    if ($count > $highest_count) {
                        $highest_count   = $count;
                        $most_popular_id = $service->ID;
                    }
                    
                    $services_with_counts[] = [
                        'id'      => $service->ID,
                        'title'   => $service->post_title,
                        'count'   => $count,
                        'revenue' => $revenue,
                    ];
                }
                
                $stats['services'] = $services_with_counts;
                
                // Set the "most_popular" service
                if ($most_popular_id) {
                    foreach ($services as $service) {
                        if ($service->ID === $most_popular_id) {
                            $stats['most_popular'] = $service;
                            break;
                        }
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Get upcoming bookings
     */
    private function get_upcoming_bookings($bookings_table, $table_exists, $limit = 5) {
        global $wpdb;
        
        if (!$table_exists) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * 
            FROM $bookings_table 
            WHERE booking_date > NOW() 
              AND status IN ('pending', 'confirmed') 
            ORDER BY booking_date ASC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get recent bookings
     */
    private function get_recent_bookings($bookings_table, $table_exists, $limit = 10) {
        global $wpdb;
        
        if (!$table_exists) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * 
            FROM $bookings_table 
            ORDER BY created_at DESC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get location statistics
     */
    private function get_location_statistics($bookings_table, $table_exists) {
        global $wpdb;
        
        $stats = [
            'areas'      => [],
            'most_active'=> null
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Adjust field names to match your DB columns
        $location_results = $wpdb->get_results("
            SELECT 
                COALESCE(customer_address, 'Unknown') as area, 
                COUNT(*) as booking_count,
                SUM(total_price) as revenue
            FROM $bookings_table
            WHERE status != 'canceled'
            GROUP BY area
            ORDER BY booking_count DESC
            LIMIT 10
        ");
        
        if ($location_results) {
            $stats['areas']      = $location_results;
            $stats['most_active']= $location_results[0]->area;
        }
        
        return $stats;
    }
    
    /**
     * Get repeat client statistics
     */
    private function get_repeat_client_statistics($bookings_table, $clients_table, $tables_exist) {
        global $wpdb;
        
        $stats = [
            'total_repeat'        => 0,
            'percentage'          => 0,
            'most_bookings'       => 0,
            'most_frequent_client'=> null
        ];
        
        if (!$tables_exist) {
            return $stats;
        }
        
        // Clients with more than one booking
        $repeat_clients = $wpdb->get_var("
            SELECT COUNT(DISTINCT client_id) 
            FROM $bookings_table
            WHERE client_id IN (
                SELECT client_id
                FROM $bookings_table
                GROUP BY client_id
                HAVING COUNT(*) > 1
            )
        ");
        
        $stats['total_repeat'] = (int) $repeat_clients;
        
        // Total clients for percentage
        $total_clients = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $clients_table
        ");
        
        if ($total_clients > 0) {
            $stats['percentage'] = round(($repeat_clients / $total_clients) * 100, 1);
        }
        
        // Get the single most frequent client
        $most_frequent = $wpdb->get_row("
            SELECT 
                b.client_id, 
                COUNT(*) as booking_count,
                c.name as client_name,
                c.email as client_email
            FROM $bookings_table b
            JOIN $clients_table c ON b.client_id = c.id
            GROUP BY b.client_id, c.name, c.email
            ORDER BY booking_count DESC
            LIMIT 1
        ");
        
        if ($most_frequent) {
            $stats['most_bookings']       = (int) $most_frequent->booking_count;
            $stats['most_frequent_client']= $most_frequent;
        }
        
        return $stats;
    }
    
    /**
     * Render the final dashboard view
     */
    private function render_dashboard_view(
        $booking_stats,
        $revenue_stats,
        $client_stats,
        $service_stats,
        $upcoming_bookings,
        $recent_bookings,
        $location_data,
        $repeat_client_stats
    ) {
        ?>
        <div id="overview" class="vandel-tab-content">
            <!-- Welcome / Header -->
            <div class="vandel-dashboard-welcome">
                <div class="vandel-welcome-content">
                    <h2><?php _e('Cleaning Business Dashboard', 'vandel-booking'); ?></h2>
                    <p><?php _e('Monitor your cleaning service performance, client bookings, and business metrics all in one place.', 'vandel-booking'); ?></p>
                </div>
                
                <!-- Quick Stats -->
                <div class="vandel-quick-stats">
                    <div class="vandel-stat-cards">
                        <!-- Total Cleanings -->
                        <div class="vandel-stat-card">
                            <div class="vandel-stat-header">
                                <div class="vandel-stat-icon">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </div>
                                <div class="vandel-stat-value">
                                    <?php echo number_format_i18n($booking_stats['total']); ?>
                                </div>
                            </div>
                            <div class="vandel-stat-footer">
                                <div class="vandel-stat-label">
                                    <?php _e('Total Cleanings', 'vandel-booking'); ?>
                                    <?php if (isset($booking_stats['growth_rate'])): ?>
                                        <span class="vandel-growth-indicator 
                                            <?php echo $booking_stats['growth_rate'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo ($booking_stats['growth_rate'] >= 0 ? '+' : '') . $booking_stats['growth_rate']; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Revenue -->
                        <div class="vandel-stat-card">
                            <div class="vandel-stat-header">
                                <div class="vandel-stat-icon">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                </div>
                                <div class="vandel-stat-value">
                                    <?php echo \VandelBooking\Helpers::formatPrice($revenue_stats['total']); ?>
                                </div>
                            </div>
                            <div class="vandel-stat-footer">
                                <div class="vandel-stat-label">
                                    <?php _e('Total Revenue', 'vandel-booking'); ?>
                                    <?php if (isset($revenue_stats['growth_rate'])): ?>
                                        <span class="vandel-growth-indicator 
                                            <?php echo $revenue_stats['growth_rate'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo ($revenue_stats['growth_rate'] >= 0 ? '+' : '') . $revenue_stats['growth_rate']; ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total Clients -->
                        <div class="vandel-stat-card">
                            <div class="vandel-stat-header">
                                <div class="vandel-stat-icon">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                                <div class="vandel-stat-value">
                                    <?php echo number_format_i18n($client_stats['total']); ?>
                                </div>
                            </div>
                            <div class="vandel-stat-footer">
                                <div class="vandel-stat-label">
                                    <?php _e('Total Clients', 'vandel-booking'); ?>
                                    <?php if ($client_stats['new_this_month'] > 0): ?>
                                        <span class="vandel-growth-indicator positive">
                                            +<?php echo number_format_i18n($client_stats['new_this_month']); ?>
                                            <?php _e('this month', 'vandel-booking'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Today's Cleanings -->
                        <div class="vandel-stat-card">
                            <div class="vandel-stat-header">
                                <div class="vandel-stat-icon">
                                    <span class="dashicons dashicons-admin-home"></span>
                                </div>
                                <div class="vandel-stat-value">
                                    <?php echo number_format_i18n($booking_stats['today']); ?>
                                </div>
                            </div>
                            <div class="vandel-stat-footer">
                                <div class="vandel-stat-label">
                                    <?php _e('Today\'s Cleanings', 'vandel-booking'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Grid -->
            <div class="vandel-dashboard-grid">
                <div class="vandel-dashboard-main">
                    
                    <!-- Booking Status Summary -->
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Cleaning Service Status', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" 
                               class="vandel-view-all"><?php _e('View All Bookings', 'vandel-booking'); ?></a>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-status-summary">
                                <div class="vandel-status-item vandel-status-pending">
                                    <div class="vandel-status-count">
                                        <?php echo number_format_i18n($booking_stats['pending']); ?>
                                    </div>
                                    <div class="vandel-status-label">
                                        <?php _e('Pending', 'vandel-booking'); ?>
                                    </div>
                                    <div class="vandel-status-icon">
                                        <span class="dashicons dashicons-clock"></span>
                                    </div>
                                </div>
                                
                                <div class="vandel-status-item vandel-status-confirmed">
                                    <div class="vandel-status-count">
                                        <?php echo number_format_i18n($booking_stats['confirmed']); ?>
                                    </div>
                                    <div class="vandel-status-label">
                                        <?php _e('Confirmed', 'vandel-booking'); ?>
                                    </div>
                                    <div class="vandel-status-icon">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </div>
                                </div>
                                
                                <div class="vandel-status-item vandel-status-completed">
                                    <div class="vandel-status-count">
                                        <?php echo number_format_i18n($booking_stats['completed']); ?>
                                    </div>
                                    <div class="vandel-status-label">
                                        <?php _e('Completed', 'vandel-booking'); ?>
                                    </div>
                                    <div class="vandel-status-icon">
                                        <span class="dashicons dashicons-saved"></span>
                                    </div>
                                </div>
                                
                                <div class="vandel-status-item vandel-status-canceled">
                                    <div class="vandel-status-count">
                                        <?php echo number_format_i18n($booking_stats['canceled']); ?>
                                    </div>
                                    <div class="vandel-status-label">
                                        <?php _e('Canceled', 'vandel-booking'); ?>
                                    </div>
                                    <div class="vandel-status-icon">
                                        <span class="dashicons dashicons-dismiss"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Cleaning Schedule -->
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Upcoming Cleaning Schedule', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" 
                               class="vandel-view-all"><?php _e('View Calendar', 'vandel-booking'); ?></a>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($upcoming_bookings)): ?>
                                <div class="vandel-empty-state">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <p><?php _e('No upcoming cleanings scheduled.', 'vandel-booking'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" 
                                       class="button button-primary"><?php _e('Schedule Cleaning', 'vandel-booking'); ?></a>
                                </div>
                            <?php else: ?>
                                <div class="vandel-cleaning-schedule">
                                    <?php foreach ($upcoming_bookings as $booking):
                                        $service      = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Standard Cleaning', 'vandel-booking');
                                    ?>
                                    <div class="vandel-schedule-item">
                                        <div class="vandel-schedule-time">
                                            <div class="vandel-date">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($booking->booking_date)); ?>
                                            </div>
                                            <div class="vandel-time">
                                                <?php echo date_i18n(get_option('time_format'), strtotime($booking->booking_date)); ?>
                                            </div>
                                        </div>
                                        <div class="vandel-schedule-info">
                                            <div class="vandel-client-name">
                                                <a href="<?php 
                                                    echo admin_url(
                                                        'admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $booking->client_id
                                                    ); 
                                                ?>">
                                                    <?php echo esc_html($booking->customer_name); ?>
                                                </a>
                                            </div>
                                            <div class="vandel-service-type">
                                                <?php echo esc_html($service_name); ?>
                                            </div>
                                            <?php if (!empty($booking->customer_address)): ?>
                                            <div class="vandel-client-address">
                                                <span class="dashicons dashicons-location"></span>
                                                <?php echo esc_html($booking->customer_address); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="vandel-schedule-status">
                                            <span class="vandel-status-badge vandel-status-badge-<?php echo $booking->status; ?>">
                                                <?php echo ucfirst($booking->status); ?>
                                            </span>
                                            <div class="vandel-schedule-actions">
                                                <a href="<?php 
                                                    echo admin_url(
                                                        'admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id
                                                    ); 
                                                ?>" 
                                                   class="vandel-action-btn">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Monthly Booking Trends -->
                    <?php if (!empty($booking_stats['monthly_trend'])): ?>
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Cleaning Service Trends', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div id="monthly-booking-trend" class="vandel-chart" style="height: 300px;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Service Performance -->
                    <?php if (!empty($service_stats['services'])): ?>
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Cleaning Service Performance', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-grid-row">
                                <div class="vandel-grid-col-6">
                                    <div id="service-performance-chart" class="vandel-chart" style="height: 250px;"></div>
                                </div>
                                <div class="vandel-grid-col-6">
                                    <div class="vandel-service-metrics">
                                        <div class="vandel-section-title">
                                            <?php _e('Service Breakdown', 'vandel-booking'); ?>
                                        </div>
                                        <div class="vandel-service-list">
                                            <?php foreach ($service_stats['services'] as $service):
                                                $percentage = $booking_stats['total'] > 0
                                                    ? round(($service['count'] / $booking_stats['total']) * 100)
                                                    : 0;
                                            ?>
                                            <div class="vandel-service-item">
                                                <div class="vandel-service-name">
                                                    <?php echo esc_html($service['title']); ?>
                                                    <span class="vandel-service-count">
                                                        (<?php echo number_format_i18n($service['count']); ?>)
                                                    </span>
                                                </div>
                                                <div class="vandel-service-progress">
                                                    <div class="vandel-progress-bar">
                                                        <div class="vandel-progress-fill" 
                                                             style="width: <?php echo esc_attr($percentage); ?>%">
                                                        </div>
                                                    </div>
                                                    <div class="vandel-progress-percentage">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                                <div class="vandel-service-revenue">
                                                    <?php echo \VandelBooking\Helpers::formatPrice($service['revenue']); ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php if ($service_stats['most_popular'] !== null): ?>
                                        <div class="vandel-card-highlight">
                                            <span class="dashicons dashicons-awards"></span>
                                            <strong>
                                                <?php _e('Most Popular Service:', 'vandel-booking'); ?>
                                            </strong>
                                            <?php echo esc_html($service_stats['most_popular']->post_title); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div><!-- .vandel-dashboard-main -->
                
                <!-- Sidebar -->
                <div class="vandel-dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Quick Actions', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-quick-action-buttons">
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" 
                                   class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    <span class="vandel-quick-action-label">
                                        <?php _e('New Cleaning', 'vandel-booking'); ?>
                                    </span>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" 
                                   class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <span class="vandel-quick-action-label">
                                        <?php _e('Add Client', 'vandel-booking'); ?>
                                    </span>
                                </a>
                                
                                <a href="<?php echo admin_url('post-new.php?post_type=vandel_service'); ?>" 
                                   class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <span class="vandel-quick-action-label">
                                        <?php _e('Add Service', 'vandel-booking'); ?>
                                    </span>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" 
                                   class="vandel-quick-action-btn">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <span class="vandel-quick-action-label">
                                        <?php _e('View Calendar', 'vandel-booking'); ?>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Client Insights -->
                    <div class="vandel-card">
                        <div class="vandel-card-header vandel-flex-header">
                            <h3><?php _e('Client Insights', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" 
                               class="vandel-view-all">
                                <?php _e('View All Clients', 'vandel-booking'); ?>
                            </a>
                        </div>
                        <div class="vandel-card-body">
                            <?php if ($repeat_client_stats['total_repeat'] > 0): ?>
                                <div class="vandel-client-metrics">
                                    <div class="vandel-metric-item">
                                        <div class="vandel-metric-value">
                                            <?php echo number_format_i18n($repeat_client_stats['total_repeat']); ?>
                                        </div>
                                        <div class="vandel-metric-label">
                                            <?php _e('Repeat Clients', 'vandel-booking'); ?>
                                        </div>
                                    </div>
                                    <div class="vandel-metric-item">
                                        <div class="vandel-metric-value">
                                            <?php echo $repeat_client_stats['percentage']; ?>%
                                        </div>
                                        <div class="vandel-metric-label">
                                            <?php _e('Client Retention Rate', 'vandel-booking'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($repeat_client_stats['most_frequent_client']): ?>
                                <div class="vandel-highlight-client">
                                    <div class="vandel-highlight-client-header">
                                        <div class="vandel-highlight-client-icon">
                                            <span class="dashicons dashicons-star-filled"></span>
                                        </div>
                                        <div class="vandel-highlight-client-title">
                                            <?php _e('Most Frequent Client', 'vandel-booking'); ?>
                                        </div>
                                    </div>
                                    <div class="vandel-highlight-client-content">
                                        <div class="vandel-client-name">
                                            <a href="<?php 
                                                echo admin_url(
                                                    'admin.php?page=vandel-dashboard&tab=client-details&client_id=' 
                                                    . $repeat_client_stats['most_frequent_client']->client_id
                                                ); 
                                            ?>">
                                                <?php echo esc_html($repeat_client_stats['most_frequent_client']->client_name); ?>
                                            </a>
                                        </div>
                                        <div class="vandel-client-info">
                                            <span class="vandel-client-email">
                                                <?php echo esc_html($repeat_client_stats['most_frequent_client']->client_email); ?>
                                            </span>
                                        </div>
                                        <div class="vandel-client-stats">
                                            <span class="vandel-booking-count">
                                                <strong>
                                                    <?php echo number_format_i18n($repeat_client_stats['most_bookings']); ?>
                                                </strong>
                                                <?php _e('bookings', 'vandel-booking'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="vandel-empty-state vandel-empty-state-small">
                                    <p><?php _e('Not enough client data yet.', 'vandel-booking'); ?></p>
                                    <p><?php _e('Add more clients and bookings to see insights here.', 'vandel-booking'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Service Area Insights -->
                    <?php if (!empty($location_data['areas'])): ?>
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Service Area Insights', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-area-metrics">
                                <div class="vandel-area-title">
                                    <?php _e('Top Service Areas by Bookings', 'vandel-booking'); ?>
                                </div>
                                <div class="vandel-area-list">
                                    <?php 
                                    // Highest count is used for the progress bars
                                    $max_count = $location_data['areas'][0]->booking_count;
                                    foreach (array_slice($location_data['areas'], 0, 5) as $area):
                                        $percentage = $max_count > 0 
                                            ? ($area->booking_count / $max_count) * 100 
                                            : 0;
                                    ?>
                                    <div class="vandel-area-item">
                                        <div class="vandel-area-name">
                                            <?php echo esc_html($area->area); ?>
                                            <span class="vandel-area-count">
                                                (<?php echo number_format_i18n($area->booking_count); ?>)
                                            </span>
                                        </div>
                                        <div class="vandel-area-bar">
                                            <div class="vandel-area-bar-fill" 
                                                 style="width: <?php echo esc_attr($percentage); ?>%">
                                            </div>
                                        </div>
                                        <div class="vandel-area-revenue">
                                            <?php echo \VandelBooking\Helpers::formatPrice($area->revenue); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if ($location_data['most_active']): ?>
                                <div class="vandel-card-highlight">
                                    <span class="dashicons dashicons-location"></span>
                                    <strong><?php _e('Most Active Area:', 'vandel-booking'); ?></strong>
                                    <?php echo esc_html($location_data['most_active']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Activity -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Recent Activity', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="vandel-empty-state vandel-empty-state-small">
                                    <p><?php _e('No recent activity.', 'vandel-booking'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="vandel-activity-feed">
                                    <?php foreach ($recent_bookings as $booking):
                                        $service      = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Standard Cleaning', 'vandel-booking');
                                        // Determine dashicon by status
                                        $icon_class   = 'dashicons-calendar-alt';
                                        switch ($booking->status) {
                                            case 'confirmed':
                                                $icon_class = 'dashicons-yes-alt';
                                                break;
                                            case 'completed':
                                                $icon_class = 'dashicons-saved';
                                                break;
                                            case 'canceled':
                                                $icon_class = 'dashicons-dismiss';
                                                break;
                                        }
                                    ?>
                                    <div class="vandel-activity-item">
                                        <div class="vandel-activity-icon">
                                            <span class="dashicons <?php echo $icon_class; ?>"></span>
                                        </div>
                                        <div class="vandel-activity-content">
                                            <div class="vandel-activity-message">
                                                <a href="<?php 
                                                    echo admin_url(
                                                        'admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id
                                                    ); 
                                                ?>">
                                                    <?php echo esc_html($booking->customer_name); ?>
                                                </a>
                                                <?php 
                                                switch ($booking->status) {
                                                    case 'pending':
                                                        _e('booked a cleaning service', 'vandel-booking');
                                                        break;
                                                    case 'confirmed':
                                                        _e('confirmed a cleaning', 'vandel-booking');
                                                        break;
                                                    case 'completed':
                                                        _e('completed a cleaning service', 'vandel-booking');
                                                        break;
                                                    case 'canceled':
                                                        _e('canceled a cleaning service', 'vandel-booking');
                                                        break;
                                                    default:
                                                        _e('updated a booking', 'vandel-booking');
                                                }
                                                ?>
                                                – <?php echo esc_html($service_name); ?>
                                            </div>
                                            <div class="vandel-activity-time">
                                                <?php echo human_time_diff(strtotime($booking->created_at), current_time('timestamp')); ?>
                                                <?php _e('ago', 'vandel-booking'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div><!-- .vandel-dashboard-sidebar -->
            </div><!-- .vandel-dashboard-grid -->
        </div><!-- #overview -->
        
        <?php if (!empty($booking_stats['monthly_trend']) || !empty($service_stats['services'])): ?>
        <script>
        jQuery(document).ready(function($) {
            <?php if (!empty($booking_stats['monthly_trend'])): ?>
            // Monthly Booking Trend (Line Chart)
            const bookingCtx = document.getElementById('monthly-booking-trend').getContext('2d');
            new Chart(bookingCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($booking_stats['monthly_trend'])); ?>,
                    datasets: [{
                        label: '<?php _e('Cleaning Services', 'vandel-booking'); ?>',
                        data: <?php echo json_encode(array_values($booking_stats['monthly_trend'])); ?>,
                        backgroundColor: 'rgba(53, 162, 235, 0.2)',
                        borderColor: 'rgba(53, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    return tooltipItems[0].label;
                                },
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.raw} bookings`;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            <?php if (!empty($service_stats['services'])): ?>
            // Service Performance (Doughnut Chart)
            const serviceCtx = document.getElementById('service-performance-chart').getContext('2d');
            new Chart(serviceCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php 
                        echo json_encode(array_map(
                            fn($service) => $service['title'], 
                            $service_stats['services']
                        )); 
                    ?>,
                    datasets: [{
                        data: <?php 
                            echo json_encode(array_map(
                                fn($service) => $service['count'], 
                                $service_stats['services']
                            )); 
                        ?>,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(199, 199, 199, 0.8)',
                            'rgba(83, 102, 255, 0.8)',
                            'rgba(40, 159, 64, 0.8)',
                            'rgba(210, 99, 132, 0.8)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const dataset = context.dataset;
                                    const total = dataset.data.reduce((acc, num) => acc + num, 0);
                                    const pct = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
        </script>
        <?php endif; ?>
        <?php
    }
}
