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

defined( 'ABSPATH' ) || exit;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Sync\Records;
use WooCommerce\Square;

/**
 * Products admin handler.
 *
 * @since 2.0.0
 */
class Products {


	/** @var array associative array of product error codes and messages */
	private $product_errors;

	/** @var array associative array of memoized errors being output for a product at one time */
	private $output_errors = array();

	/** @var int[] array of product IDs that have been scheduled for sync in this request */
	private $products_to_sync = array();

	/** @var int[] array of product IDs that have been scheduled for deletion in this request */
	private $products_to_delete = array();

	/** @var int[] array of product IDs that have been scheduled for inventory sync in this request */
	private $products_to_inventory_sync = array();

	/** @var Square\Plugin plugin instance */
	private $plugin;

	/**
	 * Sets up the products admin handler.
	 *
	 * @since 2.0.0
	 */
	public function __construct( Square\Plugin $plugin ) {

		$this->plugin = $plugin;

		// add common errors
		$this->product_errors = array(
			/* translators: Placeholder: %s - product name */
			'missing_sku'           => __( "Please add an SKU to sync %s with Square. The SKU must match the item's SKU in your Square account.", 'woocommerce-square' ),
			/* translators: Placeholder: %s - product name */
			'missing_variation_sku' => __( "Please add an SKU to every variation of %s for syncing with Square. Each SKU must be unique and match the corresponding item's SKU in your Square account.", 'woocommerce-square' ),
		);

		// add hooks
		$this->add_products_edit_screen_hooks();
		$this->add_product_edit_screen_hooks();
		$this->add_product_sync_hooks();
	}

	/**
	 * Adds hooks to the admin products edit screen.
	 *
	 * Products filtering, bulk actions, etc.
	 *
	 * @since 2.0.0
	 */
	private function add_products_edit_screen_hooks() {

		// adds an option to the "Filter by product type" dropdown
		add_action( 'restrict_manage_posts', array( $this, 'add_filter_products_synced_with_square_option' ) );
		// allow filtering products by sync status by altering results
		add_filter( 'request', array( $this, 'filter_products_synced_with_square' ) );

		// prevent copying Square data when duplicating a product automatically
		add_action( 'woocommerce_product_duplicate', array( $this, 'handle_product_duplication' ), 20, 2 );

		// handle quick/bulk edit actions in the products edit screen for setting sync status
		add_action( 'woocommerce_product_quick_edit_end', array( $this, 'add_quick_edit_inputs' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'add_bulk_edit_inputs' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'set_synced_with_square' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'set_synced_with_square' ) );

		// export product sync status.
		add_filter( 'woocommerce_product_export_column_names', array( $this, 'add_sync_status_to_column' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_sync_status_to_column' ) );
		add_filter( 'woocommerce_product_export_product_column_wc_square_synced', array( $this, 'export_sync_status_taxonomy' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'register_sync_status_for_importer' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( $this, 'map_sync_status_column' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( $this, 'import_sync_status' ), 10, 2 );
	}


	/**
	 * Adds hooks to individual products edit screens.
	 *
	 * Product data input fields, variations, etc.
	 *
	 * @since 2.0.0
	 */
	private function add_product_edit_screen_hooks() {

		// handle individual products input fields for setting sync status
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_data_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'process_product_data' ), 20 );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'maybe_adjust_square_stock' ) );

		add_action( 'admin_notices', array( $this, 'add_notice_product_hidden_from_catalog' ) );
	}

	/**
	 * Adds hooks to sync products that have been updated.
	 *
	 * @since 2.0.0
	 */
	private function add_product_sync_hooks() {

		add_action( 'woocommerce_update_product', array( $this, 'validate_product_update_and_sync' ) );
		add_action( 'trashed_post', array( $this, 'maybe_stage_products_for_deletion' ) );
		add_action( 'shutdown', array( $this, 'maybe_sync_staged_products' ) );
		add_action( 'shutdown', array( $this, 'maybe_delete_staged_products' ) );

		// Sync product inventory when a product is added to the cart.
		add_action( 'woocommerce_add_to_cart', array( $this, 'maybe_stage_products_for_sync_inventory' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'maybe_sync_product_inventory' ) );
	}

