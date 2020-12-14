<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Util;

use WC_Coupon;
use WP_Error;

class UtilMethods {

	/**
	 * Generates order lines from an order or from an order refund.
	 *
	 * @param WC_Order|WC_Order_Refund $order
	 * @param array                    $products
	 * @param bool                     $returnRefundOrderLines
	 * @return array  list of order lines
	 */
	public function createOrderLines( $order, $products = array(), $returnRefundOrderLines = false ) {
		$orderlineDetails         = array();
		$itemsToCapture           = array();
		$couponDiscountPercentage = 0; // set initial coupon discount to 0
		$taxConfiguration         = $this->getTaxConfiguration(); // get CMS tax configuration settings
		$cartItems                = $order->get_items(); // get cart items

		// If capture request is triggered
		if ( $products ) {
			foreach ( $cartItems as $key => $value ) {
				if ( in_array( $key, $products['skuList'] ) ) {
					$itemsToCapture[ $key ] = $value;
				}
			}
			$cartItems = $itemsToCapture;
		}

		// if cart is empty
		if ( ! $cartItems ) {
			return new WP_Error( 'error', __( 'There are no items in the cart ', 'altapay' ) );
		}
		// generate Orderlines product by product
		foreach ( $cartItems as $orderlineKey => $orderline ) {
			$appliedCouponItems = $order->get_items( 'coupon' ); // get items with coupon discount
			if ( $appliedCouponItems ) {
				$couponDiscountPercentage = $this->getCouponDiscount( $appliedCouponItems, $orderline );
			}
			$product     = wc_get_product( $orderline['product_id'] );
			$productType = $product->product_type;
			// get product details for each orderline
			$productDetails = $this->getProductDetails( $orderline, $taxConfiguration, $couponDiscountPercentage );
			if ( $productType === 'bundle' && $productDetails['product']['unitPrice'] == 0 ) {
				continue;
			}
			$orderlineDetails [] = $productDetails['product'];
			// check if compensation exists to bind it in orderline details
			if ( $productDetails['compensation']['unitPrice'] != 0 ) {
				$orderlineDetails [] = $productDetails['compensation'];
			}
		}
		// get the shipping Details
		$shippingDetails = reset(
			$this->getShippingDetails(
				$order,
				$products,
				$returnRefundOrderLines
			)
		);

		if ( $shippingDetails ) {
			$orderlineDetails [] = $shippingDetails;
		}

		return $orderlineDetails;
	}

	/**
	 * Returns the current tax configuration settings from WooCommerce settings
	 *
	 * @return string  list of order lines
	 */
	private function getTaxConfiguration() {
		if ( wc_prices_include_tax() ) {
			return 'taxIncluded';
		}
		return 'taxExcluded';
	}

	/**
	 * Returns the total of multiple discounts applied on each order line
	 *
	 * @param array $appliedCouponItems
	 * @param  array $orderline
	 * @return float returns the float value of percentage discounts applied using coupon codes
	 */
	public function getCouponDiscount( $appliedCouponItems, $orderline ) {
		$discountPercentageWholeCart           = 0;
		$discountPercentageOnParticularProduct = 0;
		$productsWithCoupon                    = array();

		if ( $appliedCouponItems ) {
			foreach ( $appliedCouponItems as $item_id => $item ) {
				// Retrieving the coupon ID reference
				$couponPostObj = get_page_by_title( $item->get_name(), OBJECT, 'shop_coupon' );
				$couponID      = $couponPostObj->ID;
				// Get an instance of WC_Coupon object (necessary to use WC_Coupon methods)
				$coupon         = new WC_Coupon( $couponID );
				$couponType     = $coupon->discount_type;
				$appliedCoupons = reset( $coupon );
				// Filtering with your coupon custom types
				if ( $couponType === 'percent' && empty( $appliedCoupons['product_ids'] ) ) {
					// Get the Coupon discount amounts in the order
					$orderDiscountAmount    = wc_get_order_item_meta( $item_id, 'discount_amount', true );
					$orderDiscountTaxAmount = wc_get_order_item_meta( $item_id, 'discount_amount_tax', true );
					// This calculation will assist in scenario of discount coupons on entire cart
					$totalCouponDiscountAmount = $orderDiscountAmount + $orderDiscountTaxAmount;
					// Or get the coupon amount object
					$discountPercentageWholeCart += $coupon->amount;
				} elseif ( $couponType === 'percent' && ! empty( $appliedCoupons['product_ids'] ) ) {
					$discountPercentageOnParticularProduct = $coupon->amount;
					$productsWithCoupon                    = array_values( $appliedCoupons['product_ids'] );
				}
			}
		}
		if ( in_array( $orderline['product_id'], $productsWithCoupon ) || in_array(
			$orderline['variation_id'],
			$productsWithCoupon
		) ) {
			$discountPercentage = $discountPercentageWholeCart + $discountPercentageOnParticularProduct;
		} else {
			$discountPercentage = $discountPercentageWholeCart;
		}

		return $discountPercentage;
	}

