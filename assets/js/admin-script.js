/**
 * Admin Scripts for Vandel Booking Plugin
 */
(function($) {
    'use strict';
    
    // Initialize when the DOM is ready
    $(document).ready(function() {
        console.log('Vandel Booking Admin JS loaded');
        initTabs();
    });
    
    /**
     * Initialize tabs on admin pages
     */
    function initTabs() {
        var $tabLinks = $('.vandel-tabs-navigation a');
        var $tabContents = $('.vandel-tab-content');
        
        // Handle tab clicks
        $tabLinks.on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            
            // Update active tab
            $tabLinks.removeClass('active');
            $(this).addClass('active');
            
            // Show target content
            $tabContents.hide();
            $('#' + targetTab).show();
            
            // Update URL without reloading
            if (history.pushState) {
                var url = new URL(window.location);
                url.searchParams.set('tab', targetTab);
                window.history.pushState({}, '', url);
            }
        });
        
        // Activate tab based on URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var activeTab = urlParams.get('tab');
        
        if (activeTab) {
            $('.vandel-tabs-navigation a[data-tab="' + activeTab + '"]').trigger('click');
        } else {
            // Activate first tab by default
            $tabLinks.first().trigger('click');
        }
    }
    
})(jQuery);
