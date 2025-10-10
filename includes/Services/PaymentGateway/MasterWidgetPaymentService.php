<?php
/**
 * This file uses classes from WooCommerce
 *
 * @noinspection PhpUndefinedClassInspection
 */

declare( strict_types=1 );

namespace PowerBoard\Services\PaymentGateway;

use Exception;
use PowerBoard\Enums\AdminPanelSettings\EnvironmentSettingsEnum;
use PowerBoard\Enums\AdminPanelSettings\MasterWidgetSettingsEnum;
use PowerBoard\Enums\AdminPanelSettings\SettingGroupsEnum;
use PowerBoard\Helpers\AdminPanelHelpers\EnvironmentSettingsHelper;
use PowerBoard\Helpers\AdminPanelHelpers\MasterWidgetSettingsHelper;
use PowerBoard\Helpers\AdminPanelHelpers\SettingGroupsHelper;
use PowerBoard\Helpers\AvailablePaymentMethodsHelper;
use PowerBoard\Helpers\DBSettingsHelper;
use PowerBoard\Helpers\Util\LoggerHelper;
use PowerBoard\Helpers\Util\NonceHelper;
use PowerBoard\Services\HashService;
use PowerBoard\Services\IPNResponseService;
use PowerBoard\Services\SDKAdapterService;
use PowerBoard\Services\TemplateService;
use PowerBoard\Services\Validation\ConnectionValidationService;
use WC_Admin_Settings;
use WC_Payment_Gateway;

/**
 * Some properties used comes from the extension WC_Payment_Gateway from WooCommerce
 *
 * @property string $id
 * @property string $title
 * @property string $description
 * @property string $method_title
 * @property string $method_description
 * @property string $icon
 * @property bool $has_fields
 * @property array $supports
 * @property array $settings
 * @property array $form_fields
 * @property string $enabled
 * @property string $plugin_id
 */
class MasterWidgetPaymentService extends WC_Payment_Gateway {
	protected TemplateService $template_service;
	protected const  NOT_AVAILABLE_TEMPLATE_ERROR     = 'The selected template is no longer available.';
	protected const  INVALID_TOKEN_ERROR              = 'The previously saved access token is no longer valid.';
	private array $configuration_id_options           = [];
	protected static bool $admin_settings_load_logged = false;

	const DEFAULT_TITLE       = 'PowerBoard';
	const DEFAULT_DESCRIPTION = 'Click \'Place Order\' to securely complete your payment.';
	const TITLE_MAX           = 50;
	const DESCRIPTION_MAX     = 500;

