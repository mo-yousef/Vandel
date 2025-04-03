/**
 * Vandel Client Management JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initClientDetails();
        initClientList();
        initClientForm();
        initImportExport();
    });

    /**
     * Initialize client details functionality
     */
    function initClientDetails() {
        // Handle adding notes
        const $noteForm = $('#vandel-add-client-note-form');
        const $noteInput = $('#client_note');

        if ($noteForm.length) {
            $noteForm.on('submit', function() {
                if (!$noteInput.val().trim()) {
                    $noteInput.addClass('vandel-error');
                    return false;
                }

                $noteInput.removeClass('vandel-error');
                return true;
            });

            $noteInput.on('input', function() {
                $(this).removeClass('vandel-error');
            });
        }

        // Handle recalculating client statistics
        const $recalculateBtn = $('#vandel-recalculate-stats');

        if ($recalculateBtn.length) {
            $recalculateBtn.on('click', function(e) {
                e.preventDefault();

                const clientId = $(this).data('client-id');
                const $button = $(this);

                // Disable button and show loading indicator
                $button.prop('disabled', true).addClass('button-busy');
                $button.html(
                    '<span class="spinner is-active"></span> ' +
                    vandelClientAdmin.strings.recalculating
                );

                // Send AJAX request
                $.ajax({
                    url: vandelClientAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vandel_recalculate_client_stats',
                        client_id: clientId,
                        nonce: vandelClientAdmin.nonce,
                    },
                    success: function(response) {
                        if (response.success) {
                            // Refresh the page to show updated stats
                            window.location.reload();
                        } else {
                            // Show error message
                            alert(
                                response.data.message ||
                                vandelClientAdmin.strings.recalculateError
                            );

                            // Reset button
                            $button.prop('disabled', false).removeClass('button-busy');
                            $button.html(vandelClientAdmin.strings.recalculate);
                        }
                    },
                    error: function() {
                        // Show error message
                        alert(vandelClientAdmin.strings.recalculateError);

                        // Reset button
                        $button.prop('disabled', false).removeClass('button-busy');
                        $button.html(vandelClientAdmin.strings.recalculate);
                    },
                });
            });
        }
    }

    /**
     * Initialize client list functionality
     */
    function initClientList() {
        // Handle bulk actions
        const $bulkForm = $('#vandel-clients-form');

        if ($bulkForm.length) {
            $bulkForm.on('submit', function(e) {
                const $form = $(this);
                const selectedAction = $('#bulk-action-selector-top').val();
                const $selectedClients = $form.find(
                    'input[name="client_ids[]"]:checked'
                );

                // If no action selected or no clients selected, prevent submission
                if (selectedAction === '-1' || $selectedClients.length === 0) {
                    e.preventDefault();
                    alert(vandelClientAdmin.strings.selectClientAndAction);
                    return false;
                }

                // Confirm deletion
                if (selectedAction === 'delete') {
                    if (!confirm(vandelClientAdmin.strings.confirmBulkDelete)) {
                        e.preventDefault();
                        return false;
                    }
                }

                return true;
            });
        }

        // Handle individual client deletion
        $('.vandel-delete-client').on('click', function(e) {
            if (!confirm(vandelClientAdmin.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }

            return true;
        });

        // Handle quick search
        const $quickSearch = $('#vandel-quick-search');
        if ($quickSearch.length) {
            $quickSearch.on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();

                $('.vandel-client-row').each(function() {
                    const clientName = $(this)
                        .find('.vandel-client-name')
                        .text()
                        .toLowerCase();
                    const clientEmail = $(this)
                        .find('.vandel-client-email')
                        .text()
                        .toLowerCase();
                    const clientPhone = $(this)
                        .find('.vandel-client-phone')
                        .text()
                        .toLowerCase();

                    if (
                        clientName.includes(searchTerm) ||
                        clientEmail.includes(searchTerm) ||
                        clientPhone.includes(searchTerm)
                    ) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        }
    }

    /**
     * Initialize client form functionality
     */
    function initClientForm() {
        // Form validation
        const $clientForm = $('#vandel-client-form');

        if ($clientForm.length) {
            $clientForm.on('submit', function(e) {
                const $nameField = $('#client_name');
                const $emailField = $('#client_email');
                let isValid = true;

                // Validate name
                if (!$nameField.val().trim()) {
                    $nameField.addClass('vandel-error');
                    isValid = false;
                } else {
                    $nameField.removeClass('vandel-error');
                }

                // Validate email
                const emailValue = $emailField.val().trim();
                if (!emailValue || !isValidEmail(emailValue)) {
                    $emailField.addClass('vandel-error');
                    isValid = false;
                } else {
                    $emailField.removeClass('vandel-error');
                }

                if (!isValid) {
                    e.preventDefault();
                    alert(vandelClientAdmin.strings.fillRequired);
                    return false;
                }

                return true;
            });

            // Remove error class on input
            $clientForm.find('input, textarea').on('input', function() {
                $(this).removeClass('vandel-error');
            });