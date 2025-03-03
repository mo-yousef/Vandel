/**
 * Admin Scripts for Vandel Booking Plugin
 */
(function ($) {
  "use strict";

  // Initialize when the DOM is ready
  $(document).ready(function () {
    console.log("Vandel Booking Admin JS loaded");
    initTabs();
  });

  /**
   * Initialize tabs on admin pages
   */
  function initTabs() {
    var $tabLinks = $(".vandel-tabs-navigation a");
    var $tabContents = $(".vandel-tab-content");

    // Handle tab clicks
    $tabLinks.on("click", function (e) {
      e.preventDefault();

      var targetTab = $(this).data("tab");

      // Update active tab
      $tabLinks.removeClass("active");
      $(this).addClass("active");

      // Show target content
      $tabContents.hide();
      $("#" + targetTab).show();

      // Update URL without reloading
      if (history.pushState) {
        var url = new URL(window.location);
        url.searchParams.set("tab", targetTab);
        window.history.pushState({}, "", url);
      }
    });

    // Activate tab based on URL parameter
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get("tab");

    if (activeTab) {
      $('.vandel-tabs-navigation a[data-tab="' + activeTab + '"]').trigger(
        "click"
      );
    } else {
      // Activate first tab by default
      $tabLinks.first().trigger("click");
    }
  }
})(jQuery);

/**
 * Vandel Booking Settings Page Interactions
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Settings Navigation
    function initSettingsNavigation() {
      const $navLinks = $(".vandel-settings-nav a");
      const $sections = $(".vandel-settings-section");

      $navLinks.on("click", function (e) {
        e.preventDefault();
        const targetSection = $(this).data("section");

        // Update active navigation
        $navLinks.parent().removeClass("active");
        $(this).parent().addClass("active");

        // Show/hide sections
        $sections.hide();
        $(`#${targetSection}`).show();

        // Update URL without page reload
        if (history.pushState) {
          const url = new URL(window.location);
          url.searchParams.set("section", targetSection);
          window.history.pushState({}, "", url);
        }
      });
    }

    // Timezone Detection
    function detectTimezone() {
      const $timezoneSelect = $("#vandel_default_timezone");

      // Auto-detect user's timezone if not already set
      if (!$timezoneSelect.val()) {
        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        $timezoneSelect.val(userTimezone);
      }
    }

    // Integration Tooltips
    function initIntegrationTooltips() {
      $(".vandel-integration-item").each(function () {
        const $item = $(this);
        const $checkbox = $item.find('input[type="checkbox"]');
        const $badge = $item.find(".vandel-badge");

        $checkbox.on("change", function () {
          if ($(this).prop("checked")) {
            alert(
              "This integration is not yet available. Stay tuned for future updates!"
            );
            $(this).prop("checked", false);
          }
        });

        $badge.tooltip({
          title: "Coming soon! We are working on adding this integration.",
          placement: "right",
        });
      });
    }

    // Business Hours Validation
    function validateBusinessHours() {
      const $startTime = $("#vandel_business_hours_start");
      const $endTime = $("#vandel_business_hours_end");

      $startTime.add($endTime).on("change", function () {
        const startTime = new Date(`2000-01-01T${$startTime.val()}`);
        const endTime = new Date(`2000-01-01T${$endTime.val()}`);

        if (startTime >= endTime) {
          alert("End time must be later than start time.");
          $endTime.val("");
        }
      });
    }

    // Notification Email Validation
    function validateNotificationEmail() {
      const $emailInput = $("#vandel_notification_email");
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      $emailInput.on("blur", function () {
        const email = $(this).val().trim();
        if (email && !emailRegex.test(email)) {
          alert("Please enter a valid email address.");
          $(this).focus();
        }
      });
    }

    // Initialize all functions
    initSettingsNavigation();
    detectTimezone();
    initIntegrationTooltips();
    validateBusinessHours();
    validateNotificationEmail();
  });
})(jQuery);
