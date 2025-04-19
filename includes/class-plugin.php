<?php
namespace VandelBooking;

/**
 * Main Plugin Loader
 * Handles initialization and bootstrapping of all plugin components
 */
class Plugin {
    /**
     * Plugin instance
     * @var Plugin
     */
    private static $instance = null;
    
    /**
     * Loaded components tracking
     * @var array
     */
    private $loaded_components = [];
    
    /**
     * Get plugin instance (singleton)
     * @return Plugin
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Only define constants if not already defined
        $this->defineConstants();
        $this->loadEssentialFiles();
        $this->initHooks();
    }
    
    /**
     * Define plugin constants if not already defined
     */
    private function defineConstants() {
        // These constants are already defined in the main plugin file,
        // so we don't need to redefine them here
    }
    
    /**
     * Load essential files before autoloading
     */
    private function loadEssentialFiles() {
        // Create required directories
        $this->createRequiredDirectories();
        
        // Include essential files if they haven't been loaded already
        $essential_files = [
            'includes/booking/class-booking-model.php',
            'includes/client/class-client-model.php',
            'includes/booking/class-booking-note-model.php',
            'includes/booking/class-booking-manager.php',
            'includes/location/class-zip-code-model.php'
        ];
        
        foreach ($essential_files as $file) {
            $file_path = VANDEL_PLUGIN_DIR . $file;
            $class_name = basename($file, '.php');
            
            // Only load if file exists and component not already loaded
            if (file_exists($file_path) && !in_array($class_name, $this->loaded_components)) {
                require_once $file_path;
                $this->loaded_components[] = $class_name;
            }
        }
    }
    
    /**
     * Create required directories
     */
    private function createRequiredDirectories() {
        $directories = [
            VANDEL_PLUGIN_DIR . 'includes/client',
            VANDEL_PLUGIN_DIR . 'includes/ajax',
            VANDEL_PLUGIN_DIR . 'includes/booking',
            VANDEL_PLUGIN_DIR . 'includes/location',
            VANDEL_PLUGIN_DIR . 'includes/frontend',
            VANDEL_PLUGIN_DIR . 'includes/admin',
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // These hooks are for plugin lifecycle events
        register_deactivation_hook(VANDEL_PLUGIN_DIR . 'vandel-cleaning-booking.php', [$this, 'deactivate']);
        
        // Add actions to load components
        add_action('plugins_loaded', [$this, 'loadComponents'], 10); // Lower priority than initial load
        add_action('init', [$this, 'initAjaxHandler']);
        
        // Add hook to ensure dashboard tabs are properly registered
        add_action('admin_menu', [$this, 'ensureDashboardTabs'], 999);
    }
    
    /**
     * Ensure all dashboard tabs (submenu pages) are registered
     */
    public function ensureDashboardTabs() {
        // Only run if we're in admin
        if (!is_admin()) {
            return;
        }
        
        global $submenu;
        
        // Check if our main menu exists and has submenus
        if (!isset($submenu['vandel-dashboard']) || empty($submenu['vandel-dashboard'])) {
            // If no submenu exists, let's add some default tabs
            $this->registerDefaultDashboardTabs();
        }
    }
    
    /**
     * Register default dashboard tabs as submenu items
     */
    private function registerDefaultDashboardTabs() {
        // Only register if main menu exists
        global $menu;
        $main_menu_exists = false;
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'vandel-dashboard') {
                $main_menu_exists = true;
                break;
            }
        }
        
        if (!$main_menu_exists) {
            // Register the main menu if it doesn't exist
            add_menu_page(
                __('Vandel Dashboard', 'vandel-booking'),
                __('Vandel Booking', 'vandel-booking'),
                'manage_options',
                'vandel-dashboard',
                function() {
                    // Determine which tab to load
                    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
                    do_action('vandel_dashboard_render_tab', $tab);
                },
                'dashicons-calendar-alt',
                26
            );
        }
        
