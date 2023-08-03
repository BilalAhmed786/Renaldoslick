<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Handlers;

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Handlers\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Order handler class.
 *
 * @since 2.0.0
 */
class Order {

	/**
	 * Array of previous stock values.
	 *
	 * @var array
	 */
	private $previous_stock = array();

	/**
	 * Array of product IDs that have been scheduled for sync in this request.
	 *
	 * @var array
	 */
	private $products_to_sync = array();


	/**
	 * Sets up Square order handler.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		// remove Square variation IDs from order item meta
		add_action( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_square_order_item_meta' ) );

		if ( version_compare( WC()->version, '7.6', '>=' ) ) {
			// Add hooks for stock sync.
			add_action( 'woocommerce_reduce_order_item_stock', array( $this, 'maybe_stage_stock_updates_for_product' ), 10, 3 );
			add_action( 'woocommerce_reduce_order_stock', array( $this, 'maybe_sync_staged_inventory_updates' ) );
		} else {
			// @todo Remove this block when WooCommerce 7.6 is the minimum supported version.
			// ADD hooks for stock syncs based on changes from orders not from this gateway
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_sync_stock_for_order_via_other_gateway' ), 10, 3 );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_sync_stock_for_store_api_order_via_other_gateway' ), 10, 1 );

			// Add specific hook for paypal IPN callback
			add_action( 'valid-paypal-standard-ipn-request', array( $this, 'maybe_sync_stock_for_order_via_paypal' ), 10, 1 );
		}

		// ADD hooks to restore stock for pending and cancelled order status.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_sync_inventory_for_stock_increase' ), 1 );
		add_action( 'woocommerce_order_status_pending', array( $this, 'maybe_sync_inventory_for_stock_increase' ), 1 );

		// ADD hooks to listen to refunds on orders from other gateways.
		add_action( 'woocommerce_order_refunded', array( $this, 'maybe_sync_stock_for_refund_from_other_gateway' ), 10, 2 );

		// Add gift card order item to the order edit screen.
		add_action( 'woocommerce_admin_order_items_after_fees', array( $this, 'add_admin_order_items' ) );

		// Include gift card information in payment method info.
		add_filter( 'woocommerce_order_get_payment_method_title', array( $this, 'filter_payment_method_title' ), 10, 2 );
		add_filter( 'woocommerce_gateway_title', array( $this, 'filter_gateway_title' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'filter_order_item_totals' ), 10, 2 );
	}

	/**
	 * Ensures the Square order item meta is hidden.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $hidden the hidden order item meta
	 * @return string[] updated meta
	 */
	public function hide_square_order_item_meta( $hidden ) {

		$hidden[] = '_square_item_variation_id';

		return $hidden;
	}

