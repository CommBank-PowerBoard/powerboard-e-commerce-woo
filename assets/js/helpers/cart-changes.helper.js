/**
 * Cross-tab cart synchronization helper
 * Detects cart changes in other tabs and refreshes the checkout page
 * Only active when PowerBoard payment gateway is selected
 * Uses purely event-based monitoring for optimal performance
 * Supports full cart, checkout, and mini cart interactions
 */

class CartChangesHelper {
	constructor() {
		this.storageKey              = 'powerboard_cart_changes';
		this.isCheckoutPage          = this.detectCheckoutPage();
		this.isCartPage              = this.detectCartPage();
		this.hasMiniCart             = this.detectMiniCart();
		this.cartHash                = null;
		this.debounceTimer           = null;
		this.isActive                = false;
		this.paymentMethodId         = window.powerBoardCartSyncSettings?.paymentMethodId || 'power_board';
		this.isCheckoutFromSettings  = window.powerBoardCartSyncSettings?.isCheckout || false;
		this.isCartFromSettings      = window.powerBoardCartSyncSettings?.isCart || false;
		this.hasMiniCartFromSettings = window.powerBoardCartSyncSettings?.hasMiniCart || false;

		// Use server-side detection if available, otherwise fall back to client-side detection
		if (this.hasMiniCartFromSettings && !this.hasMiniCart) {
			this.hasMiniCart = true;
		}

		if (this.isCheckoutPage) {
			this.init();
		} else if (this.isCartPage) {
			this.initCartPage();
		} else {
			this.checkPowerBoardUsage();
		}
	}

	/**
	 * Detect if we're on a cart page
	 */
	detectCartPage() {
		const isClassicCart = document.body.classList.contains( 'woocommerce-cart' );
		const isBlockCart   = document.querySelector( '.wc-block-cart' ) !== null;
		const hasCartInUrl  = window.location.href.includes( '/cart' );
		const isCartAjax    = window.location.href.includes( 'wc-ajax=cart' );

		return isClassicCart || isBlockCart || hasCartInUrl || isCartAjax || this.isCartFromSettings;
	}

	/**
	 * Detect if mini cart is present on the page
	 */
	detectMiniCart() {
		const miniCartSelectors = [
			'.widget_shopping_cart',
			'.mini_cart',
			'.cart_list',
			'.woocommerce-mini-cart',
			'.mini-cart',
			'.shopping-cart-dropdown',
			'.cart-dropdown',
			'.header-cart',
			'.cart-widget',
			'.wc-block-mini-cart',
			'.wp-block-woocommerce-mini-cart',
			'.site-header-cart'
		];

		let hasMiniCart      = false;
		const foundSelectors = [];

		miniCartSelectors.forEach(
			selector => {
				const element = document.querySelector( selector );
				if (element) {
					hasMiniCart = true;
					foundSelectors.push( selector );
				}
		}
			);

		return hasMiniCart;
	}

	/**
	 * Initialize for cart page - always monitor changes and send notifications
	 */
	initCartPage() {
		this.isActive = true; // Always active on cart pages
		this.startListening(); // Still listen for notifications from other tabs
		this.generateInitialCartHash();
		this.setupEventBasedCartChangeDetection();
	}

	/**
	 * Check if PowerBoard is being used (for non-checkout pages)
	 */
	checkPowerBoardUsage() {
		// Look for signs that PowerBoard is being used on the site
		const hasPaymentMethodCookie = document.cookie.includes( 'wc_selected_payment_method=power_board' );
		const hasPowerBoardElements  = document.querySelector( '[data-payment-method="power_board"], #payment_method_power_board' ) !== null;

		if (hasPaymentMethodCookie || hasPowerBoardElements || this.hasMiniCart) {
			this.isActive = true;
			this.setupEventBasedCartChangeDetection();
		}
	}

	/**
	 * Detect if we're on a checkout page
	 */
	detectCheckoutPage() {
		const isClassicCheckout = document.body.classList.contains( 'woocommerce-checkout' );
		const isBlockCheckout   = document.querySelector( '.wc-block-checkout' ) !== null;
		const hasCheckoutInUrl  = window.location.href.includes( '/checkout' );
		const isCheckoutAjax    = window.location.href.includes( 'wc-ajax=checkout' );

		return isClassicCheckout || isBlockCheckout || hasCheckoutInUrl || isCheckoutAjax || this.isCheckoutFromSettings;
	}

