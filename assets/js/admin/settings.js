/**
 * Vandel Booking Settings Page JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when DOM is ready
  $(document).ready(function () {
    initSettingsNav();
    initColorPicker();
    initTimezonePicker();
    initToggles();
    handleDependentFields();
  });

  /**
   * Initialize settings navigation
   */
  function initSettingsNav() {
    // Handle settings section navigation
    $(".vandel-settings-nav a").on("click", function (e) {
      e.preventDefault();

      const targetSection = $(this).attr("href").split("section=")[1];

      // Update active state
      $(".vandel-settings-nav li").removeClass("active");
      $(this).parent().addClass("active");

      // Update URL without page reload
      if (history.pushState) {
        const currentURL = new URL(window.location);
        currentURL.searchParams.set("section", targetSection);
        window.history.pushState(
          { path: currentURL.toString() },
          "",
          currentURL.toString()
        );
      }

      // Load section content via AJAX (optional enhancement)
      // For now, just reload the page
      window.location.reload();
    });
  }

  /**
   * Initialize color picker
   */
  function initColorPicker() {
    if ($.fn.wpColorPicker) {
      $("#vandel_primary_color").wpColorPicker({
        defaultColor: "#286cd6",
        change: function (event, ui) {
          // Preview color change in real-time
          document.documentElement.style.setProperty(
            "--vandel-primary-color",
            ui.color.toString()
          );
        },
      });
    }
  }

  /**
   * Initialize timezone picker with search
   */
  function initTimezonePicker() {
    const $timezoneSelect = $("#vandel_default_timezone");

    // Only initialize if select2 is available
    if ($.fn.select2 && $timezoneSelect.length) {
      $timezoneSelect.select2({
        placeholder: "Select a timezone",
        allowClear: true,
        width: "100%",
      });

      // Auto-detect user's timezone if not already set
      if (!$timezoneSelect.val()) {
        try {
          const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
          $timezoneSelect.val(userTimezone).trigger("change");
        } catch (e) {
          console.log("Could not detect timezone");
        }
      }
    }
  }

  /**
   * Initialize toggle switches
   */
  function initToggles() {
    $('.vandel-toggle input[type="checkbox"]')
      .on("change", function () {
        const $toggle = $(this);
        const toggleId = $toggle.attr("id");

        // Toggle related sections based on the toggle state
        if ($toggle.is(":checked")) {
          $(`.vandel-toggle-section[data-toggle="${toggleId}"]`).slideDown(200);
        } else {
          $(`.vandel-toggle-section[data-toggle="${toggleId}"]`).slideUp(200);
        }
      })
      .trigger("change"); // Trigger on load
  }

  /**
   * Handle dependent fields
   */
  function handleDependentFields() {
    // Enable/disable email configuration based on email notifications toggle
    $("#vandel_enable_email_notifications")
      .on("change", function () {
        const isEnabled = $(this).is(":checked");
        const $emailFields = $(".vandel-email-fields").find(
          "input, textarea, select"
        );

        if (isEnabled) {
          $emailFields.prop("disabled", false);
          $(".vandel-email-fields").removeClass("vandel-fields-disabled");
        } else {
          $emailFields.prop("disabled", true);
          $(".vandel-email-fields").addClass("vandel-fields-disabled");
        }
      })
      .trigger("change");

    // Handle ZIP code feature toggle
    $("#vandel_enable_zip_code_feature")
      .on("change", function () {
        const isEnabled = $(this).is(":checked");

        if (isEnabled) {
          $(".vandel-zip-code-actions").slideDown(200);
        } else {
          $(".vandel-zip-code-actions").slideUp(200);
        }
      })
      .trigger("change");

    // Business hours validation
    $("#vandel_business_hours_start, #vandel_business_hours_end").on(
      "change",
      function () {
        const startTime = $("#vandel_business_hours_start").val();
        const endTime = $("#vandel_business_hours_end").val();

        if (startTime && endTime) {
          const start = new Date(`2000-01-01T${startTime}`);
          const end = new Date(`2000-01-01T${endTime}`);

          if (start >= end) {
            alert("End time must be later than start time");
            $("#vandel_business_hours_end").val("");
          }
        }
      }
    );
  }
})(jQuery);
