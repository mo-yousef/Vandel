/**
 * Vandel Booking Tab JavaScript
 * Handles dynamic interactions for the bookings tab
 */
(function ($) {
  // Document ready function
  console.log("Vandel Bookings Tab JS Loaded");
  $(document).ready(function () {
    // Initialize Select2 for advanced dropdowns
    $(".vandel-select2").select2({
      placeholder: $(this).data("placeholder") || "Select an option",
      allowClear: true,
      width: "100%",
    });

    // Initialize DateRangePicker
    $(".vandel-daterange-picker").daterangepicker(
      {
        autoUpdateInput: false,
        locale: {
          cancelLabel: "Clear",
          format: "YYYY-MM-DD",
        },
        opens: "left",
      },
      function (start, end) {
        $(".vandel-daterange-picker").val(
          start.format("YYYY-MM-DD") + " to " + end.format("YYYY-MM-DD")
        );
      }
    );

    // Clear date range
    $(".vandel-daterange-picker").on("cancel.daterangepicker", function () {
      $(this).val("");
    });

    // Checkbox toggle functionality
    $("#vandel-select-all-bookings").on("change", function () {
      const isChecked = $(this).prop("checked");
      $('input[name="booking_ids[]"]').prop("checked", isChecked);
    });

    // Dropdown toggle for row actions
    $(".vandel-dropdown-toggle").on("click", function (e) {
      e.stopPropagation();
      $(this).siblings(".vandel-dropdown-menu").toggle();
    });

    // Close dropdowns when clicking outside
    $(document).on("click", function () {
      $(".vandel-dropdown-menu").hide();
    });

    // Prevent dropdown from closing when clicking inside
    $(".vandel-dropdown-menu").on("click", function (e) {
      e.stopPropagation();
    });

    // Bulk action handling
    $(".vandel-bulk-action-submit").on("click", function (e) {
      e.preventDefault();

      const selectedAction = $(".vandel-bulk-action-select").val();
      const selectedBookings = $('input[name="booking_ids[]"]:checked');

      // Validate selections
      if (selectedAction === "-1") {
        alert(vandelBookingsTab.noActionSelected);
        return false;
      }

      if (selectedBookings.length === 0) {
        alert(vandelBookingsTab.noRowsSelected);
        return false;
      }

      // Show confirmation modal
      openBulkActionModal(selectedAction);
    });

    // Booking action handlers
    $(".vandel-booking-action").on("click", function (e) {
      e.preventDefault();
      const action = $(this).data("action");
      const bookingId = $(this).data("id");

      // Perform AJAX action
      performBookingAction(action, [bookingId]);
    });

    // Modal functionality
    function openBulkActionModal(action) {
      const $modal = $("#vandel-bulk-action-modal");
      $modal.data("action", action);
      $modal.show();
    }

    // Close modal
    $(".vandel-modal-close, .vandel-modal-cancel").on("click", function () {
      $("#vandel-bulk-action-modal").hide();
    });

    // Confirm bulk action in modal
    $(".vandel-modal-confirm").on("click", function () {
      const $modal = $("#vandel-bulk-action-modal");
      const action = $modal.data("action");
      const selectedBookings = $('input[name="booking_ids[]"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get();

      $modal.hide();
      performBookingAction(action, selectedBookings);
    });

    // Perform booking action via AJAX
    function performBookingAction(action, bookingIds) {
      $.ajax({
        url: vandelBookingsTab.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_booking_bulk_action",
          nonce: vandelBookingsTab.nonce,
          bulk_action: action,
          booking_ids: bookingIds,
        },
        success: function (response) {
          if (response.success) {
            // Reload page or update table
            location.reload();
          } else {
            // Show error message
            alert(response.data.message || "An error occurred");
          }
        },
        error: function () {
          alert("An unexpected error occurred");
        },
      });
    }
  });
})(jQuery);
