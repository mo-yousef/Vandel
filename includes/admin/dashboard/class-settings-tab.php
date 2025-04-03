<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Settings Tab
 * Handles the settings management tab
 */
class Settings_Tab implements Tab_Interface {
    /**
     * Section classes
     *
     * @var array
     */
    private $section_classes = [];
    
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // No specific hooks for settings tab
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Process section-specific actions
        $this->initialize_section_classes();
        
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        if (isset($this->section_classes[$active_section]) && method_exists($this->section_classes[$active_section], 'process_actions')) {
            $this->section_classes[$active_section]->process_actions();
        }
    }
    
    /**
     * Initialize section classes
     */
    private function initialize_section_classes() {
        // Check if sections already initialized
        if (!empty($this->section_classes)) {
            return;
        }
        
        // Load section files
        $section_files = [
            'class-general-settings.php',
            'class-booking-settings.php',
            'class-notification-settings.php',
            'class-integration-settings.php',
            'class-zip-code-settings.php',
        ];
        
        foreach ($section_files as $file) {
            $file_path = VANDEL_PLUGIN_DIR . 'includes/admin/dashboard/settings/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Initialize section classes if they exist
        $this->section_classes = [
            'general' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\General_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\General_Settings() : null,
                
            'booking' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Booking_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Booking_Settings() : null,
                
            'notifications' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Notification_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Notification_Settings() : null,
                
            'integrations' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\Integration_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\Integration_Settings() : null,
                
            'zip-codes' => class_exists('\\VandelBooking\\Admin\\Dashboard\\Settings\\ZipCode_Settings') ? 
                new \VandelBooking\Admin\Dashboard\Settings\ZipCode_Settings() : null,
        ];
    }
    
    /**
     * Render tab content
     */
    public function render() {
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        ?>
        <div id="settings" class="vandel-tab-content">
            <div class="vandel-settings-container">
                <!-- Settings Navigation -->
                <?php $this->render_settings_navigation($active_section); ?>

                <!-- Settings Content -->
                <div class="vandel-settings-content">
                    <?php
                    // Initialize section classes if not already done
                    $this->initialize_section_classes();
                    
                    // Render the active section
                    if (isset($this->section_classes[$active_section]) && $this->section_classes[$active_section]) {
                        $this->section_classes[$active_section]->render();
                    } else {
                        // Fallback to built-in section renderers
                        switch ($active_section) {
                            case 'general':
                                $this->render_general_settings();
                                break;
                            case 'booking':
                                $this->render_booking_settings();
                                break;
                            case 'notifications':
                                $this->render_notification_settings();
                                break;
                            case 'integrations':
                                $this->render_integration_settings();
                                break;
                            case 'zip-codes':
                                $this->render_zip_code_settings();
                                break;
                            default:
                                $this->render_general_settings();
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings navigation
     */
    private function render_settings_navigation($active_section) {
        ?>
        <div class="vandel-settings-nav">
            <ul>
                <li <?php echo $active_section === 'general' ? 'class="active"' : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=general')); ?>">
                        <span class="dashicons dashicons-admin-generic"></span> 
                        <?php _e('General', 'vandel-booking'); ?>
                    </a>
                </li>
                <li <?php echo $active_section === 'booking' ? 'class="active"' : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=booking')); ?>">
                        <span class="dashicons dashicons-calendar-alt"></span> 
                        <?php _e('Booking', 'vandel-booking'); ?>
                    </a>
                </li>
                <li <?php echo $active_section === 'notifications' ? 'class="active"' : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=notifications')); ?>">
                        <span class="dashicons dashicons-email-alt"></span> 
                        <?php _e('Notifications', 'vandel-booking'); ?>
                    </a>
                </li>
                <li <?php echo $active_section === 'integrations' ? 'class="active"' : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=integrations')); ?>">
                        <span class="dashicons dashicons-randomize"></span> 
                        <?php _e('Integrations', 'vandel-booking'); ?>
                    </a>
                </li>
                <li <?php echo $active_section === 'zip-codes' ? 'class="active"' : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes')); ?>">
                        <span class="dashicons dashicons-location-alt"></span> 
                        <?php _e('Service Areas', 'vandel-booking'); ?>
                    </a>
                </li>