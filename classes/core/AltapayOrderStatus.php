<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

use AltapayConnectionFailedException;
use AltapayInvalidResponseException;
use AltapayMerchantAPI;
use AltapayMerchantAPIException;
use AltapayRequestTimeoutException;
use AltapayUnauthorizedAccessException;
use AltapayUnknownMerchantAPIException;
use WP_Error;

class AltapayOrderStatus {

	/**
	 * Register required hooks
	 *
	 * @return void
	 */
	public function registerHooks() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'orderStatusChanged' ), 10, 4 );
	}

	/**
	 * Trigger when the order status is changed - Cancelled order scenario is handled
	 *
	 * @param int      $orderID
	 * @param string   $currentStatus
	 * @param string   $nextStatus
	 * @param WC_Order $order
	 *
	 * @return WP_Error
	 * @throws AltapayConnectionFailedException
	 * @throws AltapayInvalidResponseException
	 * @throws AltapayMerchantAPIException
	 * @throws AltapayRequestTimeoutException
	 * @throws AltapayUnauthorizedAccessException
	 * @throws AltapayUnknownMerchantAPIException
	 */
	public function orderStatusChanged( $orderID, $currentStatus, $nextStatus, $order ) {
		$txnID    = $order->get_transaction_id();
		$captured = 0;
		$reserved = 0;
		$refunded = 0;
		$status   = '';

		$api = new AltapayMerchantAPI(
			esc_attr( get_option( 'altapay_gateway_url' ) ),
			esc_attr( get_option( 'altapay_username' ) ),
			esc_attr( get_option( 'altapay_password' ) ),
			null
		);
		try {
			$api->login();
		} catch ( Exception $e ) {
			$_SESSION['altapay_login_error'] = $e->getMessage();

			return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
		}

		$payment  = $api->getPayment( $txnID );
		$payments = $payment->getPayments();
		foreach ( $payments as $pay ) {
			$reserved = $pay->getReservedAmount();
			$captured = $pay->getCapturedAmount();
			$refunded = $pay->getRefundedAmount();
			$status   = $pay->getCurrentStatus();

		}

		if ( $currentStatus === 'cancelled' ) {
			try {
				if ( $status === 'released' ) {
					return;
				} elseif ( $captured == 0 && $refunded == 0 ) {
					$releaseResult = $api->releaseReservation( $txnID );
					if ( $releaseResult->wasSuccessful() ) {
						update_post_meta( $orderID, '_released', true );
						$order->add_order_note( __( 'Order released: "The order has been released"', 'altapay' ) );
					}
				} elseif ( $captured == $refunded && $refunded == $reserved || $refunded == $reserved ) {
					$order->update_status( 'refunded' );
					$releaseResult = $api->releaseReservation( $txnID );
					if ( ! $releaseResult->wasSuccessful() ) {
						$order->add_order_note(
							__(
								'Release failed: ' . $releaseResult->getMerchantErrorMessage(),
								'altapay'
							)
						);
						echo wp_json_encode(
							array(
								'status'  => 'error',
								'message' => $releaseResult->getMerchantErrorMessage(),
							)
						);
					}
				} else {
					$order->update_status( 'processing' );
					$releaseResult = $api->releaseReservation( $txnID );
					if ( ! $releaseResult->wasSuccessful() ) {
						$order->add_order_note( __( 'Release failed: Order cannot be released', 'altapay' ) );
					}
				}
			} catch ( Exception $e ) {
				return WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
			}
		} elseif ( $currentStatus === 'completed' ) {
			try {
				if ( $status === 'captured' ) {
					return;
				} elseif ( $captured == 0 ) {
					$captureResult = $api->captureReservation( $txnID );
					if ( $captureResult->wasSuccessful() ) {
						update_post_meta( $orderID, '_captured', true );
						$order->add_order_note( __( 'Order captured: "The order has been fully captured"', 'altapay' ) );
					}
				}
			} catch ( Exception $e ) {
				return WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
			}
		}
	}
}

