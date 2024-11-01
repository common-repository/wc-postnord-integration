<?php

defined('ABSPATH') || exit;

if (!class_exists('WC_Postnord_Exception', false)) {
    class WC_Postnord_Exception extends Exception
    {
        /**
         * Contains a log object instance
         * @access protected
         */
        protected $log;

        /**
         * Contains the object instance
         * @access protected
         */
        protected $request_data;

        /**
         * Contains the url
         * @access protected
         */
        protected $request_url;

        /**
         * Contains the response data
         * @access protected
         */
        protected $response_data;

        /**
         * __Construct function.
         *
         * Redefine the exception so message isn't optional
         *
         * @access public
         * @return void
         */
        public function __construct($message, $code = 0, Exception $previous = null, $request_url = '', $request_data = '', $response_data = '')
        {
            // make sure everything is assigned properly
            parent::__construct($message, $code, $previous);

            $this->request_data = $request_data;
            $this->request_url = $request_url;
            $this->response_data = $response_data;
        }

        /**
         * write_to_logs function.
         *
         * Stores the exception dump in the WooCommerce system logs
         *
         * @access public
         * @return void
         */
        public function write_to_logs()
        {
            WCPN_Logger::separator();
            WCPN_Logger::add('Postnord Exception file: ' . $this->getFile(), true);
            WCPN_Logger::add('Postnord Exception line: ' . $this->getLine(), true);
            WCPN_Logger::add('Postnord Exception code: ' . $this->getCode(), true);
            WCPN_Logger::add('Postnord Exception message: ' . $this->getMessage(), true);
            WCPN_Logger::separator();
        }

        /**
         * write_standard_warning function.
         *
         * Prints out a standard warning
         *
         * @access public
         * @return void
         */
        public function write_standard_warning()
        {

            wp_kses(
                __("An error occured. For more information check out the <strong>wc-postnord-exception</strong> logs inside <strong>WooCommerce -> System Status -> Logs</strong>.", 'wc-postnord-exception'), array('strong' => array())
            );

        }
    }

}

if (!class_exists('WC_Postnord_API_Exception', false)) {
    class WC_Postnord_API_Exception extends WC_Postnord_Exception
    {
        /**
         * write_to_logs function.
         *
         * Stores the exception dump in the WooCommerce system logs
         *
         * @access public
         * @return void
         */
        public function write_to_logs()
        {
            WCPN_Logger::separator('error');
            WCPN_Logger::add('Postnord API Exception file: ' . $this->getFile(), true);
            WCPN_Logger::add('Postnord API Exception line: ' . $this->getLine(), true);
            WCPN_Logger::add('Postnord API Exception code: ' . $this->getCode(), true);
            WCPN_Logger::add('Postnord API Exception message: ' . $this->getMessage(), true);

            if (!empty($this->request_url)) {
                WCPN_Logger::add('Postnord API Exception Request URL: ' . $this->request_url, true);
            }

            if (!empty($this->request_data)) {
                WCPN_Logger::add('Postnord API Exception Request DATA: ' . $this->request_data, true);
            }

            if (!empty($this->response_data)) {
                WCPN_Logger::add('Postnord API Exception Response DATA: ' . $this->response_data, true);
            }

            WCPN_Logger::separator();

        }
    }
}
