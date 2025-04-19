/**
 * Location Selection JavaScript (v2)
 * Combines original feature‑complete logic with the new improvements
 * – custom "populated" events instead of arbitrary timeouts
 * – single reset / hide helpers for cleaner UI resets
 * – DRY helpers for price + details updates
 * – retains full area & ZIP validation flows
 */
(function ($) {
  "use strict";

  $(document).ready(initLocationSelection);

  function initLocationSelection() {
    /* ---------------------------------------------------------------------
     * Cached selectors (use same IDs & classes as template/HTML)
     * ------------------------------------------------------------------ */
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

    /* ------------------------------------------------------------------ */
    /* 1. Initial state                                                   */
    /* ------------------------------------------------------------------ */
    disableSelect($citySelect);
    disableSelect($areaSelect);
    disableNextBtn();

    /* ------------------------------------------------------------------ */
    /* 2. Country change                                                  */
    /* ------------------------------------------------------------------ */
    $countrySelect.on("change", function () {
      const country = $(this).val();

      resetSelects(["city", "area"]);
      clearZipAndDetails();

      if (!country) return;

      setLoading($citySelect, vandelLocation.strings.loadingCities);
      loadCities(country);
    });

    /* ------------------------------------------------------------------ */
    /* 3. City change                                                     */
    /* ------------------------------------------------------------------ */
    $citySelect.on("change", function () {
      const city = $(this).val();
      const country = $countrySelect.val();

      resetSelects(["area"]);
      clearZipAndDetails();

      if (!city || !country || city === "loading") return;

      setLoading($areaSelect, vandelLocation.strings.loadingAreas);
      loadAreas(country, city);
    });

    /* ------------------------------------------------------------------ */
    /* 4. Area change (validate location)                                 */
    /* ------------------------------------------------------------------ */
    $areaSelect.on("change", function () {
      const area = $(this).val();
      const city = $citySelect.val();
      const country = $countrySelect.val();

      clearZipAndDetails();

      if (!area || !city || !country || area === "loading") return;

      showMessage("info", vandelLocation.strings.validatingLocation);
      validateLocation({ country, city, area_name: area });
    });

    /* ------------------------------------------------------------------ */
    /* 5. ZIP code change (direct validation)                             */
    /* ------------------------------------------------------------------ */
    $zipCodeInput.on("change", function () {
      const zipCode = $(this).val().trim();
      clearMessagesAndDetails();

      if (!zipCode) return;

      showMessage("info", vandelLocation.strings.validatingZipCode);
      validateLocation({ zip_code: zipCode });
    });

    /* ==================================================================
     *  Helper: AJAX loaders
     * ================================================================= */

    function loadCities(country) {
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocation.nonce,
          country,
        },
        success: (response) => {
          populateSelect(
            $citySelect,
            response.data,
            vandelLocation.strings.selectCity
          );
        },
        error: () => {
          showNoLocations($citySelect, vandelLocation.strings.selectCity);
        },
      });
    }

    function loadAreas(country, city) {
      $.ajax({
        url: vandelLocation.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_areas",
          nonce: vandelLocation.nonce,
          country,
          city,
        },
        success: (response) => {
          populateSelect(
            $areaSelect,
            response.data,
            vandelLocation.strings.selectArea
          );
        },
        error: () => {
          showNoLocations($areaSelect, vandelLocation.strings.selectArea);
        },
      });
    }

    /**
     * Validate by area OR zip. `data` must contain either zip_code OR country+city+area_name
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
        success: (response) => {
          if (!response.success) {
            showMessage("error", response.data.message);
            disableNextBtn();
            return;
          }

          // Cache hidden JSON so next step can re‑use the data
          $locationData.val(JSON.stringify(response.data));

          // Sync selects if validation came from ZIP or mismatched state
          syncSelectsToValidated(response.data);

          // Apply ZIP suggestion if available
          if (response.data.zip_code) {
            $zipCodeInput.val(response.data.zip_code);
          }

          // UI – show details & price info
          updateLocationDetails(response.data);

          $locationDetails.slideDown();
          enableNextBtn();
          clearMessage();
        },
        error: () => {
          showMessage("error", vandelLocation.strings.errorLocation);
          disableNextBtn();
        },
      });
    }

    /* ==================================================================
     *  Helper: select population & reset
     * ================================================================= */

    function populateSelect($select, items = [], placeholder) {
      $select.empty().append(`<option value="">${placeholder}</option>`);

      if (items.length) {
        items.forEach((i) =>
          $select.append(`<option value="${i}">${i}</option>`)
        );
        $select.prop("disabled", false).trigger("populated");
      } else {
        $select.append(
          `<option value="">${vandelLocation.strings.noLocations}</option>`
        );
        disableSelect($select);
      }
    }

    function setLoading($select, text) {
      $select.empty().append(`<option value="loading">${text}</option>`);
      disableSelect($select, true);
    }

    function showNoLocations($select, placeholder) {
      $select
        .empty()
        .append(
          `<option value="">${vandelLocation.strings.noLocations}</option>`
        );
      disableSelect($select);
    }

    function resetSelects(list) {
      if (list.includes("city")) {
        $citySelect
          .empty()
          .append(
            `<option value="">${vandelLocation.strings.selectCity}</option>`
          );
        disableSelect($citySelect);
      }
      if (list.includes("area")) {
        $areaSelect
          .empty()
          .append(
            `<option value="">${vandelLocation.strings.selectArea}</option>`
          );
        disableSelect($areaSelect);
      }
    }

    function disableSelect($el, keepValue = false) {
      $el.prop("disabled", true);
      if (!keepValue) $el.val("");
    }

    /* ==================================================================
     *  Helper: message & buttons
     * ================================================================= */

    function showMessage(type, msg) {
      const cls =
        type === "error" ? "vandel-error-message" : "vandel-info-message";
      $locationMessage.html(
        `<div class="${cls}">${
          type === "info" ? '<span class="vandel-spinner"></span> ' : ""
        }${msg}</div>`
      );
    }

    function clearMessage() {
      $locationMessage.empty();
    }

    function clearZipAndDetails() {
      $zipCodeInput.val("");
      clearMessagesAndDetails();
    }

    function clearMessagesAndDetails() {
      clearMessage();
      hideDetails();
      disableNextBtn();
    }

    function hideDetails() {
      $locationDetails.slideUp();
      $priceInfo.slideUp();
      $locationData.val("");
    }

    function enableNextBtn() {
      if ($nextButton.length) $nextButton.prop("disabled", false);
    }

    function disableNextBtn() {
      if ($nextButton.length) $nextButton.prop("disabled", true);
    }

    /* ==================================================================
     *  Helper: sync selects if validation began with ZIP
     * ================================================================= */

    function syncSelectsToValidated(data) {
      if ($countrySelect.val() !== data.country) {
        $countrySelect.val(data.country).trigger("change");
        $citySelect.one("populated", () => {
          $citySelect.val(data.city).trigger("change");
          $areaSelect.one("populated", () => $areaSelect.val(data.area_name));
        });
      } else if ($citySelect.val() !== data.city) {
        $citySelect.val(data.city).trigger("change");
        $areaSelect.one("populated", () => $areaSelect.val(data.area_name));
      } else if ($areaSelect.val() !== data.area_name) {
        $areaSelect.val(data.area_name);
      }
    }

    /* ==================================================================
     *  Helper: details & price display
     * ================================================================= */

    function updateLocationDetails(data) {
      if (data.area_name) $locationArea.text(data.area_name);
      if (data.city)
        $locationCity.text(
          `${data.city}${data.country ? ", " + data.country : ""}`
        );

      // price info
      let html = "";
      if (data.price_adjustment && parseFloat(data.price_adjustment) !== 0) {
        const sign = parseFloat(data.price_adjustment) > 0 ? "+" : "";
        html += `<div class="vandel-price-adjustment"><span class="vandel-price-label">${
          vandelLocation.strings.priceAdjustment
        }:</span> ${sign}${formatPrice(data.price_adjustment)}</div>`;
      }
      if (data.service_fee && parseFloat(data.service_fee) > 0) {
        html += `<div class="vandel-service-fee"><span class="vandel-price-label">${
          vandelLocation.strings.serviceFee
        }:</span> ${formatPrice(data.service_fee)}</div>`;
      }
      if (html) {
        $priceInfo.html(html).slideDown();
      } else {
        $priceInfo.slideUp();
      }
    }

    function formatPrice(price) {
      const symbol =
        typeof vandelBooking !== "undefined" && vandelBooking.currencySymbol
          ? vandelBooking.currencySymbol
          : "$";
      return symbol + parseFloat(price).toFixed(2);
    }
  }
})(jQuery);
