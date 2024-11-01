<?php

class WC_Postnord_Pickup_Points
{

    private $helpers = null;

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /**
     * Creates or returns an instance of this class.
     *
     * @return  Foo A single instance of this class.
     */
    public static function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @access private
     * @return void
     */
    private function __construct()
    {

        add_action('wp_ajax_get_points', array($this, 'ajax_get_points'));
        add_action('wp_ajax_nopriv_get_points', array($this, 'ajax_get_points'));
    }

    private function build_response($postcode, $data, $country_code)
    {
        $points = $data;

        $processed_points = array();

        if (!empty($points)) {
            foreach ($points as $key => $point) {
                if (!empty($point->visitingAddress->streetName)) {
                    $processed_point = new \stdClass();
                    $processed_point->servicePointId = isset($point->servicePointId) ? trim($point->servicePointId) : '';
                    $processed_point->name = isset($point->name) ? trim($point->name) : '';
                    $processed_point->visitingAddress = $this->get_delivery_address($point);

                    if (isset($point->backupApiUrl) && $point->backupApiUrl === true) {
                        $processed_point->openingHours = $point->openingHours;
                    } else {
                        $processed_point->openingHours = $this->get_working_time($point);
                    }

                    $processed_point->coordinate = $this->get_coordinates($point);
                    $processed_point->forwarderName = 'PostNord';

                    $processed_points[] = $processed_point;
                }
            }
        }

        return $processed_points;
    }

    public function vc_geocode($address, $postcode, $country_code)
    {
        $url = "https://maps.googleapis.com/maps/api/geocode/json?"
        . "address=" . urlencode($address) . "," . $postcode
            . "," . $country_code;

        $response = $this->curl_call($url);

        if (!empty($response->results)) {
            $origin = array(
                'type' => $response->results[0]->geometry->location_type,
                'postcode' => $postcode,
                'lat' => $response->results[0]->geometry->location->lat,
                'lng' => $response->results[0]->geometry->location->lng,
                'partial_match' => !empty($response->results[0]->partial_match) ? 1 : 0,
            );
        } else {
            $origin = array(
                'type' => null,
                'postcode' => null,
                'lat' => null,
                'lng' => null,
                'partial_match' => 0,
            );
        }

        return $origin;
    }

    public function ajax_get_points()
    {

        $country_code = $_POST['country_code'];

        $postnumber = $_POST['postcode'];

        return $this->get_service_points($postnumber, $country_code);
    }

    public function get_service_point($id)
    {
        return Postnord_API::execute('GET', 'businesslocation/v1/servicepoint/findNearestByAddress.json', $params);
    }
    public function get_service_points($postcode, $country_code = 'SE')
    {

        $params = array(
            "countryCode" => $country_code,
            "postalCode" => $postcode,
            "locale" => "en",
            "numberOfServicePoints" => 10
        );

        $service_selection = Postnord_API::execute('GET', 'businesslocation/v1/servicepoint/findNearestByAddress.json', $params);

        $params = array(
            "countryCode" => $country_code,
            "postalCode" => $postcode,
            "locale" => "en",
        );

        $default_service = Postnord_API::execute('GET', 'businesslocation/v1/servicepoint/findByPostalCode.json', $params);

        if (!empty($service_selection->servicePointInformationResponse->servicePoints)) {

            $service_points = $service_selection->servicePointInformationResponse->servicePoints;

            if (!empty($default_service->servicePointInformationResponse->servicePoints)) {
                $default_servicepont = reset($default_service->servicePointInformationResponse->servicePoints);
                foreach($service_points as $key => $service_point) {
                    if ($service_point->servicePointId == $default_servicepont->servicePointId) {
                        unset($service_points[$key]);
                    }
                }
                $service_points = array_merge(array($default_servicepont),$service_points);
            }

            $response = array(
                'data' => json_encode($service_points),
            );
        } else {
            $response = array(
                'error' => __('No pickup points found', 'wc-unifaun-shipping'),
            );

        }

        wp_send_json($response);

    }

    private function get_delivery_address($point)
    {

        $visitingAddress = new stdClass();
        $visitingAddress->countryCode = isset($point->visitingAddress->countryCode) ? trim($point->visitingAddress->countryCode) : '';
        $visitingAddress->city = isset($point->visitingAddress->city) ? trim($point->visitingAddress->city) : '';
        $visitingAddress->streetName = isset($point->visitingAddress->streetName) ? trim($point->visitingAddress->streetName) : '';
        $visitingAddress->streetNumber = isset($point->visitingAddress->streetNumber) ? trim($point->visitingAddress->streetNumber) : '';
        $visitingAddress->postalCode = isset($point->visitingAddress->postalCode) ? trim($point->visitingAddress->postalCode) : '';

        return $visitingAddress;
    }

    private function get_working_time($point)
    {
        global $vc_aino_widget;

        $weekdays_opening = isset($point->openingHours[0]->from1) ? substr_replace(trim($point->openingHours[0]->from1), ':', 2, 0) : '';
        $weekdays_closing = isset($point->openingHours[0]->to1) ? substr_replace(trim($point->openingHours[0]->to1), ':', 2, 0) : '';
        $weekends_opening = isset($point->openingHours[5]->from1) ? substr_replace(trim($point->openingHours[5]->from1), ':', 2, 0) : '';
        $weekends_closing = isset($point->openingHours[5]->to1) ? substr_replace(trim($point->openingHours[5]->to1), ':', 2, 0) : '';

        $openingHours = array(
            $vc_aino_widget->_t('days.mon') . '-' . $vc_aino_widget->_t('days.fri') . ': ' . $weekdays_opening . '-' . $weekdays_closing,
            $vc_aino_widget->_t('days.sat') . ': ' . $weekends_opening . '-' . $weekends_closing,
        );

        return $openingHours;
    }

    private function get_coordinates($point)
    {
        $coordinates = new \stdClass();
        $coordinates->northing = $point->coordinate->northing;
        $coordinates->easting = $point->coordinate->easting;
        $coordinates->srId = $point->coordinate->srId;

        return $coordinates;
    }

    private function default_postcodes($country_code)
    {
        if ($country_code == 'NO') {
            $postnumber = '0180';
        } else if ($country_code == "SE") {
            $postnumber = '11152';
        } else if ($country_code == "FI") {
            $postnumber = '00002';
        } else {
            $postnumber = '2630';
        }

        return $postnumber;
    }

}

$wc_postnord_picup_points = WC_Postnord_Pickup_Points::get_instance();
