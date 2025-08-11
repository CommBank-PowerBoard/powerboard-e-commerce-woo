=== PowerBoard for WooCommerce ===

Contributors: PowerBoard
https://www.commbank.com.au/
Tags: powerboard, woocommerce, payment, gateways, payment gateways
Requires PHP: 7.4
Requires at least: 6.6
Tested up to: 6.8
Stable tag: 1.4.0
License: GPL-3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The Commonwealth Bank PowerBoard payment plugin for WooCommerce.

== Description ==

PowerBoard is a payment orchestration solution that delivers payment choice, operational efficiency and security for your business. You will be able to access connected services such as card payments, digital wallets, alternative payment methods and value-added services.
With a few quick configuration steps, this plugin provides you with access to PowerBoard.

== Installation ==

Please note that WooCommerce must be installed and active before this plugin can be used.
Prerequisites to install and configure the PowerBoard plugin, you will need a WordPress instance running:

* WooCommerce version: 9.9.3
* [WooCommerce Server Requirements](https://woocommerce.com/document/server-requirements/)
* [WooCommerce PHP and WordPress Support](https://woocommerce.com/document/update-php-wordpress/)

Note: Encryption of admin values within the plugin requires Sodium or as a fallback OpenSSL to be enabled.

### Step-by-Step Installation

1. **Download the Plugin**
   - Zip files available from our releases section [here](https://github.com/CommBank-PowerBoard/powerboard-e-commerce-woo/releases/latest)

2. **Install the Plugin**
   - Go to WordPress -> Plugins -> Add new Plugin -> Upload Plugin

3. **Upload the zip file and activate the plugin**

## Third Party API and libraries

To configure your payment flows, please navigate to the PowerBoard UI and configure the templates for the Checkout feature. For more information, see:
https://developer.powerboard.commbank.com.au/reference/checkout

### Terms of Use and Privacy Policy

You can find all relevant information here:

- [Power Board Web site](https://www.commbank.com.au/business/payments/take-online-payments/powerboard.html)
- [PowerBoard Terms and Conditions to supplement the Merchant Agreement](https://www.commbank.com.au/content/dam/commbank-assets/business/merchants/2022-09/powerboard-terms-and-conditions-july-2022.pdf)
- [Group Privacy Statement](https://www.commbank.com.au/support/privacy.html?ei=CB-footer_privacy)
- [Important documents](https://www.commbank.com.au/important-info.html?ei=CB-footer_ImportantDocs)
- [Cookies policy](https://www.commbank.com.au/important-info/cookies.html?ei=CB-footer_cookies)

This plugin transmits the payment and order information that the user provides on the checkout page to Power Board only
when making a payment using one of the methods provided by this plugin.
The following data is transferred for payment:
* All payment details
* Delivery data (if delivery is included in the price and paid for when placing the order)
* Product details

## Source

This plugin contains compile and non compile js code, if you need customize something. Code that need compile for working with woocommerce block in `/resource` path.
In root dir you can find `webpack.config.js` file, its default config for compile front-end js, but you can use it as a starting point to create your own configuration.
Also, we use helper code that not need compile what contained in assets path.

== Screenshots ==

1. Frontend
2. Admin side settings

== Changelog ==

= 1.5.0 =

* Compatibility

  - Compatible with WooCommerce version `9.9.3`.

* Added

  - Enhanced logging: logs now capture environment details, masked access token validity, checkout version, configuration, and customization template IDs during load and save operations.
  - Email validation using a specific regex to prevent the checkout widget from loading with invalid email addresses.

* Bug fixes

  - Issue where applying a coupon after a failed payment caused the transaction to be approved but then refunded (resolved via improved session and cookie handling).
  - Duplicate logging on save caused by page reloads.
  - Email format validation in the classic checkout.
  - Cookie-related bug that occasionally prevented the PowerBoard widget from loading after AJAX-based checkout updates (e.g., changing the shipping method, applying coupons, or modifying the address).
  - Removed unused language files.

= 1.4.0 =

* Compatibility

  - Compatible with WooCommerce version `9.8.1`.

* Added

  - Added payment method used for order payment to meta on an order.
  - Added support for default WooCommerce terms and conditions checkbox on classic checkout.
  - Added support for hidden checkout fields on classic checkout.
  - Added improvements to admin settings panel.
  - Added fallback for configuration file fetch.
  - Added new logs for issue identification for widget and admin operations.
  - Added payment method title to orders.

* Bug fixes

  - Fixed an issue in Classic checkout where the payment method would not load if merchant did not collect shipping address on their Checkout page.
  - Fixed an issue in Classic checkout when an order id failed to retrieve an order causing us not to navigate to order confirmation page post transaction approved.
  - Fixed an issue in Classic checkout in the sending of new order and processing state emails from WooCommerce.
  - Fixed overwriting of internal styling of button by external plugin.
  - Fixed checkout shipping form validation when toggling the "Ship to a different address?" checkbox with empty fields.
  - Fixed checkout overlay z-index to prevent switching payment methods during an active payment session.

= 1.3.1 =

* Compatibility

  - Compatible with WooCommerce version `9.8.1`.

* Bug fixes

  - Fixed an issue where the Classic checkout could sometimes process a new order with the order ID of an already processing order.

= 1.3.0 =

* Compatibility

  - Compatible with WooCommerce version `9.5.2`.

* Added

  - Added compatibility fix for "Woo Additional Terms" plugin.

= 1.2.0 =

* Compatibility

  - Compatible with WooCommerce version `9.5.2`.

* Changed

  - Moved order status update out of order creation to enable compatibility with "Preorders" external plugin.

= 1.1.0 =

* Compatibility

  - Compatible with WooCommerce version `9.5.2`.

* Added

  - Enhanced logging to provide more detailed information during the payment process.
  - Added informative message when intent request fails.

* Bug Fixes

  - Fixed issue where plugin deactivation warning not being displayed.
  - Corrected outline color for validation error on classic checkout.
  - Resolved duplicate error messages on the settings page when the Version or Configuration template was not selected.
  - Addressed issue where the widget failed to load on classic checkout when only one country was selected as the selling location.
  - Fixed issue where the refund button was still visible on an order that had already been fully refunded.
  - Removed "Place order" button when order is updated using multiple tabs.

* Technical Changes

  - Cleaned up the code by removing unused library and outdated comments.

= 1.0.0 =
Initial Release

* Compatibility

  - Compatible with WooCommerce version `9.5.2`.
