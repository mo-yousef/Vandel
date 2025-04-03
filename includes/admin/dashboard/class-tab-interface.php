<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Dashboard Tab Interface
 * Interface for all dashboard tab classes
 */
interface Tab_Interface {
    /**
     * Render the tab content
     */
    public function render();
    
    /**
     * Process any actions related to this tab
     */
    public function process_actions();
    
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks();
}
