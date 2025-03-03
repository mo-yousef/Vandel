/**
 * Sub-Service Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initInputTypeToggle();
        initOptionsManagement();
        makeSortable();
    });
    
    /**
     * Initialize input type toggle functionality
     */
    function initInputTypeToggle() {
        // Cache selectors
        const $inputTypeCards = $('.vandel-input-type-card');
        const $inputTypeRadios = $('.vandel-input-type-radio');
        const $optionsContainer = $('#vandel-options-container');
        const $commonConfig = $('#vandel-common-config');
        const $numberConfig = $('#vandel-number-config');
        
        // Handle input type selection
        $inputTypeRadios.on('change', function() {
            const selectedType = $(this).val();
            
            // Update UI to show selected card
            $inputTypeCards.removeClass('selected');
            $(this).closest('.vandel-input-type-card').addClass('selected');
            
            // Show/hide options container based on input type
            if (['dropdown', 'checkbox', 'radio'].includes(selectedType)) {
                $optionsContainer.slideDown(200);
            } else {
                $optionsContainer.slideUp(200);
            }
            
            // Show/hide number config section
            if (selectedType === 'number') {
                $numberConfig.slideDown(200);
            } else {
                $numberConfig.slideUp(200);
            }
        });
    }
    
    /**
     * Initialize options management
     */
    function initOptionsManagement() {
        // Cache selectors
        const $optionsList = $('#vandel-options-list');
        const $addOptionBtn = $('#vandel-add-option');
        const optionTemplate = $('#vandel-option-template').html();
        
        // Add option handler
        $addOptionBtn.on('click', function() {
            const newIndex = $optionsList.find('.vandel-option-row').length;
            let newOptionHtml = '';
            
            // Use template from script tag if available
            if (optionTemplate) {
                newOptionHtml = optionTemplate.replace(/\{\{index\}\}/g, newIndex);
                $optionsList.append(newOptionHtml);
            } else {
                // Fetch template via AJAX if not available
                $.ajax({
                    url: vandelSubServiceAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vandel_fetch_option_template',
                        nonce: vandelSubServiceAdmin.nonce,
                        index: newIndex
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            $optionsList.append(response.data.html);
                        }
                    }
                });
            }
        });
        
        // Remove option handler (delegated event)
        $optionsList.on('click', '.vandel-remove-option', function() {
            const $row = $(this).closest('.vandel-option-row');
            
            if (confirm(vandelSubServiceAdmin.strings.confirmDelete)) {
                $row.slideUp(200, function() {
                    $(this).remove();
                    reindexOptions();
                });
            }
        });
    }
    
    /**
     * Make options sortable
     */
    function makeSortable() {
        $('.vandel-sortable').sortable({
            handle: '.vandel-option-handle',
            placeholder: 'vandel-sortable-placeholder',
            forcePlaceholderSize: true,
            update: function() {
                reindexOptions();
            }
        });
    }
    
    /**
     * Reindex option fields after sorting or removal
     */
    function reindexOptions() {
        $('#vandel-options-list .vandel-option-row').each(function(index) {
            const $row = $(this);
            
            // Update data-index attribute
            $row.attr('data-index', index);
            
            // Update input names
            $row.find('input').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    }
    
})(jQuery);