	/**
	 * Returns product Details based on product type and tax configuration settings
	 *
	 * @param object[] $orderline
	 * @param string   $taxConfiguration
	 * @param float    $couponDiscountPercentage
	 * @return array
	 */
	private function getProductDetails( $orderline, $taxConfiguration, $couponDiscountPercentage ) {
		$discountPercentage  = 0; // set discount Percent to 0 by default
		$productCartId       = $orderline->get_id(); // product Cart ID number
		$singleProduct       = wc_get_product( $orderline['product_id'] ); // Details of each product
		$productQuantity     = $orderline['qty']; // get ordered number of quantity for each orderline
		$productRegularPrice = $singleProduct->get_regular_price();
		$productSalePrice    = $singleProduct->get_sale_price();
		$unitCode            = 'unit';

		if ( $productQuantity > 1 ) {
			$unitCode = 'units';
		}

		// Get and set tax rate using orderline
		$orderlineTax = array_sum( $orderline['taxes']['total'] ) / $orderline['subtotal'];
		$taxRate      = $orderlineTax;

		// Calculate total generated from WooCommerce after calculations
		$totalCMS = number_format( $orderline['subtotal'] + $orderline['subtotal_tax'], 2, '.', '' );

		// set product ID based on the sku provided
		if ( $singleProduct->get_sku() ) {
			$productId = $productCartId . '-' . $singleProduct->get_sku();
		} else {
			$productId = $productCartId;
		}

		// get and set product details in case of variable product
		if ( $singleProduct->get_type() === 'variable' ) {
			$variablePrices      = $singleProduct->get_variation_prices(); // get all the variation prices i.e regular and sale
			$variationID         = $orderline['variation_id']; // get variation id of ordered orderline
			$productRegularPrice = $variablePrices['regular_price'][ $variationID ]; // get regular price from variation prices array
			$productSalePrice    = $variablePrices['sale_price'][ $variationID ]; // get regular price from variation prices array
		}

		// calculate discount if catalogue rule is applied on orderline i.e. product sale price is set
		if ( $singleProduct->is_on_sale() ) {
			$productDiscountAmount = $productRegularPrice - $productSalePrice; // calculate discount amount
			$discountPercentage    = number_format(
				( $productDiscountAmount / $productRegularPrice ) * 100,
				2,
				'.',
				''
			); // convert discount amount into percentage
		}

		// conditional switch for calculations based on discount and tax configuration settings
		switch ( array( $singleProduct->is_on_sale(), $taxConfiguration, $couponDiscountPercentage ) ) {
			// calculate product price if discount is applied either catalogue or cart with tax included configurations
			case ( array( $singleProduct->is_on_sale(), 'taxIncluded', $couponDiscountPercentage ) ):
				$taxRate = 1 + $orderlineTax;
				if ( $couponDiscountPercentage > 0 ) {
					$discountPercentage = $couponDiscountPercentage;
					$totalCMS           = $orderline['total'] + $orderline['total_tax'];
				}
				$productPrice = number_format( $productRegularPrice / $taxRate, 2, '.', '' );
				$taxAmount    = $productRegularPrice - $productPrice;
				break;
			// calculate product price if discount is applied either catalogue or cart with tax excluded configurations
			case ( array( $singleProduct->is_on_sale(), 'taxExcluded', $couponDiscountPercentage ) ):
				if ( $couponDiscountPercentage > 0 ) {
					$discountPercentage = $couponDiscountPercentage;
					$taxRate            = array_sum( $orderline['taxes']['subtotal'] ) / $orderline['subtotal'];
					$totalCMS           = $orderline['total'] + $orderline['total_tax'];
				}
				$productPrice = $productRegularPrice;
				$taxAmount    = $productPrice * $taxRate;
				break;
		}

		// calculate total generated from orderlines generated after calculation
		$totalOrderlines = ( ( $productPrice + $taxAmount ) - ( ( $productPrice + $taxAmount ) * ( $discountPercentage / 100 ) ) ) * $productQuantity;
		// calculate compensation amount between total generated from woocommerce and total generated from orderlines
		$compensationAmount = $totalCMS - $totalOrderlines;
		// generate compensation amount orderline using product id and amount
		$compensationOrderline = $this->compensationAmountOrderline( $productId, $compensationAmount );
		// generate linedate with all the calculated parameters
		$linedata[] = array(
			'description' => $orderline['name'],
			'itemId'      => $productId,
			'quantity'    => $productQuantity,
			'unitPrice'   => number_format( $productPrice, 2, '.', '' ),
			'taxAmount'   => number_format( $taxAmount * $productQuantity, 2, '.', '' ),
			'taxPercent'  => number_format( ( $taxAmount / $productPrice ) * 100, 2, '.', '' ),
			'unitCode'    => $unitCode,
			'discount'    => number_format( $discountPercentage, 2, '.', '' ),
			'goodsType'   => 'handling',
			'imageUrl'    => wp_get_attachment_url( get_post_thumbnail_id( $singleProduct->get_id() ) ),
			'productUrl'  => get_permalink( $singleProduct->get_id() ),
		);

		// return array with product and compensation linedata
		return array(
			'product'      => reset( $linedata ),
			'compensation' => reset( $compensationOrderline ),
		);
	}

