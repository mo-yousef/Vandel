/**
 * ZIP Code Admin Interactions
 */
(function ($) {
  "use strict";
  console.log("ZIP Code Admin JS loaded");

  $(document).ready(function () {
    // Bulk import ZIP Codes
    $("#vandel-import-zip-codes").on("click", function () {
      const $fileInput = $("#vandel-zip-codes-file");
      const file = $fileInput[0].files[0];

      if (!file) {
        alert("Please select a file to import.");
        return;
      }

      const formData = new FormData();
      formData.append("action", "vandel_import_zip_codes");
      formData.append("file", file);
      formData.append("nonce", vandelZipCodeAdmin.nonce);

      $.ajax({
        url: vandelZipCodeAdmin.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert("Import failed: " + response.data.message);
          }
        },
        error: function () {
          alert("An error occurred during import.");
        },
      });
    });

    // Export ZIP Codes
    $("#vandel-export-zip-codes").on("click", function () {
      const exportUrl =
        vandelZipCodeAdmin.ajaxUrl +
        "?action=vandel_export_zip_codes&nonce=" +
        vandelZipCodeAdmin.nonce;

      window.location.href = exportUrl;
    });

    // Validate ZIP Code input
    $("#vandel_zip_code").on("input", function () {
      const zipCode = $(this).val();
      const countryInput = $("#vandel_country");

      // Basic validation
      if (zipCode.length > 0) {
        $.ajax({
          url: vandelZipCodeAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_validate_zip_code",
            zip_code: zipCode,
            nonce: vandelZipCodeAdmin.nonce,
          },
          success: function (response) {
            if (response.success) {
              // Populate country if not set
              if (countryInput.val() === "") {
                countryInput.val(response.data.country);
              }
            }
          },
        });
      }
    });
  });
})(jQuery);

// New code from claude

/**
 * ZIP Code Admin Interactions
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    console.log("ZIP Code admin script initialized");

    // Edit ZIP Code functionality
    initEditZipCode();

    // Import/Export functionality
    initImportExport();

    // Search functionality
    initSearch();

    // Delete confirmation
    initDeleteConfirmation();
  });

  /**
   * Initialize Edit ZIP Code functionality
   */
  function initEditZipCode() {
    $(".vandel-edit-zip-code").on("click", function (e) {
      e.preventDefault();

      // Get ZIP code data from data attributes
      const zipCode = $(this).data("zip-code");
      const city = $(this).data("city");
      const state = $(this).data("state");
      const country = $(this).data("country");
      const priceAdjustment = $(this).data("price-adjustment");
      const serviceFee = $(this).data("service-fee");
      const isServiceable = $(this).data("is-serviceable");

      console.log("Edit clicked for ZIP code:", zipCode);

      // Populate edit form
      $("#edit-original-zip-code").val(zipCode);
      $("#edit-zip-code").val(zipCode);
      $("#edit-city").val(city);
      $("#edit-state").val(state);
      $("#edit-country").val(country);
      $("#edit-price-adjustment").val(priceAdjustment);
      $("#edit-service-fee").val(serviceFee);
      $("#edit-is-serviceable").prop("checked", isServiceable === "yes");

      // Show the modal
      $("#vandel-edit-zip-code-modal").show();
    });

    // Close modal when clicking X button
    $(".vandel-modal-close").on("click", function () {
      $("#vandel-edit-zip-code-modal").hide();
    });

    // Close modal when clicking outside
    $(window).on("click", function (e) {
      if ($(e.target).is("#vandel-edit-zip-code-modal")) {
        $("#vandel-edit-zip-code-modal").hide();
      }
    });
  }

  /**
   * Initialize Import/Export functionality
   */
  function initImportExport() {
    // Bulk import ZIP Codes
    $("#vandel-import-zip-codes").on("click", function () {
      console.log("Import button clicked");
      const $fileInput = $("#vandel-zip-codes-file");
      const file = $fileInput[0].files[0];

      if (!file) {
        alert("Please select a file to import.");
        return;
      }

      const formData = new FormData();
      formData.append("action", "vandel_import_zip_codes");
      formData.append("file", file);
      formData.append("nonce", vandelZipCodeAdmin.nonce);

      $.ajax({
        url: vandelZipCodeAdmin.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert("Import failed: " + response.data.message);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX error:", status, error);
          alert("An error occurred during import.");
        },
      });
    });

    // Export ZIP Codes
    $("#vandel-export-zip-codes").on("click", function () {
      console.log("Export button clicked");
      const exportUrl =
        vandelZipCodeAdmin.ajaxUrl +
        "?action=vandel_export_zip_codes&nonce=" +
        vandelZipCodeAdmin.nonce;

      window.location.href = exportUrl;
    });
  }

  /**
   * Initialize search functionality
   */
  function initSearch() {
    $("#vandel-zip-search").on("keyup", function () {
      const searchText = $(this).val().toLowerCase();

      $(".vandel-data-table tbody tr").each(function () {
        const zipCode = $(this).find("td:first-child").text().toLowerCase();
        const city = $(this).find("td:nth-child(2)").text().toLowerCase();
        const state = $(this).find("td:nth-child(3)").text().toLowerCase();

        if (
          zipCode.includes(searchText) ||
          city.includes(searchText) ||
          state.includes(searchText)
        ) {
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
    $(".vandel-delete-zip-code").on("click", function (e) {
      if (!confirm(vandelZipCodeAdmin.confirmDelete)) {
        e.preventDefault();
      }
    });
  }
})(jQuery);
