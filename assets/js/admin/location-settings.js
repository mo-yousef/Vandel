/**
 * Location Settings JavaScript
 * Handles interactions for the location management interface
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    initAreaManagement();
    initLocationManagement();
  });

  /**
   * Initialize area management functionality
   */
  function initAreaManagement() {
    // Add new area form submission
    $("#add-area-form").on("submit", function (e) {
      e.preventDefault();

      const areaName = $("#area-name").val();
      const country = $("#area-country").val();

      if (!areaName || !country) {
        alert(
          vandelLocations.strings.fillRequired ||
            "Please fill in all required fields"
        );
        return;
      }

      $.ajax({
        url: vandelLocations.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_save_area",
          nonce: vandelLocations.nonce,
          area_name: areaName,
          country: country,
        },
        success: function (response) {
          if (response.success) {
            location.reload();
          } else {
            alert(response.data.message || vandelLocations.strings.error);
          }
        },
        error: function () {
          alert(vandelLocations.strings.error);
        },
      });
    });

    // Delete area
    $(".delete-area").on("click", function (e) {
      e.preventDefault();

      const areaId = $(this).data("id");

      if (confirm(vandelLocations.strings.confirmDeleteArea)) {
        $.ajax({
          url: vandelLocations.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_delete_area",
            nonce: vandelLocations.nonce,
            area_id: areaId,
          },
          success: function (response) {
            if (response.success) {
              location.reload();
            } else {
              alert(response.data.message || vandelLocations.strings.error);
            }
          },
          error: function () {
            alert(vandelLocations.strings.error);
          },
        });
      }
    });
  }

  /**
   * Initialize location management functionality
   */
  function initLocationManagement() {
    // Initialize locations
    let locations = [];

    // Try to get locations from window if they exist
    if (typeof window.locations !== "undefined") {
      locations = window.locations;
    }

    // Add new location
    $("#add-location-btn").on("click", function () {
      const locationName = $("#new-location-name").val().trim();

      if (!locationName) {
        return;
      }

      // Add location to UI first
      const tempId = "new_" + Date.now();
      addLocationToUI({
        id: tempId,
        name: locationName,
        area_id: $("#area-id").val(),
        is_new: true,
      });

      // Clear input
      $("#new-location-name").val("").focus();
    });

    // Enter key in location input
    $("#new-location-name").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $("#add-location-btn").click();
      }
    });

    // Remove location
    $(document).on("click", ".vandel-remove-location", function () {
      const $badge = $(this).closest(".vandel-location-badge");
      const locationId = $(this).data("id");

      // If this is a new location that hasn't been saved yet
      if (locationId.toString().startsWith("new_")) {
        $badge.remove();

        // Remove from locations array
        locations = locations.filter(function (location) {
          return location.id.toString() !== locationId.toString();
        });

        return;
      }

      if (confirm(vandelLocations.strings.confirmDeleteLocation)) {
        $.ajax({
          url: vandelLocations.ajaxUrl,
          type: "POST",
          data: {
            action: "vandel_delete_location",
            nonce: vandelLocations.nonce,
            location_id: locationId,
          },
          success: function (response) {
            if (response.success) {
              $badge.remove();
            } else {
              alert(response.data.message || vandelLocations.strings.error);
            }
          },
          error: function () {
            alert(vandelLocations.strings.error);
          },
        });
      }
    });

    // Save area and locations
    $("#edit-area-form").on("submit", function (e) {
      e.preventDefault();

      const areaId = $("#area-id").val();
      const areaName = $("#edit-area-name").val();
      const adminArea = $("#edit-area-admin-area").val();

      if (!areaName || !adminArea) {
        alert(
          vandelLocations.strings.fillRequired ||
            "Please fill in all required fields"
        );
        return;
      }

      // Save area first
      $.ajax({
        url: vandelLocations.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_save_area",
          nonce: vandelLocations.nonce,
          area_id: areaId,
          area_name: areaName,
          admin_area: adminArea,
        },
        success: function (response) {
          if (response.success) {
            // Now save any new locations
            saveNewLocations(areaId, function () {
              // Redirect back to locations list
              window.location.href =
                window.location.href.split("?")[0] +
                "?page=vandel-dashboard&tab=settings&section=locations";
            });
          } else {
            alert(response.data.message || vandelLocations.strings.error);
          }
        },
        error: function () {
          alert(vandelLocations.strings.error);
        },
      });
    });
  }

  /**
   * Helper function to add location to UI
   *
   * @param {Object} location Location object
   */
  function addLocationToUI(location) {
    const $container = $("#locations-container");
    let $locationsList = $container.find(".vandel-locations-list");

    // Create locations list if it doesn't exist
    if ($locationsList.length === 0) {
      $container.empty();
      $locationsList = $('<div class="vandel-locations-list"></div>').appendTo(
        $container
      );
    }

    // Add new location badge
    const $badge = $(`
            <span class="vandel-location-badge">
                ${location.name}
                <button type="button" class="vandel-remove-location" data-id="${location.id}">×</button>
            </span>
        `);

    $locationsList.append($badge);

    // Store new location in global array if available
    if (location.is_new && typeof window.locations !== "undefined") {
      window.locations.push(location);
    }
  }

  /**
   * Helper function to save new locations
   *
   * @param {Number} areaId Area ID
   * @param {Function} callback Callback function
   */
  function saveNewLocations(areaId, callback) {
    // Try to get locations from window if they exist
    let locations = [];
    if (typeof window.locations !== "undefined") {
      locations = window.locations;
    }

    const newLocations = locations.filter((loc) => loc.is_new);

    if (newLocations.length === 0) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    let savedCount = 0;

    newLocations.forEach(function (location) {
      $.ajax({
        url: vandelLocations.ajaxUrl,
        type: "POST",
        data: {
          action: "vandel_save_location",
          nonce: vandelLocations.nonce,
          location_name: location.name,
          area_id: areaId,
        },
        success: function () {
          savedCount++;
          if (
            savedCount === newLocations.length &&
            typeof callback === "function"
          ) {
            callback();
          }
        },
        error: function () {
          savedCount++;
          if (
            savedCount === newLocations.length &&
            typeof callback === "function"
          ) {
            callback();
          }
        },
      });
    });
  }
})(jQuery);
