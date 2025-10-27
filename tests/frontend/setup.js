/**
 * Jest setup file for frontend tests
 * Configures global mocks and test utilities
 */

// Mock jQuery
global.$ = jest.fn(
	() => ( {
		length: 1,
		val: jest.fn(),
		closest: jest.fn(
			() => ( {
				find: jest.fn(
					() => ( {
						remove: jest.fn(),
						length: 0
					} )
				),
				append: jest.fn()
			} )
		),
		removeClass: jest.fn(),
		addClass: jest.fn(),
		on: jest.fn(),
		off: jest.fn(), // Add missing off method
		is: jest.fn(),
		prepend: jest.fn(
			() => ( {
				find: jest.fn()
			} )
		),
		empty: jest.fn(
			() => ( {
				append: jest.fn()
			} )
		),
		first: jest.fn(),
		ajax: jest.fn(),
		Deferred: jest.fn(
			() => ( {
				reject: jest.fn()
			} )
		)
	} )
);

global.jQuery = global.$;

// Mock window.PowerBoardAjaxCheckout
global.window = {
	PowerBoardAjaxCheckout: {
		wpnonce_process_payment: 'test-nonce',
		wpnonce_intent: 'test-intent-nonce'
	},
	cba: {
		Checkout: jest.fn().mockImplementation(
			() => ( {
				setEnv: jest.fn(),
				onPaymentSuccessful: jest.fn(),
				onPaymentFailure: jest.fn(),
				onPaymentExpired: jest.fn()
			} )
		)
	},
	widgetPowerBoard: null,
	scrollTo: jest.fn(),
	showNotice: jest.fn(),
	location: {
		href: ''
	},
	formatList: jest.fn(
		( array ) => {
			if ( !array || array.length === 0 ) {
				return '';
			}
			if ( array.length === 1 ) {
				return array[ 0 ];
			}
			if ( array.length === 2 ) {
				return array.join( ' and ' );
			}
			const lastItem    = array[ array.length - 1 ];
			const restOfArray = array.slice( 0, -1 );
			return `${restOfArray.join( ', ' )},
			and ${lastItem}`;
		}
	)
};

// Mock scrollTo to avoid JSDOM "not implemented" error
Object.defineProperty(
	global.window,
	'scrollTo',
	{
		value: jest.fn(),
		writable: true
	}
);

// Mock showNotice to avoid JSDOM "not implemented" error
Object.defineProperty(
	global.window,
	'showNotice',
	{
		value: jest.fn(),
		writable: true
	}
);

// Mock formatList to avoid JSDOM "not implemented" error
Object.defineProperty(
	global.window,
	'formatList',
	{
		value: jest.fn(
			( array ) => {
				if ( !array || array.length === 0 ) {
					return '';
				}
				if ( array.length === 1 ) {
					return array[ 0 ];
				}
				if ( array.length === 2 ) {
					return array.join( ' and ' );
				}
				const lastItem    = array[ array.length - 1 ];
				const restOfArray = array.slice( 0, -1 );
				return `${restOfArray.join( ', ' )},
			and ${lastItem}`;
			}
		),
		writable: true
	}
);

// Mock window.confirm to avoid JSDOM "not implemented" error
Object.defineProperty(
	global.window,
	'confirm',
	{
		value: jest.fn().mockReturnValue( true ),
		writable: true
	}
);

// Mock document functions (note: some document properties are provided by JSDOM)
if ( !global.document.getElementById ) {
	global.document.getElementById = jest.fn();
}
if ( !global.document.querySelector ) {
	global.document.querySelector = jest.fn();
}
if ( !global.document.querySelectorAll ) {
	global.document.querySelectorAll = jest.fn( () => [] );
}
