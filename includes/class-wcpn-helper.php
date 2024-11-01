<?php
/**
 * WCPN_Helper class
 *
 * @class         WCPN_Helper
 * @package        Woocommerce_Briox/Classes
 * @category    Logs
 */

defined('ABSPATH') || exit;

if (!class_exists('WCPN_Helper', false)) {

    class WCPN_Helper
    {

        public static function get_base_country()
        {
            $wc_countries = new WC_Countries();
            switch ($wc_countries->get_base_country()) {
                case 'SE':
                    $country = 'SWEDEN';
                    break;
                case 'DK':
                    $country = 'DENMARK';
                    break;
                case 'FI':
                    $country = 'FINLAND';
                    break;
                case 'NO':
                    $country = 'NORWAY';
                    break;
                default:
                    $country = '';
            }

            return $country;

        }

        public static function get_service_codes_combinations($base_country)
        {

            $service_codes_combination = array();

            try {

                $service_codes_combinations = Postnord_API::get_api_additional_service_codes_combinations();
                $service_codes_combination = $service_codes_combinations[$base_country];

            } catch (WC_Postnord_API_Exception $e) {

                $e->write_to_logs();

            }

            return $service_codes_combination;

        }

        public static function get_service_codes($base_country)
        {

            $selectable_service_codes = array();

            try {

                $service_codes = Postnord_API::get_api_service_codes();

                foreach ($service_codes[$base_country]->serviceCodeDetails as $service_code) {
                    $selectable_service_codes[strval($service_code->serviceCode)] = $service_code->serviceName . ' (' . $service_code->serviceCode . ')';
                }

            } catch (WC_Postnord_API_Exception $e) {

                $e->write_to_logs();

            }

            return $selectable_service_codes;

        }

        public static function get_service_countries()
        {

            $service_code_issuer_countries = array();

            try {

                $service_codes = Postnord_API::get_api_service_codes();
                $countries = array_keys($service_codes);

                foreach ($countries as $country) {
                    $service_code_issuer_countries[$country] = ucfirst(strtolower($country));
                }

            } catch (WC_Postnord_API_Exception $e) {

                $e->write_to_logs();

            }
            return $service_code_issuer_countries;

        }

        public static function remove_blanks($items)
        {
            if (is_array($items)) {

                foreach ($items as $key => $item) {

                    if (empty($item) && !is_numeric($item)) {

                        unset($items[$key]);
                        WCPN_Logger::add(print_r($key, true));

                    } elseif (empty($item) && !is_numeric($item)) {

                        if (!empty($result = self::remove_blanks($item))) {
                            $items[$key] = $result;
                        } else {
                            unset($items[$key]);
                        }

                    }
                }
            }

            return $items;
        }
    }

}
