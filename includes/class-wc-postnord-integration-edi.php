<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Postnord_Integration_EDI', false)) {

    class Postnord_Integration_EDI
    {

        private $service_code = '';
        private $additional_service_codes = array();
        private $order = false;
        private $order_id = false;

        public function __construct($order_id)
        {
            $this->order = wc_get_order($order_id);
            $this->order_id = $order_id;

        }

        public function get_document()
        {
            return $this->create_document();
        }

        private function get_service_code()
        {

            $shipping_items = $this->order->get_items('shipping');
            $shipping_item = reset($shipping_items);
            $this->service_code = $shipping_item->get_meta('_postnord_service', true, 'edit');

            if (empty($this->service_code) || 'none' === $this->service_code) {
                return false;
            }

            return true;

        }

        /**
         * Check if the EDI was created or not and set appropriate update indication
         *
         * @param string $postnord_id
         *
         * @return string
         *
         * @since 1.0.0
         */
        public function get_update_indicator($postnord_id)
        {

            if (!empty($postnord_id)) {
                return "Update";
            } else {
                return "Original";
            }

        }

        private function create_general($postnord_id)
        {

            $edi = array(
                "messageDate" => date(DateTime::ISO8601),
                "updateIndicator" => $this->get_update_indicator($postnord_id),
                "application" => array(
                    "applicationId" => 1438,
                    "name" => "SD Business",
                ),
            );
            return $edi;
        }

        private function create_shipment_general($postnord_id)
        {

            $edi = array(
                "shipmentIdentification" => array(
                    "shipmentId" => (string) ($postnord_id ?: 0),
                ),
                "dateAndTimes" => array(
                    "loadingDate" => date(DateTime::ISO8601),
                ),
                "service" => array(
                    "basicServiceCode" => $this->service_code,
                    "additionalServiceCode" => $this->additional_service_codes,
                ),
                "numberOfPackages" => array(
                    "value" => 1,
                ),
                "transportLeg" => array(
                    "transportLegType" => "MAINTRANSPORT",
                ),
                "references" => array(
                    array(
                        "referenceNo" => $this->order_id,
                        "referenceType" => "CU",
                    ),
                ),
            );

            return $edi;
        }

        private function create_consignor()
        {
            return array(
                "consignor" => array(
                    "issuerCode" => "Z12",
                    "partyIdentification" => array(
                        "partyId" => get_option('postnord_customer_number'),
                        "partyIdType" => "160",
                    ),
                    "party" => array(
                        "nameIdentification" => array(
                            "name" => get_option('postnord_sender_name'),
                        ),
                        "address" => array(
                            "streets" => array(
                                get_option('postnord_sender_address1'),
                                get_option('postnord_sender_address2'),
                            ),
                            "postalCode" => get_option('postnord_sender_zipcode'),
                            "state" => get_option('postnord_sender_state'),
                            "city" => get_option('postnord_sender_city'),
                            "countryCode" => get_option('postnord_sender_country'),
                        ),
                        "contact" => array(
                            "contactName" => get_option('postnord_sender_contact_name', ''),
                            "emailAddress" => get_option('postnord_sender_contact_email', ''),
                            "phoneNo" => get_option('postnord_sender_contact_phone', ''),
                            "smsNo" => get_option('postnord_sender_contact_sms', ''),
                        ),
                    ),
                ),

            );
        }

        private function create_goods_item()
        {

            $parcel_content_text = get_option('postnord_integration_shipping_parcel_content');
            $is_single_parcel = get_option('postnord_integration_shipping_single_parcel');

            $items = array();
            $weight_sum = 0;
            $qty_sum = 0;
            $product_titles = [];

            foreach ($this->order->get_items() as $item) {

                $product = $item->get_product();
                $weight = $product->get_weight();

                switch (get_option('woocommerce_weight_unit')) {
                    case 'g':
                        $weight *= 0.001;
                        break;
                    case 'oz':
                        $weight *= 0.0283495231;
                        break;
                    case 'lbs':
                        $weight *= 0.45359237;
                        break;
                }

                // TODO: weight unit g/kg/lbs/oz?
                $weight *= absint($item['qty']);
                $weight_sum += $weight;
                $qty_sum += $item['qty'];
                $product_titles[] = $product->get_title();

                if ('yes' !== $is_single_parcel) {

                    $items[] = array(
                        "itemIdentification" => array(
                            "itemId" => "0",
                        ),
                        "grossWeight" => array(
                            "value" => $weight_sum,
                            "unit" => "KGM",
                        ),
                    );

                }

            }

            //If we have parcel amount in meta_data, use that, else default to 1
            $parcel_copies = $this->order->get_meta('_postnord_parcels', true, 'edit');
            $copies = 1;
            if (!empty($parcel_copies) && is_numeric($parcel_copies)) {
                $copies = $parcel_copies;
                //if multiple parcels, also divide weight per parcel.
                if ($weight_sum > 0) {
                    $weight_sum = $weight_sum / $copies;
                }
            }

            if ('yes' === $is_single_parcel) {

                if (empty($parcel_content_text)) {
                    $parcel_content_text = implode(', ', $product_titles);
                }
                $items[] = array(
                    "itemIdentification" => array(
                        "itemId" => "0",
                    ),
                    "grossWeight" => array(
                        "value" => 0.5,
                        "unit" => "KGM",
                    ),
                );

            }

            return array(
                "goodsItem" => array(
                    array(
                        "goodsDescription" => $parcel_content_text,
                        "packageTypeCode" => "PC",
                        "items" => $items,
                    ),
                ),
            );

        }

        private function create_consignee()
        {
            $name = $this->order->get_shipping_first_name() ? $this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name() : $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name();

            $address1 = $this->order->get_shipping_address_1() ? $this->order->get_shipping_address_1() : $this->order->get_billing_address_1();

            $address2 = $this->order->get_shipping_address_2() ? $this->order->get_shipping_address_2() : $this->order->get_billing_address_2();

            $zipcode = $this->order->get_shipping_postcode() ? $this->order->get_shipping_postcode() : $this->order->get_billing_postcode();

            $city = $this->order->get_shipping_city() ? $this->order->get_shipping_city() : $this->order->get_billing_city();

            $country = $this->order->get_shipping_country() ? $this->order->get_shipping_country() : $this->order->get_billing_country();

            $state = $this->order->get_shipping_state() ? $this->order->get_shipping_state() : $this->order->get_billing_state();

            $phone = $this->order->get_billing_phone();

            $email = $this->order->get_billing_email();

            return array(
                "consignee" => array(
                    "party" => array(
                        "nameIdentification" => array(
                            "name" => $name,
                        ),
                        "address" => array(
                            "streets" => array(
                                $address1,
                                $address2,
                            ),
                            "postalCode" => $zipcode,
                            "state" => $state,
                            "city" => $city,
                            "countryCode" => $country,
                        ),
                        "contact" => array(
                            "contactName" => $name,
                            "emailAddress" => $email,
                            "phoneNo" => $phone,
                            "smsNo" => $phone,
                        ),
                    ),
                ),
            );
        }

        private function create_cn22()
        {
            return false;
        }

        private function create_delivery_party()
        {

            $agent = array();

            $pickup_location = json_decode(stripslashes_deep($this->order->get_meta('_postnord_agent_json', true)));

            if (!empty($pickup_location) && isset($pickup_location['id'])) {

                $pickup_point = $wc_postnord_picup_points->get_service_point($pickup_location['id']);

                $agent = array(
                    "deliveryParty" => array(
                        "partyIdentification" => array(
                            "partyId" => $pickup_point->servicePointId,
                            "partyIdType" => "156",
                        ),
                        "party" => array(
                            "nameIdentification" => array(
                                "name" => $pickup_point->name,
                            ),
                            "address" => array(
                                "streets" => array(
                                    $pickup_point->visitingAddress->streetName . ' ' . $pickup_point->visitingAddress->streetNumber,
                                ),
                                "postalCode" => $pickup_point->visitingAddress->postalCode,
                                "city" => $pickup_point->visitingAddress->city,
                                "countryCode" => $pickup_point->visitingAddress->countryCode,
                            ),
                        ),
                    ),
                );

                return $agent;
            }

        }

        /**
         * Post EDI
         */
        private function create_document(): array
        {

            $edi = array();

            if (false !== ($this->get_service_code())) {

                $postnord_id = $this->order->get_meta('_postnord_item_id', true, 'edit');

                $document = $this->create_general($postnord_id ?: '');

                $parties = array(
                    'parties' => array_merge(
                        $this->create_consignor(),
                        $this->create_consignee()
                    ),
                );

                $edi = array();
                if ($shipment_general = $this->create_shipment_general($postnord_id)) {
                    $edi = array_merge($edi, $shipment_general);
                }
                if ($goods_item = $this->create_goods_item()) {
                    $edi = array_merge($edi, $goods_item);
                }
                if ($cn22 = $this->create_cn22()) {
                    $edi = array_merge($edi, $cn22);
                }
                if ($delivery_party = $this->create_delivery_party()) {
                    $edi = array_merge($edi, $delivery_party);
                }
                $document["shipment"] = array(array_merge($edi, $parties));

            }

            return $document;

        }

    }

}
