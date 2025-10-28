/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/frontend/BlockCheckoutHandler.js":
/*!*******************************************************!*\
  !*** ./resources/js/frontend/BlockCheckoutHandler.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockCheckoutHandler: () => (/* binding */ BlockCheckoutHandler)
/* harmony export */ });
/* harmony import */ var _BlockModalManager_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./BlockModalManager.js */ "./resources/js/frontend/BlockModalManager.js");
/* harmony import */ var _BlockDataService_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./BlockDataService.js */ "./resources/js/frontend/BlockDataService.js");
/* harmony import */ var _BlockValidationService_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./BlockValidationService.js */ "./resources/js/frontend/BlockValidationService.js");
/* harmony import */ var _BlockWidgetManager_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./BlockWidgetManager.js */ "./resources/js/frontend/BlockWidgetManager.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/**
 * BlockCheckoutHandler
 * Main business logic coordinator for PowerBoard block checkout
 */







class BlockCheckoutHandler {
  constructor(store, cart, settings, responseTypes, noticeContexts) {
    this.store = store;
    this.cart = cart;
    this.settings = settings;
    this.responseTypes = responseTypes;
    this.noticeContexts = noticeContexts;

    // Initialize services
    this.dataService = new _BlockDataService_js__WEBPACK_IMPORTED_MODULE_1__.BlockDataService(store, cart, settings);
    this.modalManager = new _BlockModalManager_js__WEBPACK_IMPORTED_MODULE_0__.BlockModalManager(this.dataService);
    this.validationService = new _BlockValidationService_js__WEBPACK_IMPORTED_MODULE_2__.BlockValidationService(cart);
    this.widgetManager = new _BlockWidgetManager_js__WEBPACK_IMPORTED_MODULE_3__.BlockWidgetManager(this.modalManager, this.dataService, settings);

    // Event handlers storage
    this.eventHandlers = new Map();
  }

  /**
   * Processes checkout validation for WooCommerce Blocks
   *
   * @returns {Object} Validation result for emitResponse
   */
  processCheckoutValidation() {
    const powerBoardValidation = this.validationService.validatePowerBoardRequirements();
    if (!powerBoardValidation.isValid) {
      return {
        type: this.responseTypes.ERROR,
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(powerBoardValidation.error, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN)
      };
    }
    return {
      type: this.responseTypes.SUCCESS
    };
  }

  /**
   * Processes the payment setup for block checkout
   *
   * @returns {Object} Payment setup result
   */
  processPaymentSetup() {
    // Let WooCommerce handle basic field validation during payment setup phase
    // PowerBoard-specific validation will occur before showing the modal
    return {
      type: this.responseTypes.SUCCESS,
      meta: {
        paymentMethodData: {
          powerboard_redirect: _constants_js__WEBPACK_IMPORTED_MODULE_4__.MODAL_REDIRECT
        }
      }
    };
  }

  /**
   * Handles checkout success event from WooCommerce blocks
   *
   * @param {Object} checkoutData - Checkout success data
   * @returns {Promise<Object|boolean>} Success response or true to continue
   */
  async handleCheckoutSuccess(checkoutData) {
    try {
      const paymentDetails = checkoutData.processingResponse?.paymentDetails;
      const orderId = paymentDetails?.order_id;
      this.dataService.setPaymentData(paymentDetails);
      this.dataService.setOrderId(orderId);
      if (paymentDetails?.powerboard_redirect === _constants_js__WEBPACK_IMPORTED_MODULE_4__.MODAL_REDIRECT) {
        // Backend validation has already passed if we reach this point
        // Show modal and wait for payment result
        const modalResult = await this.showModalAndProcessPayment();
        if (modalResult.success) {
          // Call process_payment_result to complete the payment
          const response = await this.dataService.processSuccessfulOrder(orderId);
          if (response.success && response.data?.redirect_url) {
            // Return redirect response to let WooCommerce handle the redirect
            return {
              type: this.responseTypes.SUCCESS,
              redirectUrl: response.data.redirect_url
            };
          } else {
            return this.createCheckoutErrorObject((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_4__.ERROR_MESSAGES.PAYMENT_FAILED, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN));
          }
        } else {
          return this.createCheckoutErrorObject(modalResult.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_4__.ERROR_MESSAGES.PAYMENT_FAILED, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN));
        }
      }

      // Not our payment method or no modal needed, continue normally
      return true;
    } catch (error) {
      return this.createCheckoutErrorObject((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_4__.ERROR_MESSAGES.SOMETHING_WRONG, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN));
    }
  }

  /**
   * Handle returning promise resolve
   *
   * @param {String} message - Message to be shown for error
   * @returns {Object} - Error object for Woo to use
   * @private
   */
  createCheckoutErrorObject(message) {
    return {
      type: this.responseTypes.ERROR,
      message: message,
      messageContext: this.noticeContexts.PAYMENTS,
      retry: true
    };
  }

  /**
   * Shows modal and processes payment - returns Promise that resolves when payment completes
   *
   * @returns {Promise<Object>} Payment result with success/failure status
   * @private
   */
  async showModalAndProcessPayment() {
    return new Promise((resolve, reject) => {
      // Set the payment promise resolver in widget manager
      this.widgetManager.setPaymentPromiseResolver(resolve);

      // Show modal
      if (!this.modalManager.show()) {
        reject(new Error((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_4__.ERROR_MESSAGES.MODAL_LOAD_FAILED, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN)));
        this.widgetManager.setPaymentPromiseResolver(null);
        return;
      }

      // Add modal close handlers for handling Promise on user close
      this.setupOnCloseModalHandlers();

      // Initialize payment widget through widget manager
      this.widgetManager.initializeMasterWidget();
    });
  }

  /**
   * Handle when modal is closed without promise handled
   * This is then identified as a user cancellation
   *
   * @private
   */
  setupOnCloseModalHandlers() {
    // Handle modal close (user cancellation)
    this.modalManager.onClose(() => {
      this.widgetManager.handlePromiseResolveFailure((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_4__.ERROR_MESSAGES.USER_CANCELLED, _constants_js__WEBPACK_IMPORTED_MODULE_4__.TEXT_DOMAIN));
    });
  }

  /**
   * Cleans up the handler and its resources
   */
  cleanup() {
    this.modalManager.close();
    this.widgetManager.cleanup();
  }

  /**
   * Gets the current state of the checkout handler
   *
   * @returns {Object} Current state
   */
  getState() {
    return {
      isModalOpen: this.modalManager.isOpen()
    };
  }
}

