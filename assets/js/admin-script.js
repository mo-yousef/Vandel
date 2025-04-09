/**
 * Client Management JavaScript for Vandel Booking
 */
(function ($) {
  "use strict";

  // Initialize when DOM is ready
  $(document).ready(function () {
    initClientDetails();
    initClientList();
    initClientForm();
  });

  /**
   * Initialize client details functionality
   */
  function initClientDetails() {
    // Handle adding notes
    const $noteForm = $("#vandel-add-client-note-form");
    const $noteInput = $("#client_note");

    if ($noteForm.length) {
      $noteForm.on("submit", function () {
        if (!$noteInput.val().trim()) {
          $noteInput.addClass("vandel-error");
          return false;
        }

        $noteInput.removeClass("vandel-error");
        return true;
      });

      $noteInput.on("input", function () {
        $(this).removeClass("vandel-error");
      });
    }

    // Handle recalculating client statistics
    const $recalculateBtn = $("#vandel-recalculate-stats");

    if ($recalculateBtn.length) {
      $recalculateBtn.on("click", function (e) {
        e.preventDefault();

        const clientId = $(this).data("client-id");
        const $button = $(this);

        // Disable button and show loading indicator
        $button.prop("disabled", true).addClass("button-busy");
        $button.html(
          '<span class="spinner is-active"></span> ' +
            vandelClientAdmin.strings.recalculating
        );

        // Send AJAX request
        $.ajax({
          url: vandelClientAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_recalculate_client_stats",
            client_id: clientId,
            nonce: vandelClientAdmin.nonce,
          },
          success: function (response) {
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
              $button.prop("disabled", false).removeClass("button-busy");
              $button.html(vandelClientAdmin.strings.recalculate);
            }
          },
          error: function () {
            // Show error message
            alert(vandelClientAdmin.strings.recalculateError);

            // Reset button
            $button.prop("disabled", false).removeClass("button-busy");
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
    const $bulkForm = $("#vandel-clients-form");

    if ($bulkForm.length) {
      $bulkForm.on("submit", function (e) {
        const $form = $(this);
        const selectedAction = $("#bulk-action-selector-top").val();
        const $selectedClients = $form.find(
          'input[name="client_ids[]"]:checked'
        );

        // If no action selected or no clients selected, prevent submission
        if (selectedAction === "-1" || $selectedClients.length === 0) {
          e.preventDefault();
          alert(vandelClientAdmin.strings.selectClientAndAction);
          return false;
        }

        // Confirm deletion
        if (selectedAction === "delete") {
          if (!confirm(vandelClientAdmin.strings.confirmBulkDelete)) {
            e.preventDefault();
            return false;
          }
        }

        return true;
      });
    }

    // Handle individual client deletion
    $(".vandel-delete-client").on("click", function (e) {
      if (!confirm(vandelClientAdmin.strings.confirmDelete)) {
        e.preventDefault();
        return false;
      }

      return true;
    });

    // Handle quick search
    const $quickSearch = $("#vandel-quick-search");
    if ($quickSearch.length) {
      $quickSearch.on("keyup", function () {
        const searchTerm = $(this).val().toLowerCase();

        $(".vandel-client-row").each(function () {
          const clientName = $(this)
            .find(".vandel-client-name")
            .text()
            .toLowerCase();
          const clientEmail = $(this)
            .find(".vandel-client-email")
            .text()
            .toLowerCase();

          if (
            clientName.includes(searchTerm) ||
            clientEmail.includes(searchTerm)
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
    const $clientForm = $("#vandel-client-form");

    if ($clientForm.length) {
      $clientForm.on("submit", function (e) {
        const $nameField = $("#client_name");
        const $emailField = $("#client_email");
        let isValid = true;

        // Validate name
        if (!$nameField.val().trim()) {
          $nameField.addClass("vandel-error");
          isValid = false;
        } else {
          $nameField.removeClass("vandel-error");
        }

        // Validate email
        const emailValue = $emailField.val().trim();
        if (!emailValue || !isValidEmail(emailValue)) {
          $emailField.addClass("vandel-error");
          isValid = false;
        } else {
          $emailField.removeClass("vandel-error");
        }

        if (!isValid) {
          e.preventDefault();
          alert(vandelClientAdmin.strings.fillRequired);
          return false;
        }

        return true;
      });

      // Remove error class on input
      $clientForm.find("input, textarea").on("input", function () {
        $(this).removeClass("vandel-error");
      });
    }
  }

  /**
   * Validate email format
   */
  function isValidEmail(email) {
    const re =
      /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
  }
})(jQuery);
/*=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
=======================================================================================
======================================================================================= */
/**
 * Vandel Booking - Enhanced Dashboard JavaScript
 *
 * This script improves the dashboard UX, client management, and booking tracking.
 */
(function ($) {
  "use strict";

  // Initialize when DOM is ready
  $(document).ready(function () {
    initDashboardEnhancements();
    initClientManagement();
    initBookingTracker();
    initCalendarIntegration();
    initQuickActions();
  });

  /**
   * Dashboard enhancements
   */
  function initDashboardEnhancements() {
    // Add card hover effects
    $(".vandel-card").hover(
      function () {
        $(this).css("transform", "translateY(-5px)");
      },
      function () {
        $(this).css("transform", "translateY(0)");
      }
    );

    // Toggle sections
    $(".vandel-section-toggle").on("click", function () {
      $($(this).data("target")).slideToggle(200);
      $(this)
        .find(".dashicons")
        .toggleClass("dashicons-arrow-down dashicons-arrow-up");
    });

    // Remember active tab
    $(".vandel-tab-link").on("click", function () {
      localStorage.setItem("vandelActiveTab", $(this).attr("href"));
    });

    // Load active tab from local storage if available
    const savedTab = localStorage.getItem("vandelActiveTab");
    if (savedTab) {
      $(`a[href="${savedTab}"]`).tab("show");
    }
  }

  /**
   * Client management enhancements
   */
  function initClientManagement() {
    // Quick client search
    $("#client-quick-search").on("keyup", function () {
      const searchTerm = $(this).val().toLowerCase();
      $(".vandel-client-item").each(function () {
        const clientData = $(this).text().toLowerCase();
        $(this).toggle(clientData.indexOf(searchTerm) > -1);
      });
    });

    // Client notes handling
    const $clientNotes = $("#client-notes");
    const $noteForm = $("#add-client-note-form");

    if ($noteForm.length) {
      $noteForm.on("submit", function (e) {
        e.preventDefault();

        const noteText = $("#note-text").val().trim();
        if (!noteText) return;

        const clientId = $(this).data("client-id");

        // Show loading state
        $(this).find("button").prop("disabled", true);

        // Send AJAX request to save note
        $.ajax({
          url: vandelAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_add_client_note",
            client_id: clientId,
            note: noteText,
            nonce: vandelAdmin.nonce,
          },
          success: function (response) {
            if (response.success) {
              // Add note to the list
              const date = new Date().toLocaleString();
              const noteHtml = `
                                <div class="vandel-note-item">
                                    <div class="vandel-note-header">
                                        <span class="vandel-note-date">${date}</span>
                                        <span class="vandel-note-user">${vandelAdmin.currentUser}</span>
                                    </div>
                                    <div class="vandel-note-content">${noteText}</div>
                                </div>
                            `;
              $clientNotes.prepend(noteHtml);

              // Clear form
              $("#note-text").val("");
            } else {
              alert(response.data.message || "Failed to add note");
            }
          },
          error: function () {
            alert("Failed to add note. Please try again.");
          },
          complete: function () {
            $noteForm.find("button").prop("disabled", false);
          },
        });
      });
    }

    // Client data chart initialization
    if ($("#client-booking-chart").length) {
      const ctx = document
        .getElementById("client-booking-chart")
        .getContext("2d");
      new Chart(ctx, {
        type: "line",
        data: {
          labels: vandelAdmin.clientData.labels,
          datasets: [
            {
              label: "Bookings",
              data: vandelAdmin.clientData.bookings,
              borderColor: "#3498db",
              backgroundColor: "rgba(52, 152, 219, 0.1)",
              tension: 0.4,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              precision: 0,
            },
          },
        },
      });
    }
  }

  /**
   * Booking tracker enhancements
   */
  function initBookingTracker() {
    // Status change handling
    $(".vandel-booking-status-select").on("change", function () {
      const bookingId = $(this).data("booking-id");
      const newStatus = $(this).val();

      if (!confirm(`Change booking #${bookingId} status to ${newStatus}?`)) {
        // Reset to previous value if user cancels
        $(this).val($(this).data("original-value"));
        return;
      }

      // Send AJAX request to update status
      $.ajax({
        url: vandelAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_update_booking_status",
          booking_id: bookingId,
          status: newStatus,
          nonce: vandelAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Update data attribute
            $(this).data("original-value", newStatus);

            // Visual feedback
            const $row = $(this).closest("tr");
            $row.addClass("status-changed");
            setTimeout(() => {
              $row.removeClass("status-changed");
            }, 1500);
          } else {
            alert(response.data.message || "Failed to update status");
            // Reset to original value
            $(this).val($(this).data("original-value"));
          }
        },
        error: function () {
          alert("Failed to update booking status");
          // Reset to original value
          $(this).val($(this).data("original-value"));
        },
      });
    });

    // Batch booking actions
    $("#vandel-batch-action-btn").on("click", function () {
      const selectedAction = $("#vandel-batch-action").val();
      const selectedBookings = $('input[name="booking_ids[]"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get();

      if (!selectedAction || selectedBookings.length === 0) {
        alert("Please select an action and at least one booking");
        return;
      }

      if (
        !confirm(
          `Apply "${selectedAction}" to ${selectedBookings.length} selected bookings?`
        )
      ) {
        return;
      }

      // Perform batch action via AJAX
      $.ajax({
        url: vandelAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_batch_booking_action",
          booking_ids: selectedBookings,
          batch_action: selectedAction,
          nonce: vandelAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(`${response.data.count} bookings updated successfully`);
            window.location.reload();
          } else {
            alert(response.data.message || "Failed to process batch action");
          }
        },
        error: function () {
          alert("Failed to process batch action");
        },
      });
    });
  }

  /**
   * Calendar integration enhancements
   */
  function initCalendarIntegration() {
    if ($("#vandel-calendar").length === 0) return;

    // Initialize FullCalendar
    const calendar = new FullCalendar.Calendar(
      document.getElementById("vandel-calendar"),
      {
        initialView: "dayGridMonth",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay",
        },
        events:
          vandelAdmin.ajaxUrl +
          "?action=vandel_get_calendar_events&nonce=" +
          vandelAdmin.nonce,
        eventClick: function (info) {
          showBookingDetails(info.event.id);
        },
        eventDidMount: function (info) {
          $(info.el).tooltip({
            title: info.event.extendedProps.description,
            placement: "top",
            trigger: "hover",
            container: "body",
          });
        },
      }
    );

    calendar.render();

    function showBookingDetails(bookingId) {
      // Show loading state
      $("#vandel-booking-modal .modal-body").html(
        '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading booking details...</p></div>'
      );
      $("#vandel-booking-modal").modal("show");

      // Fetch booking details via AJAX
      $.ajax({
        url: vandelAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_booking_details",
          booking_id: bookingId,
          nonce: vandelAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#vandel-booking-modal .modal-body").html(response.data.html);
          } else {
            $("#vandel-booking-modal .modal-body").html(
              '<div class="alert alert-danger">Failed to load booking details</div>'
            );
          }
        },
        error: function () {
          $("#vandel-booking-modal .modal-body").html(
            '<div class="alert alert-danger">Failed to load booking details</div>'
          );
        },
      });
    }
  }

  /**
   * Quick actions
   */
  function initQuickActions() {
    // Add quick action handling
    $(".vandel-quick-action").on("click", function (e) {
      const action = $(this).data("action");
      const id = $(this).data("id");
      const confirmMsg = $(this).data("confirm");

      if (confirmMsg && !confirm(confirmMsg)) {
        e.preventDefault();
        return false;
      }

      if (action && id) {
        e.preventDefault();

        // Perform quick action via AJAX
        $.ajax({
          url: vandelAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_quick_action",
            quick_action: action,
            target_id: id,
            nonce: vandelAdmin.nonce,
          },
          success: function (response) {
            if (response.success) {
              if (response.data.redirect) {
                window.location.href = response.data.redirect;
              } else {
                window.location.reload();
              }
            } else {
              alert(response.data.message || "Action failed");
            }
          },
          error: function () {
            alert("Action failed. Please try again.");
          },
        });
      }
    });
  }
})(jQuery);