	/**
	 * Returns compensation amount orderline to bind within payment request
	 *
	 * @param int   $productId
	 * @param float $compensationAmount
	 * @return array
	 */
	public function compensationAmountOrderline( $productId, $compensationAmount ) {
		// Generate compensation amount orderline for payment, capture and refund requests
		$compensationAmountOrderline[] = array(
			'description' => 'Compensation',
			'itemId'      => $productId . '-' . 'Comp',
			'quantity'    => 1,
			'unitPrice'   => $compensationAmount,
			'taxAmount'   => '',
			'discount'    => '',
			'goodsType'   => 'handling',
		);

		return $compensationAmountOrderline;
	}

	/**
	 * Returns the shipping method orderline for order
	 *
	 * @param WC_Order $order
	 * @param array    $products
	 * @param bool     $returnRefundOrderLines
	 * @return array|bool
	 */
	private function getShippingDetails( $order, $products, $returnRefundOrderLines ) {
		 // Get the shipping method
		$orderShippingMethods = $order->get_shipping_methods();
		$shippingID           = 'NaN';
		$shippingDetails      = array();

		foreach ( $orderShippingMethods as $orderShippingKey => $orderShippingMethods ) {
			$shippingID = $orderShippingMethods['method_id'];
		}
		// In a refund it's possible to have order_shipping == 0 and order_shipping_tax != 0 at the same time
		if ( $order->get_shipping_total() != 0 || $order->get_shipping_tax() != 0 ) {
			if ( $products ) {
				if ( ! in_array( $shippingID, $products['skuList'] ) ) {
					return false;
				}
			}
			// getting shipping total and tax applied on it
			$totalShipping    = $order->get_shipping_total();
			$totalShippingTax = $order->get_shipping_tax();

			// This will trigger in case a refund action is performed
			if ( $returnRefundOrderLines ) {
				$shippingDetails[ $orderShippingKey ] = array(
					'qty'          => 1,
					'refund_total' => wc_format_decimal( $totalShipping ),
					'refund_tax'   => wc_format_decimal( $totalShippingTax ),
				);
			} else {
				$shippingDetails[] = array(
					'description' => $order->get_shipping_method(),
					'itemId'      => $shippingID,
					'quantity'    => 1,
					'unitPrice'   => number_format( $totalShipping, 2, '.', '' ),
					'taxAmount'   => number_format( $totalShippingTax, 2, '.', '' ),
					'taxPercent'  => number_format( ( $totalShippingTax / $totalShipping ) * 100, 2, '.', '' ),
					'goodsType'   => 'shipment',
				);
			}
		}
		return $shippingDetails;
	}
}