        // Register default submenu items
        add_submenu_page(
            'vandel-dashboard', 
            __('Dashboard', 'vandel-booking'), 
            __('Overview', 'vandel-booking'), 
            'manage_options', 
            'vandel-dashboard', 
            function() {
                do_action('vandel_dashboard_render_tab', 'overview');
            }
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Bookings', 'vandel-booking'), 
            __('Bookings', 'vandel-booking'), 
            'manage_options', 
            'admin.php?page=vandel-dashboard&tab=bookings', 
            ''
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Clients', 'vandel-booking'), 
            __('Clients', 'vandel-booking'), 
            'manage_options', 
            'admin.php?page=vandel-dashboard&tab=clients', 
            ''
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Analytics', 'vandel-booking'), 
            __('Analytics', 'vandel-booking'), 
            'manage_options', 
            'admin.php?page=vandel-dashboard&tab=analytics', 
            ''
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Calendar', 'vandel-booking'), 
            __('Calendar', 'vandel-booking'), 
            'manage_options', 
            'admin.php?page=vandel-dashboard&tab=calendar', 
            ''
        );
        
        add_submenu_page(
            'vandel-dashboard', 
            __('Settings', 'vandel-booking'), 
            __('Settings', 'vandel-booking'), 
            'manage_options', 
            'admin.php?page=vandel-dashboard&tab=settings', 
            ''
        );
    }
    
    /**
     * Plugin activation
     * Note: Already handled in main plugin file
     */
    public function activate() {
        // Force database tables creation
        $this->createDatabaseTables();
        
        // Initialize settings
        if (class_exists('\\VandelBooking\\Admin\\SettingsInitializer')) {
            \VandelBooking\Admin\SettingsInitializer::initializeSettings();
        }
        
        // Create essential content
        $this->createEssentialContent();
        
        // Flush rewrite rules for custom post types
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function createDatabaseTables() {
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
            $this->loaded_components[] = 'DatabaseInstaller';
        } else {
            // Try to include and instantiate the installer
            $installer_file = VANDEL_PLUGIN_DIR . 'includes/database/class-installer.php';
            if (file_exists($installer_file)) {
                require_once $installer_file;
                if (class_exists('\\VandelBooking\\Database\\Installer')) {
                    $installer = new \VandelBooking\Database\Installer();
                    $installer->install();
                    $this->loaded_components[] = 'DatabaseInstaller';
                }
            }
        }
    }
    
    /**
     * Create essential content (services, etc.)
     */
    private function createEssentialContent() {
        // Create default service if none exist
        $services = get_posts([
            'post_type' => 'vandel_service',
            'numberposts' => 1
        ]);
        
        if (empty($services)) {
            // Create a default service
            $service_id = wp_insert_post([
                'post_title' => __('Standard Cleaning', 'vandel-booking'),
                'post_status' => 'publish',
                'post_type' => 'vandel_service'
            ]);
            
            if ($service_id && !is_wp_error($service_id)) {
                // Add service metadata
                update_post_meta($service_id, '_vandel_service_subtitle', __('Basic home cleaning service', 'vandel-booking'));
                update_post_meta($service_id, '_vandel_service_description', __('Our standard cleaning service includes dusting, vacuuming, and mopping of all accessible areas.', 'vandel-booking'));
                update_post_meta($service_id, '_vandel_service_base_price', '75');
                update_post_meta($service_id, '_vandel_service_duration', '120');
                update_post_meta($service_id, '_vandel_service_is_popular', 'yes');
                update_post_meta($service_id, '_vandel_service_active', 'yes');
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize AJAX Handler
     */
    public function initAjaxHandler() {
        // Only initialize if not already loaded
        if (in_array('AjaxHandler', $this->loaded_components)) {
            return;
        }
        
        // Initialize AJAX Handler
        if (class_exists('\\VandelBooking\\Ajax\\AjaxHandler')) {
            new \VandelBooking\Ajax\AjaxHandler();
            $this->loaded_components[] = 'AjaxHandler';
        } else {
            // Try to include and instantiate the AJAX handler
            $ajax_handler_file = VANDEL_PLUGIN_DIR . 'includes/ajax/class-ajax-handler.php';
            if (file_exists($ajax_handler_file)) {
                require_once $ajax_handler_file;
                if (class_exists('\\VandelBooking\\Ajax\\AjaxHandler')) {
                    new \VandelBooking\Ajax\AjaxHandler();
                    $this->loaded_components[] = 'AjaxHandler';
                }
            }
        }
    }
    
    /**
     * Load plugin components
     */
    public function loadComponents() {
        $loaded_components = [];
        
        // Load admin components if in admin area
        if (is_admin()) {
            $loaded_components = array_merge($loaded_components, $this->loadAdminComponents());
        }
        
        // Load frontend components
        $loaded_components = array_merge($loaded_components, $this->loadFrontendComponents());
        
        // Load custom post types
        $loaded_components = array_merge($loaded_components, $this->loadPostTypes());
        
        // Load API components
        $loaded_components = array_merge($loaded_components, $this->loadApiComponents());
        
        // Load assets
        if (class_exists('\\VandelBooking\\Assets') && !in_array('Assets', $this->loaded_components)) {
            new \VandelBooking\Assets();
            $loaded_components[] = 'Assets';
        }
        
        // Add a debug action for development use
        do_action('vandel_booking_components_loaded', $loaded_components);
        
        // Store loaded components
        $this->loaded_components = array_unique(array_merge($this->loaded_components, $loaded_components));
    }
    
    /**
     * Load admin components
     * 
     * @return array Loaded components
     */
    private function loadAdminComponents() {
        $loaded_components = [];
        
        // IMPORTANT: Choose one primary dashboard controller
        // We'll prefer Dashboard_Controller since it has the tab structure
        if (class_exists('\\VandelBooking\\Admin\\Dashboard_Controller')) {
            new \VandelBooking\Admin\Dashboard_Controller();
            $loaded_components[] = 'Dashboard_Controller';
        } 
        elseif (class_exists('\\VandelBooking\\Admin\\AdminLoader')) {
            new \VandelBooking\Admin\AdminLoader();
            $loaded_components[] = 'AdminLoader';
        }
        else {
            // Try to load Dashboard_Controller
            $controller_file = VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/class-dashboard-controller.php';
            if (file_exists($controller_file)) {
                require_once $controller_file;
                if (class_exists('\\VandelBooking\\Admin\\Dashboard_Controller')) {
                    new \VandelBooking\Admin\Dashboard_Controller();
                    $loaded_components[] = 'Dashboard_Controller';
                }
            } 
            else {
                // Try to load AdminLoader as fallback
                $admin_loader_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php';
                if (file_exists($admin_loader_file)) {
                    require_once $admin_loader_file;
                    if (class_exists('\\VandelBooking\\Admin\\AdminLoader')) {
                        new \VandelBooking\Admin\AdminLoader();
                        $loaded_components[] = 'AdminLoader';
                    }
                }
            }
        }
        
        // Load individual dashboard tab classes
        $this->loadDashboardTabs();
        
        // Load ZIP Code components if feature is enabled
        if (get_option('vandel_enable_zip_code_feature', 'no') === 'yes') {
            // Load ZIP Code Handler if exists
            if (class_exists('\\VandelBooking\\Admin\\ZipCodeAjaxHandler')) {
                new \VandelBooking\Admin\ZipCodeAjaxHandler();
                $loaded_components[] = 'ZipCodeAjaxHandler';
            }
        }
        
        // Check for Dashboard Controller (new structure)
        if (!in_array('Dashboard_Controller', $this->loaded_components)) {
            if (class_exists('\\VandelBooking\\Admin\\Dashboard_Controller')) {
                new \VandelBooking\Admin\Dashboard_Controller();
                $loaded_components[] = 'Dashboard_Controller';
            } else {
                // Try to include and instantiate the controller
                $controller_file = VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/class-dashboard-controller.php';
                if (file_exists($controller_file)) {
                    require_once $controller_file;
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard_Controller')) {
                        new \VandelBooking\Admin\Dashboard_Controller();
                        $loaded_components[] = 'Dashboard_Controller';
                    }
                }
            }
        }
        
        return $loaded_components;
    }
    
    /**
     * Load dashboard tab components
     */
    private function loadDashboardTabs() {
        // Try to load all possible dashboard tab classes
        $tab_files = [
            'admin/dashboard/class-tab-interface.php',
            'admin/dashboard/class-overview-tab.php',
            'admin/dashboard/class-bookings-tab.php',
            'admin/dashboard/class-clients-tab.php',
            'admin/dashboard/class-analytics-tab.php',
            'admin/dashboard/class-calendar-tab.php',
            'admin/dashboard/class-settings-tab.php',
        ];
        
        foreach ($tab_files as $file) {
            $file_path = VANDEL_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Initialize tab handler to render content based on current tab
        add_action('vandel_dashboard_render_tab', function($tab) {
            switch ($tab) {
                case 'overview':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Overview_Tab')) {
                        $overview_tab = new \VandelBooking\Admin\Dashboard\Overview_Tab();
                        $overview_tab->render();
                    }
                    break;
                case 'bookings':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Bookings_Tab')) {
                        $bookings_tab = new \VandelBooking\Admin\Dashboard\Bookings_Tab();
                        $bookings_tab->render();
                    }
                    break;
                case 'clients':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Clients_Tab')) {
                        $clients_tab = new \VandelBooking\Admin\Dashboard\Clients_Tab();
                        $clients_tab->render();
                    }
                    break;
                case 'analytics':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Analytics_Tab')) {
                        $analytics_tab = new \VandelBooking\Admin\Dashboard\Analytics_Tab();
                        $analytics_tab->render();
                    }
                    break;
                case 'calendar':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Calendar_Tab')) {
                        $calendar_tab = new \VandelBooking\Admin\Dashboard\Calendar_Tab();
                        $calendar_tab->render();
                    }
                    break;
                case 'settings':
                    if (class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings_Tab')) {
                        $settings_tab = new \VandelBooking\Admin\Dashboard\Settings_Tab();
                        $settings_tab->render();
                    }
                    break;
            }
        });
    }
    
    /**
     * Load frontend components
     * 
     * @return array Loaded components
     */
    private function loadFrontendComponents() {
        $loaded_components = [];
        
        // Only load if not already loaded
        if (!in_array('FrontendLoader', $this->loaded_components)) {
            // Load FrontendLoader
            if (class_exists('\\VandelBooking\\Frontend\\FrontendLoader')) {
                new \VandelBooking\Frontend\FrontendLoader();
                $loaded_components[] = 'FrontendLoader';
            }
        }
        
        // Register booking form shortcode if not already loaded
        if (!in_array('BookingShortcodeRegister', $this->loaded_components)) {
            if (!function_exists('vandel_register_booking_shortcode')) {
                if (class_exists('\\VandelBooking\\BookingShortcodeRegister')) {
                    new \VandelBooking\BookingShortcodeRegister();
                    $loaded_components[] = 'BookingShortcodeRegister';
                } else {
                    // Try to include and instantiate BookingShortcodeRegister
                    $shortcode_file = VANDEL_PLUGIN_DIR . 'includes/class-booking-shortcode-register.php';
                    if (file_exists($shortcode_file)) {
                        require_once $shortcode_file;
                        if (class_exists('\\VandelBooking\\BookingShortcodeRegister')) {
                            new \VandelBooking\BookingShortcodeRegister();
                            $loaded_components[] = 'BookingShortcodeRegister';
                        }
                    }
                }
            }
        }
        


        // Load location selection component
        if (class_exists('\\VandelBooking\\Frontend\\LocationSelection')) {
            new \VandelBooking\Frontend\LocationSelection();
            $loaded_components[] = 'LocationSelection';
        }
        
        // Load client dashboard component
        if (class_exists('\\VandelBooking\\Frontend\\ClientDashboard')) {
            new \VandelBooking\Frontend\ClientDashboard();
            $loaded_components[] = 'ClientDashboard';
        }
        


        return $loaded_components;
    }
    

