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
 */

namespace WooCommerce\Square\Handlers;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\WP_Async_Request', false ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/libraries/wp-async-request.php';
}

if ( class_exists( '\\WP_Async_Request', false ) ) :
	/**
	 * Async_Request class
	 *
	 * @extends \WP_Async_Request
	 * @package WooCommerce\Square\Handlers
	 *
	 * @since x.x.x
	 */
	class Async_Request extends \WP_Async_Request {
		/**
		 * Prefix
		 *
		 * @var string
		 */
		protected $prefix = 'wc_square';

		/**
		 * Action
		 *
		 * @var string
		 */
		protected $action = 'async_request';

		/**
		 * Handle a dispatched request.
		 */
		protected function handle() {
			// Sync inventory for products.
			$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', $_POST['product_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
			if ( empty( $product_ids ) ) {
				return;
			}

			try {
				// Update products stock from Square.
				Product::update_products_stock_from_square( $product_ids );
			} catch ( \Exception $exception ) {
				wc_square()->log( 'Error in sync inventory for products: ' . implode( ',', $product_ids ) . '. ' . $exception->getMessage() );
			}
		}
	}

endif;
