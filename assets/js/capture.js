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
			// Perform capture
			capture: function (element) {
				var amount          = parseFloat( $( '#capture-amount' ).val() );
				var productsArrData = [];
				if ($( "#ap-allow-orderlines" ).attr( "checked" ) === "checked") {
					$( '.ap-orderlines-capture:has(input)' ).each(
						function () {
							var productArrData = [];
							$( 'input', this ).each(
								function () {
									if (this.getAttribute( "name" ) == "productID") {
										if ($( this ).val() == '') {
											alert( "One of the products does not have SKU defined !" );
											return;
										}
										productArrData.push(
											{
												name: 'productId',
												value: $( this ).val()
											}
										);
									} else if (this.getAttribute( "name" ) == "qty") {
										productArrData.push(
											{
												name: 'productQty',
												value: $( this ).val()
											}
										);
									}
								}
							);
							productsArrData.push( productArrData );
						}
					);
				}
				var data = {
					'action': 'altapay_capture',
					'order_id': Globals.postId,
					'amount': amount,
					'orderLines': productsArrData
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(
					ajaxurl,
					data,
					function (response) {
						var result = response.data;
						if (response.success === true) {
							jQuery( '#altapay-actions .inside .capture-status' ).html( '<b>Payment captured.</b>' )
							jQuery( '.payment-reserved' ).text( result.reserved );
							jQuery( '.payment-captured' ).text( result.captured );
							jQuery( '.payment-refunded' ).text( result.refunded );
							jQuery( '.payment-chargeable' ).text( result.chargeable );
							jQuery( 'ul.order_notes' ).prepend( result.note );
							location.reload();
						} else {
							jQuery( '#altapay-actions .inside .capture-status' ).html( '<b>Capture failed: ' + result.error + '</b>' );
						}
						jQuery( '.loader' ).css( 'display', 'none' );
					}
				);
			},
			// Recalculation of the amount for capture depending on selected order lines
			recalculateAmount: function (element) {
				var sum = 0.0000;
				$.each(
					$( 'tr.ap-orderlines-capture' ),
					function (key, value) {
						var ordered   = parseInt( $( '.ap-orderline-capture-max-quantity', value ).text() );
						var price     = $( this ).closest( "tr" ).find( "span.totalprice-capture" );
						var unitprice = price.eq( 0 ).text();
						var quantity  = parseInt( $( '.ap-order-capture-modify', value ).val() );
						// Covering the case when there is no value in the quantity field
						unitprice = (unitprice.substring( 3, unitprice.length )).replace( ",", "" );

						if (isNaN( quantity )) {
							quantity = 0;
						}
						if ((quantity > ordered) || quantity < 0) {
							alert( "Quantity cannot be negative or more than ordered!", 'Altapay' );
							if (quantity > ordered) {
								quantity = ordered;
							} else if (quantity < 0) {
								quantity = 0;
							}
							$( '.ap-order-capture-modify', value ).val( quantity );
						}
						console.log( 'sum is :', sum );
						console.log( 'unit is :', unitprice );
						console.log( 'ordered is :', ordered );
						console.log( 'quantity is :', quantity );
						sum = parseFloat( ((sum + ((unitprice / ordered) * quantity))).toFixed( 4 ) );

					}
				);
				$( '#capture-amount' ).val( sum );
			},
		};
		// Handle the actions
		jQuery( '#altapay_capture' ).on(
			'click',
			function (e) {
				e.preventDefault();
				var amount = parseFloat( $( '#capture-amount' ).val() );
				if (isNaN( amount )) {
					alert( 'The amount cannot be empty or text!' );
					return;
				}
				if (confirm( 'Are you sure you want to capture ' + amount.toFixed( 2 ) + ' ?' )) {
					altapay.capture( this );
					return;
				} else {
					return false;
				}
			}
		);
		// Recalculate the full amount when quantity is changing
		$( '.ap-order-capture-modify' ).click(
			function (e) {
				e.preventDefault();
				var element = this;
				return altapay.recalculateAmount( element );
			}
		);
	}
);