/***/ }),

/***/ "./resources/js/frontend/BlockDataService.js":
/*!***************************************************!*\
  !*** ./resources/js/frontend/BlockDataService.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockDataService: () => (/* binding */ BlockDataService)
/* harmony export */ });
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/**
 * BlockDataService
 * Handles API calls and data processing for PowerBoard block checkout
 */


class BlockDataService {
  constructor(store, cart, settings) {
    this.store = store;
    this.cart = cart;
    this.settings = settings;
    this.jQuery = window.jQuery;
  }

  /**
   * Creates a charge intent for the payment
   *
   * @returns {Promise<Object>} The API response
   */
  async createChargeIntent() {
    const data = {
      _wpnonce: this.getPaymentData()._wpnonce_intent,
      order_id: this.getOrderId(),
      total: this.cart.getCartTotals(),
      address: this.cart.getCustomerData().billingAddress
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.CREATE_INTENT,
        type: 'POST',
        data: data,
        success: response => {
          if (response.success) {
            resolve(response);
          } else {
            reject(new Error(response.data?.message || 'Failed to create charge intent'));
          }
        },
        error: (xhr, status, error) => {
          reject(new Error(`Network error: ${error}`));
        }
      });
    });
  }

  /**
   * Notify backend of successful payment via AJAX
   *
   * @param {Object} responseData - Response data from widget
   * @returns {Promise<Object>} The API response
   */
  async onPaymentSuccessful(responseData) {
    const chargeId = responseData?.charge_id;
    const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;
    const data = {
      _wpnonce: widgetEventNonce,
      order_id: this.getOrderId(),
      charge_id: chargeId,
      payment_data: responseData
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.PAYMENT_SUCCESS,
        type: 'POST',
        data: data,
        success: response => {
          if (response.success) {
            resolve(response);
          } else {
            reject(new Error(response.data?.message || 'Backend notification failed'));
          }
        },
        error: (xhr, status, error) => {
          reject(new Error(`Network error: ${error}`));
        }
      });
    });
  }

  /**
   * Notify backend of failed payment via AJAX
   *
   * @param {Object} responseData - Response data from widget
   * @returns {Promise<void>} The API response
   */
  async onPaymentFailure(responseData) {
    const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;
    const data = {
      _wpnonce: widgetEventNonce,
      order_id: this.getOrderId(),
      payment_data: responseData
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.PAYMENT_FAILURE,
        type: 'POST',
        data: data,
        success: response => {
          if (response.success) {
            resolve();
          } else {
            reject();
          }
        },
        error: (xhr, status, error) => {
          reject(new Error(`Network error: ${error}`));
        }
      });
    });
  }

  /**
   * Notify backend of failed session expired via AJAX
   *
   * @param {Object} responseData - Response data from widget
   * @returns {Promise<void>} The API response
   */
  async onPaymentExpired(responseData) {
    const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;
    const data = {
      _wpnonce: widgetEventNonce,
      order_id: this.getOrderId(),
      payment_data: responseData
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.PAYMENT_EXPIRED,
        type: 'POST',
        data: data,
        success: response => {
          if (response.success) {
            resolve();
          } else {
            reject();
          }
        },
        error: (xhr, status, error) => {
          reject(new Error(`Network error: ${error}`));
        }
      });
    });
  }

  /**
   * Notify backend of payment cancellation via AJAX
   *
   * @param {Boolean} isUserInitiated - Is this close user initiated
   * @param {String|null} errorMessage - Error message for cause of close
   * @returns {Promise<void>} The API response
   */
  async onPaymentCancelled(isUserInitiated, errorMessage = null) {
    const widgetEventNonce = this.getPaymentData()._wpnonce_widget_event;
    const data = {
      _wpnonce: widgetEventNonce,
      order_id: this.getOrderId(),
      user_cancelled: isUserInitiated,
      error_message: isUserInitiated !== false ? _constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.USER_CANCELLED : errorMessage
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.PAYMENT_CANCELLED,
        type: 'POST',
        data: data,
        success: response => {
          if (response.success) {
            resolve();
          } else {
            reject();
          }
        },
        error: (xhr, status, error) => {
          reject(new Error(`Network error: ${error}`));
        }
      });
    });
  }

  /**
   * Processes payment result after widget completion
   *
   * @param {string|null} orderId - Order ID
   * @returns {Promise<Object>} The payment processing result
   */
  async processSuccessfulOrder(orderId = null) {
    const data = {
      _wpnonce: this.getPaymentData()._wpnonce_success_order,
      order_id: orderId
    };
    return new Promise((resolve, reject) => {
      this.jQuery.ajax({
        url: _constants_js__WEBPACK_IMPORTED_MODULE_0__.AJAX_ENDPOINTS.ORDER_SUCCESS,
        method: 'POST',
        data: data,
        success: response => {
          resolve(response);
        },
        error: (xhr, status, error) => {
          reject(new Error(`Payment processing failed: ${error}`));
        }
      });
    });
  }

  /**
   * Gets the order ID from various sources
   *
   * @returns {number|null} The order ID if available
   */
  getOrderId() {
    try {
      return this.orderId || this.store.getOrderId() || null;
    } catch (error) {
      return this.orderId || null;
    }
  }

  /**
   * Sets the order ID for later use
   *
   * @param {number} orderId - The order ID to store
   */
  setOrderId(orderId) {
    this.orderId = orderId;
  }

  /**
   * Sets payment data
   *
   * @param {Object} paymentData - Payment data to store
   */
  setPaymentData(paymentData) {
    this.paymentData = paymentData || {};
  }

  /**
   * Gets stored payment data
   *
   * @returns {Object|null} Parsed payment data or null
   */
  getPaymentData() {
    return this.paymentData;
  }
}

