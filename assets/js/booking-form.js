/**
 * Vandel Booking Form JavaScript
 * Handles multi-step form navigation, validation, and AJAX requests
 */
(function ($) {
  "use strict";

  console.log("FORM FRONTEND JS");

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

    // Initialize ZIP code validation
    if (vandelBooking.zipCodeEnabled) {
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
   * Initialize ZIP code validation
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

    // Validate ZIP code on next button click
    $zipNextButton.on("click", function (e) {
      e.preventDefault();

      const zipCode = $zipField.val().trim();
      if (!zipCode) {
        showValidationError($zipMessage, vandelBooking.strings.zipCodeError);
        return false;
      }

      // Show loading state
      $zipMessage.html(
        '<div class="vandel-loading">' +
          __("Checking...", "vandel-booking") +
          "</div>"
      );

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
            $("#vandel-city-state").text(response.data.location_string);
            $("#vandel-country").text(response.data.country);
            $locationDetails.slideDown();

            // Clear validation message
            $zipMessage.empty();

            // Enable next button
            $zipNextButton.prop("disabled", false);

            // Trigger next step
            $zipNextButton.trigger("click");
          } else {
            showValidationError($zipMessage, response.data.message);
            $zipNextButton.prop("disabled", true);
            $locationDetails.slideUp();
          }
        },
        error: function () {
          showValidationError($zipMessage, vandelBooking.strings.errorOccurred);
          $zipNextButton.prop("disabled", true);
          $locationDetails.slideUp();
        },
      });
    });

    // Validate on ZIP code change
    $zipField.on("input", function () {
      // Clear previous validation
      $zipMessage.empty();
      $locationDetails.slideUp();
      $zipNextButton.prop("disabled", true);

      // Enable next button if field has value (actual validation will happen on click)
      if ($(this).val().trim().length > 3) {
        $zipNextButton.prop("disabled", false);
      }
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

    // Disable next button initially
    $serviceNextButton.prop("disabled", true);

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
        '<div class="vandel-loading">' +
          __("Loading options...", "vandel-booking") +
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

            // Update base price calculation
            formData.total_price = response.data.price;

            // Show options if available
            if (response.data.optionsHtml) {
              $optionsContentContainer.html(response.data.optionsHtml);
              $optionsContainer.slideDown();

              // Initialize options event handlers
              initOptionsHandlers($form, formData);
            } else {
              $optionsContainer.slideUp();
            }

            // Enable next button
            $serviceNextButton.prop("disabled", false);
          } else {
            $optionsContentContainer.html(
              '<div class="vandel-error">' +
                (response.data.message || vandelBooking.strings.errorOccurred) +
                "</div>"
            );
            $serviceNextButton.prop("disabled", true);
          }
        },
        error: function () {
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
    }

    // Update form data
    formData.total_price = totalPrice;

    // Update hidden field
    $("#vandel-total-price").val(totalPrice);

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
        zip_code_data: JSON.stringify(formData.zip_code_data),
        total_price: formData.total_price,
      };

      // Add options if any
      if (Object.keys(formData.selected_options).length > 0) {
        submissionData.options = {};
        Object.entries(formData.selected_options).forEach(([id, option]) => {
          submissionData.options[id] = option.value;
        });
      }

      // Show loading state
      $submitButton
        .prop("disabled", true)
        .html(
          '<span class="vandel-loading-spinner"></span> ' +
            vandelBooking.strings.processingPayment
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
            alert(response.data.message || vandelBooking.strings.errorOccurred);
            $submitButton
              .prop("disabled", false)
              .html(vandelBooking.strings.submit);
          }
        },
        error: function () {
          // Show error and re-enable submit button
          alert(vandelBooking.strings.errorOccurred);
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

    // Location (if ZIP code feature is enabled)
    if (vandelBooking.zipCodeEnabled && formData.zip_code_data) {
      $("#summary-location").text(
        formData.zip_code_data.location_string || formData.zip_code
      );
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

    // ZIP code adjustments if applicable
    if (vandelBooking.zipCodeEnabled && formData.zip_code_data) {
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
        // Already validated via AJAX
        return true;

      case "service":
        // Validate service selection
        if (!formData.service_id) {
          alert(vandelBooking.strings.requiredField);
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

  /**
   * Translation helper function
   */
  function __(text, domain) {
    return text;
  }
})(jQuery);
