<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Enhanced Overview Tab for Cleaning Business
 * Provides a comprehensive, modern dashboard with key metrics and cleaning-specific analytics
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
        
        ?>
        <div id="overview" class="vandel-dashboard-overview">
            <div class="vandel-dashboard-header">
                <div class="vandel-header-content">
                    <h1><?php _e('Dashboard Overview', 'vandel-booking'); ?></h1>
                    <p><?php _e('Your cleaning business performance at a glance', 'vandel-booking'); ?></p>
                </div>
                <div class="vandel-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span> 
                        <?php _e('New Booking', 'vandel-booking'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-users"></span> 
                        <?php _e('Add Client', 'vandel-booking'); ?>
                    </a>
                </div>
            </div>

            <div class="vandel-dashboard-grid">
                <div class="vandel-dashboard-main">
                    <!-- Key Performance Metrics -->
                    <div class="vandel-performance-metrics">
                        <div class="vandel-metric-card total-revenue">
                            <div class="vandel-metric-icon">
                                <span class="dashicons dashicons-chart-bar"></span>
                            </div>
                            <div class="vandel-metric-content">
                                <h3><?php _e('Total Revenue', 'vandel-booking'); ?></h3>
                                <div class="vandel-metric-value">
                                    <?php echo \VandelBooking\Helpers::formatPrice($revenue_stats['total']); ?>
                                </div>
                                <div class="vandel-metric-change <?php echo $revenue_stats['growth_rate'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php 
                                    echo $revenue_stats['growth_rate'] >= 0 ? '+' : '';
                                    echo number_format($revenue_stats['growth_rate'], 1) . '%'; 
                                    ?> 
                                    <span><?php _e('this month', 'vandel-booking'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="vandel-metric-card total-bookings">
                            <div class="vandel-metric-icon">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </div>
                            <div class="vandel-metric-content">
                                <h3><?php _e('Total Bookings', 'vandel-booking'); ?></h3>
                                <div class="vandel-metric-value">
                                    <?php echo number_format_i18n($booking_stats['total']); ?>
                                </div>
                                <div class="vandel-metric-change <?php echo $booking_stats['growth_rate'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php 
                                    echo $booking_stats['growth_rate'] >= 0 ? '+' : '';
                                    echo number_format($booking_stats['growth_rate'], 1) . '%'; 
                                    ?> 
                                    <span><?php _e('this month', 'vandel-booking'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="vandel-metric-card new-clients">
                            <div class="vandel-metric-icon">
                                <span class="dashicons dashicons-groups"></span>
                            </div>
                            <div class="vandel-metric-content">
                                <h3><?php _e('New Clients', 'vandel-booking'); ?></h3>
                                <div class="vandel-metric-value">
                                    <?php echo number_format_i18n($client_stats['new_this_month']); ?>
                                </div>
                                <div class="vandel-metric-change positive">
                                    +<?php 
                                    $total_clients = $client_stats['total'];
                                    echo number_format(($client_stats['new_this_month'] / max(1, $total_clients)) * 100, 1) . '%'; 
                                    ?> 
                                    <span><?php _e('this month', 'vandel-booking'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Performance Card -->
                    <?php if (!empty($service_stats['services'])): ?>
                    <div class="vandel-card service-performance">
                        <div class="vandel-card-header">
                            <h3><?php _e('Service Performance', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-chart-wrapper">
                                <canvas id="service-performance-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upcoming Bookings -->
                    <div class="vandel-card upcoming-bookings">
                        <div class="vandel-card-header">
                            <h3><?php _e('Upcoming Bookings', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" class="vandel-view-all">
                                <?php _e('View Calendar', 'vandel-booking'); ?>
                            </a>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($upcoming_bookings)): ?>
                                <div class="vandel-empty-state">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <p><?php _e('No upcoming bookings scheduled.', 'vandel-booking'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="vandel-booking-list">
                                    <?php foreach ($upcoming_bookings as $booking): 
                                        $service = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Standard Cleaning', 'vandel-booking');
                                    ?>
                                    <div class="vandel-booking-item">
                                        <div class="vandel-booking-details">
                                            <div class="vandel-booking-client">
                                                <?php echo esc_html($booking->customer_name); ?>
                                            </div>
                                            <div class="vandel-booking-service">
                                                <?php echo esc_html($service_name); ?>
                                            </div>
                                            <div class="vandel-booking-datetime">
                                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?>
                                            </div>
                                        </div>
                                        <div class="vandel-booking-status">
                                            <span class="vandel-status-badge vandel-status-badge-<?php echo esc_attr($booking->status); ?>">
                                                <?php echo esc_html(ucfirst($booking->status)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="vandel-dashboard-sidebar">
                    <!-- Client Insights -->
                    <div class="vandel-card client-insights">
                        <div class="vandel-card-header">
                            <h3><?php _e('Client Insights', 'vandel-booking'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="vandel-view-all">
                                <?php _e('View All', 'vandel-booking'); ?>
                            </a>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-client-metrics">
                                <div class="vandel-client-metric">
                                    <div class="vandel-metric-value"><?php echo number_format_i18n($client_stats['total']); ?></div>
                                    <div class="vandel-metric-label"><?php _e('Total Clients', 'vandel-booking'); ?></div>
                                </div>
                                <div class="vandel-client-metric">
                                    <div class="vandel-metric-value">
                                        <?php 
                                        // $repeat_rate = $client_stats['total'] > 0 
                                        //     ? round(($repeat_client_stats['total_repeat'] / $client_stats['total']) * 100, 1)
                                        //     : 0; 
                                        // echo $repeat_rate . '%'; 
                                        ?>
                                    </div>
                                    <div class="vandel-metric-label"><?php _e('Repeat Clients', 'vandel-booking'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="vandel-card recent-activity">
                        <div class="vandel-card-header">
                            <h3><?php _e('Recent Activity', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="vandel-empty-state">
                                    <span class="dashicons dashicons-info"></span>
                                    <p><?php _e('No recent activity.', 'vandel-booking'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="vandel-activity-feed">
                                    <?php foreach ($recent_bookings as $booking): 
                                        $service = get_post($booking->service);
                                        $service_name = $service ? $service->post_title : __('Standard Cleaning', 'vandel-booking');
                                    ?>
                                    <div class="vandel-activity-item">
                                        <div class="vandel-activity-icon">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                        </div>
                                        <div class="vandel-activity-content">
                                            <div class="vandel-activity-message">
                                                <?php echo esc_html($booking->customer_name); ?> 
                                                <?php 
                                                switch ($booking->status) {
                                                    case 'pending':
                                                        _e('created a booking', 'vandel-booking');
                                                        break;
                                                    case 'confirmed':
                                                        _e('confirmed a booking', 'vandel-booking');
                                                        break;
                                                    case 'completed':
                                                        _e('completed a cleaning', 'vandel-booking');
                                                        break;
                                                    case 'canceled':
                                                        _e('canceled a booking', 'vandel-booking');
                                                        break;
                                                    default:
                                                        _e('updated a booking', 'vandel-booking');
                                                }
                                                ?> 
                                                (<?php echo esc_html($service_name); ?>)
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
                </div>
            </div>
        </div>


        <?php if (!empty($service_stats['services']) || !empty($booking_stats['monthly_trend'])): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($service_stats['services'])): ?>
            // Service Performance Chart
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
                            'rgba(54, 162, 235, 0.8)',   // Blue
                            'rgba(255, 99, 132, 0.8)',   // Pink
                            'rgba(75, 192, 192, 0.8)',   // Teal
                            'rgba(255, 206, 86, 0.8)',   // Yellow
                            'rgba(153, 102, 255, 0.8)',  // Purple
                            'rgba(255, 159, 64, 0.8)'    // Orange
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

            <?php if (!empty($booking_stats['monthly_trend'])): ?>
            // Monthly Booking Trend Chart
            const trendCtx = document.getElementById('monthly-booking-trend').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($booking_stats['monthly_trend'])); ?>,
                    datasets: [{
                        label: '<?php _e('Bookings', 'vandel-booking'); ?>',
                        data: <?php echo json_encode(array_values($booking_stats['monthly_trend'])); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
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
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y} bookings`;
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


/**
     * Get booking statistics
     * 
     * @param string $bookings_table Bookings table name
     * @param bool $table_exists Whether the table exists
     * @return array Booking statistics
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
            'monthly_trend' => [],
            'growth_rate'   => 0
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Total bookings
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        
        // Status counts
        $status_counts = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM $bookings_table 
            GROUP BY status
        ");
        
        foreach ($status_counts as $status) {
            if (isset($stats[$status->status])) {
                $stats[$status->status] = (int) $status->count;
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
        
        // Monthly trend (last 6 months)
        $monthly_results = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(booking_date, '%Y-%m') as month,
                COUNT(*) as count
            FROM $bookings_table
            WHERE booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
            ORDER BY month ASC
        ");
        
        // Prepare monthly trend data
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                // Convert the year-month to a localizable month name
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (int) $row->count;
            }
        }
        
        // Calculate growth rate
        if (count($stats['monthly_trend']) > 1) {
            $monthly_values = array_values($stats['monthly_trend']);
            $current_month = end($monthly_values);
            $previous_month = prev($monthly_values);
            
            if ($previous_month > 0) {
                $stats['growth_rate'] = round((($current_month - $previous_month) / $previous_month) * 100, 1);
            }
        }
        
        return $stats;
    }
    
    /**
     * Get revenue statistics
     * 
     * @param string $bookings_table Bookings table name
     * @param bool $table_exists Whether the table exists
     * @return array Revenue statistics
     */
    private function get_revenue_statistics($bookings_table, $table_exists) {
        global $wpdb;
        
        $stats = [
            'total'            => 0,
            'this_month'       => 0,
            'last_month'       => 0,
            'monthly_trend'    => [],
            'avg_booking_value'=> 0,
            'growth_rate'      => 0
        ];
        
        if (!$table_exists) {
            return $stats;
        }
        
        // Total revenue (excluding canceled bookings)
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
        
        // Prepare monthly trend data
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                // Convert the year-month to a localizable month name
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (float) $row->revenue;
            }
        }
        
        // Average booking value
        $total_completed_bookings = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $bookings_table 
            WHERE status IN ('completed', 'confirmed')
        ");
        
        if ($total_completed_bookings > 0) {
            $stats['avg_booking_value'] = $stats['total'] / $total_completed_bookings;
        }
        
        // Calculate growth rate
        if ($stats['last_month'] > 0) {
            $stats['growth_rate'] = round((($stats['this_month'] - $stats['last_month']) / $stats['last_month']) * 100, 1);
        }
        
        return $stats;
    }
    
    /**
     * Get client statistics
     * 
     * @param string $clients_table Clients table name
     * @param bool $table_exists Whether the table exists
     * @return array Client statistics
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
        
        // Prepare monthly trend data
        if ($monthly_results) {
            foreach ($monthly_results as $row) {
                // Convert the year-month to a localizable month name
                $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                $stats['monthly_trend'][$month_name] = (int) $row->count;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get service statistics
     * 
     * @return array Service statistics
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
     * 
     * @param string $bookings_table Bookings table name
     * @param bool $table_exists Whether the table exists
     * @param int $limit Number of bookings to retrieve
     * @return array Upcoming bookings
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
     * 
     * @param string $bookings_table Bookings table name
     * @param bool $table_exists Whether the table exists
     * @param int $limit Number of bookings to retrieve
     * @return array Recent bookings
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
}