<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Postnord_Integration_Admin_Order {
	/**
	 * Static class instance.
	 *
	 * @var null|WC_Postnord_Integration_Admin_Order
	 */
	public static $instance = null;

	/**
	 * WC_Postnord_Integration_Admin_Order constructor.
	 */
	private function __construct() {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'admin_order_meta' ], 10, 1 );

	}

	/**
	 * Get a singelton instance of the class.
	 *
	 * @return WC_Postnord_Integration_Admin_Order
	 */
	public static function get_instance(): WC_Postnord_Integration_Admin_Order {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function admin_order_meta( $order ) {
		$service_id = $order->get_meta( '_postnord_serviceId', true );
		$service    = null;
		$parcel_nos = $order->get_meta( '_postnord_parcelNos', false );
		$shipment_no = $order->get_meta( '_postnord_shipmentNo', true );
		$agent = json_decode( stripslashes_deep( $order->get_meta( '_postnord_agent_json', true ) ) );

		$parcels    = array();
		foreach ( $parcel_nos as $parcel_no ) {
			$parcels[] = $parcel_no->value;
		}
		$parcels = implode( ', ', $parcels );

		$rows       = file( WC_POSTNORD_INTEGRATION_ABSPATH . 'data/services.tsv' );
		$header_row = array_shift( $rows );
		$header_row = explode( "\t", $header_row );
		foreach ( $rows as $row ) {
			$row  = explode( "\t", $row );
			$data = array_combine( $header_row, $row );
			if ( $data['Service Code'] === $service_id ) {
				$service = $data['Service'];
				break;
			}
		}

		$service_text = '';
		$shipment_no_text = '';
		$parcel_text  = '';
		$agent_name  = '';
		if ( ! empty( $service ) ) {
			//phpcs:disable
			$service_text = sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Service', 'wc-postnord-integration' ), wc_clean( $service ) );
			//phpcs:enable
		}
		if ( ! empty( $shipment_no ) ) {
			//phpcs:disable
			$shipment_no_text = sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Shipment number', 'wc-postnord-integration' ), wc_clean( $shipment_no ) );
			//phpcs:enable
		}
		if ( ! empty( $parcels ) ) {
			//phpcs:disable
			$parcel_text = sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Parcel Numbers', 'wc-postnord-integration' ), wc_clean( $parcels ) );
			//phpcs:enable
		}
		if ( ! empty( $agent ) ) {
			//phpcs:disable
			$agent_name = sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Pickup Point', 'wc-postnord-integration' ), wc_clean( $agent->name ) );
			//phpcs:enable
		}
		printf(
			'<h3>%1$s</h3><p>%2$s%3$s%4$s%5$s</p>',
			esc_html__( 'Shipment information', 'wc-postnord-integration' ),
			wp_kses_post( $service_text ),
			wp_kses_post( $shipment_no_text ),
			wp_kses_post( $parcel_text ),
			wp_kses_post( $agent_name )
		);
	}

}

WC_Postnord_Integration_Admin_Order::get_instance();
