/**
 * @jest-environment jsdom
 */

import { clearCheckoutNotices } from '../../resources/js/frontend/BlockPaymentComponent.js';

describe( 'BlockPaymentComponent clearCheckoutNotices', () => {
	let selectMock, dispatchMock, getNoticesMock, removeNoticeMock;

	beforeEach( () => {
		getNoticesMock = jest.fn().mockReturnValue( [ { id: 'n1' }, { id: 'n2' } ] );
		removeNoticeMock = jest.fn();
		selectMock = jest.fn().mockReturnValue( { getNotices: getNoticesMock } );
		dispatchMock = jest.fn().mockReturnValue( { removeNotice: removeNoticeMock } );

		global.window.wp = {
			data: {
				select: selectMock,
				dispatch: dispatchMock
			}
		};
	} );

	afterEach( () => {
		delete global.window.wp;
		jest.clearAllMocks();
	} );

	it( 'clears only the CHECKOUT context', () => {
		const emitResponse = {
			noticeContexts: {
				CHECKOUT: 'wc/checkout',
				PAYMENTS: 'wc/checkout/payments'
			}
		};

		clearCheckoutNotices( emitResponse );

		// Should select and dispatch once per context used
		expect( selectMock ).toHaveBeenCalledWith( 'core/notices' );
		expect( dispatchMock ).toHaveBeenCalledWith( 'core/notices' );

		// getNotices should be called for CHECKOUT only
		expect( getNoticesMock ).toHaveBeenCalledTimes( 1 );
		expect( getNoticesMock ).toHaveBeenCalledWith( 'wc/checkout' );

		// removeNotice should be called for notices in CHECKOUT only
		expect( removeNoticeMock ).toHaveBeenCalledTimes( 2 );
		expect( removeNoticeMock ).toHaveBeenNthCalledWith( 1, 'n1', 'wc/checkout' );
		expect( removeNoticeMock ).toHaveBeenNthCalledWith( 2, 'n2', 'wc/checkout' );
	} );

	it( 'is a no-op if wp.data is unavailable', () => {
		delete global.window.wp;

		expect( () => clearCheckoutNotices( { noticeContexts: { CHECKOUT: 'wc/checkout' } } ) ).not.toThrow();
	} );
} );


