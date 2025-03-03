<?php
namespace VandelBooking;

/**
 * Main plugin class that bootstraps everything
 */
class Plugin {
    /**
     * Plugin version
     * @var string
     */
    const VERSION = '1.0.0';
    
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
        $this->defineConstants();
        $this->includeFiles();
        $this->initHooks();
    }
    
    /**
     * Define plugin constants
     */
    private function defineConstants() {
        define('VANDEL_VERSION', self::VERSION);
        define('VANDEL_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        define('VANDEL_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        define('VANDEL_PLUGIN_BASENAME', plugin_basename(dirname(__FILE__)));
    }
    
    /**
     * Include required files
     */
    private function includeFiles() {
        // Include autoloader
        require_once VANDEL_PLUGIN_DIR . 'includes/autoload.php';
        
        // Include functions
        require_once VANDEL_PLUGIN_DIR . 'includes/functions.php';
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
        // Instantiate installer class
        $installer = new Installer();
        $installer->install();
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
        // Initialize admin
        if (is_admin()) {
            new Admin\AdminLoader();
        }
        
        // Initialize frontend
        new Frontend\FrontendLoader();
        
        // Initialize REST API
        new API\APILoader();
        
        // Register post types
        new PostTypes\ServicePostType();
        new PostTypes\SubServicePostType();
        
        // Initialize assets
        new Assets();
        
        // Initialize booking system
        new Booking\BookingManager();
    }
}