	/**
	 * Add hooks to ensure PayPal IPN callbacks are added caches and considered for inventory changes
	 * when the sync happens. This also adds the shutdown hook to ensure sync happens if needed at
	 * a later stage.
	 *
	 * @since 2.1.1
	 *
	 * @param array $posted values returned from PayPal Standard IPN callback.
	 */
	public function maybe_sync_stock_for_order_via_paypal( $posted ) {
		if ( empty( $posted['custom'] ) ) {
			return;
		}

		$raw_order = json_decode( $posted['custom'] );
		if ( empty( $raw_order->order_id ) ) {
			return;
		}

		$order = wc_get_order( $raw_order->order_id );

		if ( ! $order || ! $order instanceof \WC_Order ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * Checks if we should sync stock for this order.
	 * We only sync for other gateways that Square will not be aware of.
	 *
	 * This functions sets a process in motion that gathers products that will be processed on shutdown.
	 *
	 * @since 2.0.8
	 *
	 * @param int      $order_id    Order ID number.
	 * @param array    $posted_data Submitted order data.
	 * @param WC_Order $order       Order object.
	 */
	public function maybe_sync_stock_for_order_via_other_gateway( $order_id, $posted_data, $order ) {

		// Confirm we are not processing the order through the Square gateway.
		if ( ! $order instanceof \WC_Order || Plugin::GATEWAY_ID === $order->get_payment_method() ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * Checks if we should sync stock for this order.
	 * We only sync for other gateways that Square will not be aware of.
	 *
	 * This functions sets a process in motion that gathers products that will be processed on shutdown.
	 *
	 * @since x.x.x
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function maybe_sync_stock_for_store_api_order_via_other_gateway( $order ) {

		// Confirm we are not processing the order through the Square gateway.
		if ( ! $order instanceof \WC_Order || Plugin::GATEWAY_ID === $order->get_payment_method() ) {
			return;
		}

		$this->sync_stock_for_order( $order );
	}

	/**
	 * For a given order sync stock if inventory sync is enabled.
	 *
	 * @since 2.1.1
	 *
	 * @param \WC_Order $order the order for which the stock must be synced.
	 */
	protected function sync_stock_for_order( $order ) {

		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		$this->cache_previous_stock( $order );

		add_action( 'woocommerce_product_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );

		add_action( 'shutdown', array( $this, 'maybe_sync_staged_inventory_updates' ) );
	}

	/**
	 * Loop through order and cached previous stock values before they are reduced.
	 *
	 * @since 2.0.8
	 *
	 * @param WC_Order $order Order object.
	 */
	private function cache_previous_stock( $order ) {

		// Loop over all items.
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->is_type( 'line_item' ) ) {
				continue;
			}

			// Check to make sure it hasn't already been reduced.
			$product            = $item->get_product();
			$item_stock_reduced = $item->get_meta( '_reduced_stock', true );

			if ( $item_stock_reduced || ! $product || ! $product->managing_stock() ) {
				continue;
			}

			$this->previous_stock[ $product->get_id() ] = $product->get_stock_quantity();
		}
	}

	/**
	 * Stages a product inventory update for sync with Square when a product stock is updated.
	 *
	 * @internal The staged values will be stored in product_to_sync
	 *
	 * @since 2.0.8
	 *
	 * @param WC_Product $product the updated product with inventory updates.
	 */
	public function maybe_stage_inventory_updates_for_product( $product ) {

		// Do not add inventory changes if we are already doing a sync, or we are not syncing this product.
		if ( defined( 'DOING_SQUARE_SYNC' ) || ! $product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		// Compare stock to get difference.
		$product_id = $product->get_id();
		$previous   = isset( $this->previous_stock[ $product_id ] ) ? $this->previous_stock[ $product_id ] : false;
		$current    = $product->get_stock_quantity();
		$adjustment = (int) $current - $previous;

		if ( false === $previous || 0 === $adjustment ) {
			return;
		}

		// Record what type of inventory action occurred.
		$this->products_to_sync[ $product_id ] = $adjustment;
	}

	/**
	 * Stage inventory updates for products in the order.
	 * This function only used for WooCommerce 7.6 and above.
	 *
	 * @param \WC_Order_Item_Product $item   Order item data.
	 * @param array                  $change Change Details.
	 * @param \WC_Order              $order  Order data.
	 * @return void
	 *
	 * @since x.x.x
	 */
	public function maybe_stage_stock_updates_for_product( $item, $change, $order ) {
		$product = $change['product'] ?? false;

		/**
		 * Bail If
		 * 1. Order is processed using Square payment gateway OR
		 * 2. Inventory sync is not enabled OR
		 * 3. Square sync is in progress OR
		 * 4. Square sync is not enabled for the product
		 */
		if (
			! $order instanceof \WC_Order ||
			Plugin::GATEWAY_ID === $order->get_payment_method() ||
			! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ||
			defined( 'DOING_SQUARE_SYNC' ) ||
			! $product ||
			! Product::is_synced_with_square( $product )
		) {
			return;
		}

		// Get stock adjustment for the product.
		$product_id = $product->get_id();
		$previous   = $change['from'] ?? false;
		$current    = $change['to'] ?? false;
		$adjustment = (int) $current - $previous;

		if ( false === $previous || false === $current || 0 === $adjustment ) {
			return;
		}

		// Stage the inventory update.
		$this->products_to_sync[ $product_id ] = $adjustment;
	}

	/**
	 * Maybe restore stock for an order that was cancelled or moved to pending.
	 * This is only for orders that were not processed through the Square gateway.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @since x.x.x
	 */
	public function maybe_sync_inventory_for_stock_increase( $order_id ) {
		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		// Confirm we are not processing the order through the Square gateway.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || Plugin::GATEWAY_ID === $order->get_payment_method() ) {
			return;
		}

		$trigger_increase = $order->get_order_stock_reduced();

		// Only continue if we're increasing stock.
		if ( ! $trigger_increase ) {
			return;
		}

		// Cache the product's previous stock value.
		add_action( 'woocommerce_variation_before_set_stock', array( $this, 'cache_product_previous_stock' ) );
		add_action( 'woocommerce_product_before_set_stock', array( $this, 'cache_product_previous_stock' ) );

		// Stage the inventory update.
		add_action( 'woocommerce_variation_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'maybe_stage_inventory_updates_for_product' ) );

		// Sync the staged inventory updates.
		add_action( 'woocommerce_restore_order_stock', array( $this, 'maybe_sync_staged_inventory_updates' ) );
	}

	/**
	 * Cache the product's previous stock value.
	 *
	 * @param WC_Product $product Product object.
	 *
	 * @since x.x.x
	 */
	public function cache_product_previous_stock( $product ) {
		$this->previous_stock[ $product->get_id() ] = $product->get_stock_quantity();
	}

	/**
	 * Initializes a synchronization event for any staged inventory updates in this request.
	 *
	 * @internal
	 *
	 * @since 2.0.8
	 */
	public function maybe_sync_staged_inventory_updates() {

		$inventory_adjustments = array();

		foreach ( $this->products_to_sync as $product_id => $adjustment ) {

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$inventory_adjustment = Product::get_inventory_change_adjustment_type( $product, $adjustment );

			if ( empty( $inventory_adjustment ) ) {
				continue;
			}

			$inventory_adjustments[] = $inventory_adjustment;
		}

		if ( empty( $inventory_adjustments ) ) {
			return;
		}

		wc_square()->log( 'New order from other gateway inventory syncing..' );
		$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $inventory_adjustments ) ) . '_change_inventory' );
		wc_square()->get_api()->batch_change_inventory( $idempotency_key, $inventory_adjustments );
	}

	/**
	 * Handle order refunds inventory/stock changes sync.
	 *
	 * @since 2.0.8
	 *
	 * @param in $order_id
	 * @param int $refund_id
	 */
	public function maybe_sync_stock_for_refund_from_other_gateway( $order_id, $refund_id ) {

		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		// Confirm we are not processing the order through the Square gateway.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || Plugin::GATEWAY_ID === $order->get_payment_method() ) {
			return;
		}

		// don't refund items if the "Restock refunded items" option is unchecked - maintains backwards compatibility if this function is called outside of the `woocommerce_order_refunded` do_action
		if ( isset( $_POST['restock_refunded_items'] ) ) {
			// Validate the user has permissions to process this request.
			if ( ! check_ajax_referer( 'order-item', 'security', false ) || ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}

			if ( 'false' === $_POST['restock_refunded_items'] ) {
				return;
			}
		}

		$refund                = new \WC_Order_Refund( $refund_id );
		$inventory_adjustments = array();
		foreach ( $refund->get_items() as $item ) {

			if ( 'line_item' !== $item->get_type() ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$adjustment           = -1 * ( $item->get_quantity() ); // we want a positive value to increase the stock and a negative number to decrease it.
			$inventory_adjustment = Product::get_inventory_change_adjustment_type( $product, $adjustment );

			if ( empty( $inventory_adjustment ) ) {
				continue;
			}

			$inventory_adjustments[] = $inventory_adjustment;
		}

		if ( empty( $inventory_adjustments ) ) {
			return;
		}

		wc_square()->log( 'Order from other gateway Refund inventory updates syncing..' );
		$idempotency_key = wc_square()->get_idempotency_key( md5( serialize( $inventory_adjustments ) ) . '_change_inventory' );
		wc_square()->get_api()->batch_change_inventory( $idempotency_key, $inventory_adjustments );
	}

	/**
	 * Returns if the order was placed using a Square credit card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public static function is_tender_type_card( $order ) {
		return '1' === wc_square()->get_gateway()->get_order_meta( $order, 'is_tender_type_card' );
	}

	/**
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return boolean
	 */
	public static function is_tender_type_gift_card( $order ) {
		return '1' === wc_square()->get_gateway()->get_order_meta( $order, 'is_tender_type_gift_card' );
	}

	/**
	 * Sets the amount charged on the gift card for the given order.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount The total amount charged on the gift card for the order.
	 */
	public static function set_gift_card_total_charged_amount( $order, $amount ) {
		wc_square()->get_gateway()->update_order_meta( $order, 'gift_card_charged_amount', $amount );
	}

	/**
	 * Returns the amount charged on the gift card for the given order.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return float
	 */
	public static function get_gift_card_total_charged_amount( $order ) {
		return (float) wc_square()->get_gateway()->get_order_meta( $order, 'gift_card_charged_amount' );
	}

	/**
	 * Returns the last 4 digits of the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public static function get_gift_card_last4( $order ) {
		return wc_square()->get_gateway()->get_order_meta( $order, 'gift_card_last4' );
	}

	/**
	 * Sets the last 4 digits of the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public static function set_gift_card_last4( $order, $number ) {
		return wc_square()->get_gateway()->update_order_meta( $order, 'gift_card_last4', $number );
	}

	/**
	 * Returns the total amount that is refunded to the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return float
	 */
	public static function get_gift_card_total_refunded_amount( $order ) {
		$amount = (float) wc_square()->get_gateway()->get_order_meta( $order, 'gift_card_refunded_amount' );
		$amount = empty( $amount ) ? 0 : $amount;

		return $amount;
	}

	/**
	 * Sets the total amount that is refunded to the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount The total amount refunded to the gift card for the order.
	 */
	public static function set_gift_card_total_refunded_amount( $order, $amount ) {
		wc_square()->get_gateway()->update_order_meta( $order, 'gift_card_refunded_amount', $amount );
	}

	/**
	 * Sets the total order amount before applying the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount
	 */
	public static function set_order_total_before_gift_card( $order, $amount ) {
		wc_square()->get_gateway()->update_order_meta( $order, 'order_total_before_gift_card', $amount );
	}

	/**
	 * Gets the total order amount before applying the gift card.
	 *
	 * @since 3.7.0
	 *
	 * @return float
	 */
	public static function get_order_total_before_gift_card( $order ) {
		return (float) wc_square()->get_gateway()->get_order_meta( $order, 'order_total_before_gift_card' );
	}

	/**
	 * Displays the gift card details in the order item.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function add_admin_order_items( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! self::is_tender_type_gift_card( $order ) ) {
			return;
		}

		?><tr class="square_gift_card item">
			<td class="thumb">
				<div style="width: 38px;">
					<img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/assets/images/gift-card.png' ); ?>" />
				</div>
			</td>
			<td class="name">
				<div class="view">
				</div>
				<div class="view">
					<table cellspacing="0" class="display_meta">
						<tbody>
							<tr>
								<th>
									<?php esc_html_e( 'Square Gift Card:', 'woocommerce-square' ); ?>
								</th>
								<td>
									<?php
									printf(
										/* Translators: %s - last 4 digits of the gift card. */
										esc_html__( 'ending in %s', 'woocommerce-square' ),
										esc_html( self::get_gift_card_last4( $order ) )
									);
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</td>
			<td class="item_cost" width="1%">&nbsp;</td>
			<td class="quantity" width="1%">&nbsp;</td>
			<td class="line_cost" width="1%">
				<div class="view">-
				<?php
					echo wp_kses_post( wc_price( self::get_gift_card_total_charged_amount( $order ), array( 'currency' => $order->get_currency() ) ) );
					$refunded_amount = self::get_gift_card_total_refunded_amount( $order );
				?>
				</div>
			</td>
			<td class="wc-order-edit-line-item" width="1%">
		</tr>
		<?php
	}

	/**
	 * Includes info regarding gift card in the payment method title.
	 *
	 * @since 3.7.0
	 *
	 * @param string    $value Payment method title.
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	public function filter_payment_method_title( $value, $order ) {
		$is_tender_gift_card = self::is_tender_type_gift_card( $order );
		$is_tender_card      = self::is_tender_type_card( $order );

		if ( $is_tender_gift_card && $is_tender_card ) {
			$gift_card_charged   = wc_square()->get_gateway()->get_order_meta( $order, 'gift_card_partial_total' );
			$credit_card_charged = wc_square()->get_gateway()->get_order_meta( $order, 'credit_card_partial_total' );

			return sprintf(
				/* translators: %1$s - Amount charged on gift card, %2$s -  Amount charged on credit card. */
				__( 'Square Gift Card (%1$s) and Credit Card (%2$s)', 'woocommerce-square' ),
				get_woocommerce_currency_symbol( $order->get_currency() ) . Square_Helper::number_format( $gift_card_charged ),
				get_woocommerce_currency_symbol( $order->get_currency() ) . Square_Helper::number_format( $credit_card_charged )
			);
		}

		if ( $is_tender_gift_card ) {
			return sprintf(
				/* translators: %1$s - Amount charged on gift card. */
				__( 'Square Gift Card (%1$s)', 'woocommerce-square' ),
				get_woocommerce_currency_symbol( $order->get_currency() ) . $order->get_total()
			);
		}

		return $value;
	}

	/**
	 * Includes info regarding gift card in the payment gateway title.
	 *
	 * @since 3.7.0
	 *
	 * @param string $value Gateway title.
	 * @param string $id    Plugin id.
	 *
	 * @return string
	 */
	public function filter_gateway_title( $value, $id ) {
		if ( Plugin::GATEWAY_ID !== $id || ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return $value;
		}

		$screen = get_current_screen();

		if ( ! ( $screen && 'shop_order' === $screen->id ) ) {
			return $value;
		}

		if ( ! isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $value;
		}

		$post_id = wc_clean( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = wc_get_order( $post_id );

		if ( ! $order instanceof \WC_Order ) {
			return $value;
		}

		return self::filter_payment_method_title( $value, $order );
	}

	/**
	 * Includes split payment details on the order details screen.
	 *
	 * @param array     $total_rows Array of order details.
	 * @param \WC_Order $order      WooCommerce Order.
	 *
	 * @return array
	 */
	public function filter_order_item_totals( $total_rows, $order ) {
		$charge_type = wc_square()->get_gateway()->get_order_meta( $order, 'charge_type' );

		if ( Payment_Gateway::CHARGE_TYPE_PARTIAL !== $charge_type ) {
			return $total_rows;
		}

		$gift_card_partial_total   = wc_square()->get_gateway()->get_order_meta( $order, 'gift_card_partial_total' );
		$credit_card_partial_total = wc_square()->get_gateway()->get_order_meta( $order, 'credit_card_partial_total' );

		$total_rows['order_total']['value'] = sprintf(
			/* translators: %1$s - Order total, %2$s - Amount charged on gift card, %3$s -  Amount charged on credit card. */
			esc_html__( '%1$s — Total split between gift card (%2$s) and credit card (%3$s)', 'woocommerce-square' ),
			wp_kses_post( $total_rows['order_total']['value'] ),
			wp_kses_post( wc_price( $gift_card_partial_total, array( 'currency' => $order->get_currency() ) ) ),
			wp_kses_post( wc_price( $credit_card_partial_total, array( 'currency' => $order->get_currency() ) ) )
		);

		return $total_rows;
	}
}
