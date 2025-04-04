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
        $this->defineConstants();
        $this->loadEssentialFiles();
        $this->initHooks();
    }
    
    /**
     * Define plugin constants if not already defined
     */
    private function defineConstants() {
        if (!defined('VANDEL_VERSION')) {
            define('VANDEL_VERSION', '1.0.2');
        }
        if (!defined('VANDEL_PLUGIN_DIR')) {
            define('VANDEL_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        }
        if (!defined('VANDEL_PLUGIN_URL')) {
            define('VANDEL_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        }
        if (!defined('VANDEL_PLUGIN_BASENAME')) {
            define('VANDEL_PLUGIN_BASENAME', plugin_basename(dirname(__FILE__) . '/vandel-cleaning-booking.php'));
        }
    }
    
    /**
     * Load essential files before autoloading
     */
    private function loadEssentialFiles() {
        // Include autoloader
        require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';
        
        // Create required directories
        $this->createRequiredDirectories();
        
        // Include essential files
        $essential_files = [
            'includes/class-helpers.php',
            'includes/client/class-client-model.php',
            'includes/booking/class-booking-model.php',
            'includes/booking/class-booking-manager.php',
            'includes/ajax/class-ajax-handler.php'
        ];
        
        foreach ($essential_files as $file) {
            $file_path = VANDEL_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_components[] = basename($file, '.php');
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
        register_activation_hook(VANDEL_PLUGIN_DIR . 'vandel-cleaning-booking.php', [$this, 'activate']);
        register_deactivation_hook(VANDEL_PLUGIN_DIR . 'vandel-cleaning-booking.php', [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'loadComponents']);
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
        if (class_exists('\\VandelBooking\\Assets')) {
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
        
        // Load FrontendLoader
        if (class_exists('\\VandelBooking\\Frontend\\FrontendLoader')) {
            new \VandelBooking\Frontend\FrontendLoader();
            $loaded_components[] = 'FrontendLoader';
        }
        
        // Register booking form shortcode
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
        
        return $loaded_components;
    }
    
    /**
     * Load custom post types
     * 
     * @return array Loaded components
     */
    private function loadPostTypes() {
        $loaded_components = [];
        
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
        
        // Load APILoader
        if (class_exists('\\VandelBooking\\API\\APILoader')) {
            new \VandelBooking\API\APILoader();
            $loaded_components[] = 'APILoader';
        }
        
        // Load ZIP Code API if feature is enabled
        if (get_option('vandel_enable_zip_code_feature', 'no') === 'yes') {
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