<?php
declare( strict_types=1 );

namespace PowerBoard\Services;

use PowerBoard\Helpers\DBSettingsHelper;

class TemplateService {
	public $setting_service;
	private const TEMPLATE_DIR          = 'templates';
	private const ADMIN_TEMPLATE_DIR    = 'admin';
	private const CHECKOUT_TEMPLATE_DIR = 'checkout';

	private const TEMPLATE_END = '.php';

	private string $template_admin_dir;
	private string $template_checkout_dir;

	public function __construct( $service = null ) {
		$this->setting_service       = $service;
		$this->template_admin_dir    = implode( DIRECTORY_SEPARATOR, [ self::TEMPLATE_DIR, self::ADMIN_TEMPLATE_DIR ] );
		$this->template_checkout_dir = implode( DIRECTORY_SEPARATOR, [ self::TEMPLATE_DIR, self::CHECKOUT_TEMPLATE_DIR ] );
	}

	public function include_admin_html( string $template, array &$data = [] ): void {
		$settings         = DBSettingsHelper::get_powerboard_settings();
		$data['settings'] = $settings;

		$data['template_service'] = $this;

		$path = $this->get_admin_path( $template );

		if ( file_exists( $path ) ) {
			include $path; // nosemgrep: audit.php.lang.security.file.inclusion-arg  --  the following require is safe because we are checking if the file exists, and it is not a user input.
		}
	}

	public function get_admin_html( string $template, array $data = [] ): string {
		ob_start();

		$this->include_admin_html( $template, $data );

		return ob_get_clean();
	}

	private function get_admin_path( string $template ): string {
		return $this->get_template_path( $this->template_admin_dir . DIRECTORY_SEPARATOR . $template );
	}

	/**
	 * Uses a function (plugin_dir_path) from WordPress
	 */
	private function get_template_path( string $template ): string {
		return plugin_dir_path( POWER_BOARD_PLUGIN_FILE ) . $template . self::TEMPLATE_END;
	}

	public function include_checkout_html( string $template, array &$data = [] ): void {
		$data['template_service'] = $this;

		$path = $this->get_checkout_path( $template );

		if ( file_exists( $path ) ) {
			include $path; // nosemgrep: audit.php.lang.security.file.inclusion-arg  --  the following require is safe because we are checking if the file exists, and it is not a user input.
		}
	}

	private function get_checkout_path( string $template ): string {

		return $this->get_template_path( $this->template_checkout_dir . DIRECTORY_SEPARATOR . $template );
	}
}
