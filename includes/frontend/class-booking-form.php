<?php
namespace VandelBooking\Frontend;

/**
 * Booking Form with ZIP Code Support
 */
class BookingForm {
    /**
     * @var bool Whether ZIP Code feature is enabled
     */
    private $zip_code_feature_enabled;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setupCurrency();
        $this->zip_code_feature_enabled = get_option('vandel_enable_zip_code_feature', 'no') === 'yes';
    }
    
    /**
     * Render booking form
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered form
     */
    public function render($atts = []) {
        ob_start();
        ?>
        <div class="vandel-booking-form">
            <?php if ($this->zip_code_feature_enabled): ?>
                <?php $this->renderZipCodeStep(); ?>
            <?php endif; ?>
            
            <div id="vandel-service-selection" 
                 class="vandel-booking-step" 
                 style="display: <?php echo $this->zip_code_feature_enabled ? 'none' : 'block'; ?>">
                <?php $this->renderServiceSelection(); ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const zipCodeForm = document.getElementById('vandel-zip-code-form');
            const serviceSelectionStep = document.getElementById('vandel-service-selection');
            const zipValidationMessage = document.getElementById('vandel-zip-validation-message');
            
            <?php if ($this->zip_code_feature_enabled): ?>
            zipCodeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const zipCodeInput = document.getElementById('vandel_zip_code');
                const zipCode = zipCodeInput.value.trim();
                
                fetch('<?php echo esc_url(rest_url('vandel/v1/validate-zip-code')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
                    },
                    body: JSON.stringify({ zip_code: zipCode })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        zipValidationMessage.innerHTML = `
                            <div class="vandel-notice vandel-notice-success">
                                ${data.details.city}, ${data.details.state} - ${data.details.country}
                                <br>Service Fee: ${data.details.service_fee}
                                <br>Adjusted Price: ${data.details.adjusted_price}
                            </div>
                        `;
                        
                        // Store ZIP details in local storage or hidden fields
                        localStorage.setItem('vandel_zip_details', JSON.stringify(data.details));
                        
                        // Move to next step
                        zipCodeForm.style.display = 'none';
                        serviceSelectionStep.style.display = 'block';
                    } else {
                        zipValidationMessage.innerHTML = `
                            <div class="vandel-notice vandel-notice-error">
                                ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    zipValidationMessage.innerHTML = `
                        <div class="vandel-notice vandel-notice-error">
                            <?php _e('An error occurred. Please try again.', 'vandel-booking'); ?>
                        </div>
                    `;
                });
            });
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render ZIP Code step
     */
    private function renderZipCodeStep() {
        ?>
        <div id="vandel-zip-code-form" class="vandel-booking-step">
            <h3><?php _e('Enter Your Location', 'vandel-booking'); ?></h3>
            <form id="vandel-zip-validation">
                <div class="vandel-form-group">
                    <label for="vandel_zip_code"><?php _e('ZIP/Postal Code', 'vandel-booking'); ?></label>
                    <input 
                        type="text" 
                        id="vandel_zip_code" 
                        name="vandel_zip_code" 
                        class="vandel-form-control" 
                        placeholder="<?php _e('Enter your ZIP code', 'vandel-booking'); ?>" 
                        required
                    >
                </div>
                
                <div id="vandel-zip-validation-message"></div>
                
                <button type="submit" class="vandel-btn vandel-btn-primary">
                    <?php _e('Check Availability', 'vandel-booking'); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render service selection step
     */
    private function renderServiceSelection() {
        // Your existing service selection logic
        echo '<h3>' . __('Select a Service', 'vandel-booking') . '</h3>';
        // Load services, apply ZIP code pricing if available
    }
}