<?php
/**
 * Advanced autoloader for plugin classes
 */
function vandel_booking_autoloader($class_name) {
    // Check if class is in our namespace
    if (strpos($class_name, 'VandelBooking\\') !== 0) {
        return;
    }
    
    // Remove namespace
    $class_name = str_replace('VandelBooking\\', '', $class_name);
    
    // Convert class name to file path
    $path_parts = explode('\\', $class_name);
    $class_file = array_pop($path_parts); // Get the actual class name
    
    // Build directory path from namespace parts
    $directory = '';
    if (!empty($path_parts)) {
        $directory = strtolower(implode(DIRECTORY_SEPARATOR, $path_parts)) . DIRECTORY_SEPARATOR;
    }
    
    // Convert class name to different possible file name formats
    $file_formats = [
        'class-' . strtolower(str_replace('_', '-', $class_file)) . '.php',
        'class-' . strtolower($class_file) . '.php',
        strtolower(str_replace('_', '-', $class_file)) . '.php',
        strtolower($class_file) . '.php',
        $class_file . '.php'
    ];
    
    // Possible directories to look in
    $possible_directories = [
        // Standard directory from namespace
        $directory,
        
        // Client classes might be in booking directory
        ($directory === 'client/') ? 'booking/' : $directory,
        
        // Zip code classes might be in different locations
        ($class_file === 'ZipCodeModel') ? 'location/' : $directory,
        ($class_file === 'ZipCodeAPI') ? 'api/' : $directory,
    ];
    
    // Try to find the file
    foreach ($possible_directories as $dir) {
        foreach ($file_formats as $format) {
            $file_path = VANDEL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $dir . $format;
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }
    
    // Check for alternative file paths for special cases
    $alternative_paths = [
        // Client model might be in booking directory with a different name
        'client\\clientmodel' => 'booking/class-booking-client-model.php',
        
        // Booking model
        'booking\\bookingmodel' => 'booking/class-booking-model.php',
        
        // Note model
        'booking\\notemodel' => 'booking/class-booking-note-model.php',
        
        // ZIP code model
        'location\\zipcodemodel' => [
            'location/class-zip-code-model.php',
            'includes/location/class-zip-code-model.php',
            'admin/class-zip-code-ajax-handler.php'
        ],
        
        // API classes
        'api\\apiloader' => 'api/class-api-loader.php',
        'api\\zipcodeapi' => 'api/class-zip-code-api.php',
    ];
    
    $lower_class = strtolower(str_replace('\\', '', $class_name));
    if (isset($alternative_paths[$lower_class])) {
        $alt_paths = (array) $alternative_paths[$lower_class];
        
        foreach ($alt_paths as $alt_path) {
            $full_path = VANDEL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $alt_path;
            
            if (file_exists($full_path)) {
                require_once $full_path;
                return;
            }
        }
    }
    
    // One last attempt - check all includes directories recursively
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $missing_class = $class_name;
        
        // Find all PHP files in includes directory
        $directory_iterator = new RecursiveDirectoryIterator(VANDEL_PLUGIN_DIR . 'includes');
        $iterator = new RecursiveIteratorIterator($directory_iterator);
        $files = [];
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $file_contents = file_get_contents($file->getPathname());
                
                // Check if class name is in file
                if (strpos($file_contents, "class {$class_file}") !== false ||
                    strpos($file_contents, "class {$class_file} ") !== false) {
                    require_once $file->getPathname();
                    error_log("VandelBooking: Found {$class_name} in unexpected location: {$file->getPathname()}");
                    return;
                }
            }
        }
        
        // Log missing file for debugging
        error_log("VandelBooking: Unable to find class {$missing_class}. Tried standard locations and deep scan.");
    }
}

// Register the autoloader
spl_autoload_register('vandel_booking_autoloader');

/**
 * Force-load critical classes that might not be found by the autoloader
 */
function vandel_load_critical_classes() {
    $critical_files = [
        'includes/client/class-client-model.php',
        'includes/booking/class-booking-model.php',
        'includes/booking/class-booking-manager.php',
        'includes/ajax/class-ajax-handler.php'
    ];
    
    foreach ($critical_files as $file) {
        $file_path = VANDEL_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// Call this function to ensure critical classes are loaded
vandel_load_critical_classes();