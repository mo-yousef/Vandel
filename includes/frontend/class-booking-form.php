<?php
namespace VandelBooking\Frontend;

/**
 * Booking Form
 */
class BookingForm {
    /**
     * @var string Currency symbol
     */
    private $currency_symbol;
    
    /**
     * @var array Currency mapping
     */
    private $currency_mapping;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setupCurrency();
    }
    
    /**
     * Setup currency
     */
    private function setupCurrency() {
        $this->currency_symbol = get_option('vandel_currency', 'USD');
        $this->currency_mapping = [
            'USD' => '$', 'EUR' => '€', 'SEK' => 'kr', 'GBP' => '£',
            'JPY' => '¥', 'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF',
            'CNY' => '¥', 'INR' => '₹', 'NZD' => 'NZ$', 'ZAR' => 'R',
            'BRL' => 'R$', 'MXN' => 'Mex$', 'RUB' => '₽', 'SGD' => 'S$',
            'HKD' => 'HK$', 'NOK' => 'kr', 'KRW' => '₩', 'TRY' => '₺'
        ];
    }
    
    /**
     * Render booking form
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered form
     */
    public function render($atts = []) {
        ob_start();
        
        include VANDEL_PLUGIN_DIR . 'templates/booking-form.php';
        
        return ob_get_clean();
    }
    
    /**
     * Render sub-services
     * 
     * @param array $sub_services Sub-services to render
     */
    public function renderSubServices($sub_services) {
        if (empty($sub_services)) {
            echo '<p class="no-options-message">' . __('No additional options available for this service.', 'vandel-booking') . '</p>';
            return;
        }
        
        foreach ($sub_services as $sub_service) {
            $this->renderSubService($sub_service);
        }
    }
    
    /**
     * Render single sub-service
     * 
     * @param object $sub_service Sub-service to render
     */
    private function renderSubService($sub_service) {
        $sub_service_id = $sub_service->ID;
        $meta = $this->getSubServiceMeta($sub_service_id);
        
        include VANDEL_PLUGIN_DIR . 'templates/parts/sub-service.php';
    }
    
    /**
     * Get sub-service meta data
     * 
     * @param int $sub_service_id Sub-service ID
     * @return array Meta data
     */
    private function getSubServiceMeta($sub_service_id) {
        return [
            'price' => get_post_meta($sub_service_id, '_vandel_sub_service_price', true),
            'subtitle' => get_post_meta($sub_service_id, '_vandel_sub_service_subtitle', true),
            'type' => get_post_meta($sub_service_id, '_vandel_sub_service_type', true),
            'placeholder' => get_post_meta($sub_service_id, '_vandel_sub_service_placeholder', true),
            'options' => json_decode(get_post_meta($sub_service_id, '_vandel_sub_service_options', true), true)
        ];
    }
    
    /**
     * Format price with currency
     * 
     * @param float $price Price to format
     * @return string Formatted price
     */
    public function formatPrice($price) {
        $symbol = $this->currency_mapping[$this->currency_symbol] ?? '$';
        return $symbol . number_format((float)$price, 2);
    }
}