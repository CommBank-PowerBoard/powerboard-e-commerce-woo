/**
 * Unit tests for ValidationService class
 */

const { ValidationService, CONSTANTS } = require( '../../assets/js/frontend/classic-form.js' );

describe( 'ValidationService', () => {
	let validationService;
	let mockJQuery;

	beforeEach( () => {
		// Mock jQuery
		mockJQuery = jest.fn( () => ( {
			val: jest.fn().mockReturnValue( '{"environment": "sandbox"}' )
		} ) );

		// Mock global functions
		global.window = {
			formatList: jest.fn( ( arr ) => arr.join( ', ' ) )
		};

		validationService = new ValidationService( mockJQuery );
	} );

	describe( 'getFieldsList', () => {
		beforeEach( () => {
			// Mock shipping checkbox
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.SHIP_TO_DIFFERENT ) {
					return [ {
						checked: false
					} ];
				}
				return {};
			} );
		} );

		it( 'should return billing fields only when shipping is disabled', () => {
			const result = validationService.getFieldsList();

			expect( result ).toEqual( [
				'billing_first_name',
				'billing_last_name',
				'billing_country',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_email',
				'billing_company',
				'billing_phone'
			] );
		} );

		it( 'should include shipping fields when checkbox is checked', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.SHIP_TO_DIFFERENT ) {
					return [ {
						checked: true
					} ];
				}
				return {};
			} );

			const result = validationService.getFieldsList();

			expect( result ).toContain( 'billing_first_name' );
			expect( result ).toContain( 'shipping_first_name' );
		} );
	} );

	describe( 'getAddressData', () => {
		beforeEach( () => {
			// Mock document.getElementById
			global.document.getElementById = jest.fn( ( fieldName ) => {
				const mockData = {
					billing_first_name: 'John',
					billing_last_name: 'Doe',
					billing_email: 'john@example.com',
					billing_country: 'US',
					billing_address_1: '123 Main St',
					billing_city: 'Anytown',
					billing_state: 'CA',
					billing_postcode: '12345',
					billing_company: 'ACME Corp',
					shipping_first_name: 'Jane',
					shipping_last_name: 'Smith',
					shipping_country: 'CA',
					shipping_address_1: '456 Oak Ave',
					shipping_city: 'Other City',
					shipping_state: 'ON',
					shipping_postcode: 'K1A 0A9',
					shipping_company: 'Other Corp'
				};

				if ( mockData[ fieldName ] ) {
					return { value: mockData[ fieldName ] };
				}
				return null;
			} );

			// Mock getFieldsList to return a specific set of fields
			jest.spyOn( validationService, 'getFieldsList' ).mockReturnValue( [
				'billing_first_name',
				'billing_last_name',
				'billing_email',
				'billing_country',
				'billing_address_1',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_company'
			] );
		} );

		it( 'should return JSON string by default', () => {
			const result = validationService.getAddressData();

			expect( typeof result ).toBe( 'string' );
			expect( () => JSON.parse( result ) ).not.toThrow();
		} );

		it( 'should return object when returnJson is false', () => {
			const result = validationService.getAddressData( false );

			expect( typeof result ).toBe( 'object' );
			expect( result ).toHaveProperty( 'address' );
			expect( result ).toHaveProperty( 'shipping_address' );
		} );

		it( 'should correctly map billing fields', () => {
			const result = validationService.getAddressData( false );

			expect( result.address ).toEqual( {
				first_name: 'John',
				last_name: 'Doe',
				email: 'john@example.com',
				country: 'US',
				address_1: '123 Main St',
				city: 'Anytown',
				state: 'CA',
				postcode: '12345',
				company: 'ACME Corp'
			} );
		} );

		it( 'should correctly map shipping fields', () => {
			// Mock getFieldsList to include shipping fields
			jest.spyOn( validationService, 'getFieldsList' ).mockReturnValue( [
				'billing_first_name',
				'shipping_first_name',
				'shipping_last_name'
			] );

			const result = validationService.getAddressData( false );

			expect( result.shipping_address ).toEqual( {
				first_name: 'Jane',
				last_name: 'Smith'
			} );
		} );

		it( 'should handle missing form elements gracefully', () => {
			global.document.getElementById = jest.fn().mockReturnValue( null );

			const result = validationService.getAddressData( false );

			expect( result.address ).toEqual( {
				first_name: '',
				last_name: '',
				email: '',
				country: '',
				address_1: '',
				city: '',
				state: '',
				postcode: '',
				company: ''
			} );
		} );
	} );

	describe( 'getConfigs', () => {
		it( 'should parse and return settings object', () => {
			mockJQuery.mockReturnValue( {
				val: jest.fn().mockReturnValue( '{"environment": "sandbox", "publishable_key": "pk_test_123"}' )
			} );

			const result = validationService.getConfigs();

			expect( result ).toEqual( {
				environment: 'sandbox',
				publishable_key: 'pk_test_123'
			} );
		} );

		it( 'should return empty object on JSON parse error', () => {
			mockJQuery.mockReturnValue( {
				val: jest.fn().mockReturnValue( 'invalid json' )
			} );

			const result = validationService.getConfigs();

			expect( result ).toEqual( {} );
		} );

		it( 'should use correct selector for settings input', () => {
			validationService.getConfigs();

			expect( mockJQuery ).toHaveBeenCalledWith( CONSTANTS.SELECTORS.SETTINGS_INPUT );
		} );

		it( 'should handle missing settings input gracefully', () => {
			mockJQuery.mockReturnValue( {
				val: jest.fn().mockReturnValue( null )
			} );

			const result = validationService.getConfigs();

			expect( result ).toBeNull();
		} );
	} );

	describe( 'getMissingRequiredFields', () => {
		beforeEach( () => {
			global.document.getElementById = jest.fn( ( fieldName ) => {
				// Mock some fields as filled and some as empty
				const filledFields = [
					CONSTANTS.SELECTORS.BILLING_FIRST_NAME_INPUT,
					CONSTANTS.SELECTORS.BILLING_LAST_NAME_INPUT,
					CONSTANTS.SELECTORS.BILLING_EMAIL_INPUT
				];

				if ( filledFields.includes( fieldName ) ) {
					return { value: 'some value' };
				}
				return { value: '' };
			} );
		} );

		it( 'should return formatted list of missing required fields', () => {
			const result = validationService.getMissingRequiredFields();

			expect( global.window.formatList ).toHaveBeenCalled();
			expect( typeof result ).toBe( 'string' );
		} );

		it( 'should return empty list when all fields are filled', () => {
			global.document.getElementById = jest.fn().mockReturnValue( { value: 'filled' } );

			validationService.getMissingRequiredFields();

			expect( global.window.formatList ).toHaveBeenCalledWith( [] );
		} );
	} );

	describe( 'validateCheckoutForm', () => {
		it( 'should return success when PowerBoard is not selected', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ) {
					return { val: jest.fn().mockReturnValue( 'other_payment_method' ) };
				}
				return {};
			} );

			const result = validationService.validateCheckoutForm();

			expect( result ).toEqual( {
				success: true,
				errors: []
			} );
		} );

		it( 'should validate required fields when PowerBoard is selected', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ) {
					return { val: jest.fn().mockReturnValue( CONSTANTS.PAYMENT_METHOD ) };
				}
				return {};
			} );

			jest.spyOn( validationService, 'getMissingRequiredFields' ).mockReturnValue( 'First name, Last name' );

			const result = validationService.validateCheckoutForm();

			expect( result.success ).toBe( false );
			expect( result.errors[ 0 ] ).toMatch( /To complete your checkout with .+, please provide the following required fields: First name, Last name/ );
		} );

		it( 'should return success when PowerBoard is selected and all fields are valid', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ) {
					return { val: jest.fn().mockReturnValue( CONSTANTS.PAYMENT_METHOD ) };
				}
				return {};
			} );

			jest.spyOn( validationService, 'getMissingRequiredFields' ).mockReturnValue( '' );

			const result = validationService.validateCheckoutForm();

			expect( result ).toEqual( {
				success: true,
				errors: []
			} );
		} );
	} );

	describe( 'isPowerBoardSelected', () => {
		it( 'should return true when PowerBoard is selected', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ) {
					return { val: jest.fn().mockReturnValue( CONSTANTS.PAYMENT_METHOD ) };
				}
				return {};
			} );

			const result = validationService.isPowerBoardSelected();

			expect( result ).toBe( true );
		} );

		it( 'should return false when other payment method is selected', () => {
			mockJQuery.mockImplementation( ( selector ) => {
				if ( selector === CONSTANTS.SELECTORS.PAYMENT_METHOD_RADIO ) {
					return { val: jest.fn().mockReturnValue( 'other_method' ) };
				}
				return {};
			} );

			const result = validationService.isPowerBoardSelected();

			expect( result ).toBe( false );
		} );
	} );

	describe( 'validatePaymentSubmissionData', () => {
		it( 'should return success for valid payment data', () => {
			const validData = {
				order_id: 'test-order-123',
				_wpnonce_intent: 'test-intent-nonce',
				_wpnonce_widget_event: 'test-widget-nonce',
				_wpnonce_success_order: 'test-success-nonce'
			};

			const result = validationService.validatePaymentSubmissionData( validData );

			expect( result ).toEqual( {
				success: true,
				errors: []
			} );
		} );

		it( 'should return error for invalid payment data', () => {
			const invalidData = {
				order_id: 'test-order-123'
				// Missing required nonces
			};

			const result = validationService.validatePaymentSubmissionData( invalidData );

			expect( result.success ).toBe( false );
			expect( result.errors.length ).toBeGreaterThan( 0 );
		} );
	} );
} );
