<?php
/**
 * Simple autoloader for plugin classes
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
    
    // Convert class name to file name (CamelCase to kebab-case with class- prefix)
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_file)) . '.php';
    
    // Create full path
    $file_path = VANDEL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $directory . $file_name;
    
    // For debugging - log the file path being attempted
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Attempting to load: " . $file_path);
    }
    
    // Load file if it exists
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Check for alternative file paths
        $alternative_paths = [
            // Try to find client model in booking directory (old location)
            'client\\clientmodel' => 'booking' . DIRECTORY_SEPARATOR . 'class-booking-client-model.php',
        ];
        
        $lower_class = strtolower(str_replace('\\', '', $class_name));
        if (isset($alternative_paths[$lower_class])) {
            $alt_path = VANDEL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $alternative_paths[$lower_class];
            if (file_exists($alt_path)) {
                require_once $alt_path;
            }
        }
        
        // Log missing file for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Missing file: " . $file_path . " for class: " . $class_name);
        }
    }
}

spl_autoload_register('vandel_booking_autoloader');