/**
 * Location Importer JavaScript
 * Handles the location import interactions on the admin page
 */
(function ($) {
  "use strict";

  console.log("Location Importer JS loaded");

  // Initialize when DOM is ready
  $(document).ready(function () {
    initLocationImporter();
  });

  /**
   * Initialize location importer
   */
  function initLocationImporter() {
    // Import existing file button click handler
    $(".vandel-import-existing-file").on("click", function () {
      const filePath = $(this).data("file");
      const country = $(this).data("country");

      if (!filePath || !country) {
        alert("Missing file path or country code");
        return;
      }

      // Confirm import
      if (!confirm(vandelLocationImporter.strings.confirmImport)) {
        return;
      }

      // Show progress modal
      showProgressModal();

      // Prepare data for AJAX request
      const data = {
        action: "vandel_import_locations_from_csv",
        nonce: vandelLocationImporter.nonce,
        file_path: filePath,
        country: country,
        mapping: {
          zip_code: 0,
          city: 1,
          area: 2,
          state: 3,
        },
        has_header: true,
      };

      // Make AJAX request
      $.ajax({
        url: vandelLocationImporter.ajaxUrl,
        type: "POST",
        data: data,
        success: function (response) {
          hideProgressModal();

          if (response.success) {
            // Show success message
            showResultMessage(response.data.message, "success");

            // Reload the page after a delay
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            // Show error message
            showResultMessage(
              response.data.message ||
                vandelLocationImporter.strings.importError,
              "error"
            );
          }
        },
        error: function () {
          hideProgressModal();
          showResultMessage(
            vandelLocationImporter.strings.importError,
            "error"
          );
        },
      });
    });

    // File input change handler
    $("#location_csv_file").on("change", function () {
      const file = this.files[0];

      if (file) {
        // Set country name from filename if empty
        const $countryInput = $("#country");
        if (!$countryInput.val()) {
          // Extract country name from filename (remove extension)
          let countryName = file.name.replace(/\.[^/.]+$/, "");
          // Capitalize first letter
          countryName =
            countryName.charAt(0).toUpperCase() + countryName.slice(1);
          $countryInput.val(countryName);
        }

        // Parse sample of CSV to detect columns (for future enhancement)
      }
    });

    // Form submission handler for manual import
    $('form[name="vandel_upload_location_csv"]').on("submit", function () {
      showProgressModal();
    });
  }

  /**
   * Show progress modal
   */
  function showProgressModal() {
    const $modal = $("#vandel-import-progress-modal");

    // Reset progress bar
    $modal.find(".vandel-progress-bar").css("width", "0%");
    $modal
      .find(".vandel-progress-status")
      .text(vandelLocationImporter.strings.importing);

    // Show modal
    $modal.show();

    // Animate progress bar (since we don't have real-time progress)
    let progress = 0;
    const interval = setInterval(function () {
      progress += 1;
      if (progress > 95) {
        clearInterval(interval);
        return;
      }
      $modal.find(".vandel-progress-bar").css("width", progress + "%");
    }, 500);
  }

  /**
   * Hide progress modal
   */
  function hideProgressModal() {
    const $modal = $("#vandel-import-progress-modal");
    $modal.find(".vandel-progress-bar").css("width", "100%");

    // Hide after short delay
    setTimeout(function () {
      $modal.hide();
    }, 500);
  }

  /**
   * Show result message
   *
   * @param {string} message Message to display
   * @param {string} type Message type (success, error, warning)
   */
  function showResultMessage(message, type) {
    // Create message element if it doesn't exist
    let $message = $(".vandel-import-result-message");

    if ($message.length === 0) {
      $message = $('<div class="vandel-import-result-message notice"></div>');
      $(".vandel-card:first").prepend($message);
    }

    // Set message class based on type
    $message
      .removeClass("notice-success notice-error notice-warning")
      .addClass("notice-" + type)
      .html("<p>" + message + "</p>")
      .show();

    // Scroll to message
    $("html, body").animate(
      {
        scrollTop: $message.offset().top - 50,
      },
      300
    );

    // Auto-hide after delay for success messages
    if (type === "success") {
      setTimeout(function () {
        $message.fadeOut();
      }, 5000);
    }
  }
})(jQuery);
