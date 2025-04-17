<?php
/*
Plugin Name: Vandel Cleaning Booking
Description: A robust cleaning booking plugin with multi-step form and unified admin dashboard.
Version: 1.0.3
Author: Mohammad Yousif
Text Domain: vandel-booking
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('VANDEL_VERSION')) {
    define('VANDEL_VERSION', '1.0.3');
}
if (!defined('VANDEL_PLUGIN_DIR')) {
    define('VANDEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('VANDEL_PLUGIN_URL')) {
    define('VANDEL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('VANDEL_PLUGIN_BASENAME')) {
    define('VANDEL_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Define a global variable to prevent duplicate menu registration
global $vandel_menu_registered;
$vandel_menu_registered = false;

// Create required directories
function vandel_create_directories() {
    $directories = [
        VANDEL_PLUGIN_DIR . 'includes/client',
        VANDEL_PLUGIN_DIR . 'includes/ajax',
        VANDEL_PLUGIN_DIR . 'includes/booking',
        VANDEL_PLUGIN_DIR . 'includes/location',
        VANDEL_PLUGIN_DIR . 'includes/post-types',
        VANDEL_PLUGIN_DIR . 'includes/database',
        VANDEL_PLUGIN_DIR . 'includes/admin',
        VANDEL_PLUGIN_DIR . 'assets/js/admin',
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
}
register_activation_hook(__FILE__, 'vandel_create_directories');
vandel_create_directories();

// Include autoloader
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/autoload.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';
}

// Load helpers early as they're used by many components
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-helpers.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/class-helpers.php';
}

// Load Database Installer first to ensure tables exist
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php';
    
    // Create/update tables on activation
    register_activation_hook(__FILE__, function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
        }
    });
    
    // Check for database updates on plugin load
    add_action('plugins_loaded', function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            if (method_exists($installer, 'needsUpdate') && $installer->needsUpdate()) {
                $installer->update();
            }
        }
    });
}

// Load essential model files
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-model.php';
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/client/class-client-model.php';
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-note-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-note-model.php';
}

// Load BookingManager before other components
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-manager.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-manager.php';
}

// Load Location models
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/location/class-zip-code-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/location/class-zip-code-model.php';
}

// Load frontend components
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/frontend/class-booking-form.php';
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php';
}

// Force-load post type classes
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
        }
    });
}

if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-sub-service-post-type.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-sub-service-post-type.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\SubServicePostType')) {
            new \VandelBooking\PostTypes\SubServicePostType();
        }
    });
}

// Check for post type registry class and use it if available
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-post-types-registry.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-post-types-registry.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\Registry')) {
            new \VandelBooking\PostTypes\Registry();
        }
    });
}

// Force-load post type classes
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/post-types/class-service-post-type.php';
    add_action('init', function() {
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
        }
    });
}

// Force-load location classes
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/location/class-location-model.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/location/class-location-model.php';
}


// Add filter to prevent duplicate menu registration
add_filter('vandel_should_register_menu', function($should_register) {
    global $vandel_menu_registered;
    
    if ($vandel_menu_registered) {
        return false;
    }
    
    $vandel_menu_registered = true;
    return true;
}, 10, 1);

// Patch the AdminLoader class to check the filter before registering menu
add_action('plugins_loaded', function() {
    // Add this hook to modify AdminLoader behavior
    add_action('admin_menu', function() {
        global $vandel_menu_registered;
        
        // Check if class-admin-loader.php exists
        if (file_exists(VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php')) {
            // Get the content of the file
            $file_content = file_get_contents(VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php');
            
            // If the file content contains the registerMenu method without our filter
            if (strpos($file_content, 'function registerMenu') !== false 
                && strpos($file_content, 'vandel_should_register_menu') === false) {
                
                // Add our check at the very beginning of the registerMenu method
                // Only do this once
                if (!get_option('vandel_admin_loader_patched', false)) {
                    $modified_content = preg_replace(
                        '/(public\s+function\s+registerMenu\s*\(\s*\)\s*\{)/',
                        '$1' . PHP_EOL . '        // Check if we should register this menu' . PHP_EOL .
                        '        if (!apply_filters(\'vandel_should_register_menu\', true)) {' . PHP_EOL .
                        '            return;' . PHP_EOL .
                        '        }',
                        $file_content
                    );
                    
                    // Only write to the file if we made changes
                    if ($modified_content !== $file_content) {
                        file_put_contents(VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php', $modified_content);
                        update_option('vandel_admin_loader_patched', true);
                    }
                }
            }
        }
        
        // Check if class-dashboard-controller.php exists
        if (file_exists(VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/class-dashboard-controller.php')) {
            // Get the content of the file
            $file_content = file_get_contents(VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/class-dashboard-controller.php');
            
            // If the file content contains the register_admin_menu method without our filter
            if (strpos($file_content, 'function register_admin_menu') !== false 
                && strpos($file_content, 'vandel_should_register_menu') === false) {
                
                // Add our check at the very beginning of the register_admin_menu method
                // Only do this once
                if (!get_option('vandel_dashboard_controller_patched', false)) {
                    $modified_content = preg_replace(
                        '/(public\s+function\s+register_admin_menu\s*\(\s*\)\s*\{)/',
                        '$1' . PHP_EOL . '        // Check if we should register this menu' . PHP_EOL .
                        '        if (!apply_filters(\'vandel_should_register_menu\', true)) {' . PHP_EOL .
                        '            return;' . PHP_EOL .
                        '        }',
                        $file_content
                    );
                    
                    // Only write to the file if we made changes
                    if ($modified_content !== $file_content) {
                        file_put_contents(VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/class-dashboard-controller.php', $modified_content);
                        update_option('vandel_dashboard_controller_patched', true);
                    }
                }
            }
        }
    }, 5); // Run this before admin menu is loaded
}, 5);

// Initialize AJAX Handler
function vandel_init_ajax_handler() {
    $ajax_handler_file = VANDEL_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
    if (file_exists($ajax_handler_file)) {
        require_once $ajax_handler_file;
        if (class_exists('VandelBooking\\Ajax\\AjaxHandler')) {
            new VandelBooking\Ajax\AjaxHandler();
        }
    }
}
add_action('init', 'vandel_init_ajax_handler');

// Initialize the shortcode
function vandel_register_booking_shortcode() {
    if (class_exists('VandelBooking\\BookingShortcodeRegister')) {
        new VandelBooking\BookingShortcodeRegister();
    }
}
add_action('init', 'vandel_register_booking_shortcode');

// Initialize ZipCodeAjaxHandler
function vandel_init_zip_code_ajax_handler() {
    if (file_exists(VANDEL_PLUGIN_DIR . 'includes/admin/class-zip-code-ajax-handler.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/admin/class-zip-code-ajax-handler.php';
        if (class_exists('VandelBooking\\Admin\\ZipCodeAjaxHandler')) {
            new VandelBooking\Admin\ZipCodeAjaxHandler();
        }
    }
}
add_action('init', 'vandel_init_zip_code_ajax_handler');

// Initialize LocationAjaxHandler
function vandel_init_location_ajax_handler() {
    if (file_exists(VANDEL_PLUGIN_DIR . 'includes/admin/class-location-ajax-handler.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/admin/class-location-ajax-handler.php';
        if (class_exists('VandelBooking\\Admin\\LocationAjaxHandler')) {
            new VandelBooking\Admin\LocationAjaxHandler();
        }
    }
}
add_action('init', 'vandel_init_location_ajax_handler');






// Initialize CalendarView for admin
function vandel_init_calendar_view() {
    if (is_admin() && file_exists(VANDEL_PLUGIN_DIR . 'includes/admin/class-calendar-view.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/admin/class-calendar-view.php';
        if (class_exists('VandelBooking\\Admin\\CalendarView')) {
            $calendar_view = new VandelBooking\Admin\CalendarView();
            if (method_exists($calendar_view, 'registerAjaxHandlers')) {
                $calendar_view->registerAjaxHandlers();
            }
            
            // Modify the dashboard to include calendar view
            add_filter('vandel_dashboard_tabs', function($tabs) {
                $tabs['calendar'] = __('Calendar', 'vandel-booking');
                return $tabs;
            });
            
            // Render calendar view
            add_action('vandel_dashboard_tab_calendar', function() use ($calendar_view) {
                if (method_exists($calendar_view, 'render')) {
                    $calendar_view->render();
                }
            });
        }
    }
}
add_action('plugins_loaded', 'vandel_init_calendar_view');

// Initialize the plugin
function vandel_booking_init() {
    if (file_exists(VANDEL_PLUGIN_DIR . 'includes/class-plugin.php')) {
        require_once VANDEL_PLUGIN_DIR . 'includes/class-plugin.php';
        if (class_exists('\\VandelBooking\\Plugin') && method_exists('\\VandelBooking\\Plugin', 'getInstance')) {
            $GLOBALS['vandel_booking'] = \VandelBooking\Plugin::getInstance();
            return $GLOBALS['vandel_booking'];
        }
    }

    // If Plugin class isn't available, return null
    return null;
}

// Start the plugin
$GLOBALS['vandel_booking'] = vandel_booking_init();

// Update client statistics after booking creation
function vandel_update_client_stats_after_booking($booking_id, $booking_data) {
    if (!isset($booking_data['client_id']) || !class_exists('\\VandelBooking\\Client\\ClientModel')) {
        return;
    }
    
    $client_model = new \VandelBooking\Client\ClientModel();
    if (method_exists($client_model, 'addBooking')) {
        $client_model->addBooking(
            $booking_data['client_id'],
            isset($booking_data['total_price']) ? $booking_data['total_price'] : 0,
            isset($booking_data['booking_date']) ? $booking_data['booking_date'] : null
        );
    }
}
add_action('vandel_booking_created', 'vandel_update_client_stats_after_booking', 10, 2);

// Update client statistics after booking update
function vandel_update_client_stats_after_status_change($booking_id, $old_status, $new_status) {
    if (!class_exists('\\VandelBooking\\Client\\ClientModel') || !class_exists('\\VandelBooking\\Booking\\BookingModel')) {
        return;
    }
    
    // Only proceed if status changes between completed and other statuses
    $affects_total = ($old_status === 'completed' || $new_status === 'completed');
    if (!$affects_total) {
        return;
    }
    
    // Get booking details
    $booking_model = new \VandelBooking\Booking\BookingModel();
    if (!method_exists($booking_model, 'get')) {
        return;
    }
    
    $booking = $booking_model->get($booking_id);
    
    if (!$booking || !isset($booking->client_id) || $booking->client_id <= 0) {
        return;
    }
    
    // Update client statistics
    $client_model = new \VandelBooking\Client\ClientModel();
    if (method_exists($client_model, 'recalculateStats')) {
        $client_model->recalculateStats($booking->client_id);
    }
}
add_action('vandel_booking_status_changed', 'vandel_update_client_stats_after_status_change', 10, 3);

// Register client import handler
function vandel_register_client_import_handler() {
    add_action('admin_post_vandel_import_clients', function() {
        // Check if Dashboard class exists and can handle the import
        if (class_exists('\\VandelBooking\\Admin\\Dashboard')) {
            $dashboard = new \VandelBooking\Admin\Dashboard();
            if (method_exists($dashboard, 'handleClientImport')) {
                $dashboard->handleClientImport();
                return;
            }
        }
        
        // Fallback if Dashboard class can't handle it
        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=clients&action=import&error=handler'));
        exit;
    });
}
add_action('init', 'vandel_register_client_import_handler');

// Enqueue client management scripts and styles
function vandel_enqueue_client_management_assets($hook) {
    // Only load on our plugin pages
    if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
        return;
    }
    
    // Get current tab
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    
    // Only load on client-related pages
    if ($tab === 'clients' || $tab === 'client-details') {
        // Check if files exist before enqueueing
        $css_file = VANDEL_PLUGIN_URL . 'assets/css/client-management.css';
        $js_file = VANDEL_PLUGIN_URL . 'assets/js/admin/client-management.js';
        
        // Enqueue styles
        wp_enqueue_style(
            'vandel-client-management',
            $css_file,
            [],
            VANDEL_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'vandel-client-management',
            $js_file,
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vandel-client-management', 'vandelClientAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_client_admin'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this client?', 'vandel-booking'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected clients?', 'vandel-booking'),
                'selectClientAndAction' => __('Please select both clients and an action to perform.', 'vandel-booking'),
                'fillRequired' => __('Please fill all required fields.', 'vandel-booking'),
                'recalculate' => __('Recalculate Stats', 'vandel-booking'),
                'recalculating' => __('Recalculating...', 'vandel-booking'),
                'recalculateError' => __('Failed to recalculate statistics.', 'vandel-booking')
            ]
        ]);
    }
}
add_action('admin_enqueue_scripts', 'vandel_enqueue_client_management_assets');
/**
 * Enqueue modernized dashboard styles
 */
