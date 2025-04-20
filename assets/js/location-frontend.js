/**
 * Location Frontend JavaScript
 * Handles location selection in the booking form
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLocationSelection();
  });

  /**
   * Initialize location selection in booking form
   */
  function initLocationSelection() {
    // Country selection
    $("#vandel-country").on("change", function () {
      const country = $(this).val();
      const $citySelect = $("#vandel-city");

      // Reset
      $citySelect.empty().prop("disabled", true);
      $("#vandel-zip-code").val("");
      $("#vandel-location-message").empty();
      $("#vandel-location-details").hide();
      $("#vandel-location-data").val("");

      if (!country) {
        $citySelect.html(
          '<option value="">' +
            vandelLocationData.strings.selectCity +
            "</option>"
        );
        return;
      }

      // Show loading
      $citySelect.html('<option value="">Loading cities...</option>');

      // Get cities for selected country
      $.ajax({
        url: vandelLocationData.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocationData.nonce,
          country: country,
        },
        success: function (response) {
          if (response.success) {
            const cities = response.data;

            $citySelect.html(
              '<option value="">' +
                vandelLocationData.strings.selectCity +
                "</option>"
            );

            cities.forEach(function (city) {
              $citySelect.append(
                '<option value="' + city + '">' + city + "</option>"
              );
            });

            $citySelect.prop("disabled", false);
          } else {
            $citySelect.html(
              '<option value="">' +
                vandelLocationData.strings.selectCity +
                "</option>"
            );
          }
        },
        error: function () {
          $citySelect.html(
            '<option value="">' +
              vandelLocationData.strings.selectCity +
              "</option>"
          );
        },
      });
    });

    // ZIP code validation
    $("#vandel-zip-code").on("blur", function () {
      const zipCode = $(this).val().trim();

      if (!zipCode) {
        return;
      }

      // Show validating message
      $("#vandel-location-message").html(
        '<span class="validating">' +
          vandelLocationData.strings.validating +
          "</span>"
      );

      // Validate ZIP code
      $.ajax({
        url: vandelLocationData.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_validate_location",
          nonce: vandelLocationData.nonce,
          zip_code: zipCode,
        },
        success: function (response) {
          if (response.success) {
            // Clear validation message
            $("#vandel-location-message").empty();

            // Set country and city
            $("#vandel-country").val(response.data.country);

            // Fetch cities for country
            $.ajax({
              url: vandelLocationData.ajaxUrl,
              type: "POST",
              data: {
                action: "vandel_get_cities",
                nonce: vandelLocationData.nonce,
                country: response.data.country,
              },
              success: function (citiesResponse) {
                if (citiesResponse.success) {
                  const cities = citiesResponse.data;
                  const $citySelect = $("#vandel-city");

                  $citySelect.html(
                    '<option value="">' +
                      vandelLocationData.strings.selectCity +
                      "</option>"
                  );

                  cities.forEach(function (city) {
                    $citySelect.append(
                      '<option value="' +
                        city +
                        '"' +
                        (city === response.data.city ? " selected" : "") +
                        ">" +
                        city +
                        "</option>"
                    );
                  });

                  $citySelect.prop("disabled", false);
                }
              },
            });

            // Show location details
            $("#vandel-area-name").text(response.data.area_name);

            // Show price adjustment if applicable
            if (response.data.price_adjustment !== 0) {
              const sign = response.data.price_adjustment > 0 ? "+" : "";
              $("#vandel-price-adjustment").text(
                sign + formatPrice(response.data.price_adjustment)
              );
              $(".vandel-price-adjustment").show();
              $(".vandel-price-info").show();
            } else {
              $(".vandel-price-adjustment").hide();
            }

            // Show service fee if applicable
            if (response.data.service_fee > 0) {
              $("#vandel-service-fee").text(
                formatPrice(response.data.service_fee)
              );
              $(".vandel-service-fee").show();
              $(".vandel-price-info").show();
            } else {
              $(".vandel-service-fee").hide();
            }

            // Show location details
            $("#vandel-location-details").slideDown();

            // Store location data in hidden field
            $("#vandel-location-data").val(JSON.stringify(response.data));
          } else {
            // Show error message
            $("#vandel-location-message").html(
              '<span class="error">' +
                (response.data.message ||
                  vandelLocationData.strings.invalidZipCode) +
                "</span>"
            );

            // Hide location details
            $("#vandel-location-details").hide();
            $("#vandel-location-data").val("");
          }
        },
        error: function () {
          // Show error message
          $("#vandel-location-message").html(
            '<span class="error">' +
              vandelLocationData.strings.invalidZipCode +
              "</span>"
          );

          // Hide location details
          $("#vandel-location-details").hide();
          $("#vandel-location-data").val("");
        },
      });
    });
  }

  /**
   * Format price for display
   *
   * @param {number} price Price to format
   * @return {string} Formatted price
   */
  function formatPrice(price) {
    // Use the WordPress currency symbol if available
    const symbol =
      typeof vandel_currency_symbol !== "undefined"
        ? vandel_currency_symbol
        : "$";

    return symbol + parseFloat(price).toFixed(2);
  }
})(jQuery);
