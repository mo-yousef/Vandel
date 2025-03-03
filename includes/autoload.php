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
    $class_file = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    $class_file = VANDEL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'class-' . strtolower(str_replace('_', '-', $class_file)) . '.php';
    
    // Load file if it exists
    if (file_exists($class_file)) {
        require_once $class_file;
    }
}

spl_autoload_register('vandel_booking_autoloader');