function vandel_enqueue_dashboard_styles($hook) {
    // Only load on our plugin pages
    if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
        return;
    }
    
    wp_enqueue_style(
        'vandel-modern-dashboard',
        VANDEL_PLUGIN_URL . 'assets/css/admin-dashboard.css',
        [],
        VANDEL_VERSION
    );
}
add_action('admin_enqueue_scripts', 'vandel_enqueue_dashboard_styles');

// Add admin notice for newly added features
function vandel_admin_notices() {
    // Only show on plugin pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->base, 'vandel-dashboard') === false) {
        return;
    }
    
    // Check if the notice has been dismissed
    if (get_option('vandel_calendar_notice_dismissed', false)) {
        return;
    }
    
    ?>
<div class="notice notice-info is-dismissible vandel-feature-notice">
    <p>
        <?php _e('<strong>New Feature:</strong> The Vandel Booking Calendar is now available! Navigate to the Calendar tab to view and manage your bookings in a calendar view.', 'vandel-booking'); ?>
    </p>
    <p>
        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" class="button">
            <?php _e('View Calendar', 'vandel-booking'); ?>
        </a>
        <a href="#" class="dismiss-notice" style="margin-left: 10px;">
            <?php _e('Dismiss', 'vandel-booking'); ?>
        </a>
    </p>
