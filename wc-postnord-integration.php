<?php
/**
 * Plugin Name: Integrate Postnord with WooCommerce
 * Description: Creates shipping documents in Postnord based on WooCommerce orders
 * Version: 1.0.1
 * Author: BjornTech
 * Plugin URI: https://www.bjorntech.com/postnord-integration/?utm_source=wp-postnord&utm_medium=plugin&utm_campaign=product
 * Author URI: https://www.bjorntech.com/?utm_source=wp-postnord&utm_medium=plugin&utm_campaign=product
 * Text Domain: wc-postnord-integration
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_POSTNORD_INTEGRATION_ABSPATH', dirname(__FILE__) . '/');

if (!class_exists('WC_Postnord_Integration', false)) {

/**
 * Class WC_Postnord_Integration
 */
    class WC_Postnord_Integration
    {

        /**
         * The plugin version.
         *
         * @var string
         */
        public $plugin_version = '1.0.1';

        /**
         * @var string
         */
        protected $namespace = 'wc-postnord-integration';

        /**
         * Static class instance.
         *
         * @var null|WC_Postnord_Integration
         */
        public static $instance = null;
        /**
         * WC_Postnord_Integration constructor.
         */
        private function __construct()
        {

            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

            $this->includes();

            add_action('plugins_loaded', array($this, 'maybe_load_plugin'));

        }

        public function maybe_load_plugin()
        {

            if (!class_exists('WooCommerce')) {
                return;
            }

            include_once 'includes/class-wc-postnord-integration-instance-settings.php';
            include_once 'includes/class-wcpn-logger.php';
            include_once 'includes/class-wcpn-helper.php';
            include_once 'includes/class-wc-postnord-integration-exception.php';
            include_once 'includes/class-postnord-api.php';
            include_once 'includes/class-wc-postnord-integration-edi.php';

            add_filter('woocommerce_get_settings_pages', array($this, 'include_settings'));
            add_action('woocommerce_order_status_changed', [$this, 'update_woocommerce_order_status'], 5, 3);
            add_action('wp_ajax_postnord_connection', array($this, 'ajax_postnord_connection'));
            add_action('woocommerce_api_postnord', array($this, 'postnord_callback'));
            add_action('init', array($this, 'init'));

            if (is_admin()) {
                add_action('in_admin_header', array($this, 'add_admin_modal'));
                add_action('wp_ajax_postnord_check_activation', array($this, 'ajax_postnord_check_activation'));
                add_action('wp_ajax_postnord_set_service', array($this, 'ajax_postnord_set_service'));
                add_action('wcpn_sync_woocommerce_order', array($this, 'sync_order'));
            }

        }

        public function add_admin_modal()
        {?>
        <div id="postnord-modal-id" class="postnord-modal" style="display: none">
            <div class="postnord-modal-content postnord-centered">
                <span class="postnord-close">&times;</span>
                <div class="postnord-messages postnord-centered">
                    <h1><p id="postnord-status"></p></h1>
                </div>
                <div class="bjorntech-logo postnord-centered">
                    <img id="postnord-logo-id" class="postnord-centered" src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/bjorntech-logo.png'; ?>" />
                </div>
            </div>
        </div>
    <?php }

        public function ajax_postnord_set_service()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'ajax-postnord-integration')) {
                wp_die();
            }

            $order = wc_get_order($_POST['order_id']);

            $order_items = $order->get_items('shipping');
            $item_shipping = reset($order_items);
            $service = $item_shipping->get_meta('_postnord_service', true, 'edit');

            WCPN_Logger::add(print_r($service, true));

            if ($service != $_POST['service']) {
                $item_shipping->update_meta_data('_postnord_service', $_POST['service']);
                $item_shipping->save();
                WCPN_Logger::add(sprintf('Updated from %s to %s', $service, $_POST['service']));
            } else {
                WCPN_Logger::add(sprintf('No change from %s', $service));
            }

        }

        public function ajax_postnord_check_activation()
        {

            if (!wp_verify_nonce($_POST['nonce'], 'ajax-postnord-integration')) {
                wp_die();
            }

            $message = '';
            if (get_site_transient('postnord_handle_account')) {
                if ($connected = get_site_transient('postnord_connect_result')) {
                    delete_site_transient('postnord_handle_account');
                    delete_site_transient('postnord_connect_result');
                    if ($connected == 'failure') {
                        $message = __('The activation of the account failed', 'wc-postnord-integration');
                    }
                } else {
                    $message = __('We have sent a mail with the activation link. Click on the link to activate the service.', 'wc-postnord-integration');
                }
            } else {
                $connected = 'failure';
                $message = __('The link has expired, please connect again to get a new link.', 'wc-postnord-integration');
            }

            $response = array(
                'status' => $connected ? $connected : 'waiting',
                'message' => $message,
            );

            wp_send_json($response);

        }

        public function init()
        {

            Postnord_API::construct();

        }

        public function include_settings($settings)
        {
            $settings[] = require 'includes/class-wc-postnord-integration-general-settings.php';
            return $settings;
        }

        /**
         * Get a singelton instance of the class.
         *
         * @return WC_Postnord_Integration
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Add JS
         */
        public function enqueue_scripts()
        {
            wp_enqueue_script('wc-postnord-integration-script', plugins_url('/assets/js/postnord.js', __FILE__), ['jquery'], $this->plugin_version, true);

            wp_localize_script(
                'wc-postnord-integration-script',
                'wooPostnordIntegrationPhpVar',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'customerShippingCountry' => WC()->customer->get_shipping_country(),
                ]
            );
        }

        /**
         * Add Admin JS
         */
        public function admin_enqueue_scripts()
        {

            wp_register_style('postnord', plugin_dir_url(__FILE__) . 'assets/css/postnord.css', array(), $this->plugin_version);
            wp_enqueue_style('postnord');

            wp_register_style('postnord-print', plugin_dir_url(__FILE__) . 'assets/css/print.min.css', array(), $this->plugin_version);
            wp_enqueue_style('postnord-print');

            wp_enqueue_script('wc-postnord-integration-admin-script', plugins_url('/assets/js/admin.js', __FILE__), ['jquery'], $this->plugin_version, true);
            wp_localize_script(
                'wc-postnord-integration-admin-script',
                'postnord_admin',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ajax-postnord-integration'),
                ]
            );

            wp_enqueue_script('wcpn-print-script', plugins_url('/assets/js/print.min.js', __FILE__), false, $this->plugin_version, true);

        }

        /**
         * Add includes
         */
        public function includes()
        {
            include_once 'includes/class-wc-postnord-integration-checkout.php';
            include_once 'includes/class-wc-postnord-integration-order-list.php';
            include_once 'includes/class-wc-postnord-integration-admin-order.php';
            include_once 'includes/class-wc-postnord-integration-pickup.php';
        }

        /**
         * Trigger sync on orderstatus
         */
        public function update_woocommerce_order_status($order_id, $old_status, $new_status)
        {
            $sync_status = esc_attr(get_option('wc_postnord_shipping_sync_status'));
            $sync_status = str_replace('wc-', '', $sync_status);
            if ($new_status === $sync_status) {
                $this->sync_order($order_id);
            }
        }

        /**
         * Send EDI to postnord
         */
        public function sync_order($order_id)
        {
            $order = wc_get_order($order_id);

            $edi = new Postnord_Integration_EDI($order_id);
            $doc = $edi->get_document();
            $doc = WCPN_Helper::remove_blanks($doc);

            WCPN_Logger::add(json_encode($doc));
            //  WCPN_Logger::add(print_r($doc,true));

            try {

                $result = Postnord_API::send_edi_document($doc);

                $ids = reset($result->idInformation);

                foreach ($ids->ids as $id) {
                    if ($id->idType == "itemId") {
                        $order->update_meta_data('_postnord_item_id', $id->value);
                        $order->save();
                        WCPN_Logger::add(sprintf('sync_order (%s): Updating order with "_postnord_item_id" %s', $order_id, $id->value));
                    }
                }

                WCPN_Logger::add(print_r($result, true));

            } catch (WC_Postnord_API_Exception $e) {

                $e->write_to_logs();

            }

        }

        /**
         * Creates a new shipping method class instance from a method id.
         *
         * @param string $method_id
         * @return void|Class
         */
        public static function shipping_method_from_id($method_id, $instance_id = 0)
        {
            if (!$method_id) {
                return;
            }

            $method_data = explode(':', $method_id);
            $method_class_id = $method_data[0];

            // If instance ID is set in the method_data we use that. It's for older versions of WC.
            if (2 === count($method_data)) {
                $instance_id = $method_data[1];
            }

            //Table rate contains 3 values
            if (3 === count($method_data) && 'table_rate' === $method_class_id) {
                $instance_id = $method_data[1];
            }

            // Get all available shipping classes.
            $shipping_classes = WC()->shipping()->get_shipping_method_class_names();
            // Create a new instance of the shipping class and return it.
            if (isset($shipping_classes[$method_class_id])) {
                $shipping_method = new $shipping_classes[$method_class_id](absint($instance_id));
                return $shipping_method;
            }
        }

        public function postnord_callback()
        {
            if (array_key_exists('nonce', $_REQUEST) && $_REQUEST['nonce'] == get_site_transient('postnord_handle_account')) {

                if (array_key_exists('customer', $_REQUEST) && $_REQUEST['customer'] == get_option('postnord_customer_number')) {

                    $request_body = file_get_contents("php://input");
                    $json = json_decode($request_body);

                    if ($json !== null && json_last_error() === JSON_ERROR_NONE) {

                        update_option('wcpn_refresh_token', $json->refresh_token);
                        update_option('postnord_valid_to', $json->valid_to);
                        update_option('wcpn_uuid', $json->uuid);
                        delete_site_transient('wcpn_access_token');
                        WCPN_Logger::add(sprintf('Got refresh token %s from service', $json->refresh_token));
                        set_site_transient('postnord_connect_result', 'success', MINUTE_IN_SECONDS);
                        status_header(200, 'success');
                        return;
                    } else {

                        WCPN_Logger::add('Failed decoding authorize json');

                    }

                } else {

                    WCPN_Logger::add('Faulty call to admin callback');

                }

            } else {

                WCPN_Logger::add('Nonce not verified at postnord_callback');

            }

            set_site_transient('postnord_connect_result', 'failure', MINUTE_IN_SECONDS);

            status_header(403, 'unauthrized');

        }

        public function ajax_postnord_connection()
        {
            if (!wp_verify_nonce($_POST['nonce'], 'ajax-postnord-integration')) {
                wp_die();
            }

            if ('postnord_connect' == $_POST['id']) {

                $customer_number = get_option('customer_number');
                $user_email = get_option('postnord_user_email');
                $site_url = ($webhook_url = get_option('postnord_webhook_url')) ? $webhook_url : get_site_url();

                if (($customer_number == '' && $_POST['customer_number'] == '') || $_POST['customer_number'] == '') {
                    $response = array(
                        'result' => 'error',
                        'message' => __('A valid Customer number must be present.', 'wc-postnord-integration'),
                    );
                } elseif (($user_email == '' && $_POST['user_email'] == '') || $_POST['user_email'] == '') {
                    $response = array(
                        'result' => 'error',
                        'message' => __('A valid email address to where the verification mail is to be sent must be present.', 'wc-postnord-integration'),
                    );
                } else {

                    $customer_number = sanitize_text_field($_POST['customer_number']);
                    $user_email = sanitize_email($_POST['user_email']);
                    update_option('postnord_customer_number', $customer_number);
                    update_option('postnord_user_email', $user_email);

                    $nonce = wp_create_nonce('postnord_handle_account');
                    set_site_transient('postnord_handle_account', $nonce, DAY_IN_SECONDS);

                    $url = Postnord_API::get_service_url() . 'connect?' . http_build_query(array(
                        'user_email' => $user_email,
                        'plugin_version' => $this->plugin_version,
                        'customer' => $customer_number,
                        'site_url' => $site_url,
                        'nonce' => $nonce,
                    ));

                    WCPN_Logger::add($url);

                    $sw_response = wp_safe_remote_get($url, array('timeout' => 20));

                    if (is_wp_error($sw_response)) {
                        $code = $sw_response->get_error_code();
                        $error = $sw_response->get_error_message($code);
                        $response_body = json_decode(wp_remote_retrieve_body($sw_response));
                        $response = array(
                            'result' => 'error',
                            'message' => __('Something went wrong when connecting to the BjornTech service. Contact support at hello@bjorntech.com', 'wc-postnord-integration'),
                        );
                        WCPN_Logger::add(sprintf('Failed connecting the plugin to the service %s - %s', print_r($code, true), print_r($error, true)));
                    } else {
                        if ($response_body = json_decode(wp_remote_retrieve_body($sw_response))) {
                            $response = (array) $response_body;
                        }
                    }
                }
            }

            if ('postnord_disconnect' == $_POST['id']) {
                delete_option('wcpn_refresh_token');
                delete_site_transient('wcpn_access_token');
                delete_option('postnord_valid_to');
                $response = array(
                    'result' => 'success',
                    'message' => __('Successfully disconnected from postnord', 'wc-postnord-integration'),
                );
            }

            wp_send_json($response);

        }

    }

    $wc_pni = WC_Postnord_Integration::get_instance();

}
