/**
 * Location Selector Component
 * Implements hierarchical selection of Country > City > Area with ZIP code
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    initLocationSelectors();
  });

  /**
   * Initialize location selectors with dynamic loading
   */
  function initLocationSelectors() {
    const $countrySelect = $("#vandel-country");
    const $citySelect = $("#vandel-city");
    const $areaSelect = $("#vandel-area");

    // Only continue if the selectors exist on the page
    if (!$countrySelect.length || !$citySelect.length || !$areaSelect.length) {
      return;
    }

    // Handle country change
    $countrySelect.on("change", function () {
      const country = $(this).val();

      // Reset city and area selects
      $citySelect
        .empty()
        .append(
          '<option value="">' + vandelBooking.strings.selectCity + "</option>"
        );
      $citySelect.prop("disabled", !country);

      $areaSelect
        .empty()
        .append(
          '<option value="">' + vandelBooking.strings.selectArea + "</option>"
        );
      $areaSelect.prop("disabled", true);

      if (!country) {
        return;
      }

      // Show loading indicator
      $citySelect
        .empty()
        .append(
          '<option value="">' +
            vandelBooking.strings.loadingCities +
            "...</option>"
        );

      // Fetch cities for selected country
      $.ajax({
        url: vandelBooking.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          country: country,
          nonce: vandelBooking.nonce,
        },
        success: function (response) {
          if (response.success) {
            const cities = response.data;

            // Populate city dropdown
            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  vandelBooking.strings.selectCity +
                  "</option>"
              );
            $.each(cities, function (index, city) {
              $citySelect.append(
                '<option value="' + city + '">' + city + "</option>"
              );
            });

            // Enable city select
            $citySelect.prop("disabled", false);
          } else {
            // Reset on error
            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  vandelBooking.strings.selectCity +
                  "</option>"
              );
            console.error("Error loading cities:", response.data);
          }
        },
        error: function () {
          // Reset on AJAX error
          $citySelect
            .empty()
            .append(
              '<option value="">' +
                vandelBooking.strings.selectCity +
                "</option>"
            );
          console.error("AJAX error when loading cities");
        },
      });
    });

    // Handle city change
    $citySelect.on("change", function () {
      const city = $(this).val();
      const country = $countrySelect.val();

      // Reset area select
      $areaSelect
        .empty()
        .append(
          '<option value="">' + vandelBooking.strings.selectArea + "</option>"
        );
      $areaSelect.prop("disabled", !city);

      if (!country || !city) {
        return;
      }

      // Show loading indicator
      $areaSelect
        .empty()
        .append(
          '<option value="">' +
            vandelBooking.strings.loadingAreas +
            "...</option>"
        );

      // Fetch areas for selected city
      $.ajax({
        url: vandelBooking.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_areas",
          country: country,
          city: city,
          nonce: vandelBooking.nonce,
        },
        success: function (response) {
          if (response.success) {
            const areas = response.data;

            // Populate area dropdown
            $areaSelect
              .empty()
              .append(
                '<option value="">' +
                  vandelBooking.strings.selectArea +
                  "</option>"
              );
            $.each(areas, function (index, area) {
              $areaSelect.append(
                '<option value="' +
                  area.id +
                  '" data-zip-code="' +
                  area.zip_code +
                  '" data-price-adjustment="' +
                  area.price_adjustment +
                  '" data-service-fee="' +
                  area.service_fee +
                  '">' +
                  area.text +
                  "</option>"
              );
            });

            // Enable area select
            $areaSelect.prop("disabled", false);
          } else {
            // Reset on error
            $areaSelect
              .empty()
              .append(
                '<option value="">' +
                  vandelBooking.strings.selectArea +
                  "</option>"
              );
            console.error("Error loading areas:", response.data);
          }
        },
        error: function () {
          // Reset on AJAX error
          $areaSelect
            .empty()
            .append(
              '<option value="">' +
                vandelBooking.strings.selectArea +
                "</option>"
            );
          console.error("AJAX error when loading areas");
        },
      });
    });

    // Handle area change - update pricing, show area info
    $areaSelect.on("change", function () {
      const $selectedOption = $(this).find("option:selected");
      const areaId = $(this).val();

      if (!areaId) {
        // Reset price adjustments and area info
        resetPriceAdjustments();
        hideAreaInfo();
        return;
      }

      // Get area details from data attributes
      const zipCode = $selectedOption.data("zip-code");
      const priceAdjustment = parseFloat(
        $selectedOption.data("price-adjustment")
      );
      const serviceFee = parseFloat($selectedOption.data("service-fee"));

      // Update hidden fields
      $("#vandel-selected-area-id").val(areaId);
      $("#vandel-selected-zip-code").val(zipCode);

      // Update price adjustments
      updatePriceAdjustments(priceAdjustment, serviceFee);

      // Show area info
      showAreaInfo(zipCode, priceAdjustment, serviceFee);
    });

    /**
     * Update price adjustments based on selected area
     */
    function updatePriceAdjustments(priceAdjustment, serviceFee) {
      // Get base price
      const basePrice = parseFloat($("#vandel-base-price").val() || 0);

      // Calculate total price
      const totalPrice = basePrice + priceAdjustment + serviceFee;

      // Update price display
      if (priceAdjustment !== 0) {
        const sign = priceAdjustment > 0 ? "+" : "";
        $("#vandel-price-adjustment-display")
          .html(sign + formatPrice(priceAdjustment))
          .show();
      } else {
        $("#vandel-price-adjustment-display").hide();
      }

      if (serviceFee > 0) {
        $("#vandel-service-fee-display").html(formatPrice(serviceFee)).show();
      } else {
        $("#vandel-service-fee-display").hide();
      }

      // Update total price
      $("#vandel-total-price-display").html(formatPrice(totalPrice));
      $("#vandel-total-price").val(totalPrice);

      // Show price breakdown if adjustments exist
      if (priceAdjustment !== 0 || serviceFee > 0) {
        $(".vandel-price-breakdown").show();
      } else {
        $(".vandel-price-breakdown").hide();
      }
    }

    /**
     * Reset price adjustments
     */
    function resetPriceAdjustments() {
      // Get base price
      const basePrice = parseFloat($("#vandel-base-price").val() || 0);

      // Hide adjustments
      $("#vandel-price-adjustment-display").hide();
      $("#vandel-service-fee-display").hide();
      $(".vandel-price-breakdown").hide();

      // Reset total to base price
      $("#vandel-total-price-display").html(formatPrice(basePrice));
      $("#vandel-total-price").val(basePrice);

      // Clear hidden fields
      $("#vandel-selected-area-id").val("");
      $("#vandel-selected-zip-code").val("");
    }

    /**
     * Show area info with pricing details
     */
    function showAreaInfo(zipCode, priceAdjustment, serviceFee) {
      const $areaInfo = $(".vandel-area-info");

      // Build HTML for area info
      let html =
        "<strong>" +
        vandelBooking.strings.selectedArea +
        "</strong>: " +
        zipCode;

      if (priceAdjustment !== 0 || serviceFee > 0) {
        html += '<div class="vandel-area-pricing">';

        if (priceAdjustment !== 0) {
          const sign = priceAdjustment > 0 ? "+" : "";
          html +=
            '<div><span class="vandel-label">' +
            vandelBooking.strings.priceAdjustment +
            ":</span> " +
            sign +
            formatPrice(priceAdjustment) +
            "</div>";
        }

        if (serviceFee > 0) {
          html +=
            '<div><span class="vandel-label">' +
            vandelBooking.strings.serviceFee +
            ":</span> " +
            formatPrice(serviceFee) +
            "</div>";
        }

        html += "</div>";
      }

      // Update and show area info
      $areaInfo.html(html).show();
    }

    /**
     * Hide area info
     */
    function hideAreaInfo() {
      $(".vandel-area-info").hide();
    }

    /**
     * Format price based on locale
     *
     * @param {number} price Price to format
     * @return {string} Formatted price
     */
    function formatPrice(price) {
      const currencySymbol = vandelBooking.currencySymbol || "€";

      // Format number with 2 decimal places
      const formattedPrice = parseFloat(price).toFixed(2).replace(".", ",");

      return currencySymbol + " " + formattedPrice;
    }
  }
})(jQuery);
