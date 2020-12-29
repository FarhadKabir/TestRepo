<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
?>

<html>
<head>
    <meta name="viewport" content="width = device-width, initial-scale = 1">
    <link rel="stylesheet" href="https://unpkg.com/tachyons@4.10.0/css/tachyons.min.css"/>
    <style>
        .tabset > input[type="radio"] {
            position: absolute;
            left: -200vw;
        }

        .tabset .tab-panel {
            display: none;
        }

        .tabset > input:first-child:checked ~ .tab-panels > .tab-panel:first-child,
        .tabset > input:nth-child(3):checked ~ .tab-panels > .tab-panel:nth-child(2),
        .tabset > input:nth-child(5):checked ~ .tab-panels > .tab-panel:nth-child(3),
        .tabset > input:nth-child(7):checked ~ .tab-panels > .tab-panel:nth-child(4),
        .tabset > input:nth-child(9):checked ~ .tab-panels > .tab-panel:nth-child(5),
        .tabset > input:nth-child(11):checked ~ .tab-panels > .tab-panel:nth-child(6) {
            display: block;
        }

        .tabset > label {
            position: relative;
            padding: 5px 20% 25px;
            cursor: pointer;
            font-weight: 500;
        }

        .tabset > label::after {
            content: "";
            position: absolute;
            left: 0px;
            bottom: 10px;
            width: 22px;
            height: 2px;
        }

        .tabset > label:hover,
        .tabset > input:focus + label {
            background-color: #eee;
            color: #00897b;
        }

        .tabset > label:hover::after,
        .tabset > input:focus + label::after,
        .tabset > input:checked + label::after {
            background: #00897b;
            width: 100%;
        }

        .tabset > input:checked + label {
            margin-bottom: -1px;
        }

        .tab-panel {
            padding: 30px 0;
        }

        .tabset {
            max-width: 100%;
        }
    </style>
</head>

<body>
<input id="txnID" value="{{$order->get_transaction_id()}}" hidden>
<br>
<div class="tabset">
    <!-- Tab 1 -->
    <input type="radio" class="active f6 link dim bb bw1 ph7 pv2 mb2 dib" name="tabset" id="tab1"
           aria-controls="capture" checked>
    <label for="tab1">Capture</label>
    <!-- Tab 2 -->
    <input type="radio" class="f6 link dim bb bw1 ph7 pv2 mb2 dib" name="tabset" id="tab2" aria-controls="refund">
    <label for="tab2">Refund</label>
    <!-- Tab 3 -->
    <br>
    <div class="tab-panels">
        <section id="capture" class="tab-panel">
            <div id="capture" class="col s12">
                <div class="capture-status" style="margin-bottom:10px;"></div>
                <div>Payment reserved:
                    <span class="payment-reserved">{{number_format($reserved, 2)}}</span> {{$order->get_currency()}}
                </div>
                <div>Payment captured:
                    <span class="payment-captured">{{number_format($captured, 2)}}</span> {{$order->get_currency()}}
                </div>
                <div>Payment chargeable:
                    <span class="payment-chargeable">{{number_format($charge, 2)}}</span> {{$order->get_currency()}}
                </div>
                <br>
                <br>
                <div id="capture-details">
                    <div style="overflow-x:auto;">
                        <div class="responsive-table">
                            @include('tables.capture', ['order' => $order])
                        </div>
                    </div>

                    <div class="row row-ap">
                        <br>
                        <div class="col-lg-12">
                            <div>
                                <input class="action-select filled-in" name="allow-orderlines"
                                       type="checkbox" id="ap-allow-orderlines" checked="checked"/>
                                <label for="ap-allow-orderlines" class="form-check-label"> Send order
                                    lines</label>
                            </div>
                        </div>
                        @php
                            $toBeCaptured = (float)number_format($reserved - $captured, 2, '.', '');
                            $toBeRefunded = (float)number_format($captured - $refunded, 2, '.', '');
                        @endphp
                        <br>
                        <div>
                            @if (number_format($captured, 2) < number_format($reserved, 2))
                            <input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?" id="capture-amount"
                                   name="capture-amount" value="{{$toBeRefunded > 0 ? $toBeRefunded : $toBeCaptured}}"
                                   placeholder="Amount"/>
                            <a id="altapay_capture" class="f7 link dim ph4 pv2 mb1 dib white"
                               style="margin-left:20px; color:white; background-color:#006064; cursor:pointer; border-radius: 4px;">Capture</a>
                                @endif
                        </div>

                    </div>
                </div>
            </div>
        </section>
        <section id="refund" class="tab-panel">
            <div id="refund" class="col s12">
                <div class="capture-status" style="margin-bottom:10px;"></div>
                <div>Payment reserved:
                    <span class="payment-reserved">{{number_format($reserved, 2)}}</span> {{$order->get_currency()}}
                </div>
                <div>Payment refunded:
                    <span class="payment-refunded">{{number_format($refunded, 2)}}</span> {{$order->get_currency()}}
                </div>
                <br><br><br>
                <div style="overflow-x:auto;">
                    <div class="responsive-table">
                        @include('tables.refund', ['order' => $order])
                    </div>
                </div>

                <div class="row row-ap">
                    <br>
                    <div class="col-lg-12">
                        <div>
                            <input class="action-select filled-in" name="allow-refund-orderlines"
                                   type="checkbox" id="ap-allow-refund-orderlines" checked="checked"/>
                            <label for="ap-allow-refund-orderlines" class="form-check-label"> Send order
                                lines</label>
                        </div>
                    </div>
                    @php
                        $toBeCaptured = (float)number_format($reserved - $captured, 2, '.', '');
                        $toBeRefunded = (float)number_format($captured - $refunded, 2, '.', '');
                    @endphp
                    <br>
                    <div>
                        @if (number_format($refunded, 2) < number_format($reserved, 2))
                        <input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?" id="refund-amount" name="refund-amount"
                               value="{{$toBeRefunded > 0 ? $toBeRefunded : $toBeCaptured}}" placeholder="Amount"/>
                        <a id="altapay_refund" class="f7 link dim ph4 pv2 mb1 dib white"
                           style="margin-left:20px; color:white; background-color:#006064; cursor:pointer; border-radius: 4px;">Refund</a>
                            @endif
                    </div>

                </div>
            </div>
        </section>
    </div>
    @if (number_format($captured, 2) == 0)
        <a id="altapay_release_payment" class="f7 link dim ph4 pv2 mb1 dib white"
           style="color:white; background-color:#ed2939; cursor:pointer; border-radius: 4px;">Release Payment</a>
    @endif

</body>
</html>