<?php
namespace VandelBooking\Admin;

/**
 * Settings Page
 */
class SettingsPage {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Register settings
     */
    public function registerSettings() {
        // Currency settings
        register_setting('vandel_settings_group', 'vandel_currency');
        add_settings_section(
            'currency_section', 
            '', 
            null, 
            'vandel-settings'
        );
        add_settings_field(
            'vandel_currency', 
            __('Select Currency', 'vandel-booking'), 
            [$this, 'renderCurrencyField'], 
            'vandel-settings', 
            'currency_section'
        );
        
        // Branding settings
        register_setting('vandel_settings_group', 'vandel_primary_color');
        register_setting('vandel_settings_group', 'vandel_company_logo');
        add_settings_section(
            'branding_section', 
            '', 
            null, 
            'vandel-settings'
        );
        add_settings_field(
            'vandel_primary_color', 
            __('Primary Color', 'vandel-booking'), 
            [$this, 'renderPrimaryColorField'], 
            'vandel-settings', 
            'branding_section'
        );
        add_settings_field(
            'vandel_company_logo', 
            __('Company Logo', 'vandel-booking'), 
            [$this, 'renderCompanyLogoField'], 
            'vandel-settings', 
            'branding_section'
        );
        
        // Email notification settings
        register_setting('vandel_settings_group', 'vandel_email_recipients');
        register_setting('vandel_settings_group', 'vandel_email_subject');
        register_setting('vandel_settings_group', 'vandel_email_message');
        register_setting('vandel_settings_group', 'vandel_email_triggers');
        add_settings_section(
            'email_notifications_section', 
            '', 
            null, 
            'vandel-settings'
        );
        add_settings_field(
            'vandel_email_recipients',
            __('Notification Recipients', 'vandel-booking'),
            [$this, 'renderEmailRecipientsField'],
            'vandel-settings',
            'email_notifications_section'
        );
        add_settings_field(
            'vandel_email_subject',
            __('Email Subject', 'vandel-booking'),
            [$this, 'renderEmailSubjectField'],
            'vandel-settings',
            'email_notifications_section'
        );
        add_settings_field(
            'vandel_email_message',
            __('Email Message', 'vandel-booking'),
            [$this, 'renderEmailMessageField'],
            'vandel-settings',
            'email_notifications_section'
        );
        add_settings_field(
            'vandel_email_triggers',
            __('Booking Status Notifications', 'vandel-booking'),
            [$this, 'renderEmailTriggersField'],
            'vandel-settings',
            'email_notifications_section'
        );
    }
    
    /**
     * Render currency field
     */
    public function renderCurrencyField() {
        $currency = get_option('vandel_currency', 'USD');
        $currencies = [
            'USD' => ['name' => 'United States Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$'],
            'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'Mex$'],
            'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
            'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$'],
            'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr'],
            'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩'],
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
        ];
        ?>
        <select name="vandel_currency">
            <?php foreach ($currencies as $code => $data): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                    <?php echo esc_html("{$data['symbol']} - {$data['name']} ({$code})"); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Render primary color field
     */
    public function renderPrimaryColorField() {
        $color = get_option('vandel_primary_color', '#286cd6');
        ?>
        <input type="text" name="vandel_primary_color" value="<?php echo esc_attr($color); ?>" class="color-picker" data-default-color="#286cd6" />
        <p class="description"><?php _e('Select the primary color for your plugin.', 'vandel-booking'); ?></p>
        <?php
    }
    
    /**
     * Render company logo field
     */
    public function renderCompanyLogoField() {
        $logo_url = get_option('vandel_company_logo');
        ?>
        <input type="hidden" name="vandel_company_logo" id="vandel_company_logo" value="<?php echo esc_attr($logo_url); ?>" />
        <button type="button" class="button" onclick="uploadLogo()"><?php _e('Upload Logo', 'vandel-booking'); ?></button>
        
        <div id="logo-preview">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="Company Logo" style="max-width: 150px; margin-top: 10px;">
                <button type="button" class="button" onclick="removeLogo()"><?php _e('Remove Logo', 'vandel-booking'); ?></button>
            <?php endif; ?>
        </div>
        
        <script>
        function uploadLogo() {
            var customUploader = wp.media({
                title: '<?php _e('Select Logo', 'vandel-booking'); ?>',
                button: { text: '<?php _e('Use this logo', 'vandel-booking'); ?>' },
                multiple: false
            }).on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                document.getElementById('vandel_company_logo').value = attachment.url;
                document.getElementById('logo-preview').innerHTML = '<img src="' + attachment.url + '" alt="Company Logo" style="max-width: 150px; margin-top: 10px;">' +
                                                                '<button type="button" class="button" onclick="removeLogo()"><?php _e('Remove Logo', 'vandel-booking'); ?></button>';
            }).open();
        }

        function removeLogo() {
            document.getElementById('vandel_company_logo').value = '';
            document.getElementById('logo-preview').innerHTML = '';
        }
        </script>
        <?php
    }
    
    /**
     * Render email recipients field
     */
    public function renderEmailRecipientsField() {
        $recipients = get_option('vandel_email_recipients', get_option('admin_email'));
        ?>
        <textarea name="vandel_email_recipients" rows="3" placeholder="<?php _e('Enter recipient emails, separated by commas', 'vandel-booking'); ?>"><?php echo esc_textarea($recipients); ?></textarea>
        <p class="description"><?php _e('Enter email addresses to receive booking notifications, separated by commas.', 'vandel-booking'); ?></p>
        <?php
    }
    
    /**
     * Render email subject field
     */
    public function renderEmailSubjectField() {
        $subject = get_option('vandel_email_subject', __('Booking Confirmation', 'vandel-booking'));
        ?>
        <input type="text" name="vandel_email_subject" value="<?php echo esc_attr($subject); ?>" placeholder="<?php _e('Enter email subject line', 'vandel-booking'); ?>" />
        <?php
    }
    
    /**
     * Render email message field
     */
    public function renderEmailMessageField() {
        $message = get_option('vandel_email_message', __('Thank you for your booking. We look forward to serving you.', 'vandel-booking'));
        ?>
        <textarea name="vandel_email_message" rows="5" placeholder="<?php _e('Enter email message', 'vandel-booking'); ?>"><?php echo esc_textarea($message); ?></textarea>
        <p class="description"><?php _e('Customize the message sent to clients in the booking confirmation email.', 'vandel-booking'); ?></p>
        <?php
    }
    
    /**
     * Render email triggers field
     */
    public function renderEmailTriggersField() {
        $triggers = get_option('vandel_email_triggers', ['confirmed', 'canceled']);
        ?>
        <label><input type="checkbox" name="vandel_email_triggers[]" value="confirmed" <?php checked(in_array('confirmed', $triggers)); ?>> <?php _e('Confirmed', 'vandel-booking'); ?></label><br>
        <label><input type="checkbox" name="vandel_email_triggers[]" value="canceled" <?php checked(in_array('canceled', $triggers)); ?>> <?php _e('Canceled', 'vandel-booking'); ?></label><br>
        <label><input type="checkbox" name="vandel_email_triggers[]" value="pending" <?php checked(in_array('pending', $triggers)); ?>> <?php _e('Pending', 'vandel-booking'); ?></label><br>
        <p class="description"><?php _e('Select which booking statuses trigger email notifications.', 'vandel-booking'); ?></p>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render() {
        ?>
        <div class="wrap vandel-settings-page">
            <h1><?php _e('Vandel Booking Settings', 'vandel-booking'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('vandel_settings_group');
                do_settings_sections('vandel-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}