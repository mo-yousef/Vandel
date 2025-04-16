/**
 * Location Admin JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Location Admin JS loaded');
        
        // Initialize dynamic location selection
        initLocationSelect();
        
        // Initialize edit modal functionality
        initEditModal();
        
        // Initialize import/export functionality
        initImportExport();
        
        // Initialize search functionality
        initSearch();
        
        // Initialize delete confirmation
        initDeleteConfirmation();
    });

    /**
     * Initialize location select boxes with dynamic loading
     */
    function initLocationSelect() {
        const $countrySelect = $('#country');
        const $citySelect = $('#city');
        const $editCountrySelect = $('#edit-country');
        const $editCitySelect = $('#edit-city');
        
        // Handle country change
        $countrySelect.on('change', function() {
            const country = $(this).val();
            
            if (!country) {
                // Reset city select
                $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                return;
            }
            
            // Show loading indicator
            $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.loadingCities + '...</option>');
            
            // Fetch cities for selected country
            $.ajax({
                url: vandelLocationAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vandel_get_cities',
                    country: country,
                    nonce: vandelLocationAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const cities = response.data;
                        
                        // Populate city dropdown
                        $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                        $.each(cities, function(index, city) {
                            $citySelect.append('<option value="' + city + '">' + city + '</option>');
                        });
                    } else {
                        // Reset on error
                        $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                        console.error('Error loading cities:', response.data);
                    }
                },
                error: function() {
                    // Reset on AJAX error
                    $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                    console.error('AJAX error when loading cities');
                }
            });
        });
        
        // Same functionality for edit modal fields
        $editCountrySelect.on('change', function() {
            const country = $(this).val();
            
            if (!country) {
                $editCitySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                return;
            }
            
            $editCitySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.loadingCities + '...</option>');
            
            $.ajax({
                url: vandelLocationAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vandel_get_cities',
                    country: country,
                    nonce: vandelLocationAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const cities = response.data;
                        
                        $editCitySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                        $.each(cities, function(index, city) {
                            $editCitySelect.append('<option value="' + city + '">' + city + '</option>');
                        });
                    } else {
                        $editCitySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                    }
                },
                error: function() {
                    $editCitySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                }
            });
        });
    }

    /**
     * Initialize edit modal functionality
     */
    function initEditModal() {
        // Open modal when edit link is clicked
        $(document).on('click', '.vandel-edit-location', function(e) {
            e.preventDefault();
            const $link = $(this);
            
            // Get location data from attributes
            const id = $link.data('id');
            const country = $link.data('country');
            const city = $link.data('city');
            const areaName = $link.data('area-name');
            const zipCode = $link.data('zip-code');
            const priceAdjustment = $link.data('price-adjustment');
            const serviceFee = $link.data('service-fee');
            const isActive = $link.data('is-active');
            
            // Fill in the form fields
            $('#edit-location-id').val(id);
            
            const $countrySelect = $('#edit-country');
            $countrySelect.val(country);
            
            // Load cities for selected country
            const $citySelect = $('#edit-city');
            $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.loadingCities + '...</option>');
            
            $.ajax({
                url: vandelLocationAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vandel_get_cities',
                    country: country,
                    nonce: vandelLocationAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const cities = response.data;
                        
                        $citySelect.empty().append('<option value="">' + vandelLocationAdmin.strings.selectCity + '</option>');
                        $.each(cities, function(index, cityName) {
                            $citySelect.append('<option value="' + cityName + '">' + cityName + '</option>');
                        });
                        
                        // Set selected city
                        $citySelect.val(city);
                    }
                }
            });
            
            $('#edit-area-name').val(areaName);
            $('#edit-zip-code').val(zipCode);
            $('#edit-price-adjustment').val(priceAdjustment);
            $('#edit-service-fee').val(serviceFee);
            $('#edit-is-active').prop('checked', isActive === 'yes');
            
            // Show the modal
            $('#vandel-edit-location-modal').show();
        });
        
        // Close modal when clicking X
        $('.vandel-modal-close').on('click', function() {
            $('#vandel-edit-location-modal').hide();
        });
        
        // Close modal when clicking outside the content
        $(window).on('click', function(e) {
            if ($(e.target).is('#vandel-edit-location-modal')) {
                $('#vandel-edit-location-modal').hide();
            }
        });
    }

    /**
     * Initialize import/export functionality
     */
    function initImportExport() {
        // Handle location import
        $('#vandel-import-locations').on('click', function() {
            const $fileInput = $('#vandel-locations-file');
            const file = $fileInput[0].files[0];
            
            if (!file) {
                alert(vandelLocationAdmin.strings.selectFile);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'vandel_import_locations');
            formData.append('file', file);
            formData.append('nonce', vandelLocationAdmin.nonce);
            
            $.ajax({
                url: vandelLocationAdmin.ajaxUrl,
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
        
        // Handle location export
        $('#vandel-export-locations').on('click', function() {
            window.location.href = vandelLocationAdmin.ajaxUrl + '?action=vandel_export_locations&nonce=' + vandelLocationAdmin.nonce;
        });
    }

    /**
     * Initialize search functionality
     */
    function initSearch() {
        $('#vandel-location-search').on('keyup', function() {
            const searchText = $(this).val().toLowerCase();
            
            $('.vandel-data-table tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                
                if (rowText.indexOf(searchText) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }

    /**
     * Initialize delete confirmation
     */
    function initDeleteConfirmation() {
        $(document).on('click', '.vandel-delete-location', function(e) {
            if (!confirm(vandelLocationAdmin.confirmDelete)) {
                e.preventDefault();
            }
        });
    }
    
})(jQuery);