/***/ }),

/***/ "./resources/js/frontend/BlockModalManager.js":
/*!****************************************************!*\
  !*** ./resources/js/frontend/BlockModalManager.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockModalManager: () => (/* binding */ BlockModalManager)
/* harmony export */ });
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/**
 * BlockModalManager
 * Handles PowerBoard payment modal display and interactions for block checkout
 */


class BlockModalManager {
  constructor(dataService) {
    this.isModalOpen = false;
    this.closeHandlers = [];
    this.onCloseCallbacks = []; // Store callbacks for when modal closes
    this.dataService = dataService;
  }

  /**
   * Shows the payment modal with loading state
   *
   * @returns {boolean} True if modal was shown successfully
   */
  show() {
    const modal = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.MODAL);
    if (!modal) {
      return false;
    }

    // Reset modal to loading state
    this.resetToLoadingState();

    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    this.isModalOpen = true;

    // Setup close handlers
    this.setupCloseHandlers(modal);
    return true;
  }

  /**
   * Closes the payment modal and cleans up
   *
   * @param {boolean} userInitiated - Whether the close was initiated by user action
   * @param {String|null} errorReason - Error message to report for cause of close
   */
  close(userInitiated = false, errorReason = null) {
    const modal = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.MODAL);
    if (modal) {
      modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    this.isModalOpen = false;

    // Clean up widget
    if (window.widgetPowerBoard) {
      window.widgetPowerBoard = null;
    }

    // If not default values, process as cancellation
    if (userInitiated !== false || errorReason !== null) {
      this.dataService.onPaymentCancelled(userInitiated, errorReason);
    }

    // Trigger close callbacks
    this.triggerCloseCallbacks();

    // Remove event listeners
    this.removeCloseHandlers();
  }

  /**
   * Shows error state in modal
   *
   * @param {?string} errorMessage - Optional custom error message to show in modal
   */
  showError(errorMessage = null) {
    const modalLoading = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.LOADING);
    const modalWidget = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.WIDGET);
    const modalError = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.ERROR);
    if (modalLoading) {
      modalLoading.style.display = 'none';
    }
    if (modalWidget) {
      modalWidget.style.display = 'none';
    }
    if (modalError) {
      modalError.style.display = 'block';

      // Update error message if custom message provided
      if (errorMessage) {
        const errorElement = modalError.querySelector('.power-board-validation-error');
        if (errorElement) {
          errorElement.textContent = errorMessage;
        }
      }
    }

    // Auto-close modal after showing error
    setTimeout(() => {
      this.close(false, errorMessage);
    }, _constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_AUTO_CLOSE_DELAY);
  }

  /**
   * Shows widget container and hides loading state
   */
  showWidget() {
    const modalLoading = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.LOADING);
    const modalWidget = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.WIDGET);
    const modalError = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.ERROR);
    if (modalLoading) {
      modalLoading.style.display = 'none';
    }
    if (modalWidget) {
      modalWidget.style.display = 'block';
      modalWidget.classList.add('active');
    }
    if (modalError) {
      modalError.style.display = 'none';
    }
  }

  /**
   * Checks if modal is currently open
   *
   * @returns {boolean}
   */
  isOpen() {
    return this.isModalOpen;
  }

  /**
   * Resets modal to loading state
   *
   * @private
   */
  resetToLoadingState() {
    const modalLoading = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.LOADING);
    const modalWidget = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.WIDGET);
    const modalError = document.getElementById(_constants_js__WEBPACK_IMPORTED_MODULE_0__.MODAL_IDS.ERROR);
    if (modalLoading) {
      modalLoading.style.display = 'block';
    }
    if (modalWidget) {
      modalWidget.style.display = 'none';
      modalWidget.classList.remove('active');
    }
    if (modalError) {
      modalError.style.display = 'none';
    }
  }

  /**
   * Sets up close handlers for the modal
   *
   * @param {Element} modal - The modal element
   * @private
   */
  setupCloseHandlers(modal) {
    // Close button handler
    const closeBtn = modal.querySelector(_constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.MODAL_CLOSE);
    if (closeBtn) {
      const closeBtnHandler = () => this.handleUserClose();
      closeBtn.addEventListener('click', closeBtnHandler);
      this.closeHandlers.push({
        element: closeBtn,
        event: 'click',
        handler: closeBtnHandler
      });
    }

    // Outside click handler
    const outsideClickHandler = e => {
      if (e.target === modal) {
        this.handleUserClose();
      }
    };
    modal.addEventListener('click', outsideClickHandler);
    this.closeHandlers.push({
      element: modal,
      event: 'click',
      handler: outsideClickHandler
    });

    // Escape key handler
    const escapeKeyHandler = e => {
      if (e.key === 'Escape' && this.isModalOpen) {
        this.handleUserClose();
      }
    };
    document.addEventListener('keydown', escapeKeyHandler);
    this.closeHandlers.push({
      element: document,
      event: 'keydown',
      handler: escapeKeyHandler
    });
  }

  /**
   * Removes all close handlers
   *
   * @private
   */
  removeCloseHandlers() {
    this.closeHandlers.forEach(({
      element,
      event,
      handler
    }) => {
      element.removeEventListener(event, handler);
    });
    this.closeHandlers = [];
  }

  /**
   * Shows confirmation dialog before closing modal
   *
   * @returns {boolean} True if user confirmed close, false otherwise
   */
  showCloseConfirmation() {
    return window.confirm(_constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.MODAL_CLOSE_CONFIRMATION_MESSAGE);
  }

  /**
   * Handles user-initiated close with confirmation
   */
  handleUserClose() {
    if (this.showCloseConfirmation()) {
      this.close(true);
    }
  }

  /**
   * Registers a callback to be called when modal closes
   *
   * @param {Function} callback - Function to call when modal closes
   * @returns {Function} Unsubscribe function
   */
  onClose(callback) {
    if (typeof callback === 'function') {
      this.onCloseCallbacks.push(callback);

      // Return unsubscribe function
      return () => {
        const index = this.onCloseCallbacks.indexOf(callback);
        if (index > -1) {
          this.onCloseCallbacks.splice(index, 1);
        }
      };
    }
    return () => {};
  }

  /**
   * Triggers all registered close callbacks
   *
   * @private
   */
  triggerCloseCallbacks() {
    this.onCloseCallbacks.forEach(callback => {
      try {
        callback();
      } catch (error) {
        // Error in modal close callback
      }
    });
  }
}

