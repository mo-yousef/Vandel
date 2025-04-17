<?php
namespace VandelBooking\Admin\Dashboard\Settings;

use VandelBooking\Location\LocationImporter;
use VandelBooking\Location\LocationModel;

/**
 * Location Importer Admin
 * Handles admin interface for importing location data from CSV files
 */
class LocationImporterAdmin {
    /**
     * @var LocationImporter Importer instance
     */
    private $importer;
    
    /**
     * @var LocationModel Location model instance
     */
    private $location_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize importer
        if (class_exists('\\VandelBooking\\Location\\LocationImporter')) {
            $this->importer = new LocationImporter();
        }
        
        // Initialize location model
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new LocationModel();
        }
        
        // Register hooks
        add_action('admin_init', [$this, 'process_actions']);
        add_action('wp_ajax_vandel_import_locations_from_csv', [$this, 'ajax_import_locations']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Register admin menu
     */
    public function register_submenu() {
        add_submenu_page(
            'vandel-dashboard',
            __('Location Import', 'vandel-booking'),
            __('Location Import', 'vandel-booking'),
            'manage_options',
            'vandel-location-import',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only load on settings page with locations section
        if (is_admin() && 
            isset($_GET['page']) && $_GET['page'] === 'vandel-dashboard' && 
            isset($_GET['tab']) && $_GET['tab'] === 'settings' &&
            isset($_GET['section']) && $_GET['section'] === 'locations') {
            
            wp_enqueue_script(
                'vandel-location-importer',
                VANDEL_PLUGIN_URL . 'assets/js/admin/location-importer.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
            
            wp_localize_script(
                'vandel-location-importer',
                'vandelLocationImporter',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vandel_location_importer_nonce'),
                    'strings' => [
                        'importing' => __('Importing...', 'vandel-booking'),
                        'importSuccess' => __('Import successful!', 'vandel-booking'),
                        'importError' => __('Import failed.', 'vandel-booking'),
                        'confirmImport' => __('Are you sure you want to import this file? This may take some time for large files.', 'vandel-booking')
                    ]
                ]
            );
        }
    }
    
    /**
     * Process admin actions
     */
    public function process_actions() {
        // Check if we have the necessary components
        if (!$this->importer || !$this->location_model) {
            return;
        }
        
        // Check if we're on the correct page and have necessary permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle CSV file upload
        if (isset($_POST['vandel_upload_location_csv']) && 
            isset($_POST['vandel_location_importer_nonce']) && 
            wp_verify_nonce($_POST['vandel_location_importer_nonce'], 'vandel_upload_location_csv')) {
            
            // Verify file upload
            if (!isset($_FILES['location_csv_file']) || 
                !isset($_FILES['location_csv_file']['tmp_name']) || 
                empty($_FILES['location_csv_file']['tmp_name'])) {
                add_settings_error(
                    'vandel_location_importer_messages', 
                    'vandel_csv_upload_error', 
                    __('No file was uploaded or the upload failed.', 'vandel-booking'), 
                    'error'
                );
                return;
            }
            
            // Get form data
            $country = sanitize_text_field($_POST['country']);
            $has_header = isset($_POST['has_header']) ? true : false;
            
            // Get column mappings
            $mapping = [
                'zip_code' => intval($_POST['column_zip_code']),
                'city' => intval($_POST['column_city']),
                'area' => intval($_POST['column_area']),
                'state' => intval($_POST['column_state'])
            ];
            
            // Save uploaded file
            $upload_dir = wp_upload_dir();
            $filename = sanitize_file_name(strtolower($country) . '.csv');
            $target_file = $upload_dir['basedir'] . '/vandel/locations/' . $filename;
            
            // Ensure directory exists
            $target_dir = dirname($target_file);
            if (!is_dir($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['location_csv_file']['tmp_name'], $target_file)) {
                // Start import process
                $results = $this->importer->importFromCsv($target_file, $country, $mapping, $has_header);
                
                // Display results
                add_settings_error(
                    'vandel_location_importer_messages', 
                    'vandel_csv_import_results', 
                    sprintf(
                        __('Import completed: %d imported, %d updated, %d failed.', 'vandel-booking'),
                        $results['imported'],
                        $results['updated'],
                        $results['failed']
                    ), 
                    $results['failed'] > 0 ? 'warning' : 'success'
                );
                
                // Log errors if any
                if (!empty($results['errors'])) {
                    error_log('Vandel Location Import Errors: ' . print_r($results['errors'], true));
                }
            } else {
                add_settings_error(
                    'vandel_location_importer_messages', 
                    'vandel_csv_upload_error', 
                    __('Failed to save uploaded file.', 'vandel-booking'), 
                    'error'
                );
            }
        }
        
        // Handle sample CSV generation
        if (isset($_POST['vandel_generate_sample_csv']) && 
            isset($_POST['vandel_location_importer_nonce']) && 
            wp_verify_nonce($_POST['vandel_location_importer_nonce'], 'vandel_generate_sample_csv')) {
            
            $country = sanitize_text_field($_POST['country']);
            
            // Generate sample file
            $sample_file = $this->importer->generateSampleCsv($country);
            
            if ($sample_file && file_exists($sample_file)) {
                // Provide download link
                $download_url = VANDEL_PLUGIN_URL . 'data/locations/' . basename($sample_file);
                
                add_settings_error(
                    'vandel_location_importer_messages', 
                    'vandel_sample_csv_generated', 
                    sprintf(
                        __('Sample CSV generated. <a href="%s" class="button button-small" download>Download Sample</a>', 'vandel-booking'),
                        esc_url($download_url)
                    ), 
                    'success'
                );
            } else {
                add_settings_error(
                    'vandel_location_importer_messages', 
                    'vandel_sample_csv_error', 
                    __('Failed to generate sample CSV file.', 'vandel-booking'), 
                    'error'
                );
            }
        }
    }
    
    /**
     * Handle AJAX import
     */
    public function ajax_import_locations() {
        // Verify nonce
        check_ajax_referer('vandel_location_importer_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'vandel-booking')]);
            return;
        }
        
        // Check if file exists
        $country = sanitize_text_field($_POST['country']);
        $file_path = sanitize_text_field($_POST['file_path']);
        
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => __('File not found.', 'vandel-booking')]);
            return;
        }
        
        // Get mapping data
        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : [];
        $has_header = isset($_POST['has_header']) ? (bool)$_POST['has_header'] : true;
        
        // Run import
        $results = $this->importer->importFromCsv($file_path, $country, $mapping, $has_header);
        
        // Return results
        wp_send_json_success([
            'message' => sprintf(
                __('Import completed: %d imported, %d updated, %d failed.', 'vandel-booking'),
                $results['imported'],
                $results['updated'],
                $results['failed']
            ),
            'results' => $results
        ]);
    }
    
    /**
     * Render admin interface
     */
    public function render() {
        // Display settings errors
        settings_errors('vandel_location_importer_messages');
        
        // Get available countries
        $available_countries = $this->importer ? $this->importer->getAvailableCountries() : [];
        
        // Get stats on existing locations
        $location_stats = $this->get_location_stats();
        
        ?>
        <div class="vandel-settings-section">
            <h2><?php _e('Location Data Management', 'vandel-booking'); ?></h2>
            
            <div class="vandel-settings-intro">
                <p><?php _e('Import and manage location data from CSV files. This allows you to add postal codes, cities, and areas for different countries.', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Current Location Data', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($location_stats)): ?>
                        <p><?php _e('No location data available. Use the import tool below to add locations.', 'vandel-booking'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Country', 'vandel-booking'); ?></th>
                                    <th><?php _e('Cities', 'vandel-booking'); ?></th>
                                    <th><?php _e('Locations', 'vandel-booking'); ?></th>
                                    <th><?php _e('Last Updated', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($location_stats as $country => $stats): ?>
                                    <tr>
                                        <td><?php echo esc_html($stats['name']); ?></td>
                                        <td><?php echo esc_html($stats['cities']); ?></td>
                                        <td><?php echo esc_html($stats['locations']); ?></td>
                                        <td><?php echo esc_html($stats['last_updated']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="vandel-grid-row">
                <div class="vandel-grid-col">
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Import Location Data', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <form method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('vandel_upload_location_csv', 'vandel_location_importer_nonce'); ?>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label for="country"><?php _e('Country', 'vandel-booking'); ?></label>
                                        <input type="text" id="country" name="country" required class="regular-text" 
                                               placeholder="<?php _e('Enter country name (e.g., Sweden)', 'vandel-booking'); ?>">
                                    </div>
                                </div>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label for="location_csv_file"><?php _e('CSV File', 'vandel-booking'); ?></label>
                                        <input type="file" id="location_csv_file" name="location_csv_file" required 
                                               accept=".csv">
                                        <p class="description">
                                            <?php _e('Upload a CSV file with location data. The file should contain postal codes, cities, and areas.', 'vandel-booking'); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label>
                                            <input type="checkbox" name="has_header" value="1" checked>
                                            <?php _e('File has header row', 'vandel-booking'); ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <h4><?php _e('Column Mapping', 'vandel-booking'); ?></h4>
                                        <p class="description">
                                            <?php _e('Specify which columns in your CSV file contain each piece of information. Column numbers start from 0.', 'vandel-booking'); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label for="column_zip_code"><?php _e('Postal Code Column', 'vandel-booking'); ?></label>
                                        <input type="number" id="column_zip_code" name="column_zip_code" value="0" min="0" max="20" required>
                                    </div>
                                    <div class="vandel-col">
                                        <label for="column_city"><?php _e('City Column', 'vandel-booking'); ?></label>
                                        <input type="number" id="column_city" name="column_city" value="1" min="0" max="20" required>
                                    </div>
                                </div>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label for="column_area"><?php _e('Area/District Column', 'vandel-booking'); ?></label>
                                        <input type="number" id="column_area" name="column_area" value="2" min="0" max="20">
                                    </div>
                                    <div class="vandel-col">
                                        <label for="column_state"><?php _e('State/Province Column', 'vandel-booking'); ?></label>
                                        <input type="number" id="column_state" name="column_state" value="3" min="0" max="20">
                                    </div>
                                </div>
                                
                                <button type="submit" name="vandel_upload_location_csv" class="button button-primary">
                                    <?php _e('Upload & Import', 'vandel-booking'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="vandel-grid-col">
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Sample Data', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <p><?php _e('Generate a sample CSV file that you can use as a template for your own data.', 'vandel-booking'); ?></p>
                            
                            <form method="post">
                                <?php wp_nonce_field('vandel_generate_sample_csv', 'vandel_location_importer_nonce'); ?>
                                
                                <div class="vandel-form-row">
                                    <div class="vandel-col">
                                        <label for="sample_country"><?php _e('Country', 'vandel-booking'); ?></label>
                                        <select id="sample_country" name="country">
                                            <option value="SE"><?php _e('Sweden', 'vandel-booking'); ?></option>
                                            <option value="GENERIC"><?php _e('Generic Sample', 'vandel-booking'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" name="vandel_generate_sample_csv" class="button button-secondary">
                                    <?php _e('Generate Sample CSV', 'vandel-booking'); ?>
                                </button>
                            </form>
                            
                            <?php if (!empty($available_countries)): ?>
                                <div class="vandel-available-files">
                                    <h4><?php _e('Available Country Files', 'vandel-booking'); ?></h4>
                                    <ul>
                                        <?php foreach ($available_countries as $code => $country): ?>
                                            <li>
                                                <?php echo esc_html($country['name']); ?> 
                                                (<?php echo esc_html($country['count']); ?> <?php _e('locations', 'vandel-booking'); ?>)
                                                <button type="button" class="button button-small vandel-import-existing-file"
                                                        data-file="<?php echo esc_attr($country['file']); ?>"
                                                        data-country="<?php echo esc_attr($code); ?>">
                                                    <?php _e('Import', 'vandel-booking'); ?>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('CSV Format Requirements', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <p><?php _e('Your CSV file should follow these guidelines:', 'vandel-booking'); ?></p>
                            
                            <ul class="vandel-csv-requirements">
                                <li><?php _e('File should be in CSV format (comma-separated values)', 'vandel-booking'); ?></li>
                                <li><?php _e('Include a header row (recommended)', 'vandel-booking'); ?></li>
                                <li><?php _e('Required columns: Postal Code, City', 'vandel-booking'); ?></li>
                                <li><?php _e('Optional columns: Area/District, State/Province', 'vandel-booking'); ?></li>
                                <li><?php _e('If area is not provided, city name will be used', 'vandel-booking'); ?></li>
                            </ul>
                            
                            <h4><?php _e('Example CSV Structure:', 'vandel-booking'); ?></h4>
                            
                            <div class="vandel-csv-example">
                                <pre>postal_code,city,area,state
11152,Stockholm,Norrmalm,Stockholm County
11346,Stockholm,Östermalm,Stockholm County
11553,Stockholm,Södermalm,Stockholm County
41115,Göteborg,Centrum,Västra Götaland County
75236,Uppsala,Centrum,Uppsala County</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .vandel-csv-example {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .vandel-csv-requirements {
            margin-left: 20px;
        }
        
        .vandel-available-files {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .vandel-import-existing-file {
            margin-left: 5px;
        }
        </style>
        
        <!-- Progress modal for large imports -->
        <div id="vandel-import-progress-modal" class="vandel-modal" style="display:none;">
            <div class="vandel-modal-content">
                <h3><?php _e('Importing Location Data', 'vandel-booking'); ?></h3>
                <div class="vandel-progress-bar-container">
                    <div class="vandel-progress-bar"></div>
                </div>
                <p class="vandel-progress-status"><?php _e('Processing...', 'vandel-booking'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get location statistics by country
     * 
     * @return array Statistics by country
     */
    private function get_location_stats() {
        if (!$this->location_model) {
            return [];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_locations';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return [];
        }
        
        // Get country stats
        $stats_query = "
            SELECT 
                country, 
                COUNT(DISTINCT city) as cities, 
                COUNT(*) as locations,
                MAX(created_at) as last_updated
            FROM $table_name 
            GROUP BY country
            ORDER BY country
        ";
        
        $results = $wpdb->get_results($stats_query);
        
        if (empty($results)) {
            return [];
        }
        
        $stats = [];
        foreach ($results as $row) {
            $stats[$row->country] = [
                'name' => $row->country,
                'cities' => intval($row->cities),
                'locations' => intval($row->locations),
                'last_updated' => date_i18n(get_option('date_format'), strtotime($row->last_updated))
            ];
        }
        
        return $stats;
    }
}