===  Altapay for WooCommerce ===
Contributors: altapay_integrations
Tags: Altapay, Gateway, Payments, WooCommerce, Payment Card Industry
Requires PHP: 5.5
Requires at least: 4.5.3
Tested up to: 5.5.1
Stable tag: 3.1.1
License: MIT
WC requires at least: 3.0.0
WC tested up to: 4.5.2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin that integrates your WooCommerce web shop to the Valitor payments gateway.

== Description ==

Altapay has made it much easier for you as merchant/developer to receive secure payments in your WooCommerce web shop.
Altapay is fully integrated with WooCommerce via a plug-in. All you have to do is to install the plug-in, which will only take a few minutes to complete.

== Installation ==

The whole installation and configuration process is described in our [integration manual](https://www.valitor.com/wp-content/uploads/2019/01/WooCommerce-Integration-Manual.pdf).

== Screenshots ==

1. Plugin configuration for gateway access
2. Payment terminal configuration

== Support ==

Feel free to contact our support team (support@altapay.com) if you need any assistance during the installation process or have questions regarding specific payment methods and functionalities.

== About Altapay ==


AltaPay supports major acquiring banks, global payment methods and over 50 preferred local schemes like Dankort in Denmark, Vipps and Bank Axept in Norway, Swish in Sweden etc., across multiple sales channels (in-store and terminals & eCommerce), geographies and currencies. This includes credit and debit card acquiring, bank transfer networks, direct debit, wallets, mobile payment types, online invoicing, prepaid and gift card networks. With offices in Denmark, Altapay serves Pan European and Global customers including JD Sports, Sports Direct, Paul Smith, Laura Ashley, DFDS Seaways, ZARA, ECCO and Stokke.
Altapay's Payment Gateway for WooCommerce provides merchants with access to a full set of business-ready international payment and accounting functionality. With this extension, merchants are able to receive payments through Visa, Mastercard, Dankort, iDeal, PayPal, MobilePay, Klarna and ViaBill. To use the extension an account for Altapay's payment gateway is needed. Once the account is set, the merchant receives API credentials which will link the extension to the payment gateway.

== Changelog ==

= 3.1.1 =
* Added fix for payment page CSS

= 3.1.0 =
* Rebranding from Valitor to Altapay
* Added payment methods logo selection functionality
* Support provided for Wordpress version 5.5
* Support provided for Woocommerce version 4.3.2

= 3.0.1 =
* Fix - saved credit card deletion

= 3.0.0 =
* Added plugin disclaimer
* Added support for WooCommerce version 3.9.2 and Wordpress version 5.3.2
* Added support for auto-fill credit card details when using credit card token
* Major refactoring for improving the source code quality
* Added support for Klarna Payments (Klarna reintegration)
* Added release payment functionality, by:
	** using release payment button from the actions panel
	** changing order status to canceled state
* Added design improvements: settings page and action panel
* Refactored payment form template to render appropriate order information

= 2.5.0 =
* Added support for:
    ** multiple tax rates with compound configurations
    ** multiple coupon discounts for variable products
* Source code refactoring according to PSR-2

= 2.4.0 =
* Added support for bundle products
* Improved the partial captures on orderlines

= 2.3.0 =
* Added support for various coupon types and variation products
* Improvements when dealing with tax included/excluded amounts
* Fix - failed partial captures and refunds when Klarna used

= 2.2.0 =
* Compatibility with the latest WooCommerce version 3.7.0
* Added unit tests
* Improved error handling
* Fix: - tax calculation and price rules getting wrong amounts in certain situations

= 2.1.1 =
* Fix - unit price not fetched correctly when price including taxes

= 2.1.0 =
* Added support for coupons
* Cart rules are parsed as a separate order line to the payment gateway
* Fix - unit price without taxes, regardless the setting from the backend

= 2.0.0 =
* Strengthen solution for the virtual products in relation to the shipping information
* Fix - error when fetching the plugin information
* Fix - error log spammed with error messages due to the wrong autoloader implementation

= 1.9.0 =
* SDK rebranding from Altapay to Valitor
* Added support for WooCommerce 3.6.3 and WordPress 5.2.0

= 1.8.0 =
* Platform and plugin versioning information sent to the payment gateway

= 1.7.2 =
* Fix - Error message shown if create payment call fails
* Fix - Payment gateway password with special characters parsed correctly

= 1.7.1 =
* Fix - Small cosmetic fixes after rebranding

= 1.7.0 =
* Rebranding from Altapay to Valitor
* Update the Wordpress and WooCommerce supported versions
* Fix - extension update

= 1.6.3 =
* Fix - Rename the PHP SDK package and update the references

= 1.6.2 =
* Improvements - Refund operation updates the stock with the refunded products, if order lines are sent

= 1.6.1 =
* Add new tags for WooCommerce required version and tested up to
* Fix - compatibility with WooCommerce up to 3.3.3
* Improvements - PHP SDK

= 1.6.0 =
* PHP SDK update.

= 1.5.1 =
* Fix - Capture and Release buttons.
* Perform tests with latest WordPress version.

= 1.5.0 =
* Include Valitor PHP SDK through Composer.
* Upgrade the build package script.

= 1.4.0 =
* Show cart info in the payment page.

= 1.3.4 =
* Fix - connection to the payment gateway.

= 1.3.3 =
* Fix - Valitor terminals are not visible if connection to the API is not established.

= 1.3.2 =
* Fix - JavaScript code.

= 1.3.1 =
* Improve the refund section.
* Fix - captured amount shown in the view.
* Fix - no value in the quantity input field from the order lines.

= 1.3.0 =
* Add order lines for partial capture/refund.
* Add the sales_tax value, calculated for partial capture.
* Add refund functionality in the same code block as capture.
* Add shipping details as part of the order lines; hence, the shipping can be refunded.

= 1.2.14 =
* Fix - sales_tax parameter not sent to the payment gateway.

= 1.2.13 =
* Fix - regarding languages.

= 1.2.12 =
* Fix - regarding refunds.

= 1.2.11 =
* Correction for compatibility with WooCommerce 3.0.
    ** Upgrade Notice - [Review update best practices](https://docs.woocommerce.com/document/how-to-update-your-site) before upgrading.

= 1.2.10 =
* Orders are captured when their statuses are changed to Completed.

= 1.2.9 =
* Correction in templates loading.

= 1.2.8 =
* Add order lines to partial refunds.

= 1.2.7 =
* Several fixes.

= 1.2.6 =
* Security improvements.

= 1.2.5 =
* Add support for alternative payment methods.

= 1.2.1 =
* First stable version.