/**
 * Load booking components
 * 
 * @return array Loaded components
 */
private function loadBookingComponents() {
    $loaded_components = [];
    
    // Load booking workflow component
    if (class_exists('\\VandelBooking\\Booking\\BookingWorkflow')) {
        new \VandelBooking\Booking\BookingWorkflow();
        $loaded_components[] = 'BookingWorkflow';
    }
    
    return $loaded_components;
}


/**
 * Enqueue frontend assets
 */
public function enqueueAssets() {
    // ... existing code ...
    
    // Enqueue location selection script
    wp_enqueue_script(
        'vandel-location-selection',
        VANDEL_PLUGIN_URL . 'assets/js/location-selection.js',
        ['jquery'],
        VANDEL_VERSION,
        true
    );
    
    // Enqueue client dashboard assets
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vandel_client_dashboard')) {
        wp_enqueue_style(
            'vandel-client-dashboard',
            VANDEL_PLUGIN_URL . 'assets/css/client-dashboard.css',
            [],
            VANDEL_VERSION
        );
        
        wp_enqueue_script(
            'vandel-client-dashboard',
            VANDEL_PLUGIN_URL . 'assets/js/client-dashboard.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
    }
}
    /**
     * Load custom post types
     * 
     * @return array Loaded components
     */
    private function loadPostTypes() {
        $loaded_components = [];
        
        // Only proceed if post types not already loaded
        if (in_array('PostTypesRegistry', $this->loaded_components) ||
            (in_array('ServicePostType', $this->loaded_components) && 
             in_array('SubServicePostType', $this->loaded_components))) {
            return $loaded_components;
        }
        
        // Try to load Registry first
        if (class_exists('\\VandelBooking\\PostTypes\\Registry')) {
            new \VandelBooking\PostTypes\Registry();
            $loaded_components[] = 'PostTypesRegistry';
            return $loaded_components; // Registry will take care of loading post types
        }
        
        // Fallback to loading individual post types
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
            $loaded_components[] = 'ServicePostType';
        }
        
        if (class_exists('\\VandelBooking\\PostTypes\\SubServicePostType')) {
            new \VandelBooking\PostTypes\SubServicePostType();
            $loaded_components[] = 'SubServicePostType';
        }
        
        return $loaded_components;
    }
    
    /**
     * Load API components
     * 
     * @return array Loaded components
     */
    private function loadApiComponents() {
        $loaded_components = [];
        
        // Only load if not already loaded
        if (!in_array('APILoader', $this->loaded_components)) {
            // Load APILoader
            if (class_exists('\\VandelBooking\\API\\APILoader')) {
                new \VandelBooking\API\APILoader();
                $loaded_components[] = 'APILoader';
            }
        }
        
        // Load ZIP Code API if feature is enabled and not already loaded
        if (get_option('vandel_enable_zip_code_feature', 'no') === 'yes' && 
            !in_array('ZipCodeAPI', $this->loaded_components)) {
            if (class_exists('\\VandelBooking\\API\\ZipCodeAPI')) {
                new \VandelBooking\API\ZipCodeAPI();
                $loaded_components[] = 'ZipCodeAPI';
            }
        }
        
        return $loaded_components;
    }
    
    /**
     * Check if a component is loaded
     * 
     * @param string $component Component name
     * @return bool Whether the component is loaded
     */
    public function isComponentLoaded($component) {
        return in_array($component, $this->loaded_components);
    }
    
    /**
     * Get loaded components
     * 
     * @return array Loaded components
     */
    public function getLoadedComponents() {
        return $this->loaded_components;
    }


}