<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Analytics Tab
 * Handles the analytics and reporting tab
 */
class Analytics_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // No specific hooks for analytics tab
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // No actions to process for analytics tab
    }
    
    /**
     * Render tab content
     */
    public function render() {
        global $wpdb;
        
        // Get booking data for charts
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        // Initialize empty datasets
        $monthly_bookings = [];
        $revenue_data = [];
        $service_breakdown = [];
        
        if ($bookings_table_exists) {
            // Get monthly bookings data (last 6 months)
            $monthly_results = $wpdb->get_results(
                "SELECT 
                    DATE_FORMAT(booking_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as revenue
                 FROM $bookings_table
                 WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
                 ORDER BY month ASC"
            );
            
            if ($monthly_results) {
                foreach ($monthly_results as $row) {
                    $month_name = date_i18n('M Y', strtotime($row->month . '-01'));
                    $monthly_bookings[$month_name] = intval($row->count);
                    $revenue_data[$month_name] = floatval($row->revenue);
                }
            }
            
            // Get service breakdown
            $service_results = $wpdb->get_results(
                "SELECT 
                    service,
                    COUNT(*) as count
                 FROM $bookings_table
                 WHERE status != 'canceled'
                 GROUP BY service
                 ORDER BY count DESC
                 LIMIT 5"
            );
            
            if ($service_results) {
                foreach ($service_results as $row) {
                    $service = get_post($row->service);
                    $service_name = $service ? $service->post_title : __('Unknown', 'vandel-booking') . ' (#' . $row->service . ')';
                    $service_breakdown[$service_name] = intval($row->count);
                }
            }
        }
        
        ?>
        <div id="analytics" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Booking Analytics', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($monthly_bookings)): ?>
                        <div class="vandel-empty-state">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <p><?php _e('Not enough data yet to display analytics. Start adding bookings to see insights here.', 'vandel-booking'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="vandel-analytics-grid">
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Bookings by Month', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="bookingsChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                            
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Revenue by Month', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="revenueChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vandel-analytics-grid">
                            <div class="vandel-analytics-chart-container">
                                <h4><?php _e('Popular Services', 'vandel-booking'); ?></h4>
                                <div class="vandel-chart-wrapper">
                                    <canvas id="servicesChart" width="400" height="250"></canvas>
                                </div>
                            </div>
                            
                            <div class="vandel-analytics-stats">
                                <h4><?php _e('Booking Statistics', 'vandel-booking'); ?></h4>
                                
                                <?php 
                                // Calculate some statistics
                                $total_bookings = array_sum($monthly_bookings);
                                $total_revenue = array_sum($revenue_data);
                                $avg_booking_value = $total_bookings > 0 ? $total_revenue / $total_bookings : 0;
                                
                                // Determine growth if we have enough data
                                $months = array_keys($monthly_bookings);
                                $growth_text = '';
                                
                                if (count($months) >= 2) {
                                    $current_month = end($monthly_bookings);
                                    reset($monthly_bookings);
                                    $prev_month = prev($monthly_bookings);
                                    
                                    if ($prev_month > 0) {
                                        $growth_percent = (($current_month - $prev_month) / $prev_month) * 100;
                                        $growth_text = sprintf(
                                            __('%+.1f%% from previous month', 'vandel-booking'),
                                            $growth_percent
                                        );
                                    }
                                }
                                ?>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Average Booking Value', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($avg_booking_value); ?></div>
                                </div>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Total Revenue (6 months)', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo \VandelBooking\Helpers::formatPrice($total_revenue); ?></div>
                                </div>
                                
                                <div class="vandel-analytics-stat-item">
                                    <div class="vandel-stat-label"><?php _e('Current Month Bookings', 'vandel-booking'); ?></div>
                                    <div class="vandel-stat-value"><?php echo number_format_i18n(end($monthly_bookings)); ?></div>
                                    <?php if ($growth_text): ?>
                                        <div class="vandel-stat-growth <?php echo strpos($growth_text, '+') === 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $growth_text; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Load chart.js for analytics
            wp_enqueue_script('vandel-chartjs', VANDEL_PLUGIN_URL . 'assets/js/chart.min.js', [], '3.7.0', true);
            
            // Prepare chart data for JavaScript
            $chart_data = [
                'months' => array_keys($monthly_bookings),
                'bookings' => array_values($monthly_bookings),
                'revenue' => array_values($revenue_data),
                'serviceLabels' => array_keys($service_breakdown),
                'serviceData' => array_values($service_breakdown)
            ];
            
            // Add inline script for charts
            wp_add_inline_script('vandel-chartjs', '
                document.addEventListener("DOMContentLoaded", function() {
                    const chartColors = {
                        primary: "#286cd6",
                        secondary: "#6c757d",
                        success: "#28a745",
                        info: "#17a2b8",
                        warning: "#ffc107",
                        danger: "#dc3545",
                        light: "#f8f9fa",
                        dark: "#343a40"
                    };
                    
                    // Chart data
                    const chartData = ' . json_encode($chart_data) . ';
                    
                    // Bookings Chart
                    if (document.getElementById("bookingsChart")) {
                        const bookingsCtx = document.getElementById("bookingsChart").getContext("2d");
                        new Chart(bookingsCtx, {
                            type: "bar",
                            data: {
                                labels: chartData.months,
                                datasets: [{
                                    label: "' . __('Number of Bookings', 'vandel-booking') . '",
                                    data: chartData.bookings,
                                    backgroundColor: chartColors.primary,
                                    borderColor: chartColors.primary,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        precision: 0
                                    }
                                }
                            }
                        });
                    }
                    
                    // Revenue Chart
                    if (document.getElementById("revenueChart")) {
                        const revenueCtx = document.getElementById("revenueChart").getContext("2d");
                        new Chart(revenueCtx, {
                            type: "line",
                            data: {
                                labels: chartData.months,
                                datasets: [{
                                    label: "' . __('Revenue', 'vandel-booking') . '",
                                    data: chartData.revenue,
                                    backgroundColor: "rgba(40, 167, 69, 0.2)",
                                    borderColor: chartColors.success,
                                    borderWidth: 2,
                                    pointBackgroundColor: chartColors.success,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return "' . \VandelBooking\Helpers::getCurrencySymbol() . '" + value;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                    
                    // Services Chart
                    if (document.getElementById("servicesChart")) {
                        const servicesCtx = document.getElementById("servicesChart").getContext("2d");
                        new Chart(servicesCtx, {
                            type: "doughnut",
                            data: {
                                labels: chartData.serviceLabels,
                                datasets: [{
                                    data: chartData.serviceData,
                                    backgroundColor: [
                                        chartColors.primary,
                                        chartColors.success,
                                        chartColors.warning,
                                        chartColors.info,
                                        chartColors.danger
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: "right"
                                    }
                                }
                            }
                        });
                    }
                });
            ');
            ?>
        </div>
        <?php
    }
}