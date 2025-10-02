<?php
declare( strict_types=1 );

namespace PowerBoard\Enums;

class ConfigAPIEnum {
	public const PRODUCTION_API_URL             = 'https://api.powerboard.commbank.com.au/';
	public const SANDBOX_API_URL                = 'https://api.preproduction.powerboard.commbank.com.au/';
	public const STAGING_API_URL                = 'https://api.staging.powerboard.commbank.com.au/';
	public const API_VERSION_URL                = 'v1/';
	public const PRODUCTION_ENVIRONMENT_VALUE   = 'production_cba';
	public const SANDBOX_ENVIRONMENT_VALUE      = 'preproduction_cba';
	public const STAGING_ENVIRONMENT_VALUE      = 'staging_cba';
	public const PRODUCTION_ENVIRONMENT_URL_KEY = 'production';
	public const SANDBOX_ENVIRONMENT_URL_KEY    = 'preproduction';
	public const STAGING_ENVIRONMENT_URL_KEY    = 'staging';
	public const PRODUCTION_ENVIRONMENT_NAME    = 'Production';
	public const SANDBOX_ENVIRONMENT_NAME       = 'Preproduction';
	public const STAGING_ENVIRONMENT_NAME       = 'Staging';
}
