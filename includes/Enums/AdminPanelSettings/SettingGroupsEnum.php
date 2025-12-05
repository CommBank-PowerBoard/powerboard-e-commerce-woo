<?php
declare( strict_types=1 );

namespace PowerBoard\Enums\AdminPanelSettings;

use PowerBoard\Abstracts\AbstractEnum;

class SettingGroupsEnum extends AbstractEnum {
	public const ENVIRONMENT = 'ENVIRONMENT';
	public const CREDENTIALS = 'CREDENTIALS';
	public const CHECKOUT    = 'CHECKOUT';
}
