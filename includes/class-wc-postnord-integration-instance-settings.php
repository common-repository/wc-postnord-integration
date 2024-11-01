<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Postnord_Integration_Instance_Settings
{

    /**
     * Static class instance.
     *
     * @var null|WC_Postnord_Integration_Instance_Settings
     */
    public static $instance = null;

    public function __construct()
    {
        add_action('init', [$this, 'init_hooks']);
    }

    public static function get_instance(): WC_Postnord_Integration_Instance_Settings
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init_hooks()
    {
        $available_shipping_methods = WC()->shipping()->load_shipping_methods();
        foreach ($available_shipping_methods as $shipping_method) {

            $shipping_method_id = $shipping_method->id;

            add_filter('woocommerce_shipping_instance_form_fields_' . $shipping_method_id, array($this, 'additional_shipping_method_fields'));

        }
    }

    public function additional_shipping_method_fields($form_fields)
    {

        if (array_key_exists('cost', $form_fields) && array_key_exists('sanitize_callback', $form_fields['cost']) && !empty($form_fields['cost']['sanitize_callback']) && ($shipping_object = $form_fields['cost']['sanitize_callback'][0])) {

            $current_options = get_option($shipping_object->get_instance_option_key());

            $base_country = WCPN_Helper::get_base_country();
            $selectable_service_codes = WCPN_Helper::get_service_codes(get_option('wc_postnord_service_code_issuer_country', $base_country));
            $services = array_intersect_key($selectable_service_codes, array_flip(get_option('wc_postnord_services', array())));

            $form_fields['postnord_service'] = array(
                'title' => __('Postnord Service', 'wc-postnord-integration'),
                'type' => 'select',
                'default' => '',
                'options' => array_merge(array('' => __('No pre-selected service', 'wc-postnord-integration')), $services),
            );

            if (array_key_exists('postnord_service', $current_options) && ($current_section = $current_options['postnord_service'])) {

                if (!empty($additional_service_codes = get_option('postnord_integration_additional_service_codes_' . $current_section, array()))) {
                    foreach ($additional_service_codes as $additional_service_code) {
                        $form_fields['postnord_integration_additional_service_codes_' . $current_section . '_' . $additional_service_code] = array(
                            'title' => $selectable_additional_codes[$additional_service_code],
                            'type' => 'checkbox',
                        );
                    }
                }

            }

        }

        /*      $form_fields['wc_postnord_customer_selects_pickup'] = array(
        'title' => __('Customer selects pickup', 'wc-postnord-integration'),
        'type' => 'checkbox',
        'default' => '',
        );

        $form_fields['wc_postnord_add_customs'] = array(
        'title' => __('Postnord Add Customs', 'wc-postnord-integration'),
        'type' => 'checkbox',
        'default' => '',
        );

        $form_fields['postnord_dispatch'] = array(
        'title' => __('Postnord Delivery', 'wc-postnord-integration'),
        'type' => 'checkbox',
        'default' => '',
        );

        $form_fields['postnord_delivery'] = array(
        'title' => __('Postnord Dispatch', 'wc-postnord-integration'),
        'type' => 'checkbox',
        'default' => '',
        );*/

        return apply_filters('wc_postnord_instance_settings', $form_fields);
    }
}

WC_Postnord_Integration_Instance_Settings::get_instance();
