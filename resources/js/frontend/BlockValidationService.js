/**
 * BlockValidationService
 * Handles form validation logic for PowerBoard block checkout
 */

import { SELECTORS, GET_FIELD_NICE_NAME, ERROR_MESSAGES } from './constants.js';

export class BlockValidationService {
	constructor( cart ) {
		this.cart = cart;
	}

	/**
	 * Validates address data object
	 *
	 * @returns {array} An array with required fields for PowerBoard that are missing
	 * @private
	 */
	getMissingRequiredFields() {
		const addressData = this.cart.getCustomerData().billingAddress;

		if ( !addressData ) {
			return [];
		}

		const missingFields = [];

		const requiredFields = [
			SELECTORS.EMAIL_INPUT,
			SELECTORS.FIRST_NAME_INPUT,
			SELECTORS.LAST_NAME_INPUT,
			SELECTORS.ADDRESS_1_INPUT,
			SELECTORS.CITY_INPUT,
			SELECTORS.STATE_INPUT,
			SELECTORS.COUNTRY_INPUT,
			SELECTORS.POSTCODE_INPUT
		];

		// Check all required fields are present and not empty
		for ( const field of requiredFields ) {
			const fieldValue = addressData[ field ];

			if ( !fieldValue || fieldValue.trim() === '' ) {
				const niceName = GET_FIELD_NICE_NAME[ field ];
				missingFields.push( niceName );
			}
		}

		return missingFields;
	}

	/**
	 * Validates specific field requirements for PowerBoard
	 *
	 * @returns {Object} Validation result with details
	 */
	validatePowerBoardRequirements() {
		const result = {
			isValid: true,
			error: ''
		};

		// Check billing address completeness
		const invalidRequiredFields = this.getMissingRequiredFields();

		if ( invalidRequiredFields.length > 0 ) {
			result.isValid = false;
			// eslint-disable-next-line max-len
			result.error   = `${ERROR_MESSAGES.POWERBOARD_REQUIRED_FIELDS} ${window.formatList( invalidRequiredFields )}`;
		}

		return result;
	}
}
