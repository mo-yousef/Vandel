/**
 * Client Dashboard JavaScript
 * Handles the interactive functionality for the client dashboard
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    initClientDashboard();
  });

  /**
   * Initialize client dashboard
   */
  function initClientDashboard() {
    // Cache DOM elements
    const $authForm = $(".vandel-auth-form");
    const $clientEmail = $("#vandel-client-email");
    const $authenticateBtn = $("#vandel-authenticate-btn");
    const $bookingsContainer = $(".vandel-bookings-container");
    const $loadingIndicator = $("#vandel-bookings-loading");
    const $bookingsList = $("#vandel-bookings-list");
    const $noBookings = $("#vandel-no-bookings");
    const $searchInput = $("#vandel-booking-search");
    const $searchButton = $("#vandel-search-button");
    const $viewButtons = $(".vandel-view-btn");
    const $modal = $("#vandel-booking-details-modal");
    const $modalContent = $("#vandel-booking-details-content");
    const $cancelButton = $("#vandel-cancel-booking");

    // Store client email for later use
    let clientEmail = "";
    let currentView = "all";
    let currentBookings = [];
    let currentBookingId = 0;

    // Handle authentication
    $authenticateBtn.on("click", function () {
      const email = $clientEmail.val();

      if (!email || !isValidEmail(email)) {
        showMessage($authForm, "error", "Please enter a valid email address.");
        return;
      }

      // Store email for later use
      clientEmail = email;

      // Show loading state
      $authForm.slideUp();
      $bookingsContainer.show();
      $loadingIndicator.show();
      $bookingsList.empty();
      $noBookings.hide();

      // Get bookings for this client
      getClientBookings(email, currentView);
    });

    // Handle search
    $searchButton.on("click", function () {
      const searchTerm = $searchInput.val();
      getClientBookings(clientEmail, currentView, searchTerm);
    });

    $searchInput.on("keypress", function (e) {
      if (e.which === 13) {
        $searchButton.click();
      }
    });

    // Handle view toggle
    $viewButtons.on("click", function () {
      const $btn = $(this);
      const view = $btn.data("view");

      // Update active state
      $viewButtons.removeClass("active");
      $btn.addClass("active");

      // Update current view
      currentView = view;

      // Fetch bookings with new view
      getClientBookings(clientEmail, view, $searchInput.val());
    });

    // Handle booking details view
    $(document).on("click", ".vandel-btn-view-details", function () {
      const bookingId = $(this).data("booking-id");
      showBookingDetails(bookingId);
    });

    // Handle cancel booking button
    $(document).on("click", ".vandel-btn-cancel", function () {
      const bookingId = $(this).data("booking-id");
      confirmCancelBooking(bookingId);
    });

    // Handle modal cancel button
    $cancelButton.on("click", function () {
      if (currentBookingId > 0) {
        confirmCancelBooking(currentBookingId);
      }
    });

    // Handle modal close
    $(".vandel-modal-close").on("click", function () {
      $modal.hide();
    });

    $(window).on("click", function (e) {
      if ($(e.target).is($modal)) {
        $modal.hide();
      }
    });

    /**
     * Get client bookings from server
     *
     * @param {string} email Client email
     * @param {string} view View filter (all, upcoming, past)
     * @param {string} search Search term
     */
    function getClientBookings(email, view = "all", search = "") {
      // Show loading state
      $loadingIndicator.show();
      $bookingsList.empty();
      $noBookings.hide();

      $.ajax({
        url: vandelClientDashboard.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_client_get_bookings",
          nonce: vandelClientDashboard.nonce,
          email: email,
          view: view,
          search: search,
        },
        success: function (response) {
          // Hide loading indicator
          $loadingIndicator.hide();

          if (response.success) {
            // Store bookings for later use
            currentBookings = response.data.bookings;

            if (response.data.count > 0) {
              // Show bookings list
              $bookingsList.html(response.data.html).show();
              $noBookings.hide();
            } else {
              // Show no bookings message
              $bookingsList.empty().hide();
              $noBookings.show();
            }
          } else {
            // Show error message
            showMessage(
              $bookingsContainer,
              "error",
              response.data.message || vandelClientDashboard.strings.error
            );
            $bookingsList.empty().hide();
          }
        },
        error: function () {
          // Hide loading indicator
          $loadingIndicator.hide();

          // Show error message
          showMessage(
            $bookingsContainer,
            "error",
            vandelClientDashboard.strings.error
          );
          $bookingsList.empty().hide();
        },
      });
    }

    /**
     * Show booking details in modal
     *
     * @param {number} bookingId Booking ID
     */
    function showBookingDetails(bookingId) {
      // Find booking in current bookings
      const booking = currentBookings.find(
        (b) => parseInt(b.id) === parseInt(bookingId)
      );

      if (!booking) {
        return;
      }

      // Store current booking ID
      currentBookingId = bookingId;

      // Update modal content
      $modalContent.html(
        `<div class="vandel-booking-detail-grid">
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Booking ID"}</div>
            <div class="vandel-detail-value">#${booking.id}</div>
          </div>
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Service"}</div>
            <div class="vandel-detail-value">${booking.service_name}</div>
          </div>
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Date"}</div>
            <div class="vandel-detail-value">${booking.formatted_date}</div>
          </div>
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Time"}</div>
            <div class="vandel-detail-value">${booking.formatted_time}</div>
          </div>
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Status"}</div>
            <div class="vandel-detail-value status-${booking.status}">${
          booking.status_label
        }</div>
          </div>
          <div class="vandel-booking-detail-row">
            <div class="vandel-detail-label">${"Total"}</div>
            <div class="vandel-detail-value">${formatPrice(
              booking.total_price
            )}</div>
          </div>
          ${
            booking.access_info
              ? `<div class="vandel-booking-detail-row">
                  <div class="vandel-detail-label">${"Location"}</div>
                  <div class="vandel-detail-value">${booking.access_info}</div>
                </div>`
              : ""
          }
        </div>`
      );

      // Show/hide cancel button based on booking status
      if (booking.can_cancel) {
        $cancelButton.show();
      } else {
        $cancelButton.hide();
      }

      // Show modal
      $modal.show();
    }

    /**
     * Confirm and process booking cancellation
     *
     * @param {number} bookingId Booking ID
     */
    function confirmCancelBooking(bookingId) {
      if (confirm(vandelClientDashboard.strings.confirmCancel)) {
        // Close modal if open
        $modal.hide();

        // Show loading message
        showMessage(
          $bookingsContainer,
          "info",
          vandelClientDashboard.strings.canceling
        );

        // Send cancellation request
        $.ajax({
          url: vandelClientDashboard.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_client_cancel_booking",
            nonce: vandelClientDashboard.nonce,
            booking_id: bookingId,
            email: clientEmail,
          },
          success: function (response) {
            if (response.success) {
              // Show success message
              showMessage($bookingsContainer, "success", response.data.message);

              // Refresh bookings list
              getClientBookings(clientEmail, currentView, $searchInput.val());
            } else {
              // Show error message
              showMessage(
                $bookingsContainer,
                "error",
                response.data.message || vandelClientDashboard.strings.error
              );
            }
          },
          error: function () {
            // Show error message
            showMessage(
              $bookingsContainer,
              "error",
              vandelClientDashboard.strings.error
            );
          },
        });
      }
    }

    /**
     * Show message
     *
     * @param {jQuery} $container Container element
     * @param {string} type Message type (success, error, info, warning)
     * @param {string} message Message text
     */
    function showMessage($container, type, message) {
      // Remove existing messages
      $container.find(".vandel-message").remove();

      // Create message element
      const $message = $(
        `<div class="vandel-message vandel-${type}-message">${message}</div>`
      );

      // Add message to container
      $container.prepend($message);

      // Auto-hide success and info messages
      if (type === "success" || type === "info") {
        setTimeout(function () {
          $message.fadeOut(function () {
            $(this).remove();
          });
        }, 5000);
      }
    }

    /**
     * Validate email format
     *
     * @param {string} email Email address
     * @return {boolean} Whether email is valid
     */
    function isValidEmail(email) {
      const regex =
        /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
      return regex.test(String(email).toLowerCase());
    }

    /**
     * Format price
     *
     * @param {number} price Price to format
     * @return {string} Formatted price
     */
    function formatPrice(price) {
      // Simple formatting - customize as needed
      return "$" + parseFloat(price).toFixed(2);
    }
  }
})(jQuery);
