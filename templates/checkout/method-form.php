<?php
/**
 * This file uses functions (wpautop, wp_kses_post, esc_attr, and esc_html__) from WordPress
 * This file uses function wc_esc_json from WooCommerce
 *
 * @var array $data
 */

declare( strict_types=1 );

echo wp_kses_post(
	wpautop( $data['description'] )
);

?>
<fieldset id="wc-classic-power-board-checkout" class="wc-payment-form powerboard">
	<div class="powerboard-payment-methods-wrapper">
	<?php
	$settings                  = json_decode( wc_esc_json( $data['settings'], true ) );
	$available_payment_methods = $settings->available_payment_methods;

    if(!empty($available_payment_methods)) {
        foreach ($available_payment_methods as $payment_method_key => $payment_method_nice_name) {
            echo '<img id="power-board-payment-method-' . esc_html($payment_method_key) . '"
                          class="payment-method"
                          src="' . esc_html(POWER_BOARD_PLUGIN_URL) . 'assets/images/payment-methods/' . esc_html($payment_method_key) . '.svg"
                          title="' . esc_html($payment_method_nice_name) . '"
                          alt="Available payment method ' . esc_html($payment_method_nice_name) . '">';
        }
    }
	?>
	</div>

	<!-- Modal for PowerBoard Widget -->
	<div id="powerboard-payment-modal" class="powerboard-modal" style="display: none;">
		<div class="powerboard-modal-content">
			<div class="powerboard-modal-header">
				<h3><?php echo esc_html__( 'Complete Your Payment', 'power-board' ); ?></h3>
				<span class="powerboard-modal-close">&times;</span>
			</div>
			<div class="powerboard-modal-body">
				<div id="powerboard-modal-loading">
					<p class="loading-text"><?php echo esc_html__( 'Initializing payment...', 'power-board' ); ?></p>
				</div>
				<div id="powerboard-modal-widget-wrapper">
					<!-- PowerBoard widget will be initialized here -->
				</div>
				<div id="powerboard-modal-error" style="display: none;">
					<p class="power-board-validation-error"><?php echo esc_html__( 'Something went wrong, please try again.', 'power-board' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Payment method selection message -->
	<div id="powerboard-payment-info">
		<p><?php echo esc_html__( 'Click \'Place Order\' to securely complete your payment.', 'power-board' ); ?></p>
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
