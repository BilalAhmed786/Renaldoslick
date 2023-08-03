<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 */

namespace WooCommerce\Square\Gateway\API\Requests;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\API;

/**
 * The Gift Card request class.
 *
 * @since 3.7.0
 */
class Gift_Card extends API\Request {


	/**
	 * Initializes a new Catalog request.
	 *
	 * @since 3.7.0
	 *
	 * @param \Square\SquareClient $api_client the API client
	 */
	public function __construct( $api_client ) {
		$this->square_api = $api_client->getGiftCardsApi();
	}

	/**
	 * Sets data for the `retrieveGiftCardFromNonce` API method.
	 *
	 * @param string $nonce Gift Card payment nonce.
	 */
	public function set_retrieve_gift_card_data( $nonce = '' ) {
		$this->square_request    = new \Square\Models\RetrieveGiftCardFromNonceRequest( $nonce );
		$this->square_api_method = 'retrieveGiftCardFromNonce';
		$this->square_api_args   = array( $this->square_request );
	}
}
