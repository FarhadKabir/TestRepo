<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adds the form fields for the payment gateway.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$files = array( 'default' => __( 'Default - No logo', 'altapay' ) );
foreach ( new DirectoryIterator( dirname( __DIR__, 1 ) . '/assets/images/payment_icons' ) as $fileInfo ) {
	if ( $fileInfo->isDot() || ! $fileInfo->isFile() ) {
		continue;
	}
	$files[ $fileInfo->getFilename() ] = $fileInfo->getFilename();
}

$formSettingsWithToken = array(
	'enabled'        => array(
		'title'   => __( 'Enable/Disable', 'woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( ' ', 'altapay' ),
		'default' => 'yes',
	),
	'title'          => array(
		'title'       => __( 'Title', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Title to show during checkout.', 'altapay' ),
		'default'     => __( 'AltaPay', 'woocommerce' ),
		'desc_tip'    => true,
	),
	'description'    => array(
		'title'       => __( '', 'woocommerce' ),
		'description' => __( 'Message to show during checkout.', 'altapay' ),
		'type'        => 'textarea',
		'default'     => '',
	),
	'payment_action' => array(
		'title'       => __( 'Payment action', 'woocommerce' ),
		'description' => __( 'Make payment authorized or authorized and captured', 'altapay' ),
		'type'        => 'select',
		'options'     => array(
			'authorize'         => __( 'Authorize Only', 'altapay' ),
			'authorize_capture' => __( 'Authorize and Capture', 'altapay' ),
		),
		'default'     => '',
	),
	'payment_icon'   => array(
		'title'       => __( 'Icon', 'woocommerce' ),
		'description' => __( 'Select image icon to display on checkout page', 'altapay' ),
		'type'        => 'select',
		'options'     => $files,
		'default'     => '',
	),
	'token_control'  => array(
		'title'   => __( 'Token Control', 'woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Customer Token Control', 'altapay' ),
		'default' => 'no',
	),
	'currency'       => array(
		'title'       => __( 'Currency', 'altapay' ),
		'type'        => 'select',
		'description' => __( 'Select the currency does this terminal work with' ),
		'options'     => get_woocommerce_currencies(),
		'default'     => $this->default_currency,
	),
);

return apply_filters( 'altapay_gateway_payments_settings_with_token_control', $formSettingsWithToken );
