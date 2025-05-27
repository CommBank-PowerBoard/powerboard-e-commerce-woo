<?php
/**
 * This file uses functions (wpautop, wp_kses_post, esc_attr, absint, get_query_var, wp_json_encode and esc_html__) from WordPress
 * This file uses functions (wc_esc_json, is_wc_endpoint_url and wc_get_order) from WooCommerce
 *
 * @noinspection PhpUndefinedFunctionInspection
 * @var array $data
 */

declare( strict_types=1 );

echo wp_kses_post(
	wpautop( $data['description'] )
);

?>
<fieldset id="wc-classic-power-board-checkout" class="wc-payment-form powerboard">
	<div id="classic-powerBoardCheckout_wrapper">
	</div>
	<div id="required-fields-validation-error" style="display: none;">
		<p class="power-board-validation-error">Please fill in the required fields of the form to display payment methods.</p>
	</div>
	<div id="intent-creation-error" style="display: none;">
		<p class="power-board-validation-error">Something went wrong, please refresh the page and try again.</p>
	</div>
	<div id="invalid-fields-error" style="display: none;">
		<p class="power-board-validation-error">Please enter valid information in all fields to display payment methods</p>
	</div>
	<div id="loading">
		<p class="loading-text">Loading...</p>
	</div>

	<input id="chargeid" type="hidden" name="chargeid">
	<input id="intentid" type="hidden" name="intentid">
	<input id="classic-<?php echo esc_attr( $data['id'] ); ?>-nonce" type="hidden" name="_wpnonce"
			value="<?php echo esc_attr( $data['nonce'] ); ?>">
	<input id="classic-<?php echo esc_attr( $data['id'] ); ?>-settings" type="hidden"
			value='<?php echo esc_attr( wc_esc_json( $data['settings'] ) ); ?>'>
	<div id="paymentSourceToken"></div>
</fieldset>
<?php
