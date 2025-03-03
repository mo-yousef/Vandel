<?php
/*
Plugin Name: Vandel Cleaning Booking
Description: A robust cleaning booking plugin with multi-step form and unified admin dashboard.
Version: 1.0.0
Author: Mohammad Yousif
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin.php';

// Initialize the plugin
function vandel_booking_init() {
    return \VandelBooking\Plugin::getInstance();
}

// Start the plugin
vandel_booking_init();