/**
 * Location Management JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Open add location modal
    $(".add-new-location").on("click", function (e) {
      e.preventDefault();
      resetLocationForm();
      $("#modal-title").text(vandelLocationAdmin.strings.addLocation);
      $("#location-modal").show();
    });

    // Open import modal
    $(".import-locations").on("click", function (e) {
      e.preventDefault();
      $("#import-modal").show();
    });

    // Close modals
    $(".vandel-modal-close, .vandel-modal-cancel").on("click", function () {
      $(".vandel-modal").hide();
    });

    // Close modal when clicking outside
    $(window).on("click", function (e) {
      if ($(e.target).hasClass("vandel-modal")) {
        $(".vandel-modal").hide();
      }
    });

    // Edit location
    $(".edit-location").on("click", function (e) {
      e.preventDefault();

      const id = $(this).data("id");
      const country = $(this).data("country");
      const city = $(this).data("city");
      const area = $(this).data("area");
      const zip = $(this).data("zip");
      const price = $(this).data("price");
      const fee = $(this).data("fee");
      const active = $(this).data("active");

      $("#location-id").val(id);
      $("#country").val(country);
      $("#city").val(city);
      $("#area_name").val(area);
      $("#zip_code").val(zip);
      $("#price_adjustment").val(price);
      $("#service_fee").val(fee);
      $("#is_active").prop("checked", active === "yes");

      $("#modal-title").text(vandelLocationAdmin.strings.editLocation);
      $("#location-modal").show();
    });

    // Delete location
    $(".delete-location").on("click", function (e) {
      e.preventDefault();

      const id = $(this).data("id");

      if (confirm(vandelLocationAdmin.strings.confirmDelete)) {
        $.ajax({
          url: vandelLocationAdmin.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_delete_location",
            id: id,
            nonce: vandelLocationAdmin.nonce,
          },
          success: function (response) {
            if (response.success) {
              window.location.reload();
            } else {
              alert(response.data.message || vandelLocationAdmin.strings.error);
            }
          },
          error: function () {
            alert(vandelLocationAdmin.strings.error);
          },
        });
      }
    });

    // Submit location form
    $("#location-form").on("submit", function (e) {
      e.preventDefault();

      const formData = {
        action: "vandel_save_location",
        nonce: vandelLocationAdmin.nonce,
        id: $("#location-id").val(),
        country: $("#country").val(),
        city: $("#city").val(),
        area_name: $("#area_name").val(),
        zip_code: $("#zip_code").val(),
        price_adjustment: $("#price_adjustment").val(),
        service_fee: $("#service_fee").val(),
        is_active: $("#is_active").is(":checked") ? "yes" : "no",
      };

      $.ajax({
        url: vandelLocationAdmin.ajaxUrl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            window.location.reload();
          } else {
            alert(response.data.message || vandelLocationAdmin.strings.error);
          }
        },
        error: function () {
          alert(vandelLocationAdmin.strings.error);
        },
      });
    });

    // Submit import form
    $("#import-form").on("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      formData.append("action", "vandel_import_locations");
      formData.append("nonce", vandelLocationAdmin.nonce);

      $.ajax({
        url: vandelLocationAdmin.ajaxUrl,
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
          if (response.success) {
            window.location.reload();
          } else {
            alert(response.data.message || vandelLocationAdmin.strings.error);
          }
        },
        error: function () {
          alert(vandelLocationAdmin.strings.error);
        },
      });
    });

    // Reset location form
    function resetLocationForm() {
      $("#location-id").val("0");
      $("#country").val("");
      $("#city").val("");
      $("#area_name").val("");
      $("#zip_code").val("");
      $("#price_adjustment").val("0");
      $("#service_fee").val("0");
      $("#is_active").prop("checked", true);
    }
  });
})(jQuery);