</div>
<script>
jQuery(document).ready(function($) {
    $('.vandel-feature-notice .dismiss-notice').on('click', function(e) {
        e.preventDefault();

        // Hide the notice
        $(this).closest('.notice').fadeOut();

        // Save the dismissal to the server
        $.post(ajaxurl, {
            action: 'vandel_dismiss_notice',
            notice: 'calendar',
            nonce: '<?php echo wp_create_nonce('vandel_dismiss_notice'); ?>'
        });
    });
});
</script>
<?php
}
add_action('admin_notices', 'vandel_admin_notices');

// Handle notice dismissal
function vandel_dismiss_notice() {
    // Verify nonce
    check_ajax_referer('vandel_dismiss_notice', 'nonce');
    
    // Get notice type
    $notice = isset($_POST['notice']) ? sanitize_key($_POST['notice']) : '';
    
    // Save dismissal status
    if ($notice === 'calendar') {
        update_option('vandel_calendar_notice_dismissed', true);
    }
    
    wp_send_json_success();
}
add_action('wp_ajax_vandel_dismiss_notice', 'vandel_dismiss_notice');



/**
 * Display suggested PHP extensions for optimal performance
 */
function vandel_suggest_extensions() {
    // Only display on plugin settings page
    $screen = get_current_screen();
    if (!$screen || !isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || !isset($_GET['tab']) || $_GET['tab'] !== 'settings') {
        return;
    }
    
    // Extensions to check
    $extensions = [
        'zip' => __('Required for ZIP code import/export functionality', 'vandel-booking'),
        'calendar' => __('Improves date handling for booking calendar', 'vandel-booking'),
        'intl' => __('Enhances internationalization features', 'vandel-booking')
    ];
    
    $missing_extensions = [];
    foreach ($extensions as $ext => $desc) {
        if (!extension_loaded($ext)) {
            $missing_extensions[$ext] = $desc;
        }
    }
    
    if (empty($missing_extensions)) {
        return;
    }
    
    ?>
<div class="notice notice-warning">
    <p><?php _e('For optimal performance of Vandel Booking, we recommend installing the following PHP extensions:', 'vandel-booking'); ?>
    </p>
    <ul style="list-style-type: disc; padding-left: 20px;">
        <?php foreach ($missing_extensions as $ext => $desc): ?>
        <li><strong><?php echo $ext; ?></strong>: <?php echo $desc; ?></li>
        <?php endforeach; ?>
    </ul>
    <p><?php _e('Please contact your hosting provider for assistance with installing these extensions.', 'vandel-booking'); ?>
    </p>
</div>
<?php
}
add_action('admin_notices', 'vandel_suggest_extensions');



