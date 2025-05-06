# Changelog

## [1.4.0] - 2025-05-01

### Compatibility

- Compatible with WooCommerce version `9.8.1`.

### Added

- Added payment method used for order payment to meta on an order.

### Fixed

- Fixed an issue in Classic checkout where the payment method would not load if merchant did not collect shipping address on their Checkout page.

## [1.3.1] - 2025-04-16

### Compatibility

- Compatible with WooCommerce version `9.5.2`.

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
