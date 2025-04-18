/**
 * Location Selection JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLocationSelection();
  });

  /**
   * Initialize location selection
   */
  function initLocationSelection() {
    const $countrySelect = $("#vandel-country");
    const $citySelect = $("#vandel-city");
    const $areaSelect = $("#vandel-area");
    const $zipInput = $("#vandel-zip-code");
    const $locationMessage = $("#vandel-location-message");
    const $locationDetails = $("#vandel-location-details");
    const $locationData = $("#vandel-location-data");

    // Check if elements exist
    if (!$countrySelect.length || !$citySelect.length || !$areaSelect.length) {
      return;
    }

    // Country selection change
    $countrySelect.on("change", function () {
      const country = $(this).val();

      // Reset other fields
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
      $locationDetails.hide();
      $locationMessage.empty();
      $locationData.val("");

      if (!country) {
        return;
      }

      // Show loading state
      $citySelect.html(
        `<option value="">${vandelLocation.strings.loadingCities}</option>`
      );

      // Get cities for selected country
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
            const cities = response.data;

            $citySelect
              .empty()
              .append(
                `<option value="">${vandelLocation.strings.selectCity}</option>`
              );

            cities.forEach(function (city) {
              $citySelect.append(`<option value="${city}">${city}</option>`);
            });

            $citySelect.prop("disabled", false);
          } else {
            $citySelect.html(
              `<option value="">${
                response.data.message || vandelLocation.strings.noLocations
              }</option>`
            );
          }
        },
        error: function () {
          $citySelect.html(
            `<option value="">${vandelLocation.strings.noLocations}</option>`
          );
        },
      });
    });

    // City selection change
    $citySelect.on("change", function () {
      const city = $(this).val();
      const country = $countrySelect.val();

      // Reset area field
      $areaSelect
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.selectArea}</option>`
        )
        .prop("disabled", true);
      $locationDetails.hide();
      $locationMessage.empty();
      $locationData.val("");

      if (!city || !country) {
        return;
      }

      // Show loading state
      $areaSelect.html(
        `<option value="">${vandelLocation.strings.loadingAreas}</option>`
      );

      // Get areas for selected city
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
            const areas = response.data;

            $areaSelect
              .empty()
              .append(
                `<option value="">${vandelLocation.strings.selectArea}</option>`
              );

            areas.forEach(function (area) {
              $areaSelect.append(
                `<option value="${area.id}" data-zip="${area.zip_code}" data-price="${area.price_adjustment}" data-fee="${area.service_fee}">${area.area_name}</option>`
              );
            });

            $areaSelect.prop("disabled", false);
          } else {
            $areaSelect.html(
              `<option value="">${
                response.data.message || vandelLocation.strings.noLocations
              }</option>`
            );
          }
        },
        error: function () {
          $areaSelect.html(
            `<option value="">${vandelLocation.strings.noLocations}</option>`
          );
        },
      });
    });

    // Area selection change
    $areaSelect.on("change", function () {
      const $selectedOption = $(this).find("option:selected");
      const areaId = $(this).val();

      if (!areaId) {
        $locationDetails.hide();
        $locationData.val("");
        return;
      }

      // Get area data from selected option
      const zipCode = $selectedOption.data("zip");
      const priceAdjustment = parseFloat($selectedOption.data("price") || 0);
      const serviceFee = parseFloat($selectedOption.data("fee") || 0);
      const areaName = $selectedOption.text();
      const city = $citySelect.val();
      const country = $countrySelect.val();

      // Update ZIP code field
      $zipInput.val(zipCode);

      // Update location details display
      $("#vandel-location-area").text(areaName);
      $("#vandel-location-city").text(`${city}, ${country}`);

      const locationData = {
        id: areaId,
        country: country,
        city: city,
        area_name: areaName,
        zip_code: zipCode,
        price_adjustment: priceAdjustment,
        service_fee: serviceFee,
      };

      // Store location data in hidden input
      $locationData.val(JSON.stringify(locationData));

      // Update price info if available
      const $priceInfo = $("#vandel-price-info");
      let priceInfoContent = "";

      if (priceAdjustment !== 0 || serviceFee > 0) {
        if (priceAdjustment !== 0) {
          const sign = priceAdjustment > 0 ? "+" : "";
          priceInfoContent += `<div>${
            vandelLocation.strings.priceAdjustment
          }: ${sign}${formatPrice(priceAdjustment)}</div>`;
        }

        if (serviceFee > 0) {
          priceInfoContent += `<div>${
            vandelLocation.strings.serviceFee
          }: ${formatPrice(serviceFee)}</div>`;
        }

        $priceInfo.html(priceInfoContent).show();
      } else {
        $priceInfo.hide();
      }

      // Show location details
      $locationDetails.show();

      // Recalculate total price if function exists
      if (typeof calculateTotalPrice === "function") {
        calculateTotalPrice();
      }
    });

    // ZIP code input change - validate and find area
    $zipInput.on("change", function () {
      const zipCode = $(this).val();

      if (!zipCode) {
        return;
      }
      // If we already have location data, skip validation
      if ($locationData.val()) {
        return;
      }

      // Validate ZIP code and find matching area
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_validate_location",
          zip_code: zipCode,
          nonce: vandelLocation.nonce,
        },
        success: function (response) {
          if (response.success) {
            const locationData = response.data;

            // Update form selections if possible
            if (
              $countrySelect.find(`option[value="${locationData.country}"]`)
                .length
            ) {
              $countrySelect.val(locationData.country).trigger("change");

              // Need to wait for cities to load
              const cityCheckInterval = setInterval(function () {
                if (
                  $citySelect.find(`option[value="${locationData.city}"]`)
                    .length
                ) {
                  clearInterval(cityCheckInterval);
                  $citySelect.val(locationData.city).trigger("change");

                  // Need to wait for areas to load
                  const areaCheckInterval = setInterval(function () {
                    if (
                      $areaSelect.find(
                        `option:contains("${locationData.area_name}")`
                      ).length
                    ) {
                      clearInterval(areaCheckInterval);

                      // Find and select the matching area
                      $areaSelect.find("option").each(function () {
                        if ($(this).text() === locationData.area_name) {
                          $areaSelect.val($(this).val()).trigger("change");
                          return false;
                        }
                      });
                    }
                  }, 200);
                }
              }, 200);
            } else {
              // If we can't update the form selections, just show the location details
              $("#vandel-location-area").text(locationData.area_name);
              $("#vandel-location-city").text(
                `${locationData.city}, ${locationData.country}`
              );

              // Update price info if available
              const $priceInfo = $("#vandel-price-info");
              let priceInfoContent = "";

              if (
                locationData.price_adjustment !== 0 ||
                locationData.service_fee > 0
              ) {
                if (locationData.price_adjustment !== 0) {
                  const sign = locationData.price_adjustment > 0 ? "+" : "";
                  priceInfoContent += `<div>${
                    vandelLocation.strings.priceAdjustment
                  }: ${sign}${formatPrice(
                    locationData.price_adjustment
                  )}</div>`;
                }

                if (locationData.service_fee > 0) {
                  priceInfoContent += `<div>${
                    vandelLocation.strings.serviceFee
                  }: ${formatPrice(locationData.service_fee)}</div>`;
                }

                $priceInfo.html(priceInfoContent).show();
              } else {
                $priceInfo.hide();
              }

              // Store location data in hidden input
              $locationData.val(JSON.stringify(locationData));

              // Show location details
              $locationDetails.show();

              // Recalculate total price if function exists
              if (typeof calculateTotalPrice === "function") {
                calculateTotalPrice();
              }
            }
          } else {
            $locationMessage.html(
              `<div class="vandel-error">${response.data.message}</div>`
            );
            $locationDetails.hide();
            $locationData.val("");
          }
        },
        error: function () {
          $locationMessage.html(
            `<div class="vandel-error">${vandelLocation.strings.error}</div>`
          );
          $locationDetails.hide();
          $locationData.val("");
        },
      });
    });

    /**
     * Format price with currency symbol
     */
    function formatPrice(price) {
      // Use booking form currency symbol if available
      const currencySymbol = window.vandelBooking
        ? vandelBooking.currencySymbol
        : "€";
      return currencySymbol + " " + parseFloat(price).toFixed(2);
    }
  }
})(jQuery);