/***/ }),

/***/ "./resources/js/frontend/BlockPaymentComponent.js":
/*!********************************************************!*\
  !*** ./resources/js/frontend/BlockPaymentComponent.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockPaymentComponent: () => (/* binding */ BlockPaymentComponent),
/* harmony export */   clearCheckoutNotices: () => (/* binding */ clearCheckoutNotices)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _BlockCheckoutHandler_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./BlockCheckoutHandler.js */ "./resources/js/frontend/BlockCheckoutHandler.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__);
/**
 * BlockPaymentComponent
 * React component for PowerBoard block checkout
 */






const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__.getSetting)('power_board_data', {});
const availablePaymentMethods = settings.available_payment_methods || [];
const defaultInfoText = 'Click \'Place Order\' to securely complete your payment.';
const paymentInfoText = settings.payment_info_text || defaultInfoText;

/**
 * Clears checkout-related notices across likely contexts to avoid stacked errors
 *
 * @param {Object} emitResponse - Woo Blocks emitResponse object (to read noticeContexts)
 */
function clearCheckoutNotices(emitResponse) {
  try {
    if (window.wp && window.wp.data) {
      const noticesStore = window.wp.data.select('core/notices');
      const dispatchNotices = window.wp.data.dispatch('core/notices');
      const contextsToClear = [emitResponse.noticeContexts && emitResponse.noticeContexts.CHECKOUT].filter(Boolean);
      for (const ctx of contextsToClear) {
        const ctxNotices = noticesStore.getNotices(ctx) || [];
        for (const n of ctxNotices) {
          dispatchNotices.removeNotice(n.id, ctx);
        }
      }
    }
  } catch (e) {
    // no-op if notices API is unavailable
  }
}
const BlockPaymentComponent = props => {
  const {
    eventRegistration,
    emitResponse,
    store,
    cart,
    settings
  } = props;
  const {
    onPaymentSetup,
    onCheckoutSuccess,
    onCheckoutValidation
  } = eventRegistration;
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    // Initialize checkout handler
    const checkoutHandler = new _BlockCheckoutHandler_js__WEBPACK_IMPORTED_MODULE_2__.BlockCheckoutHandler(store, cart, settings, emitResponse.responseTypes, emitResponse.noticeContexts);
    const unsubscribeCheckoutValidation = onCheckoutValidation(() => {
      try {
        const result = checkoutHandler.processCheckoutValidation();
        if (result.type === emitResponse.responseTypes.SUCCESS) {
          return true;
        }

        // Clear existing notices from previous validations
        clearCheckoutNotices(emitResponse);
        return {
          type: emitResponse.responseTypes.ERROR,
          errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(result.message, _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)
        };
      } catch (error) {
        // Clear existing notices from previous validations
        clearCheckoutNotices(emitResponse);
        return {
          type: emitResponse.responseTypes.ERROR,
          errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_3__.ERROR_MESSAGES.CHECKOUT_VALIDATION, _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)
        };
      }
    });

    // Set up payment setup handler
    const unsubscribePaymentSetup = onPaymentSetup(() => {
      try {
        const result = checkoutHandler.processPaymentSetup();
        if (result.type === emitResponse.responseTypes.SUCCESS) {
          return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: result.meta
          };
        } else {
          return {
            type: emitResponse.responseTypes.ERROR,
            message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(result.message, _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN),
            messageContext: result.messageContext || emitResponse.noticeContexts.PAYMENTS
          };
        }
      } catch (error) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_3__.ERROR_MESSAGES.PAYMENT_SETUP, _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN),
          messageContext: emitResponse.noticeContexts.PAYMENTS
        };
      }
    });

    // Set up checkout success handler (for showing modal)
    const unsubscribeCheckoutSuccess = onCheckoutSuccess(async checkoutData => {
      try {
        return await checkoutHandler.handleCheckoutSuccess(checkoutData);
      } catch (error) {
        return {
          type: emitResponse.responseTypes.ERROR,
          message: _constants_js__WEBPACK_IMPORTED_MODULE_3__.ERROR_MESSAGES.PAYMENT_FAILED,
          messageContext: emitResponse.noticeContexts.PAYMENTS,
          retry: true
        };
      }
    });
    // Cleanup function
    return () => {
      checkoutHandler.cleanup();
      unsubscribePaymentSetup();
      unsubscribeCheckoutValidation();
      unsubscribeCheckoutSuccess();
    };
  }, [emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS, emitResponse.noticeContexts.PAYMENTS, onPaymentSetup, onCheckoutSuccess, store, cart, settings]);

  // Render the payment component UI
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    className: 'master-widget-wrapper'
  }, createAvailablePaymentMethodsList(), createModal(), createPaymentInfo(), createLoadingIndicator(), createHiddenPaymentField());
};

