<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Postnord_Integration_Checkout
{

    /**
     * Static class instance.
     *
     * @var null|Postnord_Interation_Woo_Shipping_Checkout
     */
    public static $instance = null;

    public function __construct()
    {
        add_action('woocommerce_after_shipping_rate', [$this, 'add_agent_select']);
        add_action('woocommerce_checkout_process', [$this, 'validate']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save']);
        add_filter('woocommerce_order_shipping_to_display', [$this, 'display_agent'], 10, 2);
    }

    public static function get_instance(): WC_Postnord_Integration_Checkout
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // TODO: Add options to autoselect pick up point in admin
    public function add_agent_select($shipping_rate)
    {
        $shipping_method = WC_Postnord_Integration::shipping_method_from_id($shipping_rate->id);
        if (empty($shipping_method) || 'yes' !== $shipping_method->get_option('wc_postnord_customer_selects_pickup')) {
            return;
        }

        $instance_id = $shipping_method->get_instance_id();

        // TODO: Not the best way to handle onclick but jQuery click events wont work.
        ?>
		<div class="postnord form-row" id="postnord_form_<?php echo sanitize_html_class($shipping_rate->id); ?>" data-method-id="<?php echo esc_attr($shipping_rate->id); ?>" >
			<input onclick="Global.getAgents('<?php echo esc_attr($shipping_rate->id); ?>')" type="button" class="postnord agents button alt" value="<?php esc_attr_e('Change pickup');?>" >
			<div class="select-agent"></div>
		</div>
		<?php
}

    /**
     * Adds tracking link to the order.
     *
     * @param string $shipping The HTLM for the shipping table row.
     * @param object $order The WooCommerce object.
     * @return string $shipping the default or updated HTML for the shipping table row.
     */
    public function display_agent($shipping, $order)
    {
        $agent = json_decode(stripslashes_deep($order->get_meta('_postnord_agent_json', true)));
        if (!empty($agent)) {
            $shipping .= '<br><small>' . sprintf(__(' Pickup point: %s ', 'wc-postnord-integration'), wc_clean($agent->name)) . '</small>';
        }
        return $shipping;
    }

    // TODO: Add option to autoselect pick up if none is selected
    public function validate()
    {
        // TODO: Not array if wc-enhanced-checkout. Should maybe change it? Is several shipping methods possible?
        //phpcs:disable
        $method_id = $_POST['shipping_method'];
        //phpcs:enable
        if (is_array($method_id) && count($method_id) > 0) {
            $method_id = reset($method_id);
        }
        $shipping_method = WC_Postnord_Integration::shipping_method_from_id($method_id);

        if (!$shipping_method) {
            return;
        }
        if (!empty($shipping_method) && 'yes' === $shipping_method->get_option('wc_postnord_customer_selects_pickup')) {
            $pickup_field_name = 'select_' . str_replace(':', '_', $method_id);
            //phpcs:disable
            if (empty($_POST[$pickup_field_name])) {
                wc_add_notice(__('Please enter a zip code and select a pick up point for your shipping method.', 'wc-postnord-integration'), 'error');
            }
            //phpcs:enable
        }
    }

    public function save($order_id)
    {
        $order = wc_get_order($order_id);

        // TODO: several shipping methods possible?
        $shipping_methods = $order->get_shipping_methods();

        if (!is_array($shipping_methods)) {
            WCPN_Logger::add('order_id: ' . $order_id . ' no shipping methods.');
            //phpcs:disable
            WCPN_Logger::add(var_export($shipping_methods, true));
            //phpcs:enable
            return;
        }

        $method = reset($shipping_methods);
        if (!method_exists($method, 'get_method_id')) {
            WCPN_Logger::add('order_id: ' . $order_id . ' get_method_id() does not exist.');
            //phpcs:disable
            WCPN_Logger::add(var_export($shipping_methods, true));
            //phpcs:enable
            return;
        }

        $method_id = $method->get_method_id();
        $instance_id = $method->get_instance_id();
        $shipping_method = WC_Postnord_Integration::shipping_method_from_id($method_id, $instance_id);

        if (empty($shipping_method) || 'yes' !== $shipping_method->get_option('wc_postnord_customer_selects_pickup')) {
            WCPN_Logger::add('order_id: ' . $order_id . ' - shipping method ( ' . print_r($shipping_method, true) . ' ) have empty shipping method or postnord_pickup is not equal to yes');
            return;
        }

        $field_name = 'select_' . str_replace(':', '_', $method_id) . '_' . str_replace(':', '_', $instance_id);
        WCPN_Logger::add('looking for: ' . $field_name);
        //phpcs:disable
        if (!empty($_POST[$field_name])) {
            WCPN_Logger::add('saving agent json: ' . $field_name . '  - ' . sanitize_text_field($_POST[$field_name]));
            $order->update_meta_data('_postnord_agent_json', sanitize_text_field($_POST[$field_name]));
            $order->save();
        }
        //phpcs:enable
    }
}

WC_Postnord_Integration_Checkout::get_instance();
