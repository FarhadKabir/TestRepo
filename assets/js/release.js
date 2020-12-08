/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

jQuery( document ).ready(
	function ($) {
		// Util
		var altapay = {
			// Perform release
			release: function (element) {
				var transactionID = $( '#txnID' ).val();
				var data          = {
					'action': 'altapay_release_payment',
					'order_id': Globals.postId,
					'transactionID': transactionID
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(
					ajaxurl,
					data,
					function (response) {
						result = jQuery.parseJSON( response );
						if (result.status == 'ok') {
							alert( 'Payment released' );
							location.reload();
						} else {
							jQuery( '#altapay-actions .inside .capture-status' ).html( '<b>Release failed: ' + result.message + '</b>' );
						}
						jQuery( '.loader' ).css( 'display', 'none' );
					}
				);
			},
		};
		// Handle the actions
		jQuery( '#altapay_release_payment' ).on(
			'click',
			function (e) {
				e.preventDefault();
				var amount = parseFloat( $( '#capture-amount' ).val() );
				if (isNaN( amount )) {
					alert( 'The amount cannot be empty or text!' );
					return;
				}
				if (confirm( 'Are you sure you want to release ' + amount.toFixed( 2 ) + ' ?' )) {
					altapay.release( this );
					return;
				} else {
					return false;
				}
			}
		);
	}
);