/**
 * Creates the PowerBoard available payment methods
 *
 * @returns {ReactElement} Payment method label component
 */
const createAvailablePaymentMethodsList = () => {
  const availablePaymentMethodsElements = [];
  for (let paymentMethodKey in availablePaymentMethods) {
    const paymentMethodNiceName = availablePaymentMethods[paymentMethodKey].nice_name;
    const paymentMethodImage = availablePaymentMethods[paymentMethodKey].image;
    availablePaymentMethodsElements.push(/*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('img', {
      src: window.powerBoardWidgetSettings.pluginUrlPrefix + `assets/images/payment-methods/${paymentMethodImage}`,
      alt: `Available payment method ${paymentMethodNiceName}`,
      id: `power-board-payment-method-${paymentMethodKey}`,
      title: paymentMethodNiceName,
      className: 'payment-method'
    }));
  }
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    className: 'powerboard-payment-methods-wrapper'
  }, ...availablePaymentMethodsElements);
};

/**
 * Creates the payment modal element
 *
 * @returns {ReactElement} Modal element
 */
const createModal = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'powerboard-payment-modal',
    className: 'powerboard-modal',
    style: {
      display: 'none'
    }
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    className: 'powerboard-modal-content'
  }, createModalHeader(), createModalBody()));
};

/**
 * Creates the modal header
 *
 * @returns {ReactElement} Modal header element
 */
