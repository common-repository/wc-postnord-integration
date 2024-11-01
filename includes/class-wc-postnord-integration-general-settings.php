<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Postnord_Integration_General_Settings', false)) {

    class WC_Postnord_Integration_General_Settings extends WC_Settings_Page
    {

        public function __construct()
        {

            $this->id = 'postnord';
            $this->label = __('Postnord', 'wc-postnord-integration');

            $this->service_code_issuer_countries = WCPN_Helper::get_service_countries();
            $this->base_country = WCPN_Helper::get_base_country();
            $this->selectable_service_codes = WCPN_Helper::get_service_codes(get_option('wc_postnord_service_code_issuer_country', $this->base_country));
            $this->selectable_service_codes_combinations = WCPN_Helper::get_service_codes_combinations(get_option('wc_postnord_service_code_issuer_country', $this->base_country));

            add_action('woocommerce_settings_postnord_integration_general_settings', array($this, 'show_connection_button'), 20);

            $this->connected = get_option('wcpn_refresh_token');

            parent::__construct();
        }

        public function show_connection_button()
        {

            echo '<div id=postnord_titledesc_connect>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            if (!$this->connected) {
                echo '<label for="postnord_connect">' . __('Connect service', 'wc-postnord-integration') . '<span class="woocommerce-help-tip" data-tip="' . __('Connect the plugn to postnord', 'wc-postnord-integration') . '"></span></label>';
            } else {
                echo '<label for="postnord_disconnect">' . __('Disconnect service', 'wc-postnord-integration') . '<span class="woocommerce-help-tip" data-tip="' . __('Disconnect the plugin from postnord', 'wc-postnord-integration') . '"></span></label>';
            }
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            if (!$this->connected) {
                echo '<button name="postnord_connect" id="postnord_connect" class="button postnord-connect">' . __('Connect', 'wc-postnord-integration') . '</button>';
            } else {
                echo '<button name="postnord_disconnect" id="postnord_disconnect" class="button postnord-disconnect">' . __('Disconnect', 'wc-postnord-integration') . '</button>';
            }
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        /**
         * Get sections.
         *
         * @since 1.0.0
         *
         * @return array
         */
        public function get_sections()
        {

            $sections = array(
                '' => __('General settings', 'wc-postnord-integration'),
                'services' => __('Services', 'wc-postnord-integration'),
                'sender' => __('Sender information', 'wc-postnord-integration'),
            );

            if (!empty($services = get_option('wc_postnord_services', array()))) {
                foreach ($services as $service) {
                    if (!array_key_exists($service, $sections)) {
                        $sections[strval($service)] = $this->selectable_service_codes[$service];
                    }
                }
            }

            $sections['advanced'] = __('Advanced', 'wc-postnord-integration');
            return $sections;

        }

        /**
         * Output the settings.
         */
        public function output()
        {
            global $current_section;
            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::output_fields($settings);
        }

        /**
         * Save settings.
         */
        public function save()
        {
            global $current_section;

            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::save_fields($settings);
        }

        /**
         * Get settings.
         *
         * @since 1.0.0
         *
         * @param string $current_section
         *
         * @return array
         */
        public function get_settings($current_section = '')
        {

            if ('services' == $current_section) {

                $settings[] = array(
                    'title' => __('Services', 'wc-postnord-integration'),
                    'type' => 'title',
                    'id' => 'postnord_integration_services_settings',
                );
                $settings[] = array(
                    'id' => 'wc_postnord_service_code_issuer_country',
                    'title' => __('Issuer country', 'wc-postnord-integration'),
                    'type' => 'select',
                    'default' => $this->base_country,
                    'options' => array_merge(array('' => __('Select country for service codes', 'wc-postnord-integration')), $this->service_code_issuer_countries),
                );

                $settings[] = array(
                    'id' => 'wc_postnord_services',
                    'title' => __('Postnord Service', 'wc-postnord-integration'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select',
                    'css' => 'width: 400px;',
                    'default' => array(),
                    'options' => $this->selectable_service_codes,
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select service codes', 'wc-postnord-integration'),
                    ),
                );
                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'postnord_integration_services_settings',
                );

            } elseif ('sender' == $current_section) {

                $settings[] = array(
                    'title' => __('Sender', 'wc-postnord-integration'),
                    'type' => 'title',
                    'id' => 'postnord_integration_sender_settings',
                );

                $settings[] = array(
                    'title' => __('Sender name'),
                    'id' => 'postnord_sender_name',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Sender address 1'),
                    'id' => 'postnord_sender_address1',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Sender address 2'),
                    'id' => 'postnord_sender_address2',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Zipcode'),
                    'id' => 'postnord_sender_zipcode',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('State'),
                    'id' => 'postnord_sender_state',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('City'),
                    'id' => 'postnord_sender_city',
                    'type' => 'text',
                );
                $settings[] = array(
                    'title' => __('Country'),
                    'id' => 'postnord_sender_country',
                    'type' => 'text',
                );

                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'postnord_integration_sender_settings',
                );

            } elseif (in_array($current_section, get_option('wc_postnord_services', array()))) {

                $settings[] = array(
                    'title' => $this->selectable_service_codes[$current_section],
                    'type' => 'title',
                    'id' => 'postnord_integration_' . $current_section . '_settings',
                );

                $selectable_additional_codes = array();
                foreach ($this->selectable_service_codes_combinations->adnlServiceCodeCombDetails as $selectable_service_codes_combination) {
                    if ($selectable_service_codes_combination->serviceCode === $current_section) {
                        $selectable_additional_codes[$selectable_service_codes_combination->adnlServiceCode] = $selectable_service_codes_combination->adnlServiceName . ' (' . $selectable_service_codes_combination->adnlServiceCode . ')';
                    }
                }

                $settings[] = array(
                    'id' => 'postnord_integration_additional_service_codes_' . $current_section,
                    'title' => __('Postnord Service', 'wc-postnord-integration'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select',
                    'css' => 'width: 400px;',
                    'default' => array(),
                    'options' => $selectable_additional_codes,
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select additional service codes', 'wc-postnord-integration'),
                    ),
                );

                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'postnord_integration_' . $current_section . '_settings',
                );

            } elseif ('advanced' == $current_section) {

                $settings[] = array(
                    'title' => __('Postnord', 'wc-postnord-integration'),
                    'type' => 'title',
                    'id' => 'postnord_integration_advanced_settings',
                );

                $settings[] = array(
                    'title' => __('Auto-Sync on status', 'wc-postnord-integration'),
                    'id' => 'wc_postnord_shipping_sync_status',
                    'desc' => __('Select on what order status customer information should be synced automatically', 'wc-postnord-integration'),
                    'type' => 'select',
                    'options' => array_merge(array('' => __('Do not sync automatically', 'wc-postnord-integration')), wc_get_order_statuses()),
                );

                // TODO: add option to products?

                $settings[] = array(
                    'title' => __('Alternate service url'),
                    'id' => 'wc_postnord_integration_alternate_service_url',
                    'default' => __('Items', 'wc-postnord-integration'),
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Alternate webhook url', 'woo-fortnox-hub'),
                    'type' => 'text',
                    'description' => __('The url used for webhook callback. Do NOT change unless instructed by BjornTech.', 'woo-fortnox-hub'),
                    'default' => '',
                    'id' => 'postnord_webhook_url',
                );

                $settings[] = array(
                    'title' => __('Parcel content text'),
                    'id' => 'postnord_integration_shipping_parcel_content',
                    'default' => __('Items', 'wc-postnord-integration'),
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Notification "From" email', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_from_email',
                    'type' => 'text',
                    'default' => get_option('admin_email'),
                );

                $settings[] = array(
                    'title' => __('Notification "CC" email', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_cc_email',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Notification "BCC" email', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_bcc_email',
                    'type' => 'text',
                );

                $settings[] = array(
                    'title' => __('Notifications language code', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_languagecode',
                    'type' => 'select',
                    'options' => array(
                        'SE' => 'SE',
                        'EN' => 'EN',
                        'DK' => 'DK',
                        'FI' => 'FI',
                    ),
                );

                $settings[] = array(
                    'title' => __('Notification email message', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_message',
                    'type' => 'text',
                    'desc' => __('General message in pre-notification e-mail. Use placeholder [orderid] to print the orders id in the message.', 'wc-postnord-integration'),
                );

                $settings[] = array(
                    'title' => __('Notification mail template', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_notification_mailtemplate',
                    'type' => 'text',
                    'desc' => __('E-mail template name. Requires Branded pre-notification feature.', 'wc-postnord-integration'),
                );

                $settings[] = array(
                    'title' => __('Single parcel per order', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_shipping_single_parcel',
                    'type' => 'checkbox',
                );

                $settings[] = array(
                    'title' => __('Auto complete shipment', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_shipping_auto_print',
                    'type' => 'checkbox',
                );

                $settings[] = array(
                    'title' => __('Test mode', 'wc-postnord-integration'),
                    'id' => 'postnord_integration_shipping_test',
                    'type' => 'checkbox',
                );

                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'postnord_integration_advanced_settings',
                );

            } else {

                $settings[] = array(
                    'title' => __('Postnord', 'wc-postnord-integration'),
                    'type' => 'title',
                    'id' => 'postnord_integration_general_settings',
                );

                if (!$this->connected) {

                    $settings[] = array(
                        'title' => __('Postnord customer number'),
                        'desc' => __('Your Postnord customer number'),
                        'id' => 'postnord_customer_number',
                        'type' => 'text',
                    );

                    $settings[] = array(
                        'title' => __('Postnord User email', 'wc-postnord-integration'),
                        'type' => 'email',
                        'default' => '',
                        'id' => 'postnord_user_email',
                    );

                }

                $settings[] = array(
                    'title' => __('Enable logging', 'wc-postnord-integration'),
                    'id' => 'woo_postnord_integration_logging',
                    'type' => 'checkbox',
                );

                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'postnord_integration_general_settings',
                );
            }

            return apply_filters('wc_postnord_general_settings', $settings);
        }
    }

    class WCPN_Service_code
    {

        private $settings_array = array(
            '00' => 'text',
        );

        public function get_code_settings($code)
        {
            if (array_key_exists($code, $settings_array)) {

            }
            return false;
        }
    }

    return new WC_Postnord_Integration_General_Settings();
}
