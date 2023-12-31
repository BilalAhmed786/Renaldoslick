<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 03 January 2022.
 */
?>

<div class="wc-order-data-row wc-order-data-row-toggle sv-wc-payment-gateway-partial-capture wc-<?php echo esc_attr( $gateway->get_id_dasherized() ); ?>-partial-capture" style="display:none;">
	<table class="wc-order-totals">

		<tr>
			<td class="label"><?php esc_html_e( 'Authorization total', 'woocommerce-square' ); ?>:</td>
			<td class="total"><?php echo wp_kses_post( wc_price( $authorization_total, array( 'currency' => $order->get_currency() ) ) ); ?></td>
		</tr>
		<tr>
			<td class="label"><?php esc_html_e( 'Amount already captured', 'woocommerce-square' ); ?>:</td>
			<td class="total"><?php echo wp_kses_post( wc_price( $total_captured, array( 'currency' => $order->get_currency() ) ) ); ?></td>
		</tr>

		<?php if ( $remaining_total > 0 ) : ?>
			<tr>
				<td class="label"><?php esc_html_e( 'Remaining order total', 'woocommerce-square' ); ?>:</td>
				<td class="total"><?php echo wp_kses_post( wc_price( $remaining_total, array( 'currency' => $order->get_currency() ) ) ); ?></td>
			</tr>
		<?php endif; ?>

		<tr>
			<td class="label"><label for="capture_amount"><?php esc_html_e( 'Capture amount', 'woocommerce-square' ); ?>:</label></td>
			<td class="total">
				<input type="text" class="text" id="capture_amount" name="capture_amount" class="wc_input_price" />
				<div class="clear"></div>
			</td>
		</tr>
		<tr>
			<td class="label"><label for="capture_comment"><?php esc_html_e( 'Comment (optional):', 'woocommerce-square' ); ?></label></td>
			<td class="total">
				<input type="text" class="text" id="capture_comment" name="capture_comment" />
				<div class="clear"></div>
			</td>
		</tr>
	</table>
	<div class="clear"></div>
	<div class="capture-actions">

		<?php $amount = '<span class="capture-amount">' . wc_price( 0, array( 'currency' => $order->get_currency() ) ) . '</span>'; ?>
		<?php // Translators: %s is the amount to be captured. ?>
		<button type="button" class="button button-primary capture-action" disabled="disabled"><?php printf( esc_html__( 'Capture %s', 'woocommerce-square' ), wp_kses_post( $amount ) ); ?></button>
		<button type="button" class="button cancel-action"><?php esc_html_e( 'Cancel', 'woocommerce-square' ); ?></button>

		<div class="clear"></div>
	</div>
</div>
