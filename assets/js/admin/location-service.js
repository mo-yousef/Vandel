/**
 * Location Service JavaScript
 * Handles interactions with the Location Service API
 */
(function ($) {
  ("use strict");

  $(document).ready(function () {
    // Country management
    initCountryManagement();

    // City management
    initCityManagement();

    // ZIP code management
    initZipCodeManagement();

    // Import functionality
    initImportFunctionality();
  });

  /**
   * Initialize country management functionality
   */
  function initCountryManagement() {
    // Add country form submission
    $("#add-country-form").on("submit", function (e) {
      e.preventDefault();

      const countryName = $("#country-name").val().trim();

      if (!countryName) {
        alert(vandelLocationService.strings.error);
        return;
      }

      const $submitBtn = $(this).find('button[type="submit"]');
      $submitBtn
        .prop("disabled", true)
        .text(vandelLocationService.strings.processing);

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_add_country",
          nonce: vandelLocationService.nonce,
          country: countryName,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(response.data.message || vandelLocationService.strings.error);
            $submitBtn.prop("disabled", false).text("Add Country");
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
          $submitBtn.prop("disabled", false).text("Add Country");
        },
      });
    });

    // Country toggle
    $(".country-toggle").on("change", function () {
      const country = $(this).data("country");
      const isActive = $(this).is(":checked");

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_toggle_location",
          nonce: vandelLocationService.nonce,
          type: "country",
          value: country,
          active: isActive,
        },
        success: function (response) {
          if (!response.success) {
            alert(response.data.message || vandelLocationService.strings.error);
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
        },
      });
    });

    // Show cities button
    $(".show-cities-btn").on("click", function (e) {
      e.preventDefault();

      const country = $(this).data("country");
      $("#country-select").val(country).trigger("change");

      // Scroll to cities section
      $("html, body").animate(
        {
          scrollTop: $("#cities-container").offset().top - 50,
        },
        500
      );
    });
  }

  /**
   * Initialize city management functionality
   */
  function initCityManagement() {
    // Country select change
    $("#country-select").on("change", function () {
      const country = $(this).val();

      if (!country) {
        $("#cities-container").html(
          '<p class="vandel-empty-state">Select a country to manage its cities</p>'
        );
        return;
      }

      // Show loading message
      $("#cities-container").html(
        '<p class="vandel-loading">' +
          vandelLocationService.strings.fetchingCities +
          "</p>"
      );

      // Fetch cities for selected country
      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocationService.nonce,
          country: country,
        },
        success: function (response) {
          if (response.success) {
            displayCities(country, response.data);
          } else {
            $("#cities-container").html(
              '<p class="vandel-error">' +
                (response.data.message || vandelLocationService.strings.error) +
                "</p>"
            );
          }
        },
        error: function () {
          $("#cities-container").html(
            '<p class="vandel-error">' +
              vandelLocationService.strings.error +
              "</p>"
          );
        },
      });
    });
  }

  /**
   * Initialize ZIP code management functionality
   */
  function initZipCodeManagement() {
    // Country select change
    $("#zipcode-country").on("change", function () {
      const country = $(this).val();
      const $citySelect = $("#zipcode-city");

      // Reset
      $citySelect
        .empty()
        .append(
          '<option value="">' +
            (country ? "Loading cities..." : "-- Select City --") +
            "</option>"
        );
      $citySelect.prop("disabled", !country);
      $("#load-zipcodes-btn").prop("disabled", true);
      $("#zipcodes-container").html(
        '<p class="vandel-empty-state">Select a country and city to manage ZIP codes</p>'
      );

      if (!country) {
        return;
      }

      // Fetch cities for selected country
      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocationService.nonce,
          country: country,
        },
        success: function (response) {
          if (response.success) {
            const cities = response.data;

            $citySelect
              .empty()
              .append('<option value="">-- Select City --</option>');

            if (cities.length === 0) {
              $citySelect.append(
                '<option value="">' +
                  vandelLocationService.strings.noResults +
                  "</option>"
              );
              return;
            }

            cities.forEach(function (city) {
              $citySelect.append(
                '<option value="' + city + '">' + city + "</option>"
              );
            });

            $citySelect.prop("disabled", false);
          } else {
            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  (response.data.message ||
                    vandelLocationService.strings.error) +
                  "</option>"
              );
          }
        },
        error: function () {
          $citySelect
            .empty()
            .append(
              '<option value="">' +
                vandelLocationService.strings.error +
                "</option>"
            );
        },
      });
    });

    // City select change
    $("#zipcode-city").on("change", function () {
      const city = $(this).val();
      $("#load-zipcodes-btn").prop("disabled", !city);
    });

    // Load ZIP codes button click
    $("#load-zipcodes-btn").on("click", function () {
      const country = $("#zipcode-country").val();
      const city = $("#zipcode-city").val();

      if (!country || !city) {
        return;
      }

      // Show loading message
      $("#zipcodes-container").html(
        '<p class="vandel-loading">Loading ZIP codes...</p>'
      );

      // Fetch ZIP codes for selected city
      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_zipcodes",
          nonce: vandelLocationService.nonce,
          country: country,
          city: city,
        },
        success: function (response) {
          if (response.success) {
            displayZipCodes(country, city, response.data);
          } else {
            $("#zipcodes-container").html(
              '<p class="vandel-error">' +
                (response.data.message || vandelLocationService.strings.error) +
                "</p>"
            );
          }
        },
        error: function () {
          $("#zipcodes-container").html(
            '<p class="vandel-error">' +
              vandelLocationService.strings.error +
              "</p>"
          );
        },
      });
    });
  }

  /**
   * Initialize import functionality
   */
  function initImportFunctionality() {
    // Country select change for import form
    $("#import-country").on("change", function () {
      const country = $(this).val();
      const $citySelect = $("#import-city");

      // Reset
      $citySelect
        .empty()
        .append(
          '<option value="">' +
            (country ? "Loading cities..." : "-- Select City --") +
            "</option>"
        );
      $citySelect.prop("disabled", !country);

      if (!country) {
        return;
      }

      // Fetch cities for selected country
      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_get_cities",
          nonce: vandelLocationService.nonce,
          country: country,
        },
        success: function (response) {
          if (response.success) {
            const cities = response.data;

            $citySelect
              .empty()
              .append('<option value="">-- Select City --</option>');

            if (cities.length === 0) {
              $citySelect.append(
                '<option value="">' +
                  vandelLocationService.strings.noResults +
                  "</option>"
              );
              return;
            }

            cities.forEach(function (city) {
              $citySelect.append(
                '<option value="' + city + '">' + city + "</option>"
              );
            });

            $citySelect.prop("disabled", false);
          } else {
            $citySelect
              .empty()
              .append(
                '<option value="">' +
                  (response.data.message ||
                    vandelLocationService.strings.error) +
                  "</option>"
              );
          }
        },
        error: function () {
          $citySelect
            .empty()
            .append(
              '<option value="">' +
                vandelLocationService.strings.error +
                "</option>"
            );
        },
      });
    });

    // Import form submission
    $("#import-zipcodes-form").on("submit", function (e) {
      e.preventDefault();

      const country = $("#import-country").val();
      const city = $("#import-city").val();
      const file = $("#zipcode-file")[0].files[0];

      if (!country || !city || !file) {
        alert("Please fill all required fields");
        return;
      }

      const formData = new FormData();
      formData.append("action", "vandel_import_zipcodes");
      formData.append("nonce", vandelLocationService.nonce);
      formData.append("country", country);
      formData.append("city", city);
      formData.append("zipcode_file", file);

      const $submitBtn = $(this).find('button[type="submit"]');
      $submitBtn
        .prop("disabled", true)
        .text(vandelLocationService.strings.importingZipCodes);

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(response.data.message || vandelLocationService.strings.error);
            $submitBtn.prop("disabled", false).text("Import ZIP Codes");
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
          $submitBtn.prop("disabled", false).text("Import ZIP Codes");
        },
      });
    });

    // Generate sample file
    $(".generate-sample").on("click", function (e) {
      e.preventDefault();

      const country = $(this).data("country");

      // TODO: Implement sample file generation or download
      alert("Sample file generation will be implemented");
    });
  }

  /**
   * Display cities for a country
   *
   * @param {string} country Country name
   * @param {Array} cities List of cities
   */
  function displayCities(country, cities) {
    const $container = $("#cities-container");

    if (cities.length === 0) {
      $container.html(
        '<p class="vandel-empty-state">' +
          vandelLocationService.strings.noResults +
          "</p>"
      );
      return;
    }

    let html = '<div class="vandel-cities-wrapper">';
    html += "<h3>Cities in " + country + "</h3>";

    // Add new city form
    html += '<div class="vandel-add-city-form">';
    html += "<h4>Add New City</h4>";
    html += '<div class="vandel-form-row">';
    html += '<div class="vandel-form-group">';
    html +=
      '<input type="text" id="new-city-name" placeholder="Enter city name" required>';
    html += "</div>";
    html += '<div class="vandel-form-submit">';
    html +=
      '<button type="button" class="button add-city-btn" data-country="' +
      country +
      '">Add City</button>';
    html += "</div>";
    html += "</div>";
    html += "</div>";

    // City list
    html += '<div class="vandel-city-list">';
    html += "<h4>Available Cities</h4>";
    html += '<ul class="vandel-checkbox-list">';

    cities.forEach(function (city) {
      html += "<li>";
      html += "<label>";
      html +=
        '<input type="checkbox" class="city-toggle" data-country="' +
        country +
        '" data-city="' +
        city +
        '">';
      html += city;
      html += "</label>";
      html += "</li>";
    });

    html += "</ul>";
    html += "</div>";
    html += "</div>";

    $container.html(html);

    // Add event handlers for the new elements

    // Add city button
    $(".add-city-btn").on("click", function () {
      const country = $(this).data("country");
      const cityName = $("#new-city-name").val().trim();

      if (!cityName) {
        alert("Please enter a city name");
        return;
      }

      $(this)
        .prop("disabled", true)
        .text(vandelLocationService.strings.processing);

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_add_city",
          nonce: vandelLocationService.nonce,
          country: country,
          city: cityName,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            // Refresh cities list
            $("#country-select").trigger("change");
          } else {
            alert(response.data.message || vandelLocationService.strings.error);
            $(".add-city-btn").prop("disabled", false).text("Add City");
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
          $(".add-city-btn").prop("disabled", false).text("Add City");
        },
      });
    });

    // City toggle
    $(".city-toggle").on("change", function () {
      const country = $(this).data("country");
      const city = $(this).data("city");
      const isActive = $(this).is(":checked");

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_toggle_location",
          nonce: vandelLocationService.nonce,
          type: "city",
          country: country,
          value: city,
          active: isActive,
        },
        success: function (response) {
          if (!response.success) {
            alert(response.data.message || vandelLocationService.strings.error);
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
        },
      });
    });
  }

  /**
   * Display ZIP codes for a city
   *
   * @param {string} country Country name
   * @param {string} city City name
   * @param {Array} zipCodes List of ZIP codes
   */
  function displayZipCodes(country, city, zipCodes) {
    const $container = $("#zipcodes-container");

    if (zipCodes.length === 0) {
      $container.html(
        '<p class="vandel-empty-state">' +
          vandelLocationService.strings.noResults +
          "</p>"
      );
      return;
    }

    let html = '<div class="vandel-zipcodes-wrapper">';
    html += "<h3>ZIP Codes in " + city + ", " + country + "</h3>";

    // ZIP codes table
    html += '<table class="wp-list-table widefat fixed striped">';
    html += "<thead>";
    html += "<tr>";
    html += "<th>ZIP Code</th>";
    html += "<th>Area Name</th>";
    html += "<th>Price Adjustment</th>";
    html += "<th>Service Fee</th>";
    html += "<th>Status</th>";
    html += "<th>Actions</th>";
    html += "</tr>";
    html += "</thead>";
    html += "<tbody>";

    zipCodes.forEach(function (zipCode) {
      html += '<tr data-id="' + zipCode.id + '">';
      html += "<td>" + zipCode.zip_code + "</td>";
      html += "<td>" + zipCode.area_name + "</td>";
      html += "<td>" + (zipCode.price_adjustment || 0) + "</td>";
      html += "<td>" + (zipCode.service_fee || 0) + "</td>";
      html += "<td>";
      html += '<label class="toggle-switch">';
      html +=
        '<input type="checkbox" class="zipcode-toggle" data-zipcode="' +
        zipCode.zip_code +
        '" data-id="' +
        zipCode.id +
        '"' +
        (zipCode.is_active === "yes" ? " checked" : "") +
        ">";
      html += '<span class="toggle-slider"></span>';
      html += "</label>";
      html += "</td>";
      html += "<td>";
      html +=
        '<button type="button" class="button button-small edit-zipcode-btn" data-id="' +
        zipCode.id +
        '">Edit</button>';
      html += "</td>";
      html += "</tr>";
    });

    html += "</tbody>";
    html += "</table>";
    html += "</div>";

    // Add edit modal
    html +=
      '<div id="edit-zipcode-modal" class="vandel-modal" style="display:none;">';
    html += '<div class="vandel-modal-content">';
    html += '<span class="vandel-modal-close">&times;</span>';
    html += "<h3>Edit ZIP Code</h3>";
    html += '<form id="edit-zipcode-form">';
    html += '<input type="hidden" id="edit-zipcode-id">';

    html += '<div class="vandel-form-row">';
    html += '<div class="vandel-form-group">';
    html += '<label for="edit-zipcode-area">Area Name</label>';
    html += '<input type="text" id="edit-zipcode-area" required>';
    html += "</div>";
    html += "</div>";

    html += '<div class="vandel-form-row">';
    html += '<div class="vandel-form-group">';
    html += '<label for="edit-zipcode-price">Price Adjustment</label>';
    html += '<input type="number" id="edit-zipcode-price" step="0.01">';
    html += "</div>";
    html += "</div>";

    html += '<div class="vandel-form-row">';
    html += '<div class="vandel-form-group">';
    html += '<label for="edit-zipcode-fee">Service Fee</label>';
    html += '<input type="number" id="edit-zipcode-fee" step="0.01" min="0">';
    html += "</div>";
    html += "</div>";

    html += '<div class="vandel-form-row">';
    html += '<div class="vandel-form-group">';
    html += "<label>";
    html += '<input type="checkbox" id="edit-zipcode-active">';
    html += "Active";
    html += "</label>";
    html += "</div>";
    html += "</div>";

    html += '<div class="vandel-form-actions">';
    html +=
      '<button type="button" class="button cancel-edit-btn">Cancel</button>';
    html +=
      '<button type="submit" class="button button-primary">Save Changes</button>';
    html += "</div>";
    html += "</form>";
    html += "</div>";
    html += "</div>";

    $container.html(html);

    // ZIP code toggle
    $(".zipcode-toggle").on("change", function () {
      const zipCode = $(this).data("zipcode");
      const isActive = $(this).is(":checked");

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_toggle_location",
          nonce: vandelLocationService.nonce,
          type: "zipcode",
          value: zipCode,
          active: isActive,
        },
        success: function (response) {
          if (!response.success) {
            alert(response.data.message || vandelLocationService.strings.error);
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
        },
      });
    });

    // Edit ZIP code button
    $(".edit-zipcode-btn").on("click", function () {
      const id = $(this).data("id");
      const $row = $(this).closest("tr");
      const zipCode = $row.find("td:first-child").text().trim();
      const areaName = $row.find("td:nth-child(2)").text().trim();
      const priceAdjustment =
        parseFloat($row.find("td:nth-child(3)").text().trim()) || 0;
      const serviceFee =
        parseFloat($row.find("td:nth-child(4)").text().trim()) || 0;
      const isActive = $row.find(".zipcode-toggle").is(":checked");

      $("#edit-zipcode-id").val(id);
      $("#edit-zipcode-area").val(areaName);
      $("#edit-zipcode-price").val(priceAdjustment);
      $("#edit-zipcode-fee").val(serviceFee);
      $("#edit-zipcode-active").prop("checked", isActive);

      $("#edit-zipcode-modal").show();
    });

    // Close modal
    $(".vandel-modal-close, .cancel-edit-btn").on("click", function () {
      $("#edit-zipcode-modal").hide();
    });

    // Submit edit form
    $("#edit-zipcode-form").on("submit", function (e) {
      e.preventDefault();

      const id = $("#edit-zipcode-id").val();
      const areaName = $("#edit-zipcode-area").val().trim();
      const priceAdjustment = parseFloat($("#edit-zipcode-price").val()) || 0;
      const serviceFee = parseFloat($("#edit-zipcode-fee").val()) || 0;
      const isActive = $("#edit-zipcode-active").is(":checked");

      if (!areaName) {
        alert("Area name is required");
        return;
      }

      const $submitBtn = $(this).find('button[type="submit"]');
      $submitBtn
        .prop("disabled", true)
        .text(vandelLocationService.strings.processing);

      $.ajax({
        url: vandelLocationService.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_update_zipcode",
          nonce: vandelLocationService.nonce,
          id: id,
          area_name: areaName,
          price_adjustment: priceAdjustment,
          service_fee: serviceFee,
          is_active: isActive,
        },
        success: function (response) {
          if (response.success) {
            // Update table row
            const $row = $('tr[data-id="' + id + '"]');
            $row.find("td:nth-child(2)").text(areaName);
            $row.find("td:nth-child(3)").text(priceAdjustment);
            $row.find("td:nth-child(4)").text(serviceFee);
            $row.find(".zipcode-toggle").prop("checked", isActive);

            // Close modal
            $("#edit-zipcode-modal").hide();

            // Reset form
            $submitBtn.prop("disabled", false).text("Save Changes");
          } else {
            alert(response.data.message || vandelLocationService.strings.error);
            $submitBtn.prop("disabled", false).text("Save Changes");
          }
        },
        error: function () {
          alert(vandelLocationService.strings.error);
          $submitBtn.prop("disabled", false).text("Save Changes");
        },
      });
    });
  }
})(jQuery);
