/**
 * @jest-environment jsdom
 */

import { BlockValidationService } from '../../resources/js/frontend/BlockValidationService.js';

describe(
	'BlockValidationService',
	() => {
		let validationService;
		let mockCart;
		beforeEach(
			() => {
			// Mock cart
				mockCart = {
					getCustomerData: jest.fn().mockReturnValue(
						{
							billingAddress: {
								first_name: 'John',
								last_name: 'Doe',
								email: 'john@example.com',
								address_1: '123 Main St',
								city: 'Anytown',
								state: 'CA',
								postcode: '12345',
								country: 'US',
								phone: '+1234567890',
								company: 'Test Company'
							},
							shippingAddress: {
								first_name: 'Jane',
								last_name: 'Smith',
								address_1: '456 Oak St',
								city: 'Othercity',
								state: 'NY',
								postcode: '67890',
								country: 'US',
								company: 'Test Shipping'
							}
						}
					),
					getCartTotals: jest.fn().mockReturnValue(
						{
							total_price: 100.00
						}
					)
				};
				// Create DOM elements for tests
				document.body.innerHTML = `
			<form class             ="wc-block-components-form">
				<input type         ="checkbox" id="terms" checked />
				<input type         ="checkbox" id="_woo_additional_terms" checked />
				<input type         ="checkbox" class="wc-block-checkout__use-address-for-billing" />
			</form>
			`;
				validationService       = new BlockValidationService( mockCart );
			}
		);
		afterEach(
			() => {
				document.body.innerHTML = '';
			}
		);
		describe(
			'constructor',
			() => {
				it(
					'should initialize with cart dependency',
					() => {
						expect( validationService.cart ).toBe( mockCart );
					}
				);
			}
		);
		describe(
			'validatePowerBoardRequirements',
			() => {
				it(
					'should return valid result for complete address data',
					() => {
						const result = validationService.validatePowerBoardRequirements();
						expect( result.isValid ).toBe( true );
						expect( result.error ).toEqual( '' );
					}
				);
				it(
					'should return error for incomplete billing address',
					() => {
						mockCart.getCustomerData.mockReturnValue(
							{
								billingAddress: {
									first_name: 'John'
									// missing required fields
								},
								shippingAddress: null
							}
						);
						const result = validationService.validatePowerBoardRequirements();
						expect( result.isValid ).toBe( false );
						expect( result.error ).toContain( 'To complete your checkout with PowerBoard, please provide the following required fields:' );
					}
				);
			}
		);
	}
);
