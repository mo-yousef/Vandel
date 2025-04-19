/**
 * Location Selection JavaScript
 * Handles the interactive location selection component for the booking form
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    initLocationSelection();
  });

  /**
   * Initialize location selection functionality
   */
  function initLocationSelection() {
    // Cache selectors
    const $countrySelect = $("#vandel-country");
    const $citySelect = $("#vandel-city");
    const $areaSelect = $("#vandel-area");
    const $zipCodeInput = $("#vandel-zip-code");
    const $locationMessage = $("#vandel-location-message");
    const $locationDetails = $("#vandel-location-details");
    const $nextButton = $(
      '.vandel-booking-step[data-step="location"] .vandel-btn-next'
    );
    const $locationData = $("#vandel-location-data");

    // Disable city and area selects initially
    $citySelect.prop("disabled", true);
    $areaSelect.prop("disabled", true);

    // Disable next button initially
    if ($nextButton.length) {
      $nextButton.prop("disabled", true);
    }

    // Handle country selection
    $countrySelect.on("change", function () {
      const country = $(this).val();

      // Reset dependent fields
      $citySelect
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.selectCity}</option>`
        )
        .prop("disabled", true);
      $areaSelect
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.selectArea}</option>`
        )
        .prop("disabled", true);
      $zipCodeInput.val("");
      $locationMessage.empty();
      $locationDetails.slideUp();

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }

      if (!country) {
        return;
      }

      // Show loading state
      $citySelect.append(
        `<option value="loading">${vandelLocation.strings.loadingCities}</option>`
      );

      // Get cities for selected country
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocation.nonce,
          country: country,
        },
        success: function (response) {
          // Remove loading option
          $citySelect.find('option[value="loading"]').remove();

          if (response.success && response.data.length > 0) {
            // Add cities to select
            response.data.forEach(function (city) {
              $citySelect.append(`<option value="${city}">${city}</option>`);
            });

            // Enable city select
            $citySelect.prop("disabled", false);
          } else {
            // No cities found
            $citySelect.append(
              `<option value="">${vandelLocation.strings.noLocations}</option>`
            );
          }
        },
        error: function () {
          // Remove loading option
          $citySelect.find('option[value="loading"]').remove();
          $citySelect.append(
            `<option value="">${vandelLocation.strings.noLocations}</option>`
          );
        },
      });
    });

    // Handle city selection
    $citySelect.on("change", function () {
      const city = $(this).val();
      const country = $countrySelect.val();

      // Reset dependent fields
      $areaSelect
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.selectArea}</option>`
        )
        .prop("disabled", true);
      $zipCodeInput.val("");
      $locationMessage.empty();
      $locationDetails.slideUp();

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }

      if (!city || !country || city === "loading") {
        return;
      }

      // Show loading state
      $areaSelect.append(
        `<option value="loading">${vandelLocation.strings.loadingAreas}</option>`
      );

      // Get areas for selected city
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_areas",
          nonce: vandelLocation.nonce,
          country: country,
          city: city,
        },
        success: function (response) {
          // Remove loading option
          $areaSelect.find('option[value="loading"]').remove();

          if (response.success && response.data.length > 0) {
            // Add areas to select
            response.data.forEach(function (area) {
              $areaSelect.append(`<option value="${area}">${area}</option>`);
            });

            // Enable area select
            $areaSelect.prop("disabled", false);
          } else {
            // No areas found
            $areaSelect.append(
              `<option value="">${vandelLocation.strings.noLocations}</option>`
            );
          }
        },
        error: function () {
          // Remove loading option
          $areaSelect.find('option[value="loading"]').remove();
          $areaSelect.append(
            `<option value="">${vandelLocation.strings.noLocations}</option>`
          );
        },
      });
    });

    // Handle area selection
    $areaSelect.on("change", function () {
      const area = $(this).val();
      const city = $citySelect.val();
      const country = $countrySelect.val();

      // Reset dependent fields
      $zipCodeInput.val("");
      $locationMessage.empty();
      $locationDetails.slideUp();

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }

      if (!area || !city || !country || area === "loading") {
        return;
      }

      // Show loading state
      $locationMessage.html(
        `<div class="vandel-info-message"><span class="vandel-spinner"></span> ${vandelLocation.strings.validatingLocation}</div>`
      );

      // Validate location
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_validate_location",
          nonce: vandelLocation.nonce,
          country: country,
          city: city,
          area_name: area,
        },
        success: function (response) {
          if (response.success) {
            // Store location data
            if ($locationData.length) {
              $locationData.val(JSON.stringify(response.data));
            }

            // Update ZIP code field if available
            if (response.data.zip_code && $zipCodeInput.length) {
              $zipCodeInput.val(response.data.zip_code);
            }

            // Show location details
            updateLocationDetails(response.data);

            // Enable next button
            if ($nextButton.length) {
              $nextButton.prop("disabled", false);
            }

            // Clear validation message
            $locationMessage.empty();
          } else {
            // Show error message
            $locationMessage.html(
              `<div class="vandel-error-message">${response.data.message}</div>`
            );
          }
        },
        error: function () {
          // Show error message
          $locationMessage.html(
            `<div class="vandel-error-message">${vandelLocation.strings.errorLocation}</div>`
          );
        },
      });
    });

    // Handle ZIP code input for direct validation
    $zipCodeInput.on("change", function () {
      const zipCode = $(this).val().trim();

      // Reset dependent fields
      $locationMessage.empty();
      $locationDetails.slideUp();

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }

      if (!zipCode) {
        return;
      }

      // Show loading state
      $locationMessage.html(
        `<div class="vandel-info-message"><span class="vandel-spinner"></span> ${vandelLocation.strings.validatingZipCode}</div>`
      );

      // Validate ZIP code
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_validate_location",
          nonce: vandelLocation.nonce,
          zip_code: zipCode,
        },
        success: function (response) {
          if (response.success) {
            // Store location data
            if ($locationData.length) {
              $locationData.val(JSON.stringify(response.data));
            }

            // Update selects if available
            if (response.data.country && $countrySelect.length) {
              $countrySelect.val(response.data.country).trigger("change");

              // We need to delay these to ensure the city options are loaded first
              setTimeout(function () {
                if (response.data.city && $citySelect.length) {
                  $citySelect.val(response.data.city).trigger("change");

                  setTimeout(function () {
                    if (response.data.area_name && $areaSelect.length) {
                      $areaSelect.val(response.data.area_name);
                    }
                  }, 300);
                }
              }, 300);
            }

            // Show location details
            updateLocationDetails(response.data);

            // Enable next button
            if ($nextButton.length) {
              $nextButton.prop("disabled", false);
            }

            // Clear validation message
            $locationMessage.empty();
          } else {
            // Show error message
            $locationMessage.html(
              `<div class="vandel-error-message">${response.data.message}</div>`
            );
          }
        },
        error: function () {
          // Show error message
          $locationMessage.html(
            `<div class="vandel-error-message">${vandelLocation.strings.errorZipCode}</div>`
          );
        },
      });
    });

    /**
     * Update location details display
     *
     * @param {Object} locationData Location data
     */
    function updateLocationDetails(locationData) {
      const $locationArea = $("#vandel-location-area");
      const $locationCity = $("#vandel-location-city");
      const $priceInfo = $("#vandel-price-info");

      // Update location text
      if ($locationArea.length && locationData.area_name) {
        $locationArea.text(locationData.area_name);
      }

      if ($locationCity.length && locationData.city) {
        $locationCity.text(
          locationData.city +
            (locationData.country ? ", " + locationData.country : "")
        );
      }

      // Update price info
      if ($priceInfo.length) {
        let priceInfoHtml = "";

        if (
          locationData.price_adjustment &&
          parseFloat(locationData.price_adjustment) !== 0
        ) {
          const sign = parseFloat(locationData.price_adjustment) > 0 ? "+" : "";
          priceInfoHtml += `<div>${
            vandelLocation.strings.priceAdjustment
          }: ${sign}${formatPrice(locationData.price_adjustment)}</div>`;
        }

        if (
          locationData.service_fee &&
          parseFloat(locationData.service_fee) > 0
        ) {
          priceInfoHtml += `<div>${
            vandelLocation.strings.serviceFee
          }: ${formatPrice(locationData.service_fee)}</div>`;
        }

        if (priceInfoHtml) {
          $priceInfo.html(priceInfoHtml).show();
        } else {
          $priceInfo.hide();
        }
      }

      // Show location details
      $locationDetails.slideDown();
    }

    /**
     * Format price with currency symbol
     *
     * @param {number} price Price to format
     * @return {string} Formatted price
     */
    function formatPrice(price) {
      // Get currency symbol from parent script if available
      const currencySymbol =
        typeof vandelBooking !== "undefined" && vandelBooking.currencySymbol
          ? vandelBooking.currencySymbol
          : "$";

      return currencySymbol + " " + parseFloat(price).toFixed(2);
    }
  }
})(jQuery);