	/**
	 * Initialize cart change detection
	 */
	init() {
		this.startListening();
		this.setupPaymentMethodMonitoring();
		this.checkInitialPaymentMethod();
	}

	/**
	 * Check initial payment method and activate if PowerBoard is selected
	 */
	checkInitialPaymentMethod() {
		// Check multiple times with increasing delays to handle DOM loading
		const checkTimes = [100, 500, 1000, 2000];

		checkTimes.forEach(
			(delay, index) => {
				setTimeout(
				() => {
					if (this.isPowerBoardSelected()) {
						this.activateCartSync();
					}
				},
				delay
				);
		}
			);
	}

	/**
	 * Setup monitoring for payment method changes
	 */
	setupPaymentMethodMonitoring() {
		// Monitor for classic checkout payment method changes
		document.addEventListener(
			'change',
			(event) => {
				if (event.target.name === 'payment_method') {
					this.handlePaymentMethodChange( event.target.value );
				}
		}
			);

		// Monitor for block checkout payment method changes with more specific selector
		document.addEventListener(
			'change',
			(event) => {
				const target = event.target;
				// Check for payment method radio changes (not shipping)
				if (target.type === 'radio' &&
				target.name &&
				target.name.includes( 'payment-method' ) &&
				target.checked) {
				this.handlePaymentMethodChange( target.value );
				}
		}
			);

		// Additional monitoring using MutationObserver for block checkout
		if (window.MutationObserver) {
			const paymentObserver = new MutationObserver(
				(mutations) => {
					mutations.forEach(
					(mutation) => {
						if (mutation.type === 'attributes' && mutation.attributeName === 'checked') {
							const target = mutation.target;
							if (target.type === 'radio' &&
							target.name &&
							target.name.includes( 'payment-method' ) &&
							target.checked) {
								this.handlePaymentMethodChange( target.value );
							}
						}
					}
					);
			}
				);

			// Observe payment method container
			const paymentContainer = document.querySelector( '.wc-block-checkout__payment-method, .wc-block-components-radio-control' );
			if (paymentContainer) {
				paymentObserver.observe(
					paymentContainer,
					{
						attributes: true,
						subtree: true,
						attributeFilter: ['checked']
				}
					);
			}
		}
	}

	/**
	 * Handle payment method change
	 */
	handlePaymentMethodChange(selectedMethod) {
		const isPowerBoard = selectedMethod === this.paymentMethodId;

		if (isPowerBoard && !this.isActive) {
			this.activateCartSync();
		} else if (!isPowerBoard && this.isActive) {
			this.deactivateCartSync();
		}
	}

