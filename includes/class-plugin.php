<?php
namespace VandelBooking;

/**
 * Main plugin class that bootstraps everything
 */
class Plugin {
    /**
     * Plugin instance
     * @var Plugin
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
        $this->includeFiles();
        $this->initHooks();
    }
    
    /**
     * Include required files
     */
    private function includeFiles() {
        // Include functions
        if (file_exists(VANDEL_PLUGIN_DIR . 'includes/functions.php')) {
            require_once VANDEL_PLUGIN_DIR . 'includes/functions.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        register_activation_hook(VANDEL_PLUGIN_DIR . 'vandel-cleaning-booking.php', [$this, 'activate']);
        register_deactivation_hook(VANDEL_PLUGIN_DIR . 'vandel-cleaning-booking.php', [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'loadComponents']);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Instantiate installer class if it exists
        if (class_exists('\\VandelBooking\\Database\\Installer')) {
            $installer = new \VandelBooking\Database\Installer();
            $installer->install();
        } else {
            // Log a message or create tables directly
            error_log('VandelBooking: Installer class not found');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup tasks
    }
    
    /**
     * Load plugin components
     */
    public function loadComponents() {
    if (is_admin()) {
        // Try direct include for AdminLoader
        $admin_loader_file = VANDEL_PLUGIN_DIR . 'includes/admin/class-admin-loader.php';
        if (file_exists($admin_loader_file)) {
            require_once $admin_loader_file;
            
            // Check if class exists after manual include
            if (class_exists('\\VandelBooking\\Admin\\AdminLoader')) {
                new \VandelBooking\Admin\AdminLoader();
                error_log('VandelBooking: AdminLoader loaded successfully');
            } else {
                error_log('VandelBooking: AdminLoader class not found after manual include');
            }
        } else {
            error_log('VandelBooking: AdminLoader file not found at: ' . $admin_loader_file);
        }
    }
    
    // Register post types
    $post_types_dir = VANDEL_PLUGIN_DIR . 'includes/post-types/';
    
    // Load ServicePostType
    $service_post_type_file = $post_types_dir . 'class-service-post-type.php';
    if (file_exists($service_post_type_file)) {
        require_once $service_post_type_file;
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
            error_log('VandelBooking: ServicePostType loaded successfully');
        } else {
            error_log('VandelBooking: ServicePostType class not found after manual include');
        }
    }
    
    // Load SubServicePostType
    $sub_service_post_type_file = $post_types_dir . 'class-sub-service-post-type.php';
    if (file_exists($sub_service_post_type_file)) {
        require_once $sub_service_post_type_file;
        if (class_exists('\\VandelBooking\\PostTypes\\SubServicePostType')) {
            new \VandelBooking\PostTypes\SubServicePostType();
            error_log('VandelBooking: SubServicePostType loaded successfully');
        }
    }
    
    // Initialize frontend if class exists
    if (class_exists('\\VandelBooking\\Frontend\\FrontendLoader')) {
        new \VandelBooking\Frontend\FrontendLoader();
    }
    
    // Initialize REST API if class exists
    if (class_exists('\\VandelBooking\\API\\APILoader')) {
        new \VandelBooking\API\APILoader();
    }
    

        // Initialize other components as needed, with class_exists checks

        
        // Register post types - ServicePostType
        if (class_exists('\\VandelBooking\\PostTypes\\ServicePostType')) {
            new \VandelBooking\PostTypes\ServicePostType();
            $loaded_components[] = 'ServicePostType';
        }
        
        // Register post types - SubServicePostType
        if (class_exists('\\VandelBooking\\PostTypes\\SubServicePostType')) {
            new \VandelBooking\PostTypes\SubServicePostType();
            $loaded_components[] = 'SubServicePostType';
        }
        
        // Register post types - Registry
        if (class_exists('\\VandelBooking\\PostTypes\\Registry')) {
            new \VandelBooking\PostTypes\Registry();
            $loaded_components[] = 'Registry';
        }

        // Initialize assets
        if (class_exists('\\VandelBooking\\Assets')) {
            new \VandelBooking\Assets();
            $loaded_components[] = 'Assets';
        }
        
        // Initialize booking system
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            new \VandelBooking\Booking\BookingManager();
            $loaded_components[] = 'BookingManager';
        }
        
        // Add a debug action for development use
        do_action('vandel_booking_components_loaded', $loaded_components);
    }
}