	/**
	 * Adds an option to filter products by sync status.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param string $post_type the post type context
	 */
	public function add_filter_products_synced_with_square_option( $post_type ) {

		if ( 'product' !== $post_type ) {
			return;
		}

		$label = esc_html__( 'Synced with Square', 'woocommerce-square' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce check not required, checked against known string, read-only action.
		$selected = isset( $_GET['product_type'] ) && 'synced-with-square' === $_GET['product_type'] ? 'selected=\"selected\"' : '';

		wc_enqueue_js(
			"
			jQuery( document ).ready( function( $ ) {
				$( 'select#dropdown_product_type' ) . append( '<option value=\"synced-with-square\" ' + '" . $selected . "' + '>' + '" . $label . "' + '</option>' );
			} );
			"
		);
	}


	/**
	 * Filters products in admin edit screen by sync status with Square.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param array $query_vars query variables
	 * @return array
	 */
	public function filter_products_synced_with_square( $query_vars ) {
		global $typenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce check not required, just filtering products, read-only action.
		if ( 'product' === $typenow && isset( $_GET['product_type'] ) && 'synced-with-square' === $_GET['product_type'] ) {

			// not really a product type, otherwise WooCommerce will handle it as such
			unset( $query_vars['product_type'] );

			if ( ! isset( $query_vars['tax_query'] ) ) {
				$query_vars['tax_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			} else {
				$query_vars['tax_query']['relation'] = 'AND';
			}

			$query_vars['tax_query'][] = array(
				'taxonomy' => Product::SYNCED_WITH_SQUARE_TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( 'yes' ),
			);
		}

		return $query_vars;
	}


	/**
	 * Adds general product data options to a product metabox.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_product_data_fields() {
		global $product_object;

		if ( ! $product_object instanceof \WC_Product ) {
			return;
		}

		// don't show fields if product sync is disabled
		if ( ! wc_square()->get_settings_handler()->is_product_sync_enabled() ) {
			return;
		}

		?>
		<div class="wc-square-sync-with-square options_group show_if_simple show_if_variable">
			<?php

			$selector = '_' . Product::SYNCED_WITH_SQUARE_TAXONOMY;
			$value    = Product::is_synced_with_square( $product_object ) ? 'yes' : 'no';
			$errors   = $this->check_product_sync_errors( $product_object );

			$setting_label = wc_square()->get_settings_handler()->is_system_of_record_square() ? __( 'Update product data with Square data', 'woocommerce-square' ) : __( 'Send product data to Square', 'woocommerce-square' );

			woocommerce_wp_checkbox(
				array(
					'id'                => $selector,
					'label'             => __( 'Sync with Square', 'woocommerce-square' ),
					'value'             => $value,
					'cbvalue'           => 'yes',
					'default'           => 'no',
					'description'       => $setting_label,
					'custom_attributes' => ! empty( $errors ) ? array( 'disabled' => 'disabled' ) : array(),
				)
			);

			?>
			<p class="form-field wc-square-sync-with-square-errors">
				<?php foreach ( $this->product_errors as $error_code => $error_message ) : ?>
					<?php $styles = ! in_array( $error_code, array_keys( $errors ), true ) ? 'display:none; color:#A00;' : 'display:block; color:#A00;'; ?>
					<span class="wc-square-sync-with-square-error <?php echo sanitize_html_class( $error_code ); ?>" style="<?php echo esc_attr( $styles ); ?>"><?php echo wp_kses_post( $this->format_product_error( $error_code, $product_object ) ); ?></span>
				<?php endforeach; ?>
			</p>

			<input type="hidden" id="<?php echo esc_attr( Product::SQUARE_VARIATION_ID_META_KEY ); ?>" value="<?php echo esc_attr( $product_object->get_meta( Product::SQUARE_VARIATION_ID_META_KEY ) ); ?>" />

		</div>
		<?php
	}


	/**
	 * Outputs HTML with a dropdown field to mark a product to be synced with Square.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $bulk whether the field is meant for bulk edit
	 */
	private function output_synced_with_square_edit_field( $bulk = false ) {

		?>
		<div class="inline-edit-group wc-square-sync-with-square">
			<label>
				<span class="title"><?php esc_html_e( 'Sync with Square?', 'woocommerce-square' ); ?></span>
				<span class="input-text-wrap">
					<select class="square-synced" name="<?php echo esc_attr( Product::SYNCED_WITH_SQUARE_TAXONOMY ); ?>">
						<?php if ( true === $bulk ) : // in bulk actions there's the option to leave the value unchanged (or unset) ?>
							<option value="">&mdash; <?php esc_html_e( 'No change', 'woocommerce-square' ); ?> &mdash;</option>
						<?php endif; ?>
						<option value="no"><?php esc_html_e( 'No', 'woocommerce-square' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Yes', 'woocommerce-square' ); ?></option>
					</select>
				</span>
			</label>
			<p class="form-field wc-square-sync-with-square-errors">
				<?php foreach ( $this->product_errors as $error_code => $error_message ) : ?>
					<?php $product_name_placeholder = __( 'This product', 'woocommerce-square' ); ?>
					<?php $product_name = strtolower( $product_name_placeholder ); ?>
					<span class="wc-square-sync-with-square-error <?php echo esc_attr( $error_code ); ?>" style="display:none; color:#A00;"><?php echo esc_html( sprintf( $error_message, $product_name ) ); ?></span>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}


	/**
	 * Adds quick edit fields to the products screen.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_quick_edit_inputs() {

		$this->output_synced_with_square_edit_field();
	}


	/**
	 * Adds bulk edit fields to the products screen.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_bulk_edit_inputs() {

		$this->output_synced_with_square_edit_field( true );
	}


	/**
	 * In case Woo is the SOR, validates whether a product can be synced with Square and disable sync if not
	 *
	 * @since 2.0.8
	 *
	 * @param int $product_id the product ID
	 */
	public function validate_product_update_and_sync( $product_id ) {
		if ( ! wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		$errors = $this->check_product_sync_errors( $product );

		if ( ! empty( $errors ) ) {
			// if there are errors, remove the link and display them
			Product::unset_synced_with_square( $product );

			foreach ( $errors as $error ) {
				wc_square()->get_message_handler()->add_error( $error );
				Records::set_record(
					array(
						'type'       => 'alert',
						'product_id' => $product_id,
						'message'    => $error,
					)
				);
			}
		} else {
			$this->maybe_stage_product_for_sync( $product );
		}
	}

	/**
	 * Stages a product for sync with Square on product save if Woo is the SOR and the product is set to 'synced with square'.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Product $product the product object
	 */
	public function maybe_stage_product_for_sync( $product ) {

		if ( ! $product || ! Product::is_synced_with_square( $product ) || in_array( $product->get_id(), $this->products_to_sync, true ) ) {
			return;
		}

		$in_progress = wc_square()->get_sync_handler()->get_job_in_progress();

		if ( $in_progress ) {
			// return early if an import that is updating existing products is in progress.
			if ( isset( $in_progress->update_products_during_import ) && $in_progress->update_products_during_import ) {
				return;
			}

			if ( in_array( $product->get_id(), $in_progress->product_ids, true ) ) {
				return;
			}
		}

		// the triggering action for this method can be called multiple times in a single request - keep track
		// of product IDs that have been scheduled for sync here to avoid multiple syncs on the same request
		$this->products_to_sync[] = $product->get_id();
	}


	/**
	 * Initializes a synchronization event for any staged products in this request.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function maybe_sync_staged_products() {

		if ( ! defined( 'DOING_SQUARE_SYNC' ) && ! empty( $this->products_to_sync ) && wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			wc_square()->get_sync_handler()->start_manual_sync( $this->products_to_sync );
		}
	}


	/**
	 * Removes a product from Square if it is deleted locally and Woo is the SOR.
	 *
	 * @since 2.0.0
	 *
	 * @param int $product_id the product ID
	 */
	public function maybe_stage_products_for_deletion( $product_id ) {

		if ( wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			$product = wc_get_product( $product_id );

			if ( $product && Product::is_synced_with_square( $product ) ) {

				// the triggering action for this method can be called multiple times in a single request - keep track
				// of product IDs that have been scheduled for sync here to avoid multiple syncs on the same request
				$this->products_to_delete[] = $product_id;
			}
		}
	}


	/**
	 * Deletes any products staged for remote deletion.
	 *
	 * @since 2.0.0
	 */
	public function maybe_delete_staged_products() {

		if ( ! empty( $this->products_to_delete ) && wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) {

			wc_square()->get_sync_handler()->start_manual_deletion( $this->products_to_delete );
		}
	}


	/**
	 * Sets a product's synced with Square status for quick/bulk edit action.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product a product object
	 */
	public function set_synced_with_square( $product ) {

		$posted_data_key = Product::SYNCED_WITH_SQUARE_TAXONOMY;

		if ( 'woocommerce_product_bulk_edit_save' === current_action() ) {
			$default_value = null; // in bulk actions this will preserve the existing setting if nothing is specified
		} else {
			$default_value = 'no'; // in individual products context, the value should be always an explicit yes or no
		}

		$square_synced = isset( $_REQUEST[ $posted_data_key ] ) && in_array( $_REQUEST[ $posted_data_key ], array( 'yes', 'no' ), true ) ? sanitize_key( $_REQUEST[ $posted_data_key ] ) : $default_value; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( is_string( $square_synced ) ) {
			$errors = $this->check_product_sync_errors( $product );
			if ( 'no' === $square_synced || empty( $errors ) ) {
				Product::set_synced_with_square( $product, $square_synced );
			} elseif ( ! empty( $errors ) ) {
				foreach ( $errors as $error ) {
					wc_square()->get_message_handler()->add_error( $error );
				}
			}
		}
	}


	/**
	 * Updates Square sync status for a product upon saving.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product product object
	 */
	public function process_product_data( $product ) {

		// don't process fields if product sync is disabled
		if ( ! wc_square()->get_settings_handler()->is_product_sync_enabled() ) {
			return;
		}

		// bail if no valid product found, if it's a variation, errors have already been output
		if ( ! $product || ( $product instanceof \WC_Product_Variation || $product->is_type( 'product_variation' ) ) || ! empty( $this->output_errors[ $product->get_id() ] ) ) {
			return;
		}

		$errors     = array();
		$posted_key = '_' . Product::SYNCED_WITH_SQUARE_TAXONOMY;
		$set_synced = isset( $_POST[ $posted_key ] ) && 'yes' === sanitize_key( $_POST[ $posted_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$was_synced = Product::is_synced_with_square( $product );

		// condition has unchanged
		if ( ! $set_synced && ! $was_synced ) {
			return;
		}

		if ( $set_synced || $was_synced ) {
			if ( $set_synced && $product->is_type( 'variable' ) && wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
				// if syncing inventory with Square, parent variable products don't manage stock
				$product->set_manage_stock( false );
			}

			// finally, set the product sync with Square flag
			Product::set_synced_with_square( $product, $set_synced ? 'yes' : 'no' );
		}
	}


	/**
	 * Adjusts a product's Square stock.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $product product object
	 */
	public function maybe_adjust_square_stock( $product ) {

		// this is hooked in to general product object save, so scope to specifically saving products via the admin
		if ( ! doing_action( 'wp_ajax_woocommerce_save_variations' ) && ! doing_action( 'woocommerce_admin_process_product_object' ) ) {
			return;
		}

		// only send stock updates for Woo SOR
		if ( ! wc_square()->get_settings_handler()->is_system_of_record_woocommerce() || ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		if ( ! $product instanceof \WC_Product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		$square_id = $product->get_meta( Product::SQUARE_VARIATION_ID_META_KEY );

		// only send when the product has an associated Square ID
		if ( ! $square_id ) {
			return;
		}

		$data    = $product->get_data();
		$changes = $product->get_changes();
		$change  = 0;

		if ( isset( $data['stock_quantity'], $changes['stock_quantity'] ) ) {
			$change = (int) $changes['stock_quantity'] - $data['stock_quantity'];
		}

		if ( 0 !== $change ) {

			try {

				if ( $change > 0 ) {
					wc_square()->get_api()->add_inventory( $square_id, $change );
				} else {
					wc_square()->get_api()->remove_inventory( $square_id, $change );
				}
			} catch ( \Exception $exception ) {

				wc_square()->log( 'Could not adjust Square inventory for ' . $product->get_formatted_name() . '. ' . $exception->getMessage() );

				$quantity = (float) $data['stock_quantity'];

				// if the API request fails, set the product quantity back from whence it came
				$product->set_stock_quantity( $quantity );
			}
		}
	}


	/**
	 * Prevents copying Square data when duplicating a product in admin.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Product $duplicated_product product duplicate
	 * @param \WC_Product $original_product product duplicated
	 */
	public function handle_product_duplication( $duplicated_product, $original_product ) {

		if ( Product::is_synced_with_square( $original_product ) ) {
			Product::unset_synced_with_square( $duplicated_product );
		}

		$duplicated_product->delete_meta_data( Product::SQUARE_ID_META_KEY );
		$duplicated_product->delete_meta_data( Product::SQUARE_VARIATION_ID_META_KEY );

		if ( $duplicated_product->is_type( 'variable' ) ) {

			foreach ( $duplicated_product->get_children() as $duplicated_variation_id ) {
				$duplicated_product_variation = wc_get_product( $duplicated_variation_id );
				if ( $duplicated_product_variation ) {

					$duplicated_product_variation->delete_meta_data( Product::SQUARE_VARIATION_ID_META_KEY );
					$duplicated_product_variation->save_meta_data();
				}
			}
		}

		$duplicated_product->save_meta_data();
	}


	/**
	 * Outputs an admin notice when a product was hidden from catalog upon a sync error.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_notice_product_hidden_from_catalog() {
		global $current_screen, $post;

		if ( $post && $current_screen && 'product' === $current_screen->id ) {

			$product = wc_get_product( $post );

			if ( $product && 'hidden' === $product->get_catalog_visibility() ) {

				$product_id = $product->get_id();
				$records    = Records::get_records( array( 'product' => $product_id ) );

				foreach ( $records as $record ) {

					if ( $record->was_product_hidden() && $product_id === $record->get_product_id() ) {

						wc_square()->get_message_handler()->add_warning(
							sprintf(
								/* translators: Placeholder: %1$s - date (localized), %2$s - time (localized), %3$s - opening <a> HTML link tag, %4$s closing </a> HTML link tag */
								esc_html__( 'The product catalog visibility has been set to "hidden", as a matching product could not be found in Square on %1$s at %2$s. %3$sCheck sync records%4$s.', 'woocommerce-square' ),
								date_i18n( wc_date_format(), $record->get_timestamp() ),
								date_i18n( wc_time_format(), $record->get_timestamp() ),
								'<a href="' . esc_url( add_query_arg( array( 'section' => 'update' ), wc_square()->get_settings_url() ) ) . '">',
								'</a>'
							)
						);

						break;
					}
				}
			}
		}
	}


	/**
	 * Check whether this product can be synced with Square
	 *
	 * @param \WC_Product $product product object
	 * @return array errors
	 */
	private function check_product_sync_errors( \WC_Product $product ) {

		$errors = array();
		if ( ! Product::has_sku( $product ) ) {
			if ( $product->is_type( 'variable' ) ) {
				$errors['missing_variation_sku'] = $this->format_product_error( 'missing_variation_sku', $product );
			} else {
				$errors['missing_sku'] = $this->format_product_error( 'missing_sku', $product );
			}
		}

		return $errors;
	}


	/**
	 * Formats product error message with product information
	 *
	 * @param string $error error identifier (e.g. 'missing_variation_sku' or 'missing_sku')
	 * @param \WC_Product $product product object
	 * @return string formatted error message
	 */
	private function format_product_error( string $error, \WC_Product $product ) {
		return sprintf(
			$this->product_errors[ $error ],
			Product::get_product_edit_link( $product )
		);
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 2.0.8
	 *
	 * @return Plugin
	 */
	protected function get_plugin() {
		return $this->plugin;
	}

	/**
	 * Adds the sync status column to the export columns.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function add_sync_status_to_column( $columns ) {
		$columns['wc_square_synced'] = __( 'Sync with Square', 'woocommerce-square' );
		return $columns;
	}

	/**
	 * Sets column data.
	 *
	 * @param mixed       $value   Value of a column
	 * @param \WC_Product $product WooCommerce product object.
	 */
	public function export_sync_status_taxonomy( $value, $product ) {
		$terms = get_terms(
			array(
				'object_ids' => $product->get_ID(),
				'taxonomy'   => 'wc_square_synced',
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) && count( $terms ) > 0 ) {
			$term = $terms[0];

			return $term->name;
		}

		return 'no';
	}

	/**
	 * Registers the sync status to the importer.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function register_sync_status_for_importer( $columns ) {
		$columns['wc_square_synced'] = __( 'Sync with Square', 'woocommerce-square' );
		return $columns;
	}

	/**
	 * Add automatic mapping support for wc_square_synced column.
	 *
	 * @param array $columns Array of columns
	 * @return array
	 */
	public function map_sync_status_column( $columns ) {
		$columns[ __( 'Sync with Square', 'woocommerce-square' ) ] = 'wc_square_synced';
		return $columns;
	}

	/**
	 * Imports square sync status.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @param array       Import data.
	 *
	 * @return array
	 */
	public function import_sync_status( $product, $data ) {
		if ( is_a( $product, 'WC_Product' ) ) {

			if ( ! empty( $data['wc_square_synced'] ) && 'yes' === $data['wc_square_synced'] ) {
				wp_set_object_terms( $product->get_id(), array( 'yes' ), 'wc_square_synced' );
			} else {
				wp_remove_object_terms( $product->get_id(), array( 'yes' ), 'wc_square_synced' );
			}
		}

		return $product;
	}

	/**
	 * Stage products for inventory sync when product is added to cart.
	 *
	 * @param string $cart_item_key  Cart item key.
	 * @param int    $product_id     Product ID.
	 * @param int    $quantity       Quantity.
	 * @param int    $variation_id   Variation ID.
	 *
	 * @since x.x.x
	 */
	public function maybe_stage_products_for_sync_inventory( $cart_item_key, $product_id, $quantity, $variation_id ) {
		if ( ! $product_id && ! $variation_id ) {
			return;
		}

		// Bail if inventory sync is not enabled.
		if ( ! wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			return;
		}

		if ( $variation_id ) {
			$product_id = $variation_id;
		}

		// Bail if the product is not synced with Square.
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || ! Product::is_synced_with_square( $product ) ) {
			return;
		}

		// Stage product for inventory sync.
		$this->products_to_inventory_sync[] = $product_id;
	}

	/**
	 * Sync product inventory of staged products.
	 *
	 * @since x.x.x
	 */
	public function maybe_sync_product_inventory() {
		if ( ! empty( $this->products_to_inventory_sync ) && wc_square()->get_settings_handler()->is_inventory_sync_enabled() ) {
			// Sync product inventory asynchronously.
			$async_request = $this->plugin->get_async_request_handler();
			if ( $async_request instanceof Async_Request ) {
				$async_request->data( array( 'product_ids' => $this->products_to_inventory_sync ) )->dispatch();
			}
		}
	}
}