	/**
	 * Check if PowerBoard payment method is currently selected
	 */
	isPowerBoardSelected() {
		// Check classic checkout
		const classicPaymentMethod = document.querySelector( 'input[name="payment_method"]:checked' );
		if (classicPaymentMethod) {
			if (classicPaymentMethod.value === this.paymentMethodId) {
				return true;
			}
		}

		// Check block checkout - try multiple selectors
		const blockSelectors = [
			'.wc-block-components-radio-control__input:checked',
			'input[type="radio"]:checked[name*="payment-method"]',
			'input[type="radio"]:checked[id*="payment-method"]',
			'.wc-block-components-radio-control-accordion-option input:checked'
		];

		for (const selector of blockSelectors) {
			const blockPaymentMethod = document.querySelector( selector );
			if (blockPaymentMethod) {
				if (blockPaymentMethod.value === this.paymentMethodId) {
					return true;
				}
			}
		}

		// Check for PowerBoard-specific elements
		const powerBoardSelectors = [
			`[data-payment-method ="${this.paymentMethodId}"]`,
			`#payment_method_${this.paymentMethodId}:checked`,
			`input[value          ="${this.paymentMethodId}"]:checked`,
			`.payment_method_${this.paymentMethodId}`,
			`[name                ="radio-control-wc-payment-method-options"][value="${this.paymentMethodId}"]:checked`
		];

		for (const selector of powerBoardSelectors) {
			const powerBoardElement = document.querySelector( selector );
			if (powerBoardElement) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Activate cart synchronization
	 */
	activateCartSync() {
		if (this.isActive) {
			return;
		}

		this.isActive = true;

		this.generateInitialCartHash();
		this.setupEventBasedCartChangeDetection();
	}

	/**
	 * Deactivate cart synchronization
	 */
	deactivateCartSync() {
		if (!this.isActive) {
			return;
		}

		this.isActive = false;

		// Clear any existing timers
		if (this.debounceTimer) {
			clearTimeout( this.debounceTimer );
		}

		// Disconnect mutation observer if it exists
		if (this.cartMutationObserver) {
			this.cartMutationObserver.disconnect();
			this.cartMutationObserver = null;
		}
	}

	/**
	 * Listen for storage events from other tabs
	 */
	startListening() {

		// Generate unique tab ID to prevent self-triggering
		this.tabId = Math.random().toString( 36 ).substr( 2, 9 );

		window.addEventListener(
			'storage',
			(event) => {
				if (event.key === this.storageKey && event.newValue) {
					try {
						const cartData = JSON.parse( event.newValue );

						// Prevent self-triggering by checking tab ID
						if (cartData.tabId === this.tabId) {
							return;
						}

						// Prevent rapid duplicate notifications
						if (this.lastReceivedNotification &&
						(Date.now() - this.lastReceivedNotification.timestamp) < 3000 &&
						this.lastReceivedNotification.cartHash === cartData.cartHash) {
							return;
						}

						this.lastReceivedNotification = {
							timestamp: Date.now(),
							cartHash: cartData.cartHash
						};

						// On checkout pages, only react if PowerBoard is selected
						// On cart pages, always react (though it's less common to have cart->cart)
						if (this.isCheckoutPage) {
							if (this.isActive && this.isPowerBoardSelected()) {
								this.handleCartChangeFromOtherTab( cartData );
							} else {
								// Fallback: retry after a short delay in case of timing issues
								if (this.isActive && !this.isPowerBoardSelected()) {
									setTimeout(
									() => {
										if (this.isPowerBoardSelected()) {
											this.handleCartChangeFromOtherTab( cartData );
										}
									},
									500
										);
								}
							}
						} else {
							// For cart and other pages, always handle if active
							if (this.isActive) {
								this.handleCartChangeFromOtherTab( cartData );
							}
						}
					} catch (error) {
						console.warn( '[PowerBoard CartSync] Error parsing cart change data:', error );
					}
				}
		}
			);
	}

	/**
	 * Handle cart change notification from another tab
	 */
	handleCartChangeFromOtherTab(cartData) {

		// Show notification before refresh
		this.showRefreshNotification();

		// Debounce the refresh to avoid multiple rapid refreshes
		clearTimeout( this.debounceTimer );
		this.debounceTimer = setTimeout(
			() => {
				this.refreshPage();
		},
			1500
			); // Increased to 1.5 seconds for better visibility
	}

	/**
	 * Show a brief notification that the page will refresh
	 */
	showRefreshNotification() {

		// Remove any existing notification
		const existingNotification = document.getElementById( 'powerboard-cart-sync-notification' );
		if (existingNotification) {
			existingNotification.remove();
		}

		// Different messages for cart vs checkout vs mini cart
		let message = '🛒 Cart updated in another tab. Refreshing...';
		if (this.isCheckoutPage) {
			message = '🛒 Cart updated in another tab. Refreshing PowerBoard checkout...';
		} else if (this.hasMiniCart && !this.isCartPage) {
			message = '🛒 Cart updated in another tab. Refreshing page...';
		}

		// Create notification element
		const notification     = document.createElement( 'div' );
		notification.id        = 'powerboard-cart-sync-notification';
		notification.innerHTML = `
			<div style         ="
				position: fixed;
				top: 20px;
				right: 20px;
				background: #2271b1;
				color: white;
				padding: 15px 20px;
				border-radius: 5px;
				box-shadow: 0 2px 10px rgba( 0,0,0,0.2 );
				z-index: 999999;
				font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
				font-size: 14px;
				animation: slideIn 0.3s ease-out;
			">
				${message}
			</div>
			<style>
				@keyframes slideIn {
					from { transform: translateX( 100% ); opacity: 0; }
					to { transform: translateX( 0 ); opacity: 1; }
		}
			</style>
		`;

		document.body.appendChild( notification );
	}

	/**
	 * Refresh the current page
	 */
	refreshPage() {
		window.location.reload();
	}

	/**
	 * Generate initial cart hash for comparison
	 */
	generateInitialCartHash() {
		this.cartHash = this.getCurrentCartHash();
	}

	/**
	 * Get current cart hash
	 */
	getCurrentCartHash() {
		// Try multiple sources to get cart data
		let cartData = '';

		// For WooCommerce blocks
		if (window.wp && window.wp.data) {
			try {
				const cartStore = window.wp.data.select( 'wc/store/cart' );
				if (cartStore) {
					const cart = cartStore.getCartData();
					// Only include stable cart data, exclude timestamps and dynamic content
					const cartInfo = {
						itemCount: cart.items?.length || 0,
						// Use only the essential totals, not the full object which might have timestamps
						totalPrice: cart.totals?.total_price || '0',
						totalTax: cart.totals?.total_tax || '0',
						shippingTotal: cart.totals?.total_shipping || '0',
						// Include shipping method selection but not dynamic data
						chosenShippingMethods: cart.chosenShippingMethods || []
					};

					// Add item-specific data (stable parts only)
					if (cart.items && cart.items.length > 0) {
						cartInfo.items = cart.items.map(
							item => ({
								id: item.id,
								quantity: item.quantity,
								totals: item.totals?.line_total || '0'
							})
							);
					}

					cartData += JSON.stringify( cartInfo );
				}
			} catch (error) {
				console.log( '[PowerBoard CartSync] Block cart store not available:', error );
			}
		}

		// For classic checkout - look for stable elements only
		const stableElements = [
			'.order-total .amount',
			'.cart-subtotal .amount',
			'.shipping .amount',
			'input[name^="shipping_method"]:checked'
		];

		stableElements.forEach(
			selector => {
				const elements = document.querySelectorAll( selector );
				elements.forEach(
				element => {
					if (element.tagName === 'INPUT') {
						cartData += element.value || '';
					} else {
						// Remove currency symbols and whitespace for stability
						const text = (element.textContent || '').replace( /[$£€¥\s,]/g, '' );
						cartData  += text;
					}
				}
				);
		}
			);

		// Include cart item quantities from stable sources
		const quantityInputs = document.querySelectorAll( 'input[name*="cart"][name*="qty"], .qty' );
		quantityInputs.forEach(
			input => {
				cartData    += input.value || '0';
		}
			);

		// Include mini cart data for hash calculation
		if (this.hasMiniCart) {
			const miniCartData = this.getMiniCartData();
			cartData          += miniCartData;
		}

		// Generate hash and add timestamp check to prevent rapid changes
		const hash = this.simpleHash( cartData );
		const now  = Date.now();

		// Store hash calculation time to prevent rapid successive changes
		this.lastHashTime = now;

		return hash;
	}

	/**
	 * Get mini cart data for hash calculation
	 */
	getMiniCartData() {
		let miniCartData = '';

		// Common mini cart selectors for quantities and prices
		const miniCartSelectors = [
			'.widget_shopping_cart .quantity',
			'.mini_cart .quantity',
			'.woocommerce-mini-cart .quantity',
			'.mini-cart .quantity',
			'.cart-dropdown .quantity',
			'.wc-block-mini-cart .quantity',
			'.widget_shopping_cart .amount',
			'.mini_cart .amount',
			'.woocommerce-mini-cart .amount',
			'.mini-cart .amount',
			'.cart-dropdown .amount',
			'.wc-block-mini-cart .amount',
			'.cart-contents-count',
			'.cart-total .amount',
			'.mini-cart-total',
			'.widget_shopping_cart_content .total'
		];

		miniCartSelectors.forEach(
			selector => {
				const elements = document.querySelectorAll( selector );
				elements.forEach(
				element => {
					// Remove currency symbols and whitespace for stability
					const text    = (element.textContent || '').replace( /[$£€¥\s,]/g, '' );
					miniCartData += text;
				}
				);
		}
			);

		// Get mini cart item count
		const miniCartCount = document.querySelector( '.cart-contents-count, .cart-count, .mini-cart-count' );
		if (miniCartCount) {
			miniCartData += miniCartCount.textContent || '0';
		}

		return miniCartData;
	}

	/**
	 * Simple hash function
	 */
	simpleHash(str) {
		let hash = 0;
		if (str.length === 0) {
			return hash;
		}
		const strLength = str.length;
		for (let i = 0; i < strLength; i++) {
			const char = str.charCodeAt( i );
			hash       = ((hash << 5) - hash) + char;
			hash       = hash & hash; // Convert to 32-bit integer
		}
		return hash.toString();
	}

	/**
	 * Setup event-based cart change detection
	 */
	setupEventBasedCartChangeDetection() {

		// Clear any existing timer-based monitoring
		if (this.cartCheckInterval) {
			clearInterval( this.cartCheckInterval );
			this.cartCheckInterval = null;
		}

		// Listen for shipping method changes specifically
		document.addEventListener(
			'change',
			(event) => {
				if (!this.isActive) {
					return;
				}
				const target = event.target;
				// Detect shipping method changes
				if (target.name && target.name.includes( 'shipping_method' )) {
					this.handleCartChangeEvent( 'shipping_method_change' );
				}

				// Detect quantity changes
				if (target.name && (target.name.includes( 'cart' ) && target.name.includes( 'qty' ))) {
					this.handleCartChangeEvent( 'quantity_change' );
				}

				// Detect mini cart quantity changes
				if (target.closest( '.widget_shopping_cart, .mini_cart, .woocommerce-mini-cart, .mini-cart, .cart-dropdown, .wc-block-mini-cart' ) &&
				(target.type === 'number' || target.name.includes( 'qty' ))) {
				this.handleCartChangeEvent( 'mini_cart_quantity_change' );
				}

				// Detect coupon-related changes
				if (target.name && target.name.includes( 'coupon' )) {
					this.handleCartChangeEvent( 'coupon_change' );
				}

				// Detect block checkout shipping changes
				if (target.type === 'radio' && target.closest( '.wc-block-components-radio-control-accordion-option' )) {
					this.handleCartChangeEvent( 'block_shipping_change' );
				}
		}
			);

		// Listen for form submissions that might change cart
		document.addEventListener(
			'submit',
			(event) => {
				if (!this.isActive) {
					return;
				}
				const form = event.target;
				if (form.classList.contains( 'woocommerce-cart-form' ) ||
				form.closest( '.woocommerce-cart-form' ) ||
				form.classList.contains( 'checkout' ) ||
				form.closest( '.checkout' ) ||
				form.closest( '.widget_shopping_cart, .mini_cart, .woocommerce-mini-cart, .mini-cart, .cart-dropdown, .wc-block-mini-cart' )) {
				this.handleCartChangeEvent( 'form_submission' );
				}
		}
			);

		// Listen for button clicks that might change cart
		document.addEventListener(
			'click',
			(event) => {
				if (!this.isActive) {
					return;
				}
				const target = event.target;
				// Update cart button
				if (target.name === 'update_cart' || target.value === 'Update cart') {
					this.handleCartChangeEvent( 'update_cart_click' );
				}

				// Apply coupon button
				if (target.name === 'apply_coupon' || target.classList.contains( 'button' ) && target.textContent.includes( 'Apply' )) {
					this.handleCartChangeEvent( 'apply_coupon_click' );
				}

				// Remove item links
				if (target.classList.contains( 'remove' ) || target.closest( '.remove' )) {
					this.handleCartChangeEvent( 'remove_item_click' );
				}

				// Mini cart specific buttons
				if (target.closest( '.widget_shopping_cart, .mini_cart, .woocommerce-mini-cart, .mini-cart, .cart-dropdown, .wc-block-mini-cart' )) {
					if (target.classList.contains( 'remove' ) ||
					target.closest( '.remove' ) ||
					target.classList.contains( 'remove_from_cart_button' ) ||
					target.name === 'update_cart' ||
					target.classList.contains( 'plus' ) ||
					target.classList.contains( 'minus' ) ||
					target.classList.contains( 'quantity-plus' ) ||
					target.classList.contains( 'quantity-minus' )) {
						this.handleCartChangeEvent( 'mini_cart_interaction' );
					}
				}

				// Block checkout buttons
				if (target.closest( '.wc-block-cart' ) || target.closest( '.wc-block-checkout' )) {
					this.handleCartChangeEvent( 'block_interaction' );
				}
		}
			);

		// Listen for common WooCommerce events
		const wooCommerceEvents = [
			'updated_cart_totals',
			'wc_cart_fragments_refresh',
			'updated_shipping_method',
			'wc-blocks_checkout_set_shipping_address',
			'wc-blocks_checkout_set_billing_address',
			'wc_cart_button_updated',
			'cart_page_refreshed',
			'checkout_error',
			'added_to_cart',
			'removed_from_cart',
			'wc_fragments_refreshed',
			'wc_fragments_loaded'
		];

		wooCommerceEvents.forEach(
			eventName => {
				document.addEventListener(
				eventName,
				() => {
					if (this.isActive) {
						this.handleCartChangeEvent( eventName );
					}
				}
				);
		}
			);

		// Listen for AJAX complete events that might indicate cart updates
		if (window.jQuery) {
			window.jQuery( document ).ajaxComplete(
				(event, xhr, settings) => {
					if (!this.isActive) {
						return;
					}
					if (settings.url && (
					settings.url.includes( 'wc-ajax=add_to_cart' ) ||
					settings.url.includes( 'wc-ajax=remove_from_cart' ) ||
					settings.url.includes( 'wc-ajax=update_cart' ) ||
					settings.url.includes( 'wc-ajax=apply_coupon' ) ||
					settings.url.includes( 'wc-ajax=remove_coupon' ) ||
					settings.url.includes( 'add-to-cart' ) ||
					settings.url.includes( 'update_cart' ) ||
					settings.url.includes( 'cart' ) ||
					// Mini cart specific AJAX
					settings.url.includes( 'wc-ajax=get_refreshed_fragments' ) ||
					settings.url.includes( 'wc-ajax=update_cart_totals' ) ||
					settings.url.includes( 'fragments' ) ||
					// Add shipping-specific AJAX endpoints
					settings.url.includes( 'wc-ajax=update_shipping_method' ) ||
					settings.url.includes( 'wc-ajax=get_shipping_methods' ) ||
					settings.url.includes( 'shipping_method' ) ||
					settings.url.includes( 'checkout' ) && settings.data && settings.data.includes( 'shipping' )
					)) {
					this.handleCartChangeEvent( 'ajax_cart_update' );
					}
			}
				);
		}

		// Setup DOM mutation observer for cart, shipping, and mini cart changes
		if (window.MutationObserver && !this.cartMutationObserver) {
			this.cartMutationObserver = new MutationObserver(
				(mutations) => {
					if (!this.isActive) {
						return;
					}
					let cartChanged     = false;
					let shippingChanged = false;
					let miniCartChanged = false;
					mutations.forEach(
					(mutation) => {
						// Look for changes in cart-related elements
						if (mutation.target.closest) {
							const isCartRelated     = mutation.target.closest( '.cart-contents, .wc-block-cart, .woocommerce-cart-form, .cart_totals, .order-total, .cart-subtotal' );
							const isShippingRelated = mutation.target.closest(
							'.shipping, .wc-block-components-totals-shipping, ' +
							'.wc-block-components-radio-control-accordion-option, ' +
							'.woocommerce-shipping-totals, .cart-shipping, ' +
							'[data-title="Shipping"], .shipping-total'
							);
							const isMiniCartRelated = mutation.target.closest(
								'.widget_shopping_cart, .mini_cart, .woocommerce-mini-cart, ' +
								'.mini-cart, .cart-dropdown, .wc-block-mini-cart, ' +
								'.cart-contents-count, .cart-count, .mini-cart-count'
							);

							if (isCartRelated) {
								cartChanged = true;
							}
							if (isShippingRelated) {
								shippingChanged = true;
							}
							if (isMiniCartRelated) {
								miniCartChanged = true;
							}
						}

						// Also check if shipping method inputs changed
						if (mutation.type === 'attributes' && mutation.attributeName === 'checked') {
							const target = mutation.target;
							if (target.name && target.name.includes( 'shipping_method' )) {
								shippingChanged = true;
							}
						}
					}
					);
				if (cartChanged || shippingChanged || miniCartChanged) {
					const changeTypes = [];
					if (cartChanged) {
						changeTypes.push( 'cart' );
					}
					if (shippingChanged) {
						changeTypes.push( 'shipping' );
					}
					if (miniCartChanged) {
						changeTypes.push( 'mini cart' );
					}

					const changeType = changeTypes.join( ' and ' );
					this.handleCartChangeEvent( 'dom_mutation' );
				}
			}
				);

			// Observe the entire document but filter for cart-related changes
			this.cartMutationObserver.observe(
				document.body,
				{
					childList: true,
					subtree: true,
					characterData: true,
					attributes: true,
					attributeFilter: ['checked', 'selected', 'value']
			}
				);

		}

		// Listen for browser navigation events (back/forward)
		window.addEventListener(
			'popstate',
			() => {
				if (this.isActive) {
					this.handleCartChangeEvent( 'navigation' );
				}
		}
			);

		// Listen for focus events that might indicate the user returned to the tab
		window.addEventListener(
			'focus',
			() => {
				if (this.isActive) {
					this.handleCartChangeEvent( 'window_focus' );
				}
		}
			);

		// Initial cart check with delay to let page stabilize
		setTimeout(
			() => {
				if (this.isActive) {
					this.checkForCartChanges();
				}
		},
			3000
			);

	}

	/**
	 * Handle cart change events with debouncing
	 */
	handleCartChangeEvent(eventType) {

		// Different debounce times for different event types
		const debounceTimeMap = {
			'shipping_method_change': 1500,
			'quantity_change': 1000,
			'mini_cart_quantity_change': 800,
			'mini_cart_interaction': 1000,
			'form_submission': 2000,
			'ajax_cart_update': 2000,
			'dom_mutation': 3000,
			'window_focus': 1000,
			'wc_fragments_refreshed': 1500,
			'added_to_cart': 1200,
			'removed_from_cart': 1200,
			'default': 1500
		};

		const debounceTime = debounceTimeMap[eventType] || debounceTimeMap['default'];

		// Clear any existing timeout for this event type
		const timeoutKey = `${eventType}Timeout`;
		if (this[timeoutKey]) {
			clearTimeout( this[timeoutKey] );
		}

		// Set new timeout
		this[timeoutKey] = setTimeout(
			() => {
				this.checkForCartChanges();
		},
			debounceTime
			);
	}

	/**
	 * Check if cart has changed and notify other tabs
	 */
	checkForCartChanges() {
		if (!this.isActive) {
			return;
		}

		// Prevent too frequent checks to avoid false positives
		const now = Date.now();
		if (this.lastCheckTime && (now - this.lastCheckTime) < 2000) {
			return;
		}
		this.lastCheckTime = now;

		const newCartHash = this.getCurrentCartHash();

		if (this.cartHash !== null && this.cartHash !== newCartHash) {
			// Additional validation - make sure this isn't a false positive

			// Wait a moment and check again to confirm the change is stable
			setTimeout(
				() => {
					const confirmHash = this.getCurrentCartHash();
					if (confirmHash === newCartHash && confirmHash !== this.cartHash) {
						this.notifyOtherTabs( newCartHash );
						this.cartHash = newCartHash;
					} else {
						// Keep the original hash since the change wasn't stable
					}
			},
				1000
				);
		} else if (this.cartHash === null) {
			this.cartHash = newCartHash;
		}
	}

	/**
	 * Notify other tabs about cart changes
	 */
	notifyOtherTabs(cartHash = null) {
		const currentUrl = window.location.href;

		// Don't notify if we're on the same page (prevent self-triggering)
		if (this.lastNotificationUrl === currentUrl && this.lastNotificationTime &&
			(Date.now() - this.lastNotificationTime) < 5000) {
			return;
		}

		const cartChangeData = {
			timestamp: Date.now(),
			cartHash: cartHash || this.cartHash,
			url: currentUrl,
			paymentMethod: this.paymentMethodId,
			userAgent: navigator.userAgent.substring( 0, 50 ), // Partial UA for debugging
			tabId: this.tabId || Math.random().toString( 36 ).substr( 2, 9 ) // Unique tab identifier
		};

		try {
			localStorage.setItem( this.storageKey, JSON.stringify( cartChangeData ) );

			// Store notification info to prevent rapid re-notifications
			this.lastNotificationUrl  = currentUrl;
			this.lastNotificationTime = Date.now();

			// Clear the storage item after a short delay to allow other tabs to read it
			setTimeout(
				() => {
					try {
						localStorage.removeItem( this.storageKey );
					} catch (error) {
						console.warn( '[PowerBoard CartSync] Could not clear notification:', error );
					}
			},
				2000
				);
		} catch (error) {
			console.warn( '[PowerBoard CartSync] Could not save cart change notification:', error );
		}
	}

	/**
	 * Manually trigger cart change check (for external use)
	 */
	triggerCartCheck() {
		this.checkForCartChanges();
	}

	/**
	 * Test the notification system
	 */
	testNotification() {
		this.showRefreshNotification();
		setTimeout(
			() => {
				console.log( '[PowerBoard CartSync] Test refresh (not actually refreshing)' );
		},
			1500
			);
	}

	/**
	 * Get current status
	 */
	getStatus() {
		return {
			isActive: this.isActive,
			isCheckoutPage: this.isCheckoutPage,
			isCartPage: this.isCartPage,
			hasMiniCart: this.hasMiniCart,
			pageType: this.isCheckoutPage ? 'checkout' : this.isCartPage ? 'cart' : 'other',
			isPowerBoardSelected: this.isCheckoutPage ? this.isPowerBoardSelected() : 'N/A (cart page)',
			paymentMethodId: this.paymentMethodId,
			cartHash: this.cartHash,
			monitoringType: 'Event-based (no timers)',
			miniCartSupport: this.hasMiniCart ? 'Enabled' : 'Not detected',
			behavior: this.isCartPage ? 'Always monitor changes and notify' :
					this.isCheckoutPage ? 'Monitor only when PowerBoard selected' :
					'Conditional monitoring'
		};
	}

	/**
	 * Get instance singleton
	 */
	static getInstance() {
		if (!window.powerBoardCartChangesHelper) {
			window.powerBoardCartChangesHelper = new CartChangesHelper();
		}
		return window.powerBoardCartChangesHelper;
	}
}

// Auto-initialize and expose for debugging
if (document.readyState === 'loading') {
	document.addEventListener(
		'DOMContentLoaded',
		() => {
			CartChangesHelper.getInstance();
	}
		);
} else {
	CartChangesHelper.getInstance();
}

// Export for use in other modules and debugging
window.CartChangesHelper = CartChangesHelper;

// Add essential debugging functions to window
window.powerBoardCartSyncDebug = {
	test: () => CartChangesHelper.getInstance().testNotification(),
	checkCart: () => CartChangesHelper.getInstance().triggerCartCheck(),
	getHash: () => CartChangesHelper.getInstance().getCurrentCartHash(),
	getInstance: () => CartChangesHelper.getInstance(),
	getStatus: () => CartChangesHelper.getInstance().getStatus(),
	activate: () => CartChangesHelper.getInstance().activateCartSync(),
	deactivate: () => CartChangesHelper.getInstance().deactivateCartSync(),

	checkPaymentMethod: () => {
		const instance   = CartChangesHelper.getInstance();
		const isSelected = instance.isPowerBoardSelected();
		console.log( 'PowerBoard selected:', isSelected );
		console.log( 'Is active:', instance.isActive );
		return isSelected;
	},

	checkMiniCart: () => {
		const instance = CartChangesHelper.getInstance();
		console.log( 'Has mini cart:', instance.hasMiniCart );
		if (instance.hasMiniCart) {
			const miniCartData = instance.getMiniCartData();
			console.log( 'Mini cart data:', miniCartData );
		}
		return { hasMiniCart: instance.hasMiniCart };
	},

	testCartHashStability: () => {
		const instance = CartChangesHelper.getInstance();
		const hashes   = [];
		for (let i = 0; i < 3; i++) {
			hashes.push( instance.getCurrentCartHash() );
		}
		const stable = hashes.every( hash => hash === hashes[0] );
		console.log( 'Hash stability:', stable ? 'STABLE' : 'UNSTABLE' );
		return { stable, hashes };
	},

	stopConstantRefresh: () => {
		const instance = CartChangesHelper.getInstance();
		if (instance.cartMutationObserver) {
			instance.cartMutationObserver.disconnect();
		}
		instance.isActive = false;
		return 'Cart sync stopped. Refresh page to restart.';
	}
};