const createModalHeader = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    className: 'powerboard-modal-header'
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('h3', null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Complete Your Payment', _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)), /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('span', {
    className: 'powerboard-modal-close'
  }, '×'));
};

/**
 * Creates the modal body
 *
 * @returns {ReactElement} Modal body element
 */
const createModalBody = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    className: 'powerboard-modal-body'
  }, createModalLoading(), createModalWidget(), createModalError());
};

/**
 * Creates the modal loading indicator
 *
 * @returns {ReactElement} Loading element
 */
const createModalLoading = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'powerboard-modal-loading'
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('p', {
    className: 'loading-text'
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Initializing payment...', _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)));
};

/**
 * Creates the modal widget container
 *
 * @returns {ReactElement} Widget container element
 */
const createModalWidget = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'powerboard-modal-widget-wrapper'
  }, '<!-- PowerBoard widget will be initialized here -->');
};

/**
 * Creates the modal error container
 *
 * @returns {ReactElement} Error container element
 */
const createModalError = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'powerboard-modal-error',
    style: {
      display: 'none'
    }
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('p', {
    className: 'power-board-validation-error'
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Something went wrong, please try again.', _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)));
};

/**
 * Creates the payment info section
 *
 * @returns {ReactElement} Payment info element
 */
const createPaymentInfo = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'powerboard-payment-info'
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('p', null, paymentInfoText));
};

/**
 * Creates the loading indicator
 *
 * @returns {ReactElement} Loading indicator element
 */
const createLoadingIndicator = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('div', {
    id: 'loading',
    style: {
      display: 'none'
    }
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('p', {
    className: 'loading-text'
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loading...', _constants_js__WEBPACK_IMPORTED_MODULE_3__.TEXT_DOMAIN)));
};

/**
 * Creates the hidden payment field for storing payment data
 *
 * @returns {ReactElement} Hidden input element
 */
const createHiddenPaymentField = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)('input', {
    type: 'hidden',
    id: 'paymentSourceToken'
  });
};

/***/ }),

/***/ "./resources/js/frontend/BlockValidationService.js":
/*!*********************************************************!*\
  !*** ./resources/js/frontend/BlockValidationService.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockValidationService: () => (/* binding */ BlockValidationService)
/* harmony export */ });
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/**
 * BlockValidationService
 * Handles form validation logic for PowerBoard block checkout
 */


class BlockValidationService {
  constructor(cart) {
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
    if (!addressData) {
      return [];
    }
    const missingFields = [];
    const requiredFields = [_constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.EMAIL_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.FIRST_NAME_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.LAST_NAME_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.ADDRESS_1_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.CITY_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.STATE_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.COUNTRY_INPUT, _constants_js__WEBPACK_IMPORTED_MODULE_0__.SELECTORS.POSTCODE_INPUT];

    // Check all required fields are present and not empty
    for (const field of requiredFields) {
      const fieldValue = addressData[field];
      if (!fieldValue || fieldValue.trim() === '') {
        const niceName = _constants_js__WEBPACK_IMPORTED_MODULE_0__.GET_FIELD_NICE_NAME[field];
        missingFields.push(niceName);
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
    if (invalidRequiredFields.length > 0) {
      result.isValid = false;
      // eslint-disable-next-line max-len
      result.error = `${_constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.POWERBOARD_REQUIRED_FIELDS} ${window.formatList(invalidRequiredFields)}`;
    }
    return result;
  }
}

/***/ }),

/***/ "./resources/js/frontend/BlockWidgetManager.js":
/*!*****************************************************!*\
  !*** ./resources/js/frontend/BlockWidgetManager.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlockWidgetManager: () => (/* binding */ BlockWidgetManager)
/* harmony export */ });
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/**
 * BlockWidgetManager
 * Handles PowerBoard widget initialization, lifecycle, and event management
 */



class BlockWidgetManager {
  constructor(modalManager, dataService, settings) {
    this.modalManager = modalManager;
    this.dataService = dataService;
    this.settings = settings;
    this.jQuery = window.jQuery;

    // Promise resolver for payment completion
    this.paymentPromiseResolve = null;
  }

  /**
   * Sets the payment promise resolver
   *
   * @param {Function} resolver - Promise resolver function
   */
  setPaymentPromiseResolver(resolver) {
    this.paymentPromiseResolve = resolver;
  }

  /**
   * Initializes the PowerBoard widget in the modal
   *
   * @returns {Promise<void>}
   */
  async initializeMasterWidget() {
    try {
      const response = await this.dataService.createChargeIntent();
      this.initializeWidget(response);
    } catch (error) {
      this.modalManager.showError(error.message);

      // Handle promise resolve failure
      this.handlePromiseResolveFailure((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.WIDGET_FAILURE, _constants_js__WEBPACK_IMPORTED_MODULE_0__.TEXT_DOMAIN));
    }
  }

