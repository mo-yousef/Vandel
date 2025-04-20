/**
 * Location Selector Component
 * Handles dynamic loading and validation of location data
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLocationSelector();
  });

  /**
   * Initialize location selector
   */
  function initLocationSelector() {
    const $countrySelect = $("#vandel-country");
    const $citySelect = $("#vandel-city");
    const $areaSelect = $("#vandel-area");
    const $zipCodeInput = $("#vandel-zip-code");
    const $locationMessage = $("#vandel-location-message");
    const $locationDetails = $("#vandel-location-details");
    const $locationArea = $("#vandel-location-area");
    const $locationCity = $("#vandel-location-city");
    const $priceInfo = $("#vandel-price-info");
    const $locationData = $("#vandel-location-data");
    const $nextButton = $(
      '.vandel-booking-step[data-step="location"] .vandel-btn-next'
    );

    // Disable next button initially
    if ($nextButton.length) {
      $nextButton.prop("disabled", true);
    }

    // Country change handler
    $countrySelect.on("change", function () {
      const country = $(this).val();

      // Reset city and area selects
      resetSelects(["city", "area"]);
      clearZipAndDetails();

      if (!country) {
        return;
      }

      // Show loading indicator
      $citySelect
        .empty()
        .append(
          `<option value="loading">${vandelLocation.strings.loadingCities}...</option>`
        );
      $citySelect.prop("disabled", true);

      // Fetch cities for selected country
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          country: country,
          nonce: vandelLocation.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Populate cities dropdown
            populateSelect(
              $citySelect,
              response.data,
              vandelLocation.strings.selectCity
            );
          } else {
            // Show error
            showNoLocations($citySelect);
          }
        },
        error: function () {
          // Show error
          showNoLocations($citySelect);
        },
      });
    });

    // City change handler
    $citySelect.on("change", function () {
      const city = $(this).val();
      const country = $countrySelect.val();

      // Reset area select and ZIP code
      resetSelects(["area"]);
      clearZipAndDetails();

      if (!city || !country || city === "loading") {
        return;
      }

      // Show loading indicator
      $areaSelect
        .empty()
        .append(
          `<option value="loading">${vandelLocation.strings.loadingAreas}...</option>`
        );
      $areaSelect.prop("disabled", true);

      // Fetch areas for selected city
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_areas",
          country: country,
          city: city,
          nonce: vandelLocation.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Populate areas dropdown
            $areaSelect
              .empty()
              .append(
                `<option value="">${vandelLocation.strings.selectArea}</option>`
              );

            if (response.data.length) {
              // Add each area with its data attributes
              $.each(response.data, function (i, area) {
                $areaSelect.append(`<option value="${area.id}" 
                                    data-zip-code="${area.zip_code}"
                                    data-price-adjustment="${area.price_adjustment}"
                                    data-service-fee="${area.service_fee}">${area.text}</option>`);
              });

              // Enable area select
              $areaSelect.prop("disabled", false);
            } else {
              // No areas available
              showNoLocations($areaSelect);
            }
          } else {
            // Show error
            showNoLocations($areaSelect);
          }
        },
        error: function () {
          // Show error
          showNoLocations($areaSelect);
        },
      });
    });

    // Area change handler
    $areaSelect.on("change", function () {
      const $selected = $(this).find("option:selected");
      const areaId = $(this).val();

      clearMessagesAndDetails();

      if (!areaId || areaId === "loading") {
        return;
      }

      // Get data from selected option
      const zipCode = $selected.data("zip-code");
      const priceAdjustment =
        parseFloat($selected.data("price-adjustment")) || 0;
      const serviceFee = parseFloat($selected.data("service-fee")) || 0;

      // Set ZIP code field
      if (zipCode) {
        $zipCodeInput.val(zipCode);
      }

      // Validate location data via AJAX
      validateLocation({
        country: $countrySelect.val(),
        city: $citySelect.val(),
        area_name: $selected.text().split(" - ")[0], // Extract area name from text
        zip_code: zipCode,
      });
    });

    // ZIP code manual entry handler
    $zipCodeInput.on("change", function () {
      const zipCode = $(this).val().trim();

      if (!zipCode) {
        clearMessagesAndDetails();
        return;
      }

      showMessage("info", vandelLocation.strings.validatingZipCode);

      // Validate ZIP code via AJAX
      validateLocation({
        zip_code: zipCode,
      });
    });

    /**
     * Validate location data via AJAX
     *
     * @param {Object} data Location data to validate
     */
    function validateLocation(data) {
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: Object.assign(
          {
            action: "vandel_validate_location",
            nonce: vandelLocation.nonce,
          },
          data
        ),
        success: function (response) {
          if (response.success) {
            // Cache location data for form submission
            $locationData.val(JSON.stringify(response.data));

            // Ensure form fields are in sync with validated data
            syncSelectsWithData(response.data);

            // Update location details display
            updateLocationDetails(response.data);

            // Enable next button
            if ($nextButton.length) {
              $nextButton.prop("disabled", false);
            }

            // Clear any validation messages
            clearMessage();
          } else {
            // Show error message
            showMessage("error", response.data.message);

            // Disable next button
            if ($nextButton.length) {
              $nextButton.prop("disabled", true);
            }
          }
        },
        error: function () {
          // Show error message
          showMessage("error", vandelLocation.strings.errorLocation);

          // Disable next button
          if ($nextButton.length) {
            $nextButton.prop("disabled", true);
          }
        },
      });
    }

    /**
     * Sync select fields with validated location data
     *
     * @param {Object} data Validated location data
     */
    function syncSelectsWithData(data) {
      // Check if country select needs updating
      if ($countrySelect.val() !== data.country) {
        $countrySelect.val(data.country);

        // Fetch cities for this country
        $.ajax({
          url: vandelLocation.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_get_cities",
            country: data.country,
            nonce: vandelLocation.nonce,
          },
          success: function (response) {
            if (response.success) {
              // Populate cities dropdown
              populateSelect(
                $citySelect,
                response.data,
                vandelLocation.strings.selectCity
              );

              // Set city value after cities are loaded
              $citySelect.val(data.city).trigger("change");
            }
          },
        });
      }
      // Check if city select needs updating (only if country is already correct)
      else if ($citySelect.val() !== data.city) {
        $citySelect.val(data.city).trigger("change");
      }
    }

    /**
     * Update location details display
     *
     * @param {Object} data Location data
     */
    function updateLocationDetails(data) {
      // Set area and city text
      $locationArea.text(data.area_name);
      $locationCity.text(`${data.city}, ${data.country}`);

      // Update price info if needed
      let priceHtml = "";

      if (data.price_adjustment && data.price_adjustment !== 0) {
        const sign = data.price_adjustment > 0 ? "+" : "";
        priceHtml += `<div class="vandel-price-adjustment">
                    <span class="vandel-price-label">${
                      vandelLocation.strings.priceAdjustment
                    }:</span> 
                    ${sign}${formatPrice(data.price_adjustment)}
                </div>`;
      }

      if (data.service_fee && data.service_fee > 0) {
        priceHtml += `<div class="vandel-service-fee">
                    <span class="vandel-price-label">${
                      vandelLocation.strings.serviceFee
                    }:</span> 
                    ${formatPrice(data.service_fee)}
                </div>`;
      }

      if (priceHtml) {
        $priceInfo.html(priceHtml).show();
      } else {
        $priceInfo.hide();
      }

      // Show location details
      $locationDetails.show();
    }

    /**
     * Show message in validation area
     *
     * @param {string} type Message type ('error' or 'info')
     * @param {string} message Message text
     */
    function showMessage(type, message) {
      const messageClass =
        type === "error" ? "vandel-error-message" : "vandel-info-message";

      $locationMessage.html(`
                <div class="${messageClass}">
                    ${
                      type === "info"
                        ? '<span class="vandel-spinner"></span>'
                        : ""
                    }
                    ${message}
                </div>
            `);
    }

    /**
     * Clear validation message
     */
    function clearMessage() {
      $locationMessage.empty();
    }

    /**
     * Clear ZIP code and location details
     */
    function clearZipAndDetails() {
      $zipCodeInput.val("");
      $locationData.val("");
      $locationDetails.hide();
      clearMessage();

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }
    }

    /**
     * Clear messages and details
     */
    function clearMessagesAndDetails() {
      clearMessage();
      $locationDetails.hide();
      $locationData.val("");

      // Disable next button
      if ($nextButton.length) {
        $nextButton.prop("disabled", true);
      }
    }

    /**
     * Reset select dropdowns
     *
     * @param {Array} selects Array of select IDs to reset
     */
    function resetSelects(selects) {
      if (selects.includes("city")) {
        $citySelect
          .empty()
          .append(
            `<option value="">${vandelLocation.strings.selectCity}</option>`
          );
        $citySelect.prop("disabled", true);
      }

      if (selects.includes("area")) {
        $areaSelect
          .empty()
          .append(
            `<option value="">${vandelLocation.strings.selectArea}</option>`
          );
        $areaSelect.prop("disabled", true);
      }
    }

    /**
     * Show "No locations available" message in a select
     *
     * @param {jQuery} $select Select element
     */
    function showNoLocations($select) {
      $select
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.noLocations}</option>`
        );
      $select.prop("disabled", true);
    }

    /**
     * Populate select dropdown with options
     *
     * @param {jQuery} $select Select element
     * @param {Array} items Array of items
     * @param {string} placeholder Placeholder text
     */
    function populateSelect($select, items, placeholder) {
      $select.empty().append(`<option value="">${placeholder}</option>`);

      if (items.length > 0) {
        $.each(items, function (i, item) {
          $select.append(`<option value="${item}">${item}</option>`);
        });

        $select.prop("disabled", false);
      } else {
        showNoLocations($select);
      }
    }

    /**
     * Format price with currency symbol
     *
     * @param {number} price Price to format
     * @return {string} Formatted price
     */
    function formatPrice(price) {
      // Get currency symbol from global settings if available
      const currencySymbol =
        typeof vandelBooking !== "undefined" && vandelBooking.currencySymbol
          ? vandelBooking.currencySymbol
          : "";

      return currencySymbol + parseFloat(price).toFixed(2);
    }
  }
})(jQuery);
