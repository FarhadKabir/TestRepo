<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Helpers\Traits;

use AltapayMerchantAPI;
use WC_Order;
use WP_Error;

trait AltapayMaster {

	/**
	 * @var bool
	 */
	private $api = false;

	/**
	 * Initialization of Woocommerce Payment module specifics
	 *
	 * @return void
	 */
	public function intFormFields() {
		// Define form setting fields
		$this->form_fields = array(
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
				'title'       => __( 'Message', 'woocommerce' ),
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
			'currency'       => array(
				'title'       => __( 'Currency', 'altapay' ),
				'type'        => 'select',
				'description' => __( 'Select the currency does this terminal work with' ),
				'options'     => get_woocommerce_currencies(),
				'default'     => $this->default_currency,
			),
		);
	}

	/**
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		// Return goto payment url
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * @return bool
	 */
	public function isAvailable() {
		 // Check if payment method is enabled
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return get_woocommerce_currency() == $this->currency;
	}

	/**
	 * Tackle scenario for scheduled subscriptions
	 *
	 * @param float    $amount
	 * @param WC_Order $renewal_order
	 * @return void
	 */
	public function scheduledSubscriptionsPayment( $amount, $renewal_order ) {
		try {
			if ( $amount == 0 ) {
				$renewal_order->payment_complete();
				return;
			}

			if ( wcs_order_contains_renewal( $renewal_order->id ) ) {
				$parent_order_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $renewal_order->id );
			}

			$orderinfo      = new WC_Order( $parent_order_id );
			$transaction_id = $orderinfo->get_transaction_id();

			if ( ! $transaction_id ) {
				// Set subscription payment as failure
				$renewal_order->update_status( 'failed', __( 'AltaPay could not locate transaction ID', 'altapay' ) );
				return;
			}

			$api = $this->apiLogin();
			if ( $api instanceof WP_Error ) {
				$_SESSION['altapay_login_error'] = $api->get_error_message();
				echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
				return;
			}
			$result = $api->chargeSubscription( $transaction_id, $amount );

			if ( $result->wasSuccessful() ) {
				$renewal_order->payment_complete();
			} else {
				$renewal_order->update_status(
					'failed',
					sprintf( __( 'AltaPay payment declined: %s', 'altapay' ), $result->getErrorMessage() )
				);
			}
		} catch ( Exception $e ) {
			$renewal_order->update_status(
				'failed',
				sprintf( __( 'AltaPay payment declined: %s', 'altapay' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Method for AltaPay api login using credentials provided in AltaPay settings page
	 *
	 * @return bool|AltapayMerchantAPI|WP_Error
	 * @throws Exception
	 */
	public function apiLogin() {
		if ( $this->api === false ) {
			$this->api = new AltapayMerchantAPI(
				esc_attr( get_option( 'altapay_gateway_url' ) ),
				esc_attr( get_option( 'altapay_username' ) ),
				esc_attr( get_option( 'altapay_password' ) ),
				null
			);
			try {
				$this->api->login();
			} catch ( Exception $e ) {
				return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
			}
		}

		return $this->api;
	}

	/**
	 * @param int $number
	 * @return string
	 */
	public function altapayGetCurrencyCode( $number ) {
		 $codes = array(
			 '004' => 'AFA',
			 '012' => 'DZD',
			 '020' => 'ADP',
			 '031' => 'AZM',
			 '032' => 'ARS',
			 '036' => 'AUD',
			 '044' => 'BSD',
			 '048' => 'BHD',
			 '050' => 'BDT',
			 '051' => 'AMD',
			 '052' => 'BBD',
			 '060' => 'BMD',
			 '064' => 'BTN',
			 '068' => 'BOB',
			 '072' => 'BWP',
			 '084' => 'BZD',
			 '096' => 'BND',
			 '100' => 'BGL',
			 '108' => 'BIF',
			 '124' => 'CAD',
			 '132' => 'CVE',
			 '152' => 'CLP',
			 '156' => 'CNY',
			 '170' => 'COP',
			 '188' => 'CRC',
			 '191' => 'HRK',
			 '192' => 'CUP',
			 '196' => 'CYP',
			 '203' => 'CZK',
			 '208' => 'DKK',
			 '214' => 'DOP',
			 '218' => 'ECS',
			 '230' => 'ETB',
			 '232' => 'ERN',
			 '233' => 'EEK',
			 '238' => 'FKP',
			 '242' => 'FJD',
			 '262' => 'DJF',
			 '270' => 'GMD',
			 '288' => 'GHC',
			 '292' => 'GIP',
			 '320' => 'GTQ',
			 '324' => 'GNF',
			 '328' => 'GYD',
			 '340' => 'HNL',
			 '344' => 'HKD',
			 '532' => 'ANG',
			 '533' => 'AWG',
			 '578' => 'NOK',
			 '624' => 'GWP',
			 '752' => 'SEK',
			 '756' => 'CHF',
			 '784' => 'AED',
			 '818' => 'EGP',
			 '826' => 'GBP',
			 '840' => 'USD',
			 '973' => 'AOA',
			 '974' => 'BYR',
			 '975' => 'BGN',
			 '976' => 'CDF',
			 '977' => 'BAM',
			 '978' => 'EUR',
			 '981' => 'GEL',
			 '983' => 'ECV',
			 '984' => 'BOV',
			 '986' => 'BRL',
			 '990' => 'CLF',
		 );
		 return $codes[ $number ];
	}
}