  /**
   * Initializes the PowerBoard widget with response data
   *
   * @param {Object} response - API response with widget data
   */
  initializeWidget(response) {
    this.modalManager.showWidget();
    window.widgetPowerBoard = new window.cba.Checkout('#powerboard-modal-widget-wrapper', response.data.token);
    window.widgetPowerBoard.setEnv(this.settings.environment);
    this.setupWidgetEventHandlers();
  }

  /**
   * Sets up PowerBoard widget event handlers
   */
  setupWidgetEventHandlers() {
    // Handle payment success
    window.widgetPowerBoard.onPaymentSuccessful(async data => {
      let paymentSuccessResponse = await this.dataService.onPaymentSuccessful(data);
      if (this.paymentPromiseResolve) {
        this.paymentPromiseResolve({
          success: true,
          redirectUrl: data.success_url ?? null,
          payment_data: data
        });
        this.paymentPromiseResolve = null;
      } else {
        if (paymentSuccessResponse.data.success_url) {
          window.location.href = paymentSuccessResponse.data.success_url;
        }
      }
      this.modalManager.close();
    });

    // Handle payment expiry
    window.widgetPowerBoard.onPaymentExpired(data => {
      this.dataService.onPaymentExpired(data);
      this.handlePromiseResolveFailure((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.PAYMENT_EXPIRED, _constants_js__WEBPACK_IMPORTED_MODULE_0__.TEXT_DOMAIN));
      this.modalManager.close();
    });

    // Handle payment failure
    window.widgetPowerBoard.onPaymentFailure(data => {
      this.dataService.onPaymentFailure(data);
      this.handlePromiseResolveFailure((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)(_constants_js__WEBPACK_IMPORTED_MODULE_0__.ERROR_MESSAGES.PAYMENT_FAILED, _constants_js__WEBPACK_IMPORTED_MODULE_0__.TEXT_DOMAIN));
      this.modalManager.close();
    });
  }

  /**
   * Handle promise resolve failure
   *
   * @param {string} message - Error message
   */
  handlePromiseResolveFailure(message) {
    if (this.paymentPromiseResolve) {
      this.paymentPromiseResolve({
        success: false,
        message: message
      });
      this.paymentPromiseResolve = null;
    }
  }

  /**
   * Clean up widget resources
   */
  cleanup() {
    if (window.widgetPowerBoard) {
      window.widgetPowerBoard = null;
    }
    this.paymentPromiseResolve = null;
  }
}

/***/ }),

/***/ "./resources/js/frontend/constants.js":
/*!********************************************!*\
  !*** ./resources/js/frontend/constants.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AJAX_ENDPOINTS: () => (/* binding */ AJAX_ENDPOINTS),
/* harmony export */   ERROR_MESSAGES: () => (/* binding */ ERROR_MESSAGES),
/* harmony export */   GET_FIELD_NICE_NAME: () => (/* binding */ GET_FIELD_NICE_NAME),
/* harmony export */   MODAL_AUTO_CLOSE_DELAY: () => (/* binding */ MODAL_AUTO_CLOSE_DELAY),
/* harmony export */   MODAL_IDS: () => (/* binding */ MODAL_IDS),
/* harmony export */   MODAL_REDIRECT: () => (/* binding */ MODAL_REDIRECT),
/* harmony export */   PAYMENT_METHOD: () => (/* binding */ PAYMENT_METHOD),
/* harmony export */   SELECTORS: () => (/* binding */ SELECTORS),
/* harmony export */   TEXT_DOMAIN: () => (/* binding */ TEXT_DOMAIN)
/* harmony export */ });
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Shared constants for PowerBoard Block Checkout
 */




const MODAL_IDS = {
  MODAL: 'powerboard-payment-modal',
  LOADING: 'powerboard-modal-loading',
  WIDGET: 'powerboard-modal-widget-wrapper',
  ERROR: 'powerboard-modal-error'
};
const SELECTORS = {
  MODAL_CLOSE: '.powerboard-modal-close',
  FORM: '.wc-block-components-form',
  EMAIL_INPUT: 'email',
  FIRST_NAME_INPUT: 'first_name',
  LAST_NAME_INPUT: 'last_name',
  ADDRESS_1_INPUT: 'address_1',
  CITY_INPUT: 'city',
  STATE_INPUT: 'state',
  COUNTRY_INPUT: 'country',
  POSTCODE_INPUT: 'postcode'
};
const GET_FIELD_NICE_NAME = {
  [SELECTORS.EMAIL_INPUT]: 'Email',
  [SELECTORS.FIRST_NAME_INPUT]: 'First name',
  [SELECTORS.LAST_NAME_INPUT]: 'Last name',
  [SELECTORS.ADDRESS_1_INPUT]: 'Street address',
  [SELECTORS.CITY_INPUT]: 'City',
  [SELECTORS.STATE_INPUT]: 'State',
  [SELECTORS.COUNTRY_INPUT]: 'Country',
  [SELECTORS.POSTCODE_INPUT]: 'Postcode'
};
const AJAX_ENDPOINTS = {
  CHECKOUT: '/?wc-ajax=checkout',
  CREATE_INTENT: '/?wc-ajax=power-board-create-charge-intent',
  PAYMENT_SUCCESS: '/?wc-ajax=power-board-payment-successful',
  PAYMENT_FAILURE: '/?wc-ajax=power-board-payment-failure',
  PAYMENT_EXPIRED: '/?wc-ajax=power-board-payment-expired',
  PAYMENT_CANCELLED: '/?wc-ajax=power-board-payment-cancelled',
  ORDER_SUCCESS: '/?wc-ajax=power-board-process-successful-order'
};
const PAYMENT_METHOD = 'power_board';

