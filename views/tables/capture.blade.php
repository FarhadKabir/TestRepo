<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
?>

<table class="w-100 center" cellspacing="0">
    <tbody>
    <tr style="font-weight: bold; border-collapse: collapse; padding: 15px;">
    <thead>
    <tr>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Product name</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Price with tax</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Price without tax</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Ordered</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Discount Percent</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Quantity</th>
        <th class="fw6 bb b--black-20 tl pb3 pr3 bg-white">Total amount</th>
    </tr>
    </thead>
    </tr>
    <tbody class="lh-copy">

    @foreach($order->get_items() as $itemData)

        @php
            $productID = $itemData->get_id();
            $product = wc_get_product($itemData['product_id']);
            $qty = $itemData->get_quantity();
            $orderedItems = $order->get_items('coupon');
            $discountPercentageWholeCart = 0;
        @endphp

        @if(!empty($product->get_sku()))
            @php($sku = $productID . '-' . $product->get_sku())
        @else
            @php($sku = $productID)
        @endif

        @if($orderedItems)
            @foreach($orderedItems as $itemID => $item)
                @php
                    // Retrieving the coupon ID reference
                    $couponPostObj = get_page_by_title($item->get_name(), OBJECT, 'shop_coupon');
                    $couponID = $couponPostObj->ID;
                    // Get an instance of WC_Coupon object (necessary to use WC_Coupon methods)
                    $coupon = new WC_Coupon($couponID);
                    $couponType = $coupon->discount_type;
                    $appliedCoupons = reset($coupon);
                    // Filtering with your coupon custom types
                @endphp
                @if ($couponType == 'percent' && empty($appliedCoupons['product_ids']))
                    @php
                        // Get the Coupon discount amounts in the order
                        $orderDiscountAmount = wc_get_order_item_meta($itemID, 'discount_amount', true);
                        $orderDiscountTaxAmount = wc_get_order_item_meta($itemID, 'discount_amount_tax', true);
                        $totalCouponDiscountAmmount = $orderDiscountAmount + $orderDiscountTaxAmount;
                        // Or get the coupon amount object
                        $discountPercentageWholeCart += $coupon->amount;
                    @endphp
                @elseif ($couponType == 'percent' && !empty($appliedCoupons['product_ids']))
                    @php
                        $discountPercentageOnParticularProduct = $coupon->amount;
                        $productsWithCoupon = array_values($appliedCoupons['product_ids']);
                    @endphp
                @endif
            @endforeach
        @endif

        @if (in_array($itemData['product_id'], $productsWithCoupon) || in_array($itemData['variation_id'], $productsWithCoupon))
            @php($discountPercentage = $discountPercentageWholeCart + $discountPercentageOnParticularProduct)
        @else
            @php($discountPercentage = $discountPercentageWholeCart)
        @endif

        @php
            $discountPercent = (float)number_format(((($itemData->get_subtotal() + $itemData->get_subtotal_tax()) - ($itemData->get_total() + $itemData->get_total_tax())) / ($itemData->get_subtotal() + $itemData->get_subtotal_tax())) * 100, 2, '.', '');
            $productUnitPriceWithoutTax = (float)number_format(($itemData->get_total() / $qty), 2, '.', '');
            $productUnitPriceWithTax = (float)number_format(($itemData->get_total() / $qty), 2, '.', '') + ($itemData->get_total_tax() / $qty);
            $totalIncTax = round($itemData->get_total() + $itemData->get_total_tax(), 2);
        @endphp

        <tr class="ap-orderlines-capture">
            <td style="display:none"><input class="form-control ap-order-product-sku pv3 pr3 bb b--black-20" name="productID" type="text" value="{{$sku}}"/></td>
            <td class="pv3 pr3 bb b--black-20"> {{$itemData->get_product()->get_name()}} </td>
            <td class="ap-orderline-unit-price pv3 pr3 bb b--black-20">{{$productUnitPriceWithTax}}</td>
            <td class="pv3 pr3 bb b--black-20">{{$productUnitPriceWithoutTax}}</td>
            <td class="ap-orderline-capture-max-quantity pv3 pr3 bb b--black-20">{{$qty}}</td>
            <td class="ap-orderline-discount-percent pv3 pr3 bb b--black-20">{{$discountPercent}}</td>
            <td class="pv3 pr3 bb b--black-20"><input class="form-control ap-order-capture-modify" name="qty" value="{{$qty}}" type="number"/></td>
            <td class="ap-orderline-totalprice-capture pv3 pr3 bb b--black-20"><span class="totalprice-capture">{{$order->get_currency()}} {{$totalIncTax}}</span></td>
        </tr>
    @endforeach

    @if ($order->get_shipping_total() <> 0 || $order->get_shipping_tax() <> 0)
        @php
            $order_shipping_methods = $order->get_shipping_methods();
            $discountPercentage = 0;
            $totalIncTax = (float)number_format($order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', '');
            $excTax =$order->get_shipping_total();
        @endphp

        @foreach ($order_shipping_methods as $ordershipping_key => $ordershippingmethods)
            @php($shipping_id = $ordershippingmethods['method_id'])
        @endforeach

        <tr class="ap-orderlines-capture">
            <td style="display:none"><input class="form-control ap-order-product-sku pv3 pr3 bb b--black-20" name="productID" type="text" value="{{$shipping_id}}"/></td>
            <td class="pv3 pr3 bb b--black-20">{{$order->get_shipping_method()}}</td>
            <td class="ap-orderline-unit-price pv3 pr3 bb b--black-20">{{$totalIncTax}}</td>
            <td class="pv3 pr3 bb b--black-20">{{$excTax}}</td>
            <td class="ap-orderline-capture-max-quantity pv3 pr3 bb b--black-20">1</td>
            <td class="ap-orderline-discount-percent pv3 pr3 bb b--black-20">{{$discountPercentage}}</td>
            <td class="pv3 pr3 bb b--black-20"><input class="form-control ap-order-capture-modify" name="qty" value="1" type="number"/></td>
            <td class="ap-orderline-totalprice-capture pv3 pr3 bb b--black-20"><span class="totalprice-capture">{{$order->get_currency()}} {{$totalIncTax}}</span></td>
        </tr>
    @endif

</table>