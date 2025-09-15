<?php
declare( strict_types=1 );

namespace PowerBoard\Helpers\AdminPanelHelpers;

use PowerBoard\Enums\SettingGroupsEnum;

class SettingGroupsHelper {
	public static function get_label( string $key ): string {
		switch ( $key ) {
			case SettingGroupsEnum::ENVIRONMENT:
				return 'Environment';
			case SettingGroupsEnum::CREDENTIALS:
				return 'API Credential';
			case SettingGroupsEnum::CHECKOUT:
				return 'Checkout';
			default:
				return '';
		}
	}
}