// Get dynamic gateway title from settings
const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('power_board_data', {});
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('PowerBoard', 'power-board');
const gatewayTitle = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(settings.title) || defaultLabel;
const ERROR_MESSAGES = {
  MODAL_LOAD_FAILED: 'Failed to show payment modal',
  CHECKOUT_VALIDATION: 'An unexpected error occurred during checkout validation.',
  PAYMENT_SETUP: 'An unexpected error occurred during payment setup.',
  WIDGET_FAILURE: 'An unexpected error occurred while rendering payment options.',
  PAYMENT_FAILED: 'Payment processing failed. Please try again.',
  PAYMENT_EXPIRED: 'Your payment session has expired. Please retry your payment',
  PAYMENT_CANCELLED_PROCESSING_FAILED: 'Payment cancelled processing failed',
  SOMETHING_WRONG: 'Something went wrong, please try again.',
  POWERBOARD_REQUIRED_FIELDS: `To complete your checkout with ${gatewayTitle}, ` + 'please provide the following required fields:',
  USER_CANCELLED: 'Payment window closed by user',
  MODAL_CLOSE_CONFIRMATION_MESSAGE: 'If you’ve submitted your payment, ' + 'please wait for a confirmation message before closing the window. ' + 'Do you still wish to close?'
};
const TEXT_DOMAIN = 'power-board';
const MODAL_AUTO_CLOSE_DELAY = 2000;
const MODAL_REDIRECT = 'modal';

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/block-data":
/*!**************************************!*\
  !*** external ["wc","wcBlocksData"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksData"];

/***/ }),

/***/ "@woocommerce/blocks-registry":
/*!******************************************!*\
  !*** external ["wc","wcBlocksRegistry"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksRegistry"];

/***/ }),

/***/ "@woocommerce/settings":
/*!************************************!*\
  !*** external ["wc","wcSettings"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcSettings"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!****************************************!*\
  !*** ./resources/js/frontend/index.js ***!
  \****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PowerBoardContent: () => (/* binding */ PowerBoardContent),
/* harmony export */   PowerBoardPaymentMethod: () => (/* binding */ PowerBoardPaymentMethod),
/* harmony export */   createPaymentMethodLabel: () => (/* binding */ createPaymentMethodLabel)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _BlockPaymentComponent_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./BlockPaymentComponent.js */ "./resources/js/frontend/BlockPaymentComponent.js");
/* harmony import */ var _constants_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./constants.js */ "./resources/js/frontend/constants.js");
/**
 * PowerBoard Block Checkout - Main Entry Point
 * Registers the PowerBoard payment method for WooCommerce blocks
 */











// Initialize WooCommerce store and cart selectors
const store = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.select)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__.CHECKOUT_STORE_KEY);
const cart = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.select)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__.CART_STORE_KEY);
const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__.getSetting)('power_board_data', {});
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('PowerBoard', _constants_js__WEBPACK_IMPORTED_MODULE_8__.TEXT_DOMAIN);
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__.decodeEntities)(settings.title) || defaultLabel;

/**
 * Creates the PowerBoard payment method label with logo
 *
 * @returns {ReactElement} Payment method label component
 */
const createPaymentMethodLabel = () => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)(() => /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)('div', {
    className: 'power-board-payment-method-label-wrapper'
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)('div', {
    className: 'power-board-payment-method-label'
  }, /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)('span', {
    className: 'power-board-payment-method-label-text'
  }, label), /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)('img', {
    src: window.powerBoardWidgetSettings.pluginUrlPrefix + 'assets/images/logo.svg',
    alt: label,
    className: 'power-board-payment-method-label-logo'
  }))));
};

/**
 * PowerBoard content component - rendered in the checkout block when PowerBoard is selected
 */
const PowerBoardContent = props => {
  return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)(_BlockPaymentComponent_js__WEBPACK_IMPORTED_MODULE_7__.BlockPaymentComponent, {
    ...props,
    store,
    cart,
    settings
  });
};

/**
 * PowerBoard payment method configuration object
 */
const PowerBoardPaymentMethod = {
  name: 'power_board',
  label: createPaymentMethodLabel(),
  content: /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)(PowerBoardContent),
  edit: /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_4__.createElement)(PowerBoardContent),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports
  }
};

// Register the payment method with WooCommerce Blocks
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_1__.registerPaymentMethod)(PowerBoardPaymentMethod);

// Export for testing purposes

/******/ })()
;
//# sourceMappingURL=blocks.js.map