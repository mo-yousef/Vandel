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

/**
 * Enhanced Dashboard Functionality
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initCardHoverEffects();
    initFilterControls();
    initStatusToggle();
    initTabMemory();
  });

  /**
   * Initialize subtle hover effects on cards
   */
  function initCardHoverEffects() {
    $(".vandel-card").hover(
      function () {
        $(this).css("transform", "translateY(-2px)");
      },
      function () {
        $(this).css("transform", "translateY(0)");
      }
    );
  }

  /**
   * Improve filter controls
   */
  function initFilterControls() {
    // Auto-submit filters on change (optional)
    $(".vandel-filter-select").on("change", function () {
      if ($(this).closest("form").find('[type="submit"]').length) {
        $(this).closest("form").submit();
      }
    });

    // Clear all filters
    $(".vandel-reset-btn").on("click", function (e) {
      e.preventDefault();

      // Get the base URL without query parameters
      const baseUrl = window.location.href.split("?")[0];
      const currentTab = $('#vandel-filter-form input[name="tab"]').val();

      // Redirect to clean URL with just the tab parameter
      window.location.href =
        baseUrl + "?page=vandel-dashboard&tab=" + currentTab;
    });
  }

  /**
   * Initialize booking status toggle functionality
   */
  function initStatusToggle() {
    $(".vandel-toggle-status-btn").on("click", function (e) {
      e.preventDefault();

      const bookingId = $(this).data("booking-id");

      // Create dropdown menu
      const $menu = $(
        '<div class="vandel-dropdown-menu">' +
          '<a href="#" data-status="confirmed" class="vandel-status-action">' +
          '<span class="dashicons dashicons-yes"></span> ' +
          vandelDashboard.strings.confirm +
          "</a>" +
          '<a href="#" data-status="completed" class="vandel-status-action">' +
          '<span class="dashicons dashicons-saved"></span> ' +
          vandelDashboard.strings.complete +
          "</a>" +
          '<a href="#" data-status="canceled" class="vandel-status-action">' +
          '<span class="dashicons dashicons-dismiss"></span> ' +
          vandelDashboard.strings.cancel +
          "</a>" +
          "</div>"
      );

      // Position and show dropdown
      const $button = $(this);
      $menu.css({
        position: "absolute",
        top: $button.offset().top + $button.outerHeight(),
        left: $button.offset().left,
        zIndex: 100,
        background: "#fff",
        border: "1px solid #e2e8f0",
        borderRadius: "6px",
        boxShadow: "0 2px 5px rgba(0, 0, 0, 0.1)",
        padding: "5px 0",
      });

      // Style dropdown items
      $menu.find("a").css({
        display: "block",
        padding: "8px 15px",
        color: "#4a5568",
        textDecoration: "none",
        fontSize: "14px",
      });

      $menu.find("a").hover(
        function () {
          $(this).css("background", "#f7fafc");
        },
        function () {
          $(this).css("background", "");
        }
      );

      // Add to body
      $("body").append($menu);

      // Close on outside click
      $(document).on("click.statusMenu", function (e) {
        if (
          !$(e.target).closest(".vandel-dropdown-menu").length &&
          !$(e.target).is($button)
        ) {
          $menu.remove();
          $(document).off("click.statusMenu");
        }
      });

      // Handle status change
      $menu.find(".vandel-status-action").on("click", function (e) {
        e.preventDefault();

        const status = $(this).data("status");

        // Send AJAX request to update status
        $.ajax({
          url: vandelDashboard.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_update_booking_status",
            nonce: vandelDashboard.nonce,
            booking_id: bookingId,
            status: status,
          },
          success: function (response) {
            if (response.success) {
              // Reload the page to show updated status
              window.location.reload();
            } else {
              alert(
                response.data.message || vandelDashboard.strings.updateError
              );
            }
          },
          error: function () {
            alert(vandelDashboard.strings.updateError);
          },
        });
      });
    });
  }

  /**
   * Remember active tab between page loads
   */
  function initTabMemory() {
    // Store active tab in localStorage when clicked
    $(".vandel-tabs-navigation a").on("click", function () {
      const tabId = $(this).data("tab");
      localStorage.setItem("vandelActiveTab", tabId);
    });

    // Check if we should activate a tab from localStorage
    if (window.location.href.indexOf("tab=") === -1) {
      const savedTab = localStorage.getItem("vandelActiveTab");
      if (savedTab) {
        const $tab = $(
          '.vandel-tabs-navigation a[data-tab="' + savedTab + '"]'
        );
        if ($tab.length) {
          $tab.click();
        }
      }
    }
  }
})(jQuery);
