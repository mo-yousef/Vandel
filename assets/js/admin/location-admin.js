/**
 * Enhanced Location Management JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    console.log("Enhanced Location Admin JS loaded");

    // Initialize ZIP code edit functionality
    initZipCodeEdit();

    // Initialize location edit functionality
    initLocationEdit();

    // Initialize search functionality
    initSearch();

    // Initialize delete confirmation
    initDeleteConfirmation();
  });

  /**
   * Initialize ZIP code edit functionality
   */
  function initZipCodeEdit() {
    // Use event delegation to handle dynamically added elements
    $(document).on("click", ".vandel-edit-zip-code", function (e) {
      e.preventDefault();
      console.log("Edit ZIP code button clicked");

      // Get ZIP code data from data attributes
      const zipCode = $(this).data("zip-code");
      const city = $(this).data("city");
      const state = $(this).data("state");
      const country = $(this).data("country");
      const priceAdjustment = $(this).data("price-adjustment");
      const serviceFee = $(this).data("service-fee");
      const isServiceable = $(this).data("is-serviceable");

      console.log("Loading ZIP code data:", {
        zipCode,
        city,
        state,
        country,
        priceAdjustment,
        serviceFee,
        isServiceable,
      });

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
    $(document).on("click", ".vandel-modal-close", function () {
      $("#vandel-edit-zip-code-modal, #vandel-edit-location-modal").hide();
    });

    // Close modal when clicking outside
    $(window).on("click", function (e) {
      if ($(e.target).is("#vandel-edit-zip-code-modal")) {
        $("#vandel-edit-zip-code-modal").hide();
      }
      if ($(e.target).is("#vandel-edit-location-modal")) {
        $("#vandel-edit-location-modal").hide();
      }
    });
  }

  /**
   * Initialize location edit functionality
   */
  function initLocationEdit() {
    $(document).on("click", ".vandel-edit-location", function (e) {
      e.preventDefault();
      const $link = $(this);

      // Get location data from attributes
      const id = $link.data("id");
      const country = $link.data("country");
      const city = $link.data("city");
      const areaName = $link.data("area-name");
      const zipCode = $link.data("zip-code");
      const priceAdjustment = $link.data("price-adjustment");
      const serviceFee = $link.data("service-fee");
      const isActive = $link.data("is-active");

      console.log("Editing location:", {
        id,
        country,
        city,
        areaName,
        zipCode,
        priceAdjustment,
        serviceFee,
        isActive,
      });

      // Fill in the form fields
      $("#edit-location-id").val(id);

      const $countrySelect = $("#edit-country");
      $countrySelect.val(country);

      // Load cities for selected country
      const $citySelect = $("#edit-city");
      $citySelect
        .empty()
        .append(
          '<option value="">' +
            (window.vandelLocationAdmin?.strings?.loadingCities ||
              "Loading cities...") +
            "</option>"
        );

      // Enhanced debugging
      console.log("Fetching cities for country:", country);
      console.log("AJAX URL:", window.vandelLocationAdmin?.ajaxUrl || ajaxurl);
      console.log("Nonce:", window.vandelLocationAdmin?.nonce || "not set");

      // Add additional error handling and debugging
      $.ajax({
        url: window.vandelLocationAdmin?.ajaxUrl || ajaxurl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          country: country,
          nonce: window.vandelLocationAdmin?.nonce || "",
        },
        success: function (response) {
          console.log("AJAX response:", response);

          if (response.success) {
            const cities = response.data;

            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  (window.vandelLocationAdmin?.strings?.selectCity ||
                    "Select city") +
                  "</option>"
              );

            $.each(cities, function (index, cityName) {
              $citySelect.append(
                '<option value="' + cityName + '">' + cityName + "</option>"
              );
            });

            // Set selected city
            $citySelect.val(city);
          } else {
            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  (response.data?.message || "Error loading cities") +
                  "</option>"
              );
            console.error("Error loading cities:", response);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX error:", status, error);
          console.log("Response text:", xhr.responseText);

          $citySelect
            .empty()
            .append(
              '<option value="">Error: ' + status + " - " + error + "</option>"
            );
        },
        complete: function () {
          console.log("AJAX request completed");
        },
      });

      $("#edit-area-name").val(areaName);
      $("#edit-zip-code").val(zipCode);
      $("#edit-price-adjustment").val(priceAdjustment);
      $("#edit-service-fee").val(serviceFee);
      $("#edit-is-active").prop("checked", isActive === "yes");

      // Show the modal
      $("#vandel-edit-location-modal").show();
    });
  }

  /**
   * Initialize search functionality
   */
  function initSearch() {
    $("#vandel-zip-search, #vandel-location-search").on("keyup", function () {
      const searchText = $(this).val().toLowerCase();
      const isZipSearch = $(this).attr("id") === "vandel-zip-search";
      const tableSelector = isZipSearch
        ? ".vandel-data-table"
        : ".vandel-location-table";

      $(`${tableSelector} tbody tr`).each(function () {
        const rowText = $(this).text().toLowerCase();
        $(this).toggle(rowText.indexOf(searchText) > -1);
      });
    });
  }

  /**
   * Initialize delete confirmation
   */
  function initDeleteConfirmation() {
    $(document).on(
      "click",
      ".vandel-delete-zip-code, .vandel-delete-location",
      function (e) {
        const confirmMessage =
          window.vandelLocationAdmin?.confirmDelete ||
          window.vandelZipCodeAdmin?.confirmDelete ||
          "Are you sure you want to delete this item?";

        if (!confirm(confirmMessage)) {
          e.preventDefault();
        }
      }
    );
  }
})(jQuery);