// Add this to the beginning of vandel-cleaning-booking.php
if (isset($_POST['vandel_add_zip_code'])) {
    // Write to a separate log file to ensure we see the output
    file_put_contents(
        WP_CONTENT_DIR . '/zip-code-debug.log',
        date('[Y-m-d H:i:s] ') . 'Form submitted: ' . print_r($_POST, true) . "\n",
        FILE_APPEND
    );
}



add_action('admin_init', function() {
    // Only run on the settings page with zip-codes section
    if (isset($_GET['page']) && $_GET['page'] === 'vandel-dashboard' && 
        isset($_GET['tab']) && $_GET['tab'] === 'settings' &&
        isset($_GET['section']) && $_GET['section'] === 'zip-codes') {
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_zip_codes';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        file_put_contents(
            WP_CONTENT_DIR . '/zip-code-debug.log',
            date('[Y-m-d H:i:s] ') . 'ZIP code table exists: ' . ($table_exists ? 'Yes' : 'No') . "\n",
            FILE_APPEND
        );
        
        if ($table_exists) {
            // Check table structure
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            file_put_contents(
                WP_CONTENT_DIR . '/zip-code-debug.log',
                date('[Y-m-d H:i:s] ') . 'Table columns: ' . print_r($columns, true) . "\n",
                FILE_APPEND
            );
        }
    }
});

// From Workspace

// Load Database Installer first to ensure tables exist
if (file_exists(VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php')) {
    require_once VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php';
    
    // Create/update tables on activation
    register_activation_hook(__FILE__, function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
        }
        
        // Initialize the location data with Sweden locations
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $location_model = new \VandelBooking\Location\LocationModel();
            
            // Check if the model has the initializeSweden method
            if (method_exists($location_model, 'initializeSweden')) {
                $location_model->initializeSweden();
            }
        }
    });
    
    // Check for database updates on plugin load
    add_action('plugins_loaded', function() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            if (method_exists($installer, 'needsUpdate') && $installer->needsUpdate()) {
                $installer->update();
            }
        }
    });
}

// Enqueue scripts and styles for the admin area
wp_enqueue_script(
    'vandel-zip-code-admin',
    VANDEL_PLUGIN_URL . 'assets/js/admin/location-admin.js',
    ['jquery'],
    VANDEL_VERSION,
    true
);
// Enqueue location-importer.js
wp_enqueue_script(
    'vandel-location-importer',
    VANDEL_PLUGIN_URL . 'assets/js/admin/location-importer.js',
    ['jquery'],
    VANDEL_VERSION,
    true
);