	/**
	 * Uses functions (__, _x, add_action) from WordPress
	 * Uses a method (init_settings) from WC_Payment_Gateway
	 * Uses a property (method_description) from WC_Payment_Gateway
	 */
	public function __construct() {
		$this->id                 = POWER_BOARD_PLUGIN_PREFIX;
		$this->has_fields         = true;
		$this->supports           = [ 'products', 'default_credit_card_form' ];
		$this->method_title       = _x( 'PowerBoard payment', 'PowerBoard payment method', 'power-board' );
		$this->method_description = __(
			'PowerBoard simplify how you manage your payments. Reduce costs, technical headaches & streamline compliance using PowerBoard\'s payment orchestration.',
			'power-board'
		);
		$this->title              = $this->get_option( 'title', self::DEFAULT_TITLE );
		$this->description        = $this->get_option( 'description', self::DEFAULT_DESCRIPTION );
		$this->icon               = POWER_BOARD_PLUGIN_URL . 'assets/images/logo.svg';

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		if ( is_admin() ) {
			$this->title            = $this->method_title;
			$this->template_service = new TemplateService( $this );

			$this->settings[ DBSettingsHelper::get_access_token_key() ] = DBSettingsHelper::get_access_token();
			$this->update_available_payment_methods();
		}

		new IPNResponseService();
		add_action( 'woocommerce_settings_page_init', [ $this, 'log_admin_settings_load' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options_with_logging' ] );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_action( 'woocommerce_order_get_payment_method_title', [ $this, 'admin_display_order_pmt_type' ], 10, 2 );
	}

	/**
	 * Clears the available_payment_methods from settings when there's no configuration template
	 * Adds fallback for users with already saved settings before available_payment_methods feature was implemented
	 * */
	private function update_available_payment_methods() {
		$config_template           = DBSettingsHelper::get_configuration_id();
		$available_payment_methods = DBSettingsHelper::get_available_payment_methods();

		if ( empty( $available_payment_methods ) ) {
			$this->handle_available_payment_methods_settings(); // can be removed in the future
			update_option( 'woocommerce_power_board_settings', $this->settings );
		} elseif ( empty( $config_template ) ) {
			$this->settings[ DBSettingsHelper::get_available_payment_methods_key() ] = [];
			update_option( 'woocommerce_power_board_settings', $this->settings );
		}
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function get_title(): string {
		return trim( $this->title ) ? $this->title : $this->method_title;
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function is_available(): bool {
		if ( $this->enabled === 'yes' ) {
			// Prevent using this gateway on frontend if there are any configuration errors.
			return $this->has_valid_required_fields();
		}

		return parent::is_available();
	}

	/**
	 * Initialise settings form fields.
	 *
	 * Add an array of fields to be displayed on the gateway's settings screen.
	 *
	 * @since  1.0.0
	 */
	public function init_form_fields(): void {
		$this->form_fields['title'] = [
			'type'        => 'text',
			'title'       => __( 'Title', 'power-board' ),
			/* translators: %d: maximum number of characters allowed for the title */
			'description' => sprintf( __( 'Shown at checkout. Max %d chars. Leave empty for default.', 'power-board' ), self::TITLE_MAX ),
			'default'     => self::DEFAULT_TITLE,
			'desc_tip'    => true,
		];

		$this->form_fields['description'] = [
			'type'        => 'textarea',
			'title'       => __( 'Description', 'power-board' ),
			/* translators: %d: maximum number of characters allowed for the description */
			'description' => sprintf( __( 'Short help text. Max %d chars. Leave empty for default.', 'power-board' ), self::DESCRIPTION_MAX ),
			'default'     => self::DEFAULT_DESCRIPTION,
			'desc_tip'    => true,
			'class'       => 'powerboard-textarea',
			'css'         => 'width:400px; height:62px;',
		];

		foreach ( SettingGroupsEnum::cases() as $setting_group ) {
			$key = DBSettingsHelper::get_option_name(
					[
						$setting_group,
						'label',
					]
				);

			$this->form_fields[ $key ] = [
				'type'  => 'big_label',
				'title' => SettingGroupsHelper::get_label( $setting_group ),
			];

			switch ( $setting_group ) {
				case SettingGroupsEnum::ENVIRONMENT:
					$merged_options = $this->get_environment_options();
					break;
				case SettingGroupsEnum::CREDENTIALS:
					$merged_options = $this->get_credential_options();
					break;
				case SettingGroupsEnum::CHECKOUT:
					$merged_options = $this->get_checkout_options();
					break;
				default:
					$merged_options = [];
					break;
			}

			$this->form_fields = array_merge( $this->form_fields, $merged_options );
		}
	}

	/**
	 * Called only for Classic Checkout
	 * Process the payment and return the result.
	 * This function is used on WC_Payment_Gateway
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @noinspection PhpUnused
	 * @throws Exception If intent status is not completed
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ): array {
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );

		/**
		 * Process payment for classic checkout
		 *
		 * For classic checkout: use redirect mechanism
		 */
		return [
			'result'                                => 'success',
			'redirect'                              => 'powerboard_show_modal',
			'order_id'                              => $order_id,
			'_wpnonce_intent'                       => wp_create_nonce( 'power-board-create-charge-intent' ),
			'_wpnonce_widget_event'                 => wp_create_nonce( 'power-board-widget-event' ),
			'_wpnonce_success_order'                => wp_create_nonce( 'power-board-successful-order' ),
			'_wpnonce_error_notice'                 => wp_create_nonce( 'power-board-create-error-notice' ),
			'_wpnonce_woocommerce_process_checkout' => wp_create_nonce( 'woocommerce-process_checkout' ),
			'message'                               => 'Please complete your payment in the PowerBoard modal.',
		];
	}
	// phpcs:enable


	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	public function admin_display_order_pmt_type( $title, $order ) {
		if ( $order instanceof WC_Order ) {
			$pb = $order->get_meta( '_power_board_payment_type' );
			if ( $pb ) {
				return $pb;
			}
		}
		return $title;
	}

	/**
	 * Called to retrieve url to navigate to
	 */
	public function process_successful_order() {
		// Validate nonce for security
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$valid_nonce = NonceHelper::is_valid_nonce( $_REQUEST['_wpnonce'] ?? '', 'power-board-successful-order' );
		if ( ! $valid_nonce ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is performed by NonceHelper which handles sanitization and validation
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			LoggerHelper::log_callback_event(
				'Error: Payment was processed but order was not found',
				[
					'order_id' => $order_id ?? null,
				],
				'error'
			);
			wp_send_json_error( [ 'message' => 'Order not found' ] );
		}

		$redirect_url = $this->get_return_url( $order );

		LoggerHelper::log_callback_event(
			'Order processed successfully',
			[
				'order_id'     => $order_id ?? null,
				'order_status' => $order->get_status(),
			],
		);
		wp_send_json_success(
			[
				'redirect_url' => $redirect_url,
				'order_id'     => $order_id,
				'order_status' => $order->get_status(),
			],
			200
		);
	}

	public function get_settings(): array {
		$powerboard_settings = DBSettingsHelper::get_powerboard_settings();

		return [
			// Widget.
			'title'                     => MasterWidgetSettingsHelper::get_gateway_title( $this->settings, self::TITLE_MAX ?? null ),
			'description'               => MasterWidgetSettingsHelper::get_gateway_description( $this->settings, self::DESCRIPTION_MAX ?? null ),
			// Master Widget Checkout.
			'environment'               => $powerboard_settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ],
			'checkout_template_version' => $powerboard_settings[ DBSettingsHelper::LOCAL_VERSION_ID ],
			'checkout_customisation_id' => $powerboard_settings[ DBSettingsHelper::LOCAL_CUSTOMISATION_TEMPLATE_ID ],
			'checkout_configuration_id' => $powerboard_settings[ DBSettingsHelper::LOCAL_CONFIGURATION_TEMPLATE_ID ],
			'available_payment_methods' => $powerboard_settings[ DBSettingsHelper::LOCAL_AVAILABLE_PAYMENT_METHODS_ID ],
		];
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 * Uses functions (wp_create_nonce and wp_json_encode) from WordPress
	 *
	 * @noinspection PhpUnused
	 */
	public function payment_fields(): void {
		$template = new TemplateService( $this );
		SDKAdapterService::get_instance();

		$settings = $this->get_settings();

		/* @noinspection PhpUndefinedFunctionInspection */
		$data = [
			'description' => $this->description,
			'id'          => $this->id,
			'settings'    => wp_json_encode( $settings ),
		];
		$template->include_checkout_html(
			'method-form',
			$data
		);
	}

	public function process_admin_options_with_logging(): bool {
		$processed = $this->process_admin_options();
		$this->log_admin_settings( 'Admin settings saved' );
		return $processed;
	}

	/**
	 * Processes the admin options for the payment gateway
	 * This function is used on WC_Payment_Gateway
	 * Uses functions (wp_unslash, do_action, update_option and apply_filters) from WordPress
	 * Uses a function (wc_clean) from WooCommerce
	 * Uses methods (init_settings, get_form_fields, get_field_type, validate_text_field, get_option, add_error and get_option_key) from WC_Payment_Gateway
	 * phpcs:disable WordPress.Security.NonceVerification -- processed through the WooCommerce form handler
	 *
	 * @noinspection PhpUnused
	 */
	public function process_admin_options(): bool {
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'power_board_selected_CONFIGURATION_ID_template_not_available', false );
		/* @noinspection PhpUndefinedFunctionInspection */
		set_transient( 'power_board_selected_CUSTOMISATION_ID_template_not_available', false );
		/* @noinspection PhpUndefinedMethodInspection */
		$this->init_settings();

		if ( $this->has_validation_errors_to_show() ) {
			return false;
		}

		$this->save_settings();
		$this->save_hashed_settings();
		$this->handle_available_payment_methods_settings();

		/* @noinspection PhpUndefinedMethodInspection */
		$option_key = $this->get_option_key();
		/* @noinspection PhpUndefinedFunctionInspection */
		do_action( 'woocommerce_update_option', [ 'id' => $option_key ] );

		$title = MasterWidgetSettingsHelper::get_gateway_title( $this->settings, self::TITLE_MAX ?? null );
		$desc  = MasterWidgetSettingsHelper::get_gateway_description( $this->settings, self::DESCRIPTION_MAX ?? null );

		if ( mb_strlen( $title ) > self::TITLE_MAX ) {
			$title = mb_substr( $title, 0, self::TITLE_MAX );
		}

		if ( mb_strlen( $desc ) > self::DESCRIPTION_MAX ) {
			$desc = mb_substr( $desc, 0, self::DESCRIPTION_MAX );
		}

		if ( $title === '' ) {
			$title = self::DEFAULT_TITLE;
		}

		if ( $desc === '' ) {
			$desc = self::DEFAULT_DESCRIPTION;
		}

		$this->settings['title']       = $title;
		$this->settings['description'] = $desc;

		$this->title       = $title;
		$this->description = $desc;

		/* @noinspection PhpUndefinedFunctionInspection */
		return update_option(
			$option_key,
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ),
			'yes'
		);
	}
	// phpcs:enable

	private function handle_available_payment_methods_settings() {
		$available_payment_methods = [];
		$configuration_id          = $this->settings[ DBSettingsHelper::get_configuration_template_key() ];

		if ( !empty( $configuration_id ) ) {
			$available_payment_methods = AvailablePaymentMethodsHelper::fetch_available_payment_methods( $configuration_id );
		}

		$this->settings[ DBSettingsHelper::get_available_payment_methods_key() ] = $available_payment_methods;
	}

	public function add_error( $error ) {
		parent::add_error( $error );
		WC_Admin_Settings::add_error( $error );
	}

	/**
	 * This function is used on admin.php template
	 *
	 * @noinspection PhpUnused
	 */
	public function parent_generate_settings_html( $form_fields = [], $should_echo = true ): ?string {
		return parent::generate_settings_html( $form_fields, $should_echo );
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 * Uses a method (get_form_fields) from WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function generate_settings_html( $form_fields = [], $should_echo = true ): ?string {
		if ( empty( $form_fields ) ) {
			/* @noinspection PhpUndefinedMethodInspection */
			$form_fields = $this->get_form_fields();
		}

		$credential_key                    = DBSettingsHelper::get_access_token_key();
		$this->settings[ $credential_key ] = ! empty( $this->settings[ $credential_key ] ) ? '********************' : '';
		$form_fields                       = compact( 'form_fields' );

		if ( $should_echo ) {
			$this->template_service->include_admin_html( 'admin', $form_fields );
		} else {
			return $this->template_service->get_admin_html( 'admin', $form_fields );
		}

		return null;
	}

	/**
	 * This function is used on WC_Payment_Gateway
	 *
	 * @noinspection PhpUnused
	 */
	public function generate_big_label_html( $key, $value ): string {
		return $this->template_service->get_admin_html( 'big-label', compact( 'key', 'value' ) );
	}

	private function get_credential_options(): array {
		$this->configuration_id_options = MasterWidgetSettingsHelper::get_options_for_ui( MasterWidgetSettingsEnum::CONFIGURATION_ID );

		$key = DBSettingsHelper::get_access_token_key();

		/* @noinspection PhpUndefinedFunctionInspection */
		$invalid_access_token = get_transient( 'invalid_access_token' );
		$add_error            = false;

		if ( isset( $invalid_access_token ) && $invalid_access_token === '1' ) {
			$add_error = true;
		}

		return [
			$key => [
				'type'        => 'password',
				'title'       => 'API Access Token',
				'description' => $add_error ? self::INVALID_TOKEN_ERROR : '',
				'desc_tip'    => 'Enter your API Access Token. This token is used to securely authenticate your payment operations. It is also used to retrieve the values for the Checkout Template ID fields shown below.',
			],
		];
	}

	private function get_checkout_options(): array {
		$fields = [];

		foreach ( MasterWidgetSettingsEnum::cases() as $checkout_settings ) {
			$add_error = false;
			$key       = DBSettingsHelper::get_option_name(
				[
					SettingGroupsEnum::CHECKOUT,
					$checkout_settings,
				]
			);

			/* @noinspection PhpUndefinedFunctionInspection */
			$selected_template_not_available = get_transient( 'power_board_selected_' . $checkout_settings . '_template_not_available' );

			if ( isset( $selected_template_not_available ) && $selected_template_not_available === '1' ) {
				$add_error = true;
			}

			$description = null;
			if ( $add_error ) {
				$description = self::NOT_AVAILABLE_TEMPLATE_ERROR;

				if ( MasterWidgetSettingsEnum::CONFIGURATION_ID === $checkout_settings ) {
					$description = $description . ' Please select a new template and save your configuration.';
				}
			}

			$fields[ $key ] = [
				'type'        => MasterWidgetSettingsHelper::get_input_type( $checkout_settings ),
				'title'       => preg_replace( [ '/ Id/', '/ id/' ], ' ID', MasterWidgetSettingsHelper::get_label( $checkout_settings ) ),
				'description' => $description,
			];
			$environment    = DBSettingsHelper::get_environment();

			if ( MasterWidgetSettingsEnum::VERSION === $checkout_settings || ! empty( $environment ) ) {
				if ( $checkout_settings === MasterWidgetSettingsEnum::CONFIGURATION_ID ) {
					$options = $this->configuration_id_options;
				} else {
					$options = MasterWidgetSettingsHelper::get_options_for_ui( $checkout_settings );
				}

				if ( ! empty( $options ) && ( MasterWidgetSettingsHelper::get_input_type( $checkout_settings ) ) === 'select' ) {
					$fields[ $key ]['options'] = $options;
					$fields[ $key ]['class']   = POWER_BOARD_PLUGIN_PREFIX . '-settings' . ( MasterWidgetSettingsEnum::CUSTOMISATION_ID === $checkout_settings ? ' is-optional grey-description' : '' );
					$fields[ $key ]['default'] = '';
				}
			}
		}

		return $fields;
	}

	private function get_environment_options(): array {
		$fields = [];
		foreach ( EnvironmentSettingsEnum::cases() as $environment_settings ) {
			$key = DBSettingsHelper::get_option_name(
				[
					SettingGroupsEnum::ENVIRONMENT,
					$environment_settings,
				]
			);

			$fields[ $key ] = [
				'type'  => EnvironmentSettingsHelper::get_input_type( $environment_settings ),
				'title' => preg_replace( [ '/ Id/', '/ id/' ], ' ID', EnvironmentSettingsHelper::get_label( $environment_settings ) ),
			];

			$options = EnvironmentSettingsHelper::get_options_for_ui( $environment_settings );

			if ( ! empty( $options ) && ( EnvironmentSettingsHelper::get_input_type( $environment_settings ) ) === 'select' ) {
				$fields[ $key ]['options'] = $options;
				$fields[ $key ]['class']   = POWER_BOARD_PLUGIN_PREFIX . '-settings';
				$fields[ $key ]['default'] = EnvironmentSettingsHelper::get_default( $environment_settings );
			}
		}

		return $fields;
	}

	private function has_valid_required_fields(): bool {
		$powerboard_settings = DBSettingsHelper::get_powerboard_settings();
		$version             = $powerboard_settings[ DBSettingsHelper::LOCAL_VERSION_ID ];
		$environment         = $powerboard_settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ];
		$access_token        = $powerboard_settings[ DBSettingsHelper::LOCAL_ACCESS_TOKEN_ID ];
		$configuration_id    = $powerboard_settings[ DBSettingsHelper::LOCAL_CONFIGURATION_TEMPLATE_ID ];

		return !empty( $version ) && !empty( $environment ) && !empty( $access_token ) && !empty( $configuration_id );
	}

	public function log_admin_settings_load(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking admin page section parameter
		if ( isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
			$this->log_powerboard_admin_load();
		}
	}

	protected function log_powerboard_admin_load(): void {
		if ( self::$admin_settings_load_logged ) {
			return;
		}
		self::$admin_settings_load_logged = true;

		$this->log_admin_settings( 'Admin settings page refreshed' );
	}

	protected function log_admin_settings( $log_title ): void {
		$powerboard_settings = DBSettingsHelper::get_powerboard_settings();

		$raw_token   = $powerboard_settings[ DBSettingsHelper::LOCAL_ACCESS_TOKEN_ID ];
		$token_valid = ! empty( $raw_token );
		$masked      = false;

		if ( $raw_token ) {
			$masked = '...' . substr( $raw_token, -4 );
		}

		LoggerHelper::log(
			$log_title,
			'info',
			[
				'environment'               => $powerboard_settings[ DBSettingsHelper::LOCAL_ENVIRONMENT_ID ],
				'access_token_valid'        => $token_valid,
				'access_token_masked'       => $masked,
				'checkout_version'          => $powerboard_settings[ DBSettingsHelper::LOCAL_VERSION_ID ],
				'configuration_template'    => $powerboard_settings[ DBSettingsHelper::LOCAL_CONFIGURATION_TEMPLATE_ID ],
				'customisation_template'    => $powerboard_settings[ DBSettingsHelper::LOCAL_CUSTOMISATION_TEMPLATE_ID ],
				'available_payment_methods' => $powerboard_settings[ DBSettingsHelper::LOCAL_AVAILABLE_PAYMENT_METHODS_ID ],
			]
		);
	}

	private function save_hashed_settings() {
		$hashed_credential_keys = $this->get_hashed_credential_keys();
		foreach ( $hashed_credential_keys as $key => $credential_settings ) {
			try {
				$decrypted_key = HashService::decrypt( $this->settings[ $key ] );
			} catch ( Exception $error ) {
				$decrypted_key = null;
				$this->add_error( $error );
			}
			$is_encrypted = $decrypted_key !== $this->settings[ $key ];

			if ( ! empty( $this->settings[ $key ] ) && ! $is_encrypted ) {
				try {
					$encrypted_key = HashService::encrypt( $this->settings[ $key ] );
				} catch ( Exception $error ) {
					$encrypted_key = null;
					$this->add_error( $error );
				}
				$this->settings[ $key ] = $encrypted_key;
			}
		}
	}

	private function save_settings() {
		$settings_keys         = $this->get_settings_keys();
		$empty_template_fields = false;
		/* @noinspection PhpUndefinedMethodInspection */
		foreach ( $this->get_form_fields() as $key => $field ) {
			/* @noinspection PhpUndefinedMethodInspection */
			$type = $this->get_field_type( $field );

			$option_key = $this->plugin_id . $this->id . '_' . $key;

            // phpcs:disable WordPress.Security.NonceVerification.Missing -- This is processed through the WooCommerce form handler
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- This is processed through the WooCommerce form handler
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean() and wp_unslash()
			$value = ! empty( $_POST[ $option_key ] ) ? wc_clean( wp_unslash( $_POST[ $option_key ] ) ) : null;
            // phpcs:enable

			if ( method_exists( $this, 'validate_' . $type . '_field' ) ) {
				$value = $this->{'validate_' . $type . '_field'}( $key, $value );
			} else {
				/* @noinspection PhpUndefinedMethodInspection */
				$value = $this->validate_text_field( $key, $value );
			}

			if ( array_key_exists( $key, $settings_keys ) ) {
				if ( $value === '********************' ) {
					/* @noinspection PhpUndefinedMethodInspection */
					$value = $this->get_option( $key );
				} elseif ( $key === 'power_board_CREDENTIALS_ACCESS_KEY' ) {
					$empty_template_fields = true;
				}
			}

			if ( $empty_template_fields &&
				( $key === 'power_board_CHECKOUT_CONFIGURATION_ID' ||
					$key === 'power_board_CHECKOUT_CUSTOMISATION_ID' ) ) {
				$this->settings[ $key ] = '';
			} else {
				$this->settings[ $key ] = $value;
			}
		}
	}

	private function get_settings_keys(): array {
		$settings_keys                = [];
		$access_key                   = DBSettingsHelper::get_access_token_key();
		$settings_keys[ $access_key ] = 'ACCESS_KEY';

		foreach ( EnvironmentSettingsEnum::cases() as $environment_settings ) {
			$key                   = DBSettingsHelper::get_option_name(
				[
					SettingGroupsEnum::ENVIRONMENT,
					$environment_settings,
				]
			);
			$settings_keys[ $key ] = $environment_settings;
		}

		foreach ( MasterWidgetSettingsEnum::cases() as $master_widget_settings ) {
			$key                   = DBSettingsHelper::get_option_name(
				[
					SettingGroupsEnum::CHECKOUT,
					$master_widget_settings,
				]
			);
			$settings_keys[ $key ] = $master_widget_settings;
		}

		return $settings_keys;
	}

	private function get_hashed_credential_keys(): array {
		$access_key                            = DBSettingsHelper::get_access_token_key();
		$hashed_credential_keys[ $access_key ] = 'ACCESS_KEY';

		return $hashed_credential_keys;
	}

	private function has_validation_errors_to_show(): bool {
		$validation_service = new ConnectionValidationService( $this );
		$errors             = $validation_service->get_errors();
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				WC_Admin_Settings::add_error( $error );
				break;
			}
			return true;
		}

		return false;
	}
}
