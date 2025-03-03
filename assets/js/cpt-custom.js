/**
 * Custom Post Type Admin Scripts
 */
(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initSortableFields();
        handleQuickSave();
    });
    
    /**
     * Initialize sortable fields
     */
    function initSortableFields() {
        // Add sortable functionality to specific lists or fields
        $('.vandel-sortable').sortable({
            handle: '.vandel-sort-handle',
            placeholder: 'vandel-sortable-placeholder',
            forcePlaceholderSize: true,
            update: function(event, ui) {
                // Trigger reordering logic if needed
                console.log('Sorting updated');
            }
        });
    }
    
    /**
     * Handle quick save functionality
     */
    function handleQuickSave() {
        // Check if we're on a custom post type page
        if (typeof vandelAdmin === 'undefined') return;
        
        // Prevent multiple form submissions
        let isSaving = false;
        
        // Quick save functionality
        $('.vandel-quick-save').on('click', function(e) {
            e.preventDefault();
            
            if (isSaving) return;
            
            const $button = $(this);
            const $form = $button.closest('form');
            
            // Show saving message
            $button.prop('disabled', true)
                   .html(vandelAdmin.messages.saving);
            
            isSaving = true;
            
            $.ajax({
                url: vandelAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=vandel_quick_save&nonce=' + vandelAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        $button.html(vandelAdmin.messages.saved);
                        
                        // Optional: Show temporary success message
                        setTimeout(function() {
                            $button.html('Save');
                            $button.prop('disabled', false);
                        }, 2000);
                    } else {
                        $button.html(vandelAdmin.messages.error);
                        console.error('Quick save failed:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $button.html(vandelAdmin.messages.error);
                    console.error('Quick save error:', error);
                },
                complete: function() {
                    isSaving = false;
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Confirmation for delete actions
        $('.vandel-delete-item').on('click', function(e) {
            if (!confirm(vandelAdmin.messages.deleteConfirm)) {
                e.preventDefault();
            }
        });
    }
    
})(jQuery);
