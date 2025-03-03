/**
 * ZIP Code Admin Interactions
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Bulk import ZIP Codes
        $('#vandel-import-zip-codes').on('click', function() {
            const $fileInput = $('#vandel-zip-codes-file');
            const file = $fileInput[0].files[0];

            if (!file) {
                alert('Please select a file to import.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'vandel_import_zip_codes');
            formData.append('file', file);
            formData.append('nonce', vandelZipCodeAdmin.nonce);

            $.ajax({
                url: vandelZipCodeAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Import failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during import.');
                }
            });
        });

        // Export ZIP Codes
        $('#vandel-export-zip-codes').on('click', function() {
            const exportUrl = vandelZipCodeAdmin.ajaxUrl + 
                '?action=vandel_export_zip_codes&nonce=' + 
                vandelZipCodeAdmin.nonce;
            
            window.location.href = exportUrl;
        });

        // Validate ZIP Code input
        $('#vandel_zip_code').on('input', function() {
            const zipCode = $(this).val();
            const countryInput = $('#vandel_country');

            // Basic validation
            if (zipCode.length > 0) {
                $.ajax({
                    url: vandelZipCodeAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vandel_validate_zip_code',
                        zip_code: zipCode,
                        nonce: vandelZipCodeAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Populate country if not set
                            if (countryInput.val() === '') {
                                countryInput.val(response.data.country);
                            }
                        }
                    }
                });
            }
        });
    });
})(jQuery);
