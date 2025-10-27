<?php
declare( strict_types=1 );

namespace PowerBoard\Enums;

class APIActionEnum {
	public const REFUND                                     = 'Refund';
	public const CREATE_INTENT                              = 'Create intent';
	public const GET_CONFIGURATION_TEMPLATE_IDS             = 'Get Configuration Template IDs';
	public const GET_CONFIGURATION_TEMPLATE_BY_ID           = 'Get Configuration Template by ID';
	public const GET_CUSTOMISATION_TEMPLATE_IDS             = 'Get Customisation Template IDs';
	public const GET_CONFIGURATION_TEMPLATES_FOR_VALIDATION = 'Get Configuration Template IDs: Token Validation';
	public const GET_CHARGE_BY_ID                           = 'Get Charge By ID';
}
