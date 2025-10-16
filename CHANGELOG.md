# Changelog

## [2.1.0] - 2025-10-20

### Compatibility

- Compatible with Wordpress version `6.8.3`
- Compatible with WooCommerce version `10.2.2`.

### Added

- Plugin configuration data is now automatically removed upon uninstallation

### Fixed

- Implemented fix to prevent WooCommerce themes from overriding the colour of the Google Pay "Pay" button
- Update GooglePay mark icon used

## [2.0.0] - 2025-09-29

### Compatibility

- Compatible with Wordpress version `6.8.2`
- Compatible with WooCommerce version `9.9.3`.

### Added

- Redesigned the order flow by implementing modal popup solution for PowerBoard payments, further enhancing the checkout and payment flow
- Added support for custom title & description in the plugin settings page.

### Fixed

- Fixed an error in the plugin settings page.

## [1.5.0] - 2025-07-25

### Compatibility

- Compatible with WooCommerce version `9.9.3`.

### Added

- Enhanced logging: logs now capture environment details, masked access token validity, checkout version, configuration, and customization template IDs during load and save operations.
- Email validation using a specific regex to prevent the checkout widget from loading with invalid email addresses.
- Validation for terms and conditions checkbox using block checkout.
- Refreshing of Checkout page for shipping changes in different tabs in current window on Cart or Checkout pages.

### Fixed

- Issue where applying a coupon after a failed payment caused the transaction to be approved but then refunded (resolved via improved session and cookie handling).
- Duplicate logging on save caused by page reloads.
- Email format validation in the classic checkout.
- Cookie-related bug that occasionally prevented the PowerBoard widget from loading after AJAX-based checkout updates (e.g., changing the shipping method, applying coupons, or modifying the address).
- Removed unused language files.

## [1.4.0] - 2025-06-02

### Compatibility

- Compatible with WooCommerce version `9.8.1`.

### Added

- Added payment method used for order payment to meta on an order.
- Added support for default WooCommerce terms and conditions checkbox on classic checkout.
- Added support for hidden checkout fields on classic checkout.
- Added improvements to admin settings panel.
- Added fallback for configuration file fetch.
- Added new logs for issue identification for widget and admin operations.
- Added payment method title to orders.

### Fixed

- Fixed an issue in Classic checkout where the payment method would not load if merchant did not collect shipping address on their Checkout page.
- Fixed an issue in Classic checkout when an order id failed to retrieve an order causing us not to navigate to order confirmation page post transaction approved.
- Fixed an issue in Classic checkout in the sending of new order and processing state emails from WooCommerce.
- Fixed overwriting of internal styling of button by external plugin.
- Fixed checkout shipping form validation when toggling the "Ship to a different address?" checkbox with empty fields.
- Fixed checkout overlay z-index to prevent switching payment methods during an active payment session.

## [1.3.1] - 2025-04-16

### Compatibility

- Compatible with WooCommerce version `9.8.1`.

### Fixed

- Fixed an issue where the Classic checkout could sometimes process a new order with the order ID of an already processing order.

## [1.3.0] - 2025-04-15

### Compatibility

- Compatible with WooCommerce version `9.5.2`.

### Added

- Added compatibility fix for "Woo Additional Terms" plugin.

## [1.2.0] - 2025-04-11

### Compatibility

- Compatible with WooCommerce version `9.5.2`.

### Changed

- Moved order status update out of order creation to enable compatibility with "Preorders" external plugin.

## [1.1.0] - 2025-03-28

### Compatibility

- Compatible with WooCommerce version `9.5.2`.

### Added

- Enhanced logging to provide more detailed information during the payment process.
- Added informative message when intent request fails.

### Bug Fixes

- Fixed issue where plugin deactivation warning not being displayed.
- Corrected outline color for validation error on classic checkout.
- Resolved duplicate error messages on the settings page when the Version or Configuration template was not selected.
- Addressed issue where the widget failed to load on classic checkout when only one country was selected as the selling location.
- Fixed issue where the refund button was still visible on an order that had already been fully refunded.
- Removed "Place order" button when order is updated using multiple tabs.

### Technical Changes

- Cleaned up the code by removing unused library and outdated comments.

## [1.0.0] - 2025-03-13

Initial Release

### Compatibility

- Compatible with WooCommerce version `9.5.2`.
