/**
 * Vandel Booking Form JavaScript
 * Improved version with real-time ZIP code validation
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    initBookingForm();
  });

  /**
   * Initialize booking form functionality
   */
  function initBookingForm() {
    const $form = $("#vandel-booking-form");

    if (!$form.length) {
      return;
    }

    // Form data object to collect all inputs
    const formData = {
      zip_code: "",
      zip_code_data: {},
      service_id: "",
      service_data: {},
      selected_options: {},
      total_price: 0,
    };

    // Initialize form navigation
    initFormNavigation($form, formData);

    // Initialize ZIP code validation with enhanced real-time feedback
    if (typeof vandelBooking !== "undefined" && vandelBooking.zipCodeEnabled) {
      initZipCodeValidation($form, formData);
    }

    // Initialize service selection
    initServiceSelection($form, formData);

    // Initialize form submission
    initFormSubmission($form, formData);

    // Pre-select service if specified
    const preSelectedService = $("#vandel-selected-service").val();
    if (preSelectedService) {
      $(
        '.vandel-service-card[data-service-id="' + preSelectedService + '"]'
      ).trigger("click");
    }
  }

  /**
   * Initialize form navigation (next/prev buttons)
   */
  function initFormNavigation($form, formData) {
    const $steps = $form.find(".vandel-booking-step");
    const $progressSteps = $(".vandel-steps .vandel-step");

    // Next button click
    $form.on("click", ".vandel-btn-next", function () {
      const $currentStep = $form.find(".vandel-booking-step.active");
      const currentStepName = $currentStep.data("step");

      // Validate current step
      if (!validateStep(currentStepName, $form, formData)) {
        return false;
      }

      // Find next step
      const $nextStep = $currentStep.next(".vandel-booking-step");
      if ($nextStep.length) {
        // Update UI
        $currentStep.removeClass("active");
        $nextStep.addClass("active");

        // Update progress indicator
        $progressSteps
          .filter('[data-step="' + currentStepName + '"]')
          .removeClass("active")
          .addClass("completed");
        $progressSteps
          .filter('[data-step="' + $nextStep.data("step") + '"]')
          .addClass("active");

        // If moving to confirmation step, update summary
        if ($nextStep.data("step") === "confirmation") {
          updateBookingSummary($form, formData);
        }

        // Scroll to top of form
        scrollToForm();
      }
    });

    // Previous button click
    $form.on("click", ".vandel-btn-prev", function () {
      const $currentStep = $form.find(".vandel-booking-step.active");
      const currentStepName = $currentStep.data("step");

      // Find previous step
      const $prevStep = $currentStep.prev(".vandel-booking-step");
      if ($prevStep.length) {
        // Update UI
        $currentStep.removeClass("active");
        $prevStep.addClass("active");

        // Update progress indicator
        $progressSteps
          .filter('[data-step="' + currentStepName + '"]')
          .removeClass("active");
        $progressSteps
          .filter('[data-step="' + $prevStep.data("step") + '"]')
          .removeClass("completed")
          .addClass("active");

        // Scroll to top of form
        scrollToForm();
      }
    });
  }

  /**
   * Enhanced ZIP code validation with real-time feedback
   */
  function initZipCodeValidation($form, formData) {
    const $zipField = $("#vandel-zip-code");
    const $zipMessage = $("#vandel-zip-validation-message");
    const $locationDetails = $("#vandel-location-details");
    const $zipNextButton = $form.find(
      '.vandel-booking-step[data-step="location"] .vandel-btn-next'
    );

    // Disable next button initially
    $zipNextButton.prop("disabled", true);

    // Create validation status indicator
    const $statusIndicator = $('<div class="vandel-validation-status"></div>');
    $zipField.after($statusIndicator);

    // Add input state indicators
    $zipField.addClass("vandel-validating-input");

    // Debounce function to limit API calls
    let zipTimeout = null;
    const DEBOUNCE_DELAY = 500; // ms

    // Validate on ZIP code input with debounce
    $zipField.on("input", function () {
      const zipCode = $(this).val().trim();

      // Clear previous timeout and validation
      clearTimeout(zipTimeout);
      $zipMessage.empty();
      $locationDetails.slideUp().find(".vandel-price-info").remove();
      $zipNextButton.prop("disabled", true);

      // Remove previous validation classes
      $zipField.removeClass("valid-zip invalid-zip");
      $statusIndicator.removeClass("valid-indicator invalid-indicator").empty();

      // Enable next button if field has value (actual validation will happen via API)
      if (zipCode.length < 3) {
        // Not enough characters yet, just wait
        $statusIndicator.removeClass("validating-indicator");
        return;
      }

      // Show validating indicator
      $statusIndicator
        .addClass("validating-indicator")
        .html('<span class="validating-spinner"></span>');

      // Set a timeout to validate after user stops typing
      zipTimeout = setTimeout(function () {
        validateZipCode(zipCode);
      }, DEBOUNCE_DELAY);
    });

    // Function to validate ZIP code via AJAX
    function validateZipCode(zipCode) {
      if (!zipCode) {
        showValidationError($zipMessage, vandelBooking.strings.zipCodeError);
        $zipField.addClass("invalid-zip");
        $statusIndicator
          .addClass("invalid-indicator")
          .html('<span class="dashicons dashicons-no"></span>');
        return;
      }

      // Call AJAX to validate ZIP code
      $.ajax({
        url: vandelBooking.ajaxurl,
        type: "POST",
        data: {
          action: "vandel_validate_zip_code",
          nonce: vandelBooking.nonce,
          zip_code: zipCode,
        },
        success: function (response) {
          if (response.success) {
            // Store ZIP code data
            formData.zip_code = zipCode;
            formData.zip_code_data = response.data;

            // Update ZIP code hidden field
            $("#vandel-zip-code-data").val(JSON.stringify(response.data));

            // Show location details
            let locationText = response.data.city;
            if (response.data.area_name) {
              locationText = response.data.area_name + ", " + locationText;
            }
            if (response.data.state) {
              locationText += ", " + response.data.state;
            }

            $("#vandel-city-state").text(locationText);
            $("#vandel-country").text(response.data.country);
            $locationDetails.slideDown();

            // Display price info if available
            let priceInfo = "";
            if (
              response.data.price_adjustment &&
              parseFloat(response.data.price_adjustment) !== 0
            ) {
              const sign =
                parseFloat(response.data.price_adjustment) > 0 ? "+" : "";
              priceInfo += `<div>Location Fee: ${sign}${
                vandelBooking.currencySymbol
              }${parseFloat(response.data.price_adjustment).toFixed(2)}</div>`;
            }
            if (
              response.data.service_fee &&
              parseFloat(response.data.service_fee) > 0
            ) {
              priceInfo += `<div>Service Fee: ${
                vandelBooking.currencySymbol
              }${parseFloat(response.data.service_fee).toFixed(2)}</div>`;
            }
            if (priceInfo) {
              $locationDetails.append(
                `<div class="vandel-price-info">${priceInfo}</div>`
              );
            }

            // Update validation status
            $zipField.addClass("valid-zip");
            $statusIndicator
              .removeClass("validating-indicator")
              .addClass("valid-indicator")
              .html('<span class="dashicons dashicons-yes"></span>');

            // Clear validation message
            $zipMessage.empty();

            // Enable next button
            $zipNextButton.prop("disabled", false);
          } else {
            showValidationError($zipMessage, response.data.message);
            $zipField.addClass("invalid-zip");
            $statusIndicator
              .removeClass("validating-indicator")
              .addClass("invalid-indicator")
              .html('<span class="dashicons dashicons-no"></span>');
            $zipNextButton.prop("disabled", true);
            $locationDetails.slideUp();
          }
        },
        error: function () {
          showValidationError($zipMessage, vandelBooking.strings.errorOccurred);
          $zipField.addClass("invalid-zip");
          $statusIndicator
            .removeClass("validating-indicator")
            .addClass("invalid-indicator")
            .html('<span class="dashicons dashicons-warning"></span>');
          $zipNextButton.prop("disabled", true);
          $locationDetails.slideUp();
        },
      });
    }

    // Also keep the next button validation for better UX
    $zipNextButton.on("click", function (e) {
      const zipCode = $zipField.val().trim();
      if (!zipCode) {
        e.preventDefault();
        showValidationError($zipMessage, vandelBooking.strings.zipCodeError);
        return false;
      }

      // If we already have validated data, proceed
      if (formData.zip_code && formData.zip_code === zipCode) {
        return true;
      }

      // Otherwise re-validate to be sure
      e.preventDefault();
      validateZipCode(zipCode);
    });
  }

  /**
   * Initialize service selection
   */
  function initServiceSelection($form, formData) {
    const $serviceCards = $(".vandel-service-card");
    const $optionsContainer = $("#vandel-service-options");
    const $optionsContentContainer = $("#vandel-options-container");
    const $serviceNextButton = $form.find(
      '.vandel-booking-step[data-step="service"] .vandel-btn-next'
    );

    // Disable next button initially if no service is pre-selected
    if (!$("#vandel-selected-service").val()) {
      $serviceNextButton.prop("disabled", true);
    }

    // Service card selection
    $serviceCards.on("click", function () {
      const $card = $(this);
      const serviceId = $card.data("service-id");

      // Update UI
      $serviceCards.removeClass("selected");
      $card.addClass("selected");

      // Store service ID
      formData.service_id = serviceId;
      $("#vandel-selected-service").val(serviceId);

      // Show loading state
      $optionsContentContainer.html(
        '<div class="vandel-loading"><span class="vandel-spinner"></span> ' +
          vandelBooking.strings.loadingOptions +
          "</div>"
      );
      $optionsContainer.slideDown();

      // Get service details via AJAX
      $.ajax({
        url: vandelBooking.ajaxurl,
        type: "POST",
        data: {
          action: "vandel_get_service_details",
          nonce: vandelBooking.nonce,
          service_id: serviceId,
        },
        success: function (response) {
          if (response.success) {
            // Store service data
            formData.service_data = response.data;
            formData.total_price = response.data.price;

            // Show options if available
            if (response.data.optionsHtml) {
              $optionsContentContainer.html(response.data.optionsHtml);
              $optionsContainer.slideDown();
              initOptionsHandlers($form, formData);
            } else {
              $optionsContainer.slideUp();
            }

            // Update any price display
            if ($("#vandel-price-display").length) {
              $("#vandel-price-display").text(
                formatPrice(formData.total_price)
              );
            }

            // Enable next button
            $serviceNextButton.prop("disabled", false);
          } else {
            const errorMsg =
              response.data && response.data.message
                ? response.data.message
                : vandelBooking.strings.errorOccurred;

            $optionsContentContainer.html(
              '<div class="vandel-error">' + errorMsg + "</div>"
            );
            $serviceNextButton.prop("disabled", true);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error:", status, error);
          $optionsContentContainer.html(
            '<div class="vandel-error">' +
              vandelBooking.strings.errorOccurred +
              "</div>"
          );
          $serviceNextButton.prop("disabled", true);
        },
      });
    });
  }

  /**
   * Initialize options event handlers
   */
  function initOptionsHandlers($form, formData) {
    const $optionItems = $(".vandel-option-item");

    // Handle option changes
    $optionItems.each(function () {
      const $option = $(this);
      const optionId = $option.data("option-id");
      const optionType = $option.data("option-type");

      switch (optionType) {
        case "checkbox":
          $option.find('input[type="checkbox"]').on("change", function () {
            const isChecked = $(this).is(":checked");
            const optionPrice = parseFloat($(this).data("price") || 0);

            // Update selected options
            if (isChecked) {
              formData.selected_options[optionId] = {
                id: optionId,
                value: "yes",
                price: optionPrice,
                type: optionType,
              };
            } else {
              delete formData.selected_options[optionId];
            }

            // Update total price
            calculateTotalPrice(formData);
          });
          break;

        case "radio":
          $option.find('input[type="radio"]').on("change", function () {
            const value = $(this).val();
            const optionPrice = parseFloat($(this).data("price") || 0);

            // Update selected options
            formData.selected_options[optionId] = {
              id: optionId,
              value: value,
              price: optionPrice,
              type: optionType,
            };

            // Update total price
            calculateTotalPrice(formData);
          });
          break;

        case "dropdown":
          $option.find("select").on("change", function () {
            const value = $(this).val();
            const selectedOption = $(this).find("option:selected");
            const optionPrice = parseFloat(selectedOption.data("price") || 0);

            // Update selected options
            if (value) {
              formData.selected_options[optionId] = {
                id: optionId,
                value: value,
                price: optionPrice,
                type: optionType,
              };
            } else {
              delete formData.selected_options[optionId];
            }

            // Update total price
            calculateTotalPrice(formData);
          });
          break;

        case "number":
          $option.find('input[type="number"]').on("change", function () {
            const value = parseInt($(this).val() || 0, 10);
            const pricePerUnit = parseFloat($(this).data("price") || 0);

            // Update selected options
            if (value > 0) {
              formData.selected_options[optionId] = {
                id: optionId,
                value: value,
                price: pricePerUnit * value,
                pricePerUnit: pricePerUnit,
                type: optionType,
              };
            } else {
              delete formData.selected_options[optionId];
            }

            // Update total price
            calculateTotalPrice(formData);
          });
          break;

        case "text":
        case "textarea":
          $option.find("input, textarea").on("change", function () {
            const value = $(this).val();
            const optionPrice = parseFloat(
              $option.find(".vandel-option-price").data("price") || 0
            );

            // Update selected options
            if (value) {
              formData.selected_options[optionId] = {
                id: optionId,
                value: value,
                price: optionPrice,
                type: optionType,
              };
            } else {
              delete formData.selected_options[optionId];
            }

            // Update total price
            calculateTotalPrice(formData);
          });
          break;
      }
    });
  }

  /**
   * Calculate total price based on selections
   */
  function calculateTotalPrice(formData) {
    let totalPrice = formData.service_data.price || 0;
    let optionsPrice = 0;

    // Add options prices
    Object.values(formData.selected_options).forEach((option) => {
      optionsPrice += option.price;
    });

    totalPrice += optionsPrice;

    // Add location-based adjustments if available
    if (formData.zip_code_data) {
      const priceAdjustment = parseFloat(
        formData.zip_code_data.price_adjustment || 0
      );
      const serviceFee = parseFloat(formData.zip_code_data.service_fee || 0);

      totalPrice += priceAdjustment + serviceFee;

      // Update any display elements with fee information
      if ($("#summary-adjustment").length) {
        if (priceAdjustment !== 0) {
          const sign = priceAdjustment > 0 ? "+" : "";
          $("#summary-adjustment").text(sign + formatPrice(priceAdjustment));
          $("#summary-adjustment-container").show();
        } else {
          $("#summary-adjustment-container").hide();
        }

        if (serviceFee > 0) {
          $("#summary-service-fee").text(formatPrice(serviceFee));
          $("#summary-service-fee-container").show();
        } else {
          $("#summary-service-fee-container").hide();
        }
      }
    }

    // Update form data
    formData.total_price = totalPrice;

    // Update hidden field
    $("#vandel-total-price").val(totalPrice);

    // Update price display if available
    if ($("#vandel-price-display").length) {
      $("#vandel-price-display").text(formatPrice(totalPrice));
    }

    return totalPrice;
  }

  /**
   * Initialize form submission
   */
  function initFormSubmission($form, formData) {
    const $submitButton = $form.find(".vandel-btn-submit");
    const $successMessage = $(".vandel-booking-success");

    $submitButton.on("click", function (e) {
      e.preventDefault();

      // Validate final step
      if (!validateStep("confirmation", $form, formData)) {
        return false;
      }

      // Collect all form data
      const submissionData = {
        action: "vandel_submit_booking",
        nonce: vandelBooking.nonce,
        service_id: formData.service_id,
        name: $("#vandel-name").val(),
        email: $("#vandel-email").val(),
        phone: $("#vandel-phone").val(),
        date: $("#vandel-date").val(),
        time: $("#vandel-time").val(),
        comments: $("#vandel-comments").val(),
        terms: $("#vandel-terms").is(":checked") ? "yes" : "",
        zip_code_data: JSON.stringify(formData.zip_code_data || {}),
        total_price: formData.total_price,
      };

      // Add options if any
      if (Object.keys(formData.selected_options).length > 0) {
        Object.entries(formData.selected_options).forEach(([id, option]) => {
          submissionData[`options[${id}]`] = option.value;
        });
      }

      // Show loading state
      $submitButton
        .prop("disabled", true)
        .html(
          '<span class="vandel-loading-spinner"></span> ' +
            vandelBooking.strings.processingBooking
        );

      // Submit booking via AJAX
      $.ajax({
        url: vandelBooking.ajaxurl,
        type: "POST",
        data: submissionData,
        success: function (response) {
          if (response.success) {
            // Show success message
            $form.find(".vandel-booking-step").removeClass("active");
            $successMessage.show();

            // Update booking reference
            $("#vandel-booking-reference").text(response.data.booking_id);

            // Scroll to success message
            scrollToForm();
          } else {
            // Show error and re-enable submit button
            let errorMessage =
              response.data.message || vandelBooking.strings.errorOccurred;

            // If debug info is available, show it in console
            if (response.data.debug_info) {
              console.error("Booking error details:", response.data.debug_info);
            }

            alert(errorMessage);
            $submitButton
              .prop("disabled", false)
              .html(vandelBooking.strings.submit);
          }
        },
        error: function (xhr, status, error) {
          // Show detailed error information
          console.error("AJAX Error:", {
            status: status,
            error: error,
            response: xhr.responseText,
          });

          // Show error and re-enable submit button
          alert(vandelBooking.strings.errorOccurred + ": " + status);
          $submitButton
            .prop("disabled", false)
            .html(vandelBooking.strings.submit);
        },
      });
    });
  }

  /**
   * Update booking summary before confirmation
   */
  function updateBookingSummary($form, formData) {
    // Service details
    $("#summary-service").text(formData.service_data.title || "--");

    // Contact information
    $("#summary-name").text($("#vandel-name").val() || "--");
    $("#summary-email").text($("#vandel-email").val() || "--");
    $("#summary-phone").text($("#vandel-phone").val() || "--");

    // Date and time
    const date = $("#vandel-date").val();
    const time = $("#vandel-time").val();

    if (date) {
      const formattedDate = new Date(date).toLocaleDateString();
      $("#summary-date").text(formattedDate);
    } else {
      $("#summary-date").text("--");
    }

    $("#summary-time").text(time || "--");

    // Location information
    if (formData.zip_code_data) {
      let locationText = formData.zip_code_data.city || "--";
      if (formData.zip_code_data.area_name) {
        locationText = formData.zip_code_data.area_name + ", " + locationText;
      }
      if (formData.zip_code_data.state) {
        locationText += ", " + formData.zip_code_data.state;
      }

      $("#summary-location").text(locationText);
      $("#summary-zip-code").text(
        formData.zip_code_data.zip_code || formData.zip_code
      );
      $("#summary-location-container").show();
    } else {
      $("#summary-location-container").hide();
    }

    // Comments
    $("#summary-comments").text($("#vandel-comments").val() || "--");

    // Pricing details
    $("#summary-base-price").text(
      formatPrice(formData.service_data.price || 0)
    );

    // Options
    const optionsContainer = $("#summary-options");
    const optionsCount = Object.keys(formData.selected_options).length;
    let optionsTotalPrice = 0;

    if (optionsCount > 0) {
      let optionsHtml = '<ul class="vandel-summary-options-list">';

      Object.values(formData.selected_options).forEach((option) => {
        const optionTitle = $(
          '.vandel-option-item[data-option-id="' +
            option.id +
            '"] .vandel-option-title'
        ).text();
        let optionValue = option.value;

        // Format option value based on type
        if (option.type === "checkbox" && optionValue === "yes") {
          optionValue = "Yes";
        }

        optionsHtml +=
          "<li>" +
          '<span class="vandel-summary-option-name">' +
          optionTitle +
          ": </span>" +
          '<span class="vandel-summary-option-value">' +
          optionValue +
          "</span>" +
          '<span class="vandel-summary-option-price">' +
          formatPrice(option.price) +
          "</span>" +
          "</li>";

        optionsTotalPrice += option.price;
      });

      optionsHtml += "</ul>";
      optionsContainer.html(optionsHtml);
      $("#summary-options-container").show();
      $("#summary-options-price").text(formatPrice(optionsTotalPrice));
      $("#summary-options-price-container").show();
    } else {
      $("#summary-options-container").hide();
      $("#summary-options-price-container").hide();
    }

    // Location fees
    if (formData.zip_code_data) {
      const priceAdjustment = parseFloat(
        formData.zip_code_data.price_adjustment || 0
      );
      const serviceFee = parseFloat(formData.zip_code_data.service_fee || 0);

      if (priceAdjustment !== 0) {
        const sign = priceAdjustment > 0 ? "+" : "";
        $("#summary-adjustment").text(sign + formatPrice(priceAdjustment));
        $("#summary-adjustment-container").show();
      } else {
        $("#summary-adjustment-container").hide();
      }

      if (serviceFee > 0) {
        $("#summary-service-fee").text(formatPrice(serviceFee));
        $("#summary-service-fee-container").show();
      } else {
        $("#summary-service-fee-container").hide();
      }
    }

    // Total price
    $("#summary-total").text(formatPrice(formData.total_price));
  }

  /**
   * Validate step before proceeding
   */
  function validateStep(step, $form, formData) {
    switch (step) {
      case "location":
        // Make sure we have valid ZIP code data
        if (formData.zip_code === "") {
          const $zipField = $("#vandel-zip-code");
          const $zipMessage = $("#vandel-zip-validation-message");

          showValidationError($zipMessage, vandelBooking.strings.zipCodeError);
          $zipField.addClass("invalid-zip").focus();
          return false;
        }
        return true;

      case "service":
        // Validate service selection
        if (!formData.service_id) {
          alert(vandelBooking.strings.selectServiceError);
          return false;
        }
        return true;

      case "details":
        // Validate required fields
        const $requiredFields = $form.find(
          '.vandel-booking-step[data-step="details"] [required]'
        );
        let isValid = true;

        $requiredFields.each(function () {
          const $field = $(this);

          if (!$field.val()) {
            showFieldError($field, vandelBooking.strings.requiredField);
            isValid = false;
          } else {
            clearFieldError($field);

            // Additional validation for email
            if ($field.attr("type") === "email") {
              if (!isValidEmail($field.val())) {
                showFieldError($field, vandelBooking.strings.invalidEmail);
                isValid = false;
              }
            }

            // Additional validation for phone
            if ($field.attr("type") === "tel") {
              if (!isValidPhone($field.val())) {
                showFieldError($field, vandelBooking.strings.invalidPhone);
                isValid = false;
              }
            }
          }
        });

        return isValid;

      case "confirmation":
        // Terms checkbox validation
        if (!$("#vandel-terms").is(":checked")) {
          alert(vandelBooking.strings.termsRequired);
          return false;
        }
        return true;

      default:
        return true;
    }
  }

  /**
   * Show validation error for field
   */
  function showFieldError($field, message) {
    // Remove existing error
    clearFieldError($field);

    // Add error class
    $field.addClass("vandel-error");

    // Create error message
    const $error = $('<div class="vandel-field-error">' + message + "</div>");
    $field.after($error);
  }

  /**
   * Clear field error
   */
  function clearFieldError($field) {
    $field.removeClass("vandel-error");
    $field.next(".vandel-field-error").remove();
  }

  /**
   * Show validation error message
   */
  function showValidationError($container, message) {
    $container.html('<div class="vandel-error-message">' + message + "</div>");
  }

  /**
   * Format price with currency symbol
   */
  function formatPrice(price) {
    return vandelBooking.currencySymbol + " " + parseFloat(price).toFixed(2);
  }

  /**
   * Scroll to top of form
   */
  function scrollToForm() {
    $("html, body").animate(
      {
        scrollTop: $(".vandel-booking-form-container").offset().top - 50,
      },
      500
    );
  }

  /**
   * Validate email format
   */
  function isValidEmail(email) {
    const emailRegex =
      /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    return emailRegex.test(email);
  }

  /**
   * Validate phone number format
   */
  function isValidPhone(phone) {
    // Simple validation - at least 6 digits
    const phoneRegex = /^[\d\s\+\-\(\)]{6,20}$/;
    return phoneRegex.test(phone);
  }
})(jQuery);
