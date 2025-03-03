<?php
namespace VandelBooking;

/**
 * Helper functions
 */
class Helpers {
    /**
     * Format price with currency
     * 
     * @param float $amount Amount to format
     * @param int $decimals Number of decimal places
     * @return string Formatted price
     */
    public static function formatPrice($amount, $decimals = 2) {
        $amount = is_numeric($amount) ? (float) $amount : 0;
        $currency = get_option('vandel_currency', 'USD');
        $symbol = self::getCurrencySymbol($currency);
        
        return $symbol . ' ' . number_format($amount, $decimals);
    }
    
    /**
     * Get currency symbol
     * 
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public static function getCurrencySymbol($currency = null) {
        if (!$currency) {
            $currency = get_option('vandel_currency', 'USD');
        }
        
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'SEK' => 'kr',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            'NZD' => 'NZ$',
            'ZAR' => 'R',
            'BRL' => 'R$',
            'MXN' => 'Mex$',
            'RUB' => '₽',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'NOK' => 'kr',
            'KRW' => '₩',
            'TRY' => '₺'
        ];
        
        return isset($symbols[$currency]) ? $symbols[$currency] : '$';
    }
    
    /**
     * Format date
     * 
     * @param string $date Date string
     * @param string $format Date format
     * @return string Formatted date
     */
    public static function formatDate($date, $format = '') {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        
        return date_i18n($format, strtotime($date));
    }
    
    /**
     * Calculate time difference
     * 
     * @param string $date1 First date
     * @param string $date2 Second date
     * @return string Human readable time difference
     */
    public static function timeDifference($date1, $date2 = '') {
        if (empty($date2)) {
            $date2 = current_time('mysql');
        }
        
        $time1 = strtotime($date1);
        $time2 = strtotime($date2);
        $diff = abs($time2 - $time1);
        
        if ($diff < 60) {
            return sprintf(_n('%s second', '%s seconds', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 60);
        if ($diff < 60) {
            return sprintf(_n('%s minute', '%s minutes', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 60);
        if ($diff < 24) {
            return sprintf(_n('%s hour', '%s hours', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 24);
        if ($diff < 7) {
            return sprintf(_n('%s day', '%s days', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 7);
        if ($diff < 4) {
            return sprintf(_n('%s week', '%s weeks', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 4);
        if ($diff < 12) {
            return sprintf(_n('%s month', '%s months', $diff, 'vandel-booking'), $diff);
        }
        
        $diff = round($diff / 12);
        return sprintf(_n('%s year', '%s years', $diff, 'vandel-booking'), $diff);
    }
    
    /**
     * Format option value for display
     * 
     * @param array $option Option data
     * @return string Formatted value
     */
    public static function formatOptionValue($option) {
        if (!is_array($option) || !isset($option['type'])) {
            return '';
        }
        
        $value = $option['value'] ?? '';
        
        switch ($option['type']) {
            case 'checkbox':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                return $value ? __('Yes', 'vandel-booking') : __('No', 'vandel-booking');
                
            case 'number':
                return $value . ' ' . __('units', 'vandel-booking');
                
            case 'select':
            case 'radio':
                // Remove price in parentheses
                return preg_replace('/\s*\([^)]*\)/', '', $value);
                
            default:
                return is_array($value) ? implode(', ', $value) : (string) $value;
        }
    }
    
    /**
     * Format option price for display
     * 
     * @param array $option Option data
     * @return string Formatted price
     */
    public static function formatOptionPrice($option) {
        if (!is_array($option)) {
            return self::formatPrice(0);
        }
        
        if (isset($option['totalPrice'])) {
            return self::formatPrice($option['totalPrice']);
        }
        
        if (isset($option['price'])) {
            return self::formatPrice($option['price']);
        }
        
        return self::formatPrice(0);
    }
    
    /**
     * Get template part
     * 
     * @param string $template Template name
     * @param string $part Template part
     * @param array $args Template arguments
     * @return string Template content
     */
    public static function getTemplatePart($template, $part = '', $args = []) {
        $template_path = VANDEL_PLUGIN_DIR . 'templates/';
        
        if ($part) {
            $template = "{$template}-{$part}";
        }
        
        $template = $template_path . $template . '.php';
        
        if (file_exists($template)) {
            ob_start();
            
            // Extract args to variables
            if ($args && is_array($args)) {
                extract($args);
            }
            
            include $template;
            
            return ob_get_clean();
        }
        
        return '';
    }
}