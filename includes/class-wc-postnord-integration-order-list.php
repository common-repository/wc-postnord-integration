<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Postnord_Integration_Order_List
{
    /**
     * Static class instance.
     *
     * @var null|WC_Postnord_Integration_Order_List
     */
    public static $instance = null;

    /**
     * WC_Postnord_Integration_Order_List constructor.
     */
    private function __construct()
    {
        // TODO: Is it better to use Rest for admin side aswell?
        add_action('wp_ajax_postnord_sync', array($this, 'sync_order'));
        add_action('wp_ajax_postnord_print', array($this, 'print_order'));
        add_action('wp_ajax_postnord_delete', array($this, 'delete_order'));

        add_filter('manage_edit-shop_order_columns', array($this, 'add_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_column_content'));
        add_action('admin_print_styles', array($this, 'add_style'));

        // bulk sync
        if (is_admin()) {
            add_action('admin_footer-edit.php', array($this, 'bulk_sync_admin_footer'));
            add_action('load-edit.php', array($this, 'bulk_sync_action'));
        }
    }

    /**
     * Get a singelton instance of the class.
     *
     * @return WC_Postnord_Integration
     */
    public static function get_instance(): WC_Postnord_Integration_Order_List
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Adds 'Postnord' column header to 'Orders' page
     *
     * @param string[] $columns
     * @return string[] $columns
     */
    public function add_column($columns)
    {

        $columns['order_postnord'] = __('Postnord', 'wc-postnord-integration');
        return $columns;

    }

    /**
     * Adds 'Postnord' column content to 'Orders' page
     *
     * @param string[] $column name of column being displayed
     */
    public function add_column_content($column)
    {

        global $post;

        if ('order_postnord' === $column) {

            $order = wc_get_order($post->ID);
            $order_items = $order->get_items('shipping');
            $item_shipping = reset($order_items);

            if (empty($item_shipping)) {
                return;
            }

            $postnord_service = $item_shipping->get_meta('_postnord_service', true, 'edit');

            if (empty($postnord_service) || 'none' === $postnord_service) {

                if (!empty($services = get_option('wc_postnord_services', array()))) {

                    $base_country = WCPN_Helper::get_base_country();
                    $selectable_service_codes = WCPN_Helper::get_service_codes(get_option('wc_postnord_service_code_issuer_country', $base_country));

                    echo '<ul class="submitbox">';
                    echo '<li class="wide wcpn_order_actions" id="actions">';
                    echo '<select name="wcpn_order_action">';
                    echo '<option value="">' . __('Select service', 'wc-postnord-integration') . '</option>';

                    foreach ($services as $service) {
                        echo '<option value="' . $service . '">' . $selectable_service_codes[$service] . '</option>';
                    }

                    echo '</select>';
                    echo '<button class="button wc-action-button button wc-reload" id="' . esc_html($order->get_id()) . '">' . __('Sync', 'wc-postnord-integration') . '</button>';
                    echo '</li>';
                    echo '</ul>';
                }

                return;

            }

			$postnord_id = $order->get_meta('_postnord_item_id', true, 'edit');
			$parcel_printout = 	$order->get_meta('_postnord_parcel_printout', true, 'edit');

            if (!empty($postnord_id) && 'failed' === $postnord_id) {
                echo '<a class="button button wc-action-button postnord sync" data-order-id="' . esc_html($order->get_id()) . '">' . sprintf(__('Resync (%s)', 'wc-postnord-integration'), $postnord_service) . '</a>';
            } elseif (!empty($postnord_id) && is_numeric($postnord_id)) {
				echo '<a class="button button wc-action-button postnord sync" data-order-id="' . esc_html($order->get_id()) . '">' . sprintf(__('Resync (%s)', 'wc-postnord-integration'), $postnord_service) . '</a>';
				if ($parcel_printout == 'OK') {
					echo '<a class="button button wc-action-button postnord print" data-order-id="' . esc_html($order->get_id()) . '">' . __('Printed', 'wc-postnord-integration') . '</a>';
				} else {
					echo '<a class="button button wc-action-button postnord print" data-order-id="' . esc_html($order->get_id()) . '">' . __('Print', 'wc-postnord-integration') . '</a>';
				}

            } else {
                echo '<a class="button button wc-action-button postnord sync" data-order-id="' . esc_html($order->get_id()) . '">' . sprintf(__('Sync (%s)', 'wc-postnord-integration'), $postnord_service) . '</a>';
            }
        }
    }

    /**
     * Adjusts the styles for the new 'Postnord' column.
     *
     * @return void
     */
    public function add_style()
    {
        $css = '.widefat .column-order_date, .widefat .column-order_postnord { width: 9%; }';
        wp_add_inline_style('woocommerce_admin_styles', $css);
    }

    /**
     * Sync order
     *
     * @return void
     */
    public function sync_order()
    {
        if (!defined('ABSPATH')) {
            exit;
        }

        do_action('wcpn_sync_woocommerce_order', $_POST['order_id']);

        die;

    }

    /**
     * Print order
     */
    public function print_order()
    {
        if (!defined('ABSPATH')) {
            exit;
        }

        $order = wc_get_order($_POST['order_id']);

        try {

			$pdf = Postnord_API::print_shipment($order);

        } catch (WC_Postnord_API_Exception $e) {

            $e->write_to_logs();

		}

		wp_send_json( $pdf );

    }

    /**
     * Delete order
     */
    public function delete()
    {
        if (!defined('ABSPATH')) {
            exit;
        }

        // TODO!
        die;
    }

    public function bulk_sync($order_ids)
    {
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            Postnord_API::post_edi($order);
        }
    }

    public function bulk_sync_admin_footer()
    {
        global $post_type;

        if ('shop_order' === $post_type) {
            ?>
				<script>
					jQuery(document).ready(function() {
						jQuery('<option>').val('postnord_bulk_sync').text('<?php esc_html_e('Postnord Bulk Sync');?>').appendTo("select[name='action']");
						jQuery('<option>').val('postnord_bulk_sync').text('<?php esc_html_e('Postnord Bulk Sync');?>').appendTo("select[name='action2']");
					});
				</script>
			<?php
}
    }

    public function bulk_sync_action()
    {
        global $typenow;
        $post_type = $typenow;

        if ('shop_order' === $post_type) {

            // get the action
            $wp_list_table = _get_list_table('WP_Posts_List_Table'); // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();

            $allowed_actions = ['postnord_bulk_sync'];
            if (!in_array($action, $allowed_actions, true)) {
                return;
            }

            // security check
            check_admin_referer('bulk-posts');

            // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
            if (isset($_REQUEST['post'])) {
                $post_ids = array_map('intval', $_REQUEST['post']);
            }

            if (empty($post_ids)) {
                return;
            }

            // this is based on wp-admin/edit.php
            $sendback = remove_query_arg(array('postnord_bulk_sync', 'untrashed', 'deleted', 'ids'), wp_get_referer());
            if (!$sendback) {
                $sendback = admin_url("edit.php?post_type=$post_type");
            }

            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg('paged', $pagenum, $sendback);

            switch ($action) {
                case 'postnord_bulk_sync':
                    $this->bulk_sync($post_ids);
                    break;

                default:
                    return;
            }

            $sendback = remove_query_arg(['action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'], $sendback);
            // phpcs:disable
            wp_redirect($sendback);
            //phpcs:enable
            exit();
        }
    }
}

WC_Postnord_Integration_Order_List::get_instance();
