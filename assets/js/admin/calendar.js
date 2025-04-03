/**
 * Vandel Booking Calendar JavaScript
 */
(function($) {
    'use strict';
    
    // Calendar instance
    let calendar;
    
    // Modal elements
    const $modal = $('#vandel-booking-modal');
    const $closeModal = $('.vandel-modal-close');
    const $statusSelect = $('#vandel-change-status');
    const $updateStatusBtn = $('#vandel-update-status');
    
    // Currently selected booking ID
    let currentBookingId = null;
    
    /**
     * Initialize the calendar
     */
    function initCalendar() {
        const calendarEl = document.getElementById('vandel-calendar');
        
        if (!calendarEl) {
            return;
        }
        
        // Initialize FullCalendar
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: vandelCalendar.strings.today,
                month: vandelCalendar.strings.month,
                week: vandelCalendar.strings.week,
                day: vandelCalendar.strings.day
            },
            events: function(info, successCallback, failureCallback) {
                // Fetch events via AJAX
                $.ajax({
                    url: vandelCalendar.ajaxUrl,
                    type: 'GET',
                    data: {
                        action: 'vandel_get_calendar_events',
                        nonce: vandelCalendar.nonce,
                        start: info.startStr,
                        end: info.endStr,
                        status: $('#vandel-calendar-status-filter').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            successCallback(response.data);
                        } else {
                            failureCallback(response.data.message);
                        }
                    },
                    error: function() {
                        failureCallback(vandelCalendar.strings.errorOccurred);
                    }
                });
            },
            eventClick: function(info) {
                openBookingModal(info.event);
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: 'short'
            },
            firstDay: 1, // Start week on Monday
            height: 'auto',
            aspectRatio: 1.8
        });
        
        calendar.render();
    }
    
    /**
     * Open booking details modal
     * 
     * @param {Object} event Calendar event object
     */
    function openBookingModal(event) {
        const props = event.extendedProps;
        currentBookingId = event.id;
        
        // Populate modal with event details
        $('#vandel-booking-id .id-placeholder').text(event.id);
        $('#vandel-client-name').text(props.customer_name);
        $('#vandel-client-email').text(props.customer_email);
        $('#vandel-client-phone').text(props.phone || 'N/A');
        $('#vandel-service-name').text(props.service);
        $('#vandel-booking-date').text(formatDateTime(event.start));
        $('#vandel-booking-price').text(formatPrice(props.total_price));
        
        // Update status badge
        const $statusBadge = $('#vandel-booking-status');
        $statusBadge.text(capitalizeFirstLetter(props.status));
        $statusBadge.removeClass().addClass('vandel-status-badge');
        $statusBadge.addClass('vandel-status-badge-' + props.status);
        
        // Update links
        $('#vandel-view-details').attr('href', vandelCalendar.bookingDetailsUrl + event.id);
        $('#vandel-edit-booking').attr('href', vandelCalendar.editBookingUrl + event.id);
        
        // Reset status select
        $statusSelect.val('');
        
        // Show modal
        $modal.css('display', 'block');
    }
    
    /**
     * Close booking details modal
     */
    function closeBookingModal() {
        $modal.css('display', 'none');
        currentBookingId = null;
    }
    
    /**
     * Update booking status
     */
    function updateBookingStatus() {
        const newStatus = $statusSelect.val();
        
        if (!currentBookingId || !newStatus) {
            return;
        }
        
        // Disable button and show loading state
        $updateStatusBtn.prop('disabled', true);
        $updateStatusBtn.text(vandelCalendar.strings.loading);
        
        // Send AJAX request
        $.ajax({
            url: vandelCalendar.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vandel_update_booking_status',
                nonce: vandelCalendar.nonce,
                booking_id: currentBookingId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    const $statusBadge = $('#vandel-booking-status');
                    $statusBadge.text(capitalizeFirstLetter(newStatus));
                    $statusBadge.removeClass().addClass('vandel-status-badge');
                    $statusBadge.addClass('vandel-status-badge-' + newStatus);
                    
                    // Reset status select
                    $statusSelect.val('');
                    
                    // Refresh calendar
                    calendar.refetchEvents();
                    
                    // Show success message
                    alert(vandelCalendar.strings.statusChanged);
                } else {
                    // Show error message
                    alert(response.data.message || vandelCalendar.strings.errorOccurred);
                }
                
                // Reset button
                $updateStatusBtn.prop('disabled', false);
                $updateStatusBtn.text(vandelCalendar.strings.update);
            },
            error: function() {
                // Show error message
                alert(vandelCalendar.strings.errorOccurred);
                
                // Reset button
                $updateStatusBtn.prop('disabled', false);
                $updateStatusBtn.text(vandelCalendar.strings.update);
            }
        });
    }
    
    /**
     * Format date and time
     * 
     * @param {Date} date Date object
     * @return {string} Formatted date and time
     */
    function formatDateTime(date) {
        if (!date) {
            return 'N/A';
        }
        
        const options = {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return date.toLocaleDateString(undefined, options);
    }
    
    /**
     * Format price with currency symbol
     * 
     * @param {number} price Price to format
     * @return {string} Formatted price
     */
    function formatPrice(price) {
        if (typeof price !== 'number') {
            price = parseFloat(price);
        }
        
        return '$' + price.toFixed(2);
    }
    
    /**
     * Capitalize first letter of a string
     * 
     * @param {string} string Input string
     * @return {string} String with first letter capitalized
     */
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    /**
     * Filter calendar events
     */
    function filterCalendarEvents() {
        calendar.refetchEvents();
    }
    
    /**
     * Change calendar view
     * 
     * @param {string} view View name
     */
    function changeCalendarView(view) {
        calendar.changeView(view);
    }
    
    /**
     * Initialize events
     */
    function initEvents() {
        // Close modal when clicking the close button
        $closeModal.on('click', closeBookingModal);
        
        // Close modal when clicking outside the modal content
        $(window).on('click', function(event) {
            if ($(event.target).is($modal)) {
                closeBookingModal();
            }
        });
        
        // Update booking status
        $updateStatusBtn.on('click', updateBookingStatus);
        
        // Filter events by status
        $('#vandel-calendar-status-filter').on('change', filterCalendarEvents);
        
        // Change calendar view
        $('#vandel-calendar-view-filter').on('change', function() {
            changeCalendarView($(this).val());
        });
    }
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        initCalendar();
        initEvents();
    });
    
})(jQuery);
