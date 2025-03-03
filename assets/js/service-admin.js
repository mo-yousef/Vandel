/**
 * Service Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initSubServiceCards();
    });
    
    /**
     * Initialize sub-service card functionality
     */
    function initSubServiceCards() {
        // Cache selectors
        const $subServiceCards = $('.vandel-sub-service-card');
        const $checkboxes = $subServiceCards.find('input[type="checkbox"]');
        
        // Handle checkbox changes
        $checkboxes.on('change', function() {
            const $card = $(this).closest('.vandel-sub-service-card');
            
            if ($(this).is(':checked')) {
                $card.addClass('assigned');
            } else {
                $card.removeClass('assigned');
            }
            
            updateSelectedCount();
        });
        
        // Handle card clicks (except on checkbox and edit link)
        $subServiceCards.on('click', function(e) {
            if (!$(e.target).is('input[type="checkbox"], a, .dashicons-edit')) {
                const $checkbox = $(this).find('input[type="checkbox"]');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
        
        // Initial update of selected count
        updateSelectedCount();
    }
    
    /**
     * Update selected sub-services count
     */
    function updateSelectedCount() {
        const $checkboxes = $('.vandel-sub-service-card input[type="checkbox"]');
        const checkedCount = $checkboxes.filter(':checked').length;
        const $statusIndicator = $('.vandel-sub-services-status');
        
        if ($statusIndicator.length) {
            $statusIndicator.html(checkedCount + ' ' + (checkedCount === 1 ? 'Selected' : 'Selected'));
        }
    }
    
})(jQuery);
