<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Postnord_API', false)) {

    class Postnord_API
    {

        const SERVICE_URL = 'https://postnord.bjorntech.net/v1';
        private static $uuid;
        private static $service_url;
        private static $refresh_token;
        private static $access_token;
        private static $account_uuid;

        public static function construct()
        {
            self::$service_url = trailingslashit(get_option('wc_postnord_integration_alternate_service_url') ?: self::SERVICE_URL);
            self::$uuid = get_option('wcpn_uuid');
            self::$refresh_token = get_option('wcpn_refresh_token');
        }

        public static function get_service_url()
        {
            return self::$service_url;
        }

        private static function get_access_token()
        {

            global $wc_pni;

            if (!empty(self::$refresh_token)) {

                $time_now = time();

                if (false === ($access_token = get_site_transient('wcpn_access_token'))) {

                    $body = array(
                        'grant_type' => 'refresh_token',
                        'account_uuid' => self::$account_uuid,
                        'refresh_token' => self::$refresh_token,
                        'plugin_version' => $wc_pni->plugin_version,
                    );

                    $args = array(
                        'headers' => array(
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ),
                        'timeout' => 60,
                        'body' => $body,
                    );

                    $url = self::$service_url . 'token';

                    $response = wp_remote_post($url, $args);

                    if (is_wp_error($response)) {

                        $code = $response->get_error_code();
                        $error = $response->get_error_message($code);
                        throw new WC_Postnord_API_Exception($error, 0, null, $url, json_encode($body));

                    } else {

                        $response_body = json_decode(wp_remote_retrieve_body($response));

                        if (($http_code = wp_remote_retrieve_response_code($response)) != 200) {
                            throw new WC_Postnord_API_Exception($response_body->error, $http_code, null, $url, $body, $response);
                        }

                        set_site_transient('wcpn_access_token', $response_body->access_token, $response_body->expires_in);
                        update_option('wcpn_refresh_token', self::$refresh_token = $response_body->refresh_token);

                    }

                }

            } else {

                throw new WC_Postnord_API_Exception('No connection to BjornTech Postnord service', 9999);

            }

            return $access_token;

        }

        public static function execute($method, $path, $body = null)
        {

            if (is_array($body) && !empty($body)) {

                if ('GET' == $method || 'DELETE' == $method) {
                    $path .= '?' . preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', http_build_query($body, '', '&'));
                    $body = null;
                } else {
                    $body = json_encode($body, JSON_UNESCAPED_SLASHES);
                }

            }

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . self::get_access_token(),
                    'X-Url' => $path,
                    'X-Headers' => $body ? json_encode(array("Content-Type" => "application/json", "Accept" => "application/json")) : null,
                ),
                'body' => $body,
                'method' => $method,
                'timeout' => 60,
            );

            $response = wp_remote_request(self::$service_url . 'api', $args);

            if (is_wp_error($response)) {

                $code = $response->get_error_code();
                WCPN_Logger::add(print_r($code, true));
                $error = $response->get_error_message($code);
                WCPN_Logger::add(print_r($error, true));
                throw new WC_Postnord_API_Exception($error . ' ' . $code, 999, null, self::$service_url);

            } else {

                $response_body_json = wp_remote_retrieve_body($response);
                $response_body = json_decode($response_body_json);

                if (($http_code = wp_remote_retrieve_response_code($response)) > 299) {

                    if ($response_body && is_object($response_body) && property_exists($response_body, 'message')) {

                        if (property_exists($response_body, 'compositeFault')) {
                            foreach ($response_body->compositeFault->faults as $fault) {
                                WCPN_Logger::add(sprintf('execute (%s): %s - %s', $http_code, $response_body->message, $fault->explanationText));
                            }
                        }

                        throw new WC_Postnord_API_Exception($response_body->message, $http_code, null, $path, $body, $response_body_json);

                    } else {

                        throw new WC_Postnord_API_Exception(sprintf(__('Error %s occured when communicating with Postnord', 'woo-postnord-e-commerce'), $http_code), $http_code, null, $path, $body, $response_body_json);

                    }
                }

                return $response_body;

            }

        }

        public static function get_api_service_codes()
        {

            $service_codes = get_site_transient('wcpn_service_codes');
            if (!is_object($service_codes)) {
                $service_codes = self::execute('GET', 'shipment/v3/edi/servicecodes');
                set_site_transient('wcpn_service_codes', $service_codes, WEEK_IN_SECONDS);
            }

            return array_column($service_codes->data, null, 'issuerCountry');

        }

        public static function get_api_additional_service_codes()
        {

            $adnl_service_codes = get_site_transient('postnord_additional_api_service_codes');
            if (!is_object($adnl_service_codes)) {
                $adnl_service_codes = self::execute('GET', 'shipment/v3/edi/adnlservicecodes');
                set_site_transient('postnord_additional_api_service_codes', $adnl_service_codes, WEEK_IN_SECONDS);
            }

            return array_column($adnl_service_codes->data, null, 'issuerCountry');

        }

        public static function get_api_additional_service_codes_combinations()
        {

            $adnl_service_codes_combinations = get_site_transient('wcpn_service_codes_combinations');
            if (!is_object($adnl_service_codes_combinations)) {
                $adnl_service_codes_combinations = self::execute('GET', 'shipment/v3/edi/servicecodes/adnlservicecodes/combinations');
                set_site_transient('wcpn_service_codes_combinations', $adnl_service_codes_combinations, WEEK_IN_SECONDS);
            }

            return array_column($adnl_service_codes_combinations->data, null, 'issuerCountry');

        }

        public static function send_edi_document($document)
        {

            return self::execute('POST', 'shipment/v3/edi', $document);

        }

        public static function print_shipment($order)
        {

            $postnord_id = $order->get_meta('_postnord_item_id', true, 'edit');
            $order_id = $order->get_id();

            if (empty($postnord_id)) {
                WCPN_Logger::add(sprintf('print_shipment (%s): Meta "_postnord_item_id" is missning', $order_id));
                throw new WC_Postnord_API_Exception(__('Trying to print a shipment before syncing', 'woo-postnord-e-commerce'), 9999);
            }

            $params = array(
                "paperSize" => "A4",
                "rotate" => 0,
                "multiPDF" => false,
                "pnInfoText" => false,
                "labelsPerPage" => 100,
                "page" => 1,
                "processOffline" => false,
            );

            $request_data = array(
                array(
                    "id" => (string) $postnord_id,
                ),
            );

            $body = self::execute('POST', 'shipment/v3/labels/ids/pdf?' . http_build_query($params, '', '&'), $request_data);

            if (isset($body[0]->printout)) {

                WCPN_Logger::add(sprintf('print_shipment (%s): Successfully got print data', $order_id));

                $body = apply_filters('wcpn_shipment_print_response_body', $body, $order);

                $order->update_meta_data('_postnord_parcel_printout', $body[0]->itemIds[0]->status);
                $order->save();

                return $body[0]->printout;

            } else {

                WCPN_Logger::add(sprintf('print_shipment (%s): Failed to find print data %s', $order_id, wp_json_encode($body)));
                return null;
            }

        }

    }

}
