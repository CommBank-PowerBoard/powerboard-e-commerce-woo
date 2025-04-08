<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use Exception;

class OrderService {
	protected TemplateService $template_service;

	/**
	 * Uses functions (is_admin and add_action) from WordPress
	 */
	public function __construct() {
		/* @noinspection PhpUndefinedFunctionInspection */
		if ( is_admin() ) {
			$this->template_service = new TemplateService( $this );
			/* @noinspection PhpUndefinedFunctionInspection */
			add_action( 'admin_notices', [ $this, 'display_status_change_error' ] );
		}
	}

	/**
	 * Uses a function (wc_get_order) from WooCommerce
	 */
	public static function update_status( $id, $new_status, $status_note = null ): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		$order = wc_get_order( $id );

		if ( ! $order || strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
			return;
		}

		$order->set_status( $new_status, $status_note );
		$order->save();
	}

	/**
	 * Uses a function (wp_enqueue_style) from WordPress
	 */
	public function init_power_board_order_buttons( $order ): void {
		if ( ! $order || strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
			return;
		}

		$order_status          = $order->get_status();
		$total_refund          = round( (float) $order->get_total_refunded(), 2 );
		$order_total           = round( (float) $order->get_total( false ), 2 );
		$power_board_charge_id = $order->get_meta( '_power_board_charge_id' );
		if (
			$order_total === $total_refund ||
			!in_array(
				$order_status,
				[
					'processing',
					'refunded',
					'completed',
					'cancelled',
				],
				true
			) ||
			empty( $power_board_charge_id )
		) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_enqueue_style(
				'hide-refund-button-styles',
				POWER_BOARD_PLUGIN_URL . 'assets/css/admin/hide-refund-button.css',
				[],
				POWER_BOARD_PLUGIN_VERSION,
			);
		}

		if ( $order_status === 'on-hold' ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			wp_enqueue_style(
				'hide-on-hold-buttons',
				POWER_BOARD_PLUGIN_URL . 'assets/css/admin/hide-on-hold-buttons.css',
				[],
				POWER_BOARD_PLUGIN_VERSION,
			);
		}
	}

	/**
	 * Checks if status change is allowed
	 * Uses functions (__, set_transient, get_current_user_id and esc_html) from WordPress
	 * Uses a function (wc_get_order_status_name) from WooCommerce
	 *
	 * @throws Exception If status change is not allowed
	 */
	public function status_change_verification( $order_id, $old_status_key, $new_status_key, $order ): void {
		if ( ! is_object( $order ) || strpos( $order->get_payment_method(), POWER_BOARD_PLUGIN_PREFIX ) === false ) {
			return;
		}

		$order->delete_meta_data( '_status_change_verification_failed' );
		$order->save();
		if (
			$old_status_key === $new_status_key ||
			! empty( $GLOBALS['power_board_is_updating_order_status'] ) ||
			$order_id === null
		) {
			return;
		}
		$is_status_change_allowed = self::check_is_status_change_allowed( $old_status_key, $new_status_key );
		if ( ! $is_status_change_allowed ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$new_status_name = wc_get_order_status_name( $new_status_key );
			/* @noinspection PhpUndefinedFunctionInspection */
			$old_status_name = wc_get_order_status_name( $old_status_key );
			/* @noinspection PhpUndefinedFunctionInspection */
			$error = sprintf(
				/* translators: 1: Old status name, 2: New status name */
				__( 'You can not change status from "%1$s"  to "%2$s"', 'power-board' ),
				$old_status_name,
				$new_status_name
			);
			$GLOBALS['power_board_is_updating_order_status'] = true;
			$order->update_meta_data( '_status_change_verification_failed', 1 );
			$order->update_status( $old_status_key, $error );
			/* @noinspection PhpUndefinedFunctionInspection */
			set_transient( 'power_board_status_change_error_' . get_current_user_id(), $error, 300 );
			unset( $GLOBALS['power_board_is_updating_order_status'] );
			$this->remove_status_related_notes( $order_id );
			/* @noinspection PhpUndefinedFunctionInspection */
			throw new Exception( esc_html( $error ) );
		}
	}

	/**
	 * Handles the order notes’ verbiage for switching statuses back and forth. Woo core behaviour, can't be avoided
	 */
	public function remove_status_related_notes( $order_id ) {
		/* @noinspection PhpUndefinedFunctionInspection */
		$notes = wc_get_order_notes(
			[
				'order_id'   => $order_id,
				'date_query' => [
					'after' => '-30 sec',
				],
			],
		);

		if ( ! empty( $notes ) ) {
			foreach ( $notes as $note ) {
				$note_content = $note->content;

				$related_notes = [
					'Order status changed',
					'Error during status transition',
				];

				foreach ( $related_notes as $message ) {
					if ( strpos( $note_content, $message ) !== false ) {
						/* @noinspection PhpUndefinedFunctionInspection */
						wp_delete_comment( $note->id, true );
					}
				}
			}
		}
	}

	/**
	 * Uses functions (get_transient, get_current_user_id, esc_html and delete_transient) from WordPress
	 */
	public function display_status_change_error(): void {
		/* @noinspection PhpUndefinedFunctionInspection */
		$error_message = get_transient( 'power_board_status_change_error_' . get_current_user_id() );
		if ( $error_message ) {
			/* @noinspection PhpUndefinedFunctionInspection */
			echo '<div id="power-board-error-message" class="notice notice-error is-dismissible">
				<p>' . esc_html( $error_message ) . '</p>
			</div>';
			echo '<script type="text/javascript">
				jQuery(document).ready(function($) {
					$("#message.updated.notice.notice-success").hide();
				});
			</script>';
			/* @noinspection PhpUndefinedFunctionInspection */
			delete_transient( 'power_board_status_change_error_' . get_current_user_id() );
		}
	}

	/**
	 * Removes the bulk action success message when an error has occurred.
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @return void
	 */
	public function remove_bulk_action_message() {
		/* @noinspection PhpUndefinedFunctionInspection */
		$is_wc_orders_page = isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wc-orders';
		/* @noinspection PhpUndefinedFunctionInspection */
		$is_shop_order_page = isset( $_GET['post_type'] ) && sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) === 'shop_order';

		if (
			( $is_wc_orders_page || $is_shop_order_page ) &&
			isset( $_GET['bulk_action'], $_GET['changed'] )
		) {
			/* @noinspection PhpUndefinedFunctionInspection */
			$error_key = 'power_board_status_change_error_' . get_current_user_id();

			/* @noinspection PhpUndefinedFunctionInspection */
			if ( get_transient( $error_key ) ) {
				/* @noinspection PhpUndefinedFunctionInspection */
				wp_safe_redirect( remove_query_arg( 'changed' ) );
				exit;
			}
		}
	}
	// phpcs:enable

	public static function check_is_status_change_allowed( $old_status_key, $new_status_key ): bool {
		$statuses_rules = [
			'processing' => [ 'refunded', 'cancelled', 'failed', 'pending', 'completed' ],
			'refunded'   => [ 'cancelled', 'failed' ],
			'cancelled'  => [ 'failed', 'refunded' ],
		];
		if ( ! empty( $statuses_rules[ $old_status_key ] ) && ! in_array( $new_status_key, $statuses_rules[ $old_status_key ], true ) ) {
			return false;
		}

		return true;
	}
}
