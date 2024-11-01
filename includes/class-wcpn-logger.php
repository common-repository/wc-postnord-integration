<?php
/**
 * WCPN_Logger class
 *
 * @class         WCPN_Logger
 * @package        Woocommerce_Briox/Classes
 * @category    Logs
 */

defined('ABSPATH') || exit;

if (!class_exists('WCPN_Logger', false)) {

    class WCPN_Logger
    {

        private static $logger;
        private static $log_all;
        private static $handle = 'wc-postnord-integration';

        /**
         * Log
         *
         * @param string|arrray $message
         */
        public static function add($message, $force = false, $wp_debug = false)
        {
            if (empty(self::$logger)) {
                self::$logger = wc_get_logger();
                self::$log_all = 'yes' === get_option('woo_postnord_integration_logging');
            }

            if (true === self::$log_all || true === $force) {

                if (is_array($message)) {
                    $message = print_r($message, true);
                }

                self::$logger->add(
                    self::$handle,
                    $message
                );

                if (true === $wp_debug && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($message);
                }
            }
        }

        /**
         * separator function.
         *
         * Inserts a separation line for better overview in the logs.
         *
         * @access public
         * @return void
         */
        public static function separator()
        {
            self::add('-------------------------------------------------------');
        }

    }

}
