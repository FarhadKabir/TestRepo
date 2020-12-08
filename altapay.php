<?php
/**
 * Plugin Name: Altapay for WooCommerce - Payments less complicated
 * Plugin URI: https://www.altapay.com/knowledge-base/omni-channel/integration-manuals/
 * Description: Payment Gateway to use with WordPress WooCommerce
 * Author: AltaPay
 * Author URI: https://www.altapay.com/knowledge-base/omni-channel/
 * Version: 3.1.1
 * Name: SDM_Altapay
 * WC requires at least: 3.0.0
 * WC tested up to: 4.7.0
 *
 * @package Altapay
 */

use Altapay\Classes\Core;
use Altapay\Classes\Util;
use Altapay\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Include the autoloader so we can dynamically include the rest of the classes.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes/Autoloader.php';

/**
 * Init AltaPay settings and gateway
 */
function init_altapay_settings() {
	// Make sure WooCommerce and WooCommerce gateway is enabled and loaded
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	$settings = new Core\AltapaySettings();
	// Add Gateway to WooCommerce if enabled
	if ( json_decode( get_option( 'altapay_terminals_enabled' ) ) ) {
		add_filter( 'woocommerce_payment_gateways', 'altapay_add_gateway' );
	}

	/**
	 * Add gateways to WooCommerce.
	 *
	 * @param array $methods
	 *
	 * @return array
	 */
	function altapay_add_gateway( $methods ) {
		$pluginDir = plugin_dir_path( __FILE__ );
		// Directory for the terminals
		$terminalDir = $pluginDir . 'terminals/';
		// Temp dir in case the one from above is not writable
		$tmpDir = sys_get_temp_dir();
		// Get enabled terminals
		$terminals = json_decode( get_option( 'altapay_terminals_enabled' ) );

		if ( $terminals ) {
			foreach ( $terminals as $terminal ) {
				// Load Terminal information
				$terminalInfo = json_decode( get_option( 'altapay_terminals' ) );
				$terminalName = $terminal;
				foreach ( $terminalInfo as $term ) {
					if ( $term->key === $terminal ) {
						$terminalName   = $term->name;
						$terminalNature = $term->nature;
						if ( in_array( 'CreditCard', $terminalNature, true ) ) {
							$tokenStatus = 'CreditCard';
						}
					}
				}
				// Check if file exists
				$path    = $terminalDir . $terminal . '.class.php';
				$tmpPath = $tmpDir . '/' . $terminal . '.class.php';

				if ( file_exists( $path ) ) {
					require_once $path;
					$methods[] = 'WC_Gateway_' . $terminal;
				} elseif ( file_exists( $tmpPath ) ) {
					require_once $tmpPath;
					$methods[] = 'WC_Gateway_' . $terminal;
				} else {
					// Create file
					$template = file_get_contents( $pluginDir . 'views/paymentClass.tpl' );
					$filename = $terminalDir . $terminal . '.class.php';
					// Check if terminals folder is writable or use tmp as fallback
					if ( ! is_writable( $terminalDir ) ) {
						$filename = $tmpDir . '/' . $terminal . '.class.php';
					}
					// Replace patterns
					$content = str_replace( array( '{key}', '{name}', '{tokenStatus}' ), array( $terminal, $terminalName, $tokenStatus ), $template );

					file_put_contents( $filename, $content );
				}
			}
		}
		return $methods;
	}

	// Make sure payment page form loads payment template
	add_filter( 'template_include', 'altapay_page_template', 99 );

	/**
	 * Load payment template
	 *
	 * @param string $template Template to load.
	 *
	 * @return string
	 */
	function altapay_page_template( $template ) {
		// Get payment form page id
		$paymentFormPageID = esc_attr( get_option( 'altapay_payment_page' ) );
		if ( $paymentFormPageID && is_page( $paymentFormPageID ) ) {
			// Make sure the template is only loaded from AltaPay.
			// Load template override
			$template = locate_template( 'altapay-payment-form.php' );

			// If no template override load template from plugin
			if ( ! $template ) {
				$template = __DIR__ . '/views/altapay-payment-form.php';
			}
		}

		return $template;
	}

	// Capture function
	add_action( 'add_meta_boxes', 'altapayAddMetaBoxes' );
	add_action( 'wp_ajax_altapay_capture', 'altapayCaptureCallback' );
	add_action( 'wp_ajax_altapay_refund', 'altapayRefundCallback' );
	add_action( 'wp_ajax_altapay_release_payment', 'altapayReleasePayment' );
	add_action( 'admin_footer', 'altapayActionJavascript' );
	// Create payment page function
	add_action( 'altapay_checkout_order_review', 'woocommerceOrderReview' );
	add_action( 'wp_ajax_create_altapay_payment_page', 'createAltapayPaymentPageCallback' );

	/**
	 * Register meta box for order details page
	 */
	function altapayAddMetaBoxes() {
		global $post;

		if ( $post->post_type !== 'shop_order' ) {
			return true;
		}
		// Load order
		$order         = new WC_Order( $post->ID );
		$paymentMethod = $order->get_payment_method();

		// Only show on AltaPay orders
		if ( strpos( $paymentMethod, 'altapay' ) !== false || strpos( $paymentMethod, 'valitor' ) !== false ) {
			add_meta_box(
				'altapay-actions',
				__( 'AltaPay actions', 'altapay' ),
				'altapay_meta_box',
				'shop_order',
				'normal'
			);
		}

		return true;
	}

	/**
	 * Meta box display callback
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	function altapay_meta_box( $post ) {
		// Load order
		$order      = new WC_Order( $post->ID );
		$orderItems = $order->get_items();
		$txnID      = $order->get_transaction_id();

		if ( $txnID ) {
			$settings = new Core\AltapaySettings();
			$api      = $settings->apiLogin();
			if ( $api instanceof WP_Error ) {
				$_SESSION['altapay_login_error'] = $api->get_error_message();
				echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';

				return;
			}

			$payment = $api->getPayment( $txnID );
			if ( $payment ) {
				$payments = $payment->getPayments();
				foreach ( $payments as $pay ) {
					$reserved = $pay->getReservedAmount();
					$captured = $pay->getCapturedAmount();
					$refunded = $pay->getRefundedAmount();
					$status   = $pay->getCurrentStatus();

					if ( $status === 'released' ) {
						echo '<br /><b>' . __( 'Payment released', 'altapay' ) . '</b>';
					} else {
						$charge = $reserved - $captured - $refunded;
						if ( $charge <= 0 ) {
							$charge = 0.00;
						}
						$blade = new Helpers\AltapayHelpers();
						echo $blade->loadBladeLibrary()->run(
							'tables.index',
							array(
								'reserved' => $reserved,
								'captured' => $captured,
								'charge'   => $charge,
								'refunded' => $refunded,
								'order'    => $order,
							)
						);
					}
				}
			}
		} else {
			esc_html_e( 'Order got no transaction', 'altapay' );
		}
	}

	/**
	 * Add scripts for the order details page
	 *
	 * @return void
	 */
	function altapayActionJavascript() {
		global $post;
		if ( isset( $post->ID ) ) {
			// Check if WooCommerce order
			if ( $post->post_type === 'shop_order' ) {
				?>
				<script type="text/javascript">
					let Globals = <?php echo wp_json_encode( array( 'postId' => $post->ID ) ); ?>;
				</script>
				<?php
				wp_enqueue_script(
					'captureScript',
					plugin_dir_url( __FILE__ ) . 'assets/js/capture.js',
					array( 'jquery' ),
					'1.1.0',
					true
				);
				wp_register_script(
					'jQuery',
					'https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js',
					null,
					'2.1.3',
					true
				);
				wp_enqueue_script( 'jQuery' );
				wp_enqueue_script(
					'refundScript',
					plugin_dir_url( __FILE__ ) . 'assets/js/refund.js',
					array( 'jquery' ),
					'1.1.0',
					true
				);
				wp_enqueue_script(
					'releaseScript',
					plugin_dir_url( __FILE__ ) . 'assets/js/release.js',
					array( 'jquery' ),
					'1.1.0',
					true
				);
			}
		}
	}

	/**
	 * Method for creating payment page on call back
	 *
	 * @return void
	 */
	function createAltapayPaymentPageCallback() {
		global $userID;

		// Create page data
		$page = array(
			'post_type'    => 'page',
			'post_content' => '',
			'post_parent'  => 0,
			'post_author'  => $userID,
			'post_status'  => 'publish',
			'post_title'   => 'AltaPay payment form',
		);

		// Create page
		$pageID = wp_insert_post( $page );
		if ( $pageID == 0 ) {
			echo wp_json_encode(
				array(
					'status'  => 'error',
					'message' => __(
						'Error creating page, try again',
						'altapay'
					),
				)
			);
		} else {
			echo wp_json_encode(
				array(
					'status'  => 'ok',
					'message' => __( 'Payment page created', 'altapay' ),
					'page_id' => $pageID,
				)
			);
		}
		wp_die();
	}

	/**
	 * Method for handling capture action and call back
	 *
	 * @return WP_Error
	 */
	function altapayCaptureCallback() {
		$utilMethods = new Util\UtilMethods();
		$orderID     = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$amount      = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : '';

		if ( ! $orderID || ! $amount ) {
			echo wp_json_encode(
				array(
					'status'  => 'error',
					'message' => 'error',
				)
			);
			wp_die();
		}

		// Load order
		$order = new WC_Order( $orderID );
		$txnID = $order->get_transaction_id();
		if ( $txnID ) {
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
			$postOrderLines = isset( $_POST['orderLines'] ) ? wp_unslash( $_POST['orderLines'] ) : '';
			if ( $postOrderLines ) {
				$selectedProducts = array(
					'skuList' => array(),
					'skuQty'  => array(),
				);
				foreach ( $postOrderLines as $productData ) {
					if ( $productData[1]['value'] > 0 ) {
						$selectedProducts['skuList'][]                          = $productData[0]['value'];
						$selectedProducts['skuQty'][ $productData[0]['value'] ] = $productData[1]['value'];
					}
				}

				$orderLines = $utilMethods->createOrderLines( $order, $selectedProducts );
				$salesTax   = 0;
				foreach ( $orderLines as $orderLine ) {
					$salesTax += $orderLine['taxAmount'];
				}
			} else {
				$orderLines = array( array() );
				$salesTax   = $order->get_total_tax();
			}

			// Capture amount
			$captureResult = $api->captureReservation( $txnID, $amount, $orderLines, $salesTax );
			if ( ! $captureResult->wasSuccessful() ) {
				// log to history
				$order->add_order_note( __( 'Capture failed: ' . $captureResult->getMerchantErrorMessage(), 'Altapay' ) );
				echo wp_json_encode(
					array(
						'status'  => 'error',
						'message' => $captureResult->getMerchantErrorMessage(),
					)
				);
			} else {
				// Get payment data
				$payment = $api->getPayment( $txnID );
				if ( $payment ) {
					$payments = $payment->getPayments();
					foreach ( $payments as $pay ) {
						$reserved = $pay->getReservedAmount();
						$captured = $pay->getCapturedAmount();
						$refunded = $pay->getRefundedAmount();
						$charge   = $reserved - $captured - $refunded;
						if ( $charge <= 0 ) {
							$charge = 0.00;
						}
					}
				}

				update_post_meta( $orderID, '_captured', true );
				$orderNote = __( 'Order captured: amount: ' . $amount, 'Altapay' );
				$order->add_order_note( $orderNote );
				$noteHtml = '<li class="note system-note"><div class="note_content"><p>' . $orderNote . '</p></div><p class="meta"><abbr class="exact-date">' . sprintf(
					__(
						'added on %1$s at %2$s',
						'woocommerce'
					),
					date_i18n( wc_date_format(), time() ),
					date_i18n( wc_time_format(), time() )
				) . '</abbr></p></li>';

				echo wp_json_encode(
					array(
						'status'     => 'ok',
						'captured'   => number_format( $captured, 2 ),
						'reserved'   => number_format( $reserved, 2 ),
						'refunded'   => number_format( $refunded, 2 ),
						'chargeable' => number_format( $charge, 2 ),
						'message'    => $captureResult,
						'note'       => $noteHtml,
					)
				);
			}
		}

		wp_die();
	}

	/**
	 * Method for handling refund action and call back
	 *
	 * @return WP_Error
	 */
	function altapayRefundCallback() {
		$utilMethods        = new Util\UtilMethods();
		$orderLines         = array( array() );
		$wcRefundOrderLines = array( array() );
		$orderID            = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$amount             = (float) $_POST['amount'];

		if ( ! $orderID || ! $amount ) {
			echo wp_json_encode(
				array(
					'status'  => 'error',
					'message' => 'error',
				)
			);
			wp_die();
		}

		// Load order
		$order = new WC_Order( $orderID );
		$txnID = $order->get_transaction_id();

		if ( $txnID ) {
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
			$postOrderLines = wp_unslash( $_POST['orderLines'] );
			if ( $postOrderLines ) {
				$selectedProducts = array(
					'skuList' => array(),
					'skuQty'  => array(),
				);
				foreach ( $postOrderLines as $productData ) {
					if ( $productData[1]['value'] > 0 ) {
						$selectedProducts['skuList'][]                          = $productData[0]['value'];
						$selectedProducts['skuQty'][ $productData[0]['value'] ] = $productData[1]['value'];
					}
				}
				$orderLines         = $utilMethods->createOrderLines( $order, $selectedProducts );
				$wcRefundOrderLines = $utilMethods->createOrderLines( $order, $selectedProducts, true );
			}

			/**
			 * @param WC_Order $order
			 * @param array    $wcRefundOrderLines
			 * @param float    $amount
			 * @param int      $orderId
			 * @return void
			 */
			function wcRefund( $order, $wcRefundOrderLines, $amount, $orderId ) {
				// Restock the items
				$refundOperation = wc_create_refund(
					array(
						'amount'         => $amount,
						'reason'         => null,
						'order_id'       => $orderId,
						'line_items'     => $wcRefundOrderLines,
						'refund_payment' => false,
						'restock_items'  => true,
					)
				);
				if ( $refundOperation instanceof WP_Error ) {
					$order->add_order_note( __( $refundOperation->get_error_message(), 'altapay' ) );
				} else {
					$order->add_order_note( __( 'Refunded products have been re-added to the inventory', 'altapay' ) );
				}
			}

			// Refund the amount OR release if a refund is not possible
			$releaseFlag = false;
			if ( get_post_meta( $orderID, '_captured', true ) || get_post_meta( $orderID, '_refunded', true ) ) {
				$refundResult = $api->refundCapturedReservation( $txnID, $amount, $orderLines );
				if ( $refundResult->wasSuccessful() ) {
					wcRefund( $order, $wcRefundOrderLines, $amount, $orderID );
					update_post_meta( $orderID, '_refunded', true );
				}
			} else {
				if ( $order->get_remaining_refund_amount() == 0 ) {
					$refundResult = $api->releaseReservation( $txnID );
					if ( $refundResult->wasSuccessful() ) {
						$releaseFlag = true;
						update_post_meta( $orderID, '_released', true );
					}
				} else {
					$refundResult = $api->refundCapturedReservation( $txnID, $amount, $orderLines );
					if ( $refundResult->wasSuccessful() ) {
						wcRefund( $order, $wcRefundOrderLines, $amount, $orderID );
						update_post_meta( $orderID, '_refunded', true );
					}
				}
			}

			if ( ! $refundResult->wasSuccessful() ) {
				$order->add_order_note( __( 'Refund failed: ' . $refundResult->getMerchantErrorMessage(), 'altapay' ) );
				echo wp_json_encode(
					array(
						'status'  => 'error',
						'message' => $refundResult->getMerchantErrorMessage(),
					)
				);
			} else {
				// Get payment data
				$payment = $api->getPayment( $txnID );
				if ( $payment ) {
					$payments = $payment->getPayments();
					foreach ( $payments as $pay ) {
						$reserved = $pay->getReservedAmount();
						$captured = $pay->getCapturedAmount();
						$refunded = $pay->getRefundedAmount();
						$charge   = $reserved - $captured - $refunded;
						if ( $charge <= 0 ) {
							$charge = 0.00;
						}
					}
				}
				if ( $releaseFlag ) {
					$order->add_order_note( __( 'Order released', 'altapay' ) );
					$orderNote = 'The order has been released';
				} else {
					$order->add_order_note( __( 'Order refunded: amount ' . $amount, 'altapay' ) );
					$orderNote = 'Order refunded: amount ' . $amount;
				}
				$noteHtml = '<li class="note system-note"><div class="note_content"><p>' . $orderNote . '</p></div><p class="meta"><abbr class="exact-date">' . sprintf(
					__(
						'added on %1$s at %2$s',
						'woocommerce'
					),
					date_i18n( wc_date_format(), time() ),
					date_i18n( wc_time_format(), time() )
				) . '</abbr></p></li>';
				echo wp_json_encode(
					array(
						'status'     => 'ok',
						'captured'   => number_format( $captured, 2 ),
						'reserved'   => number_format( $reserved, 2 ),
						'refunded'   => number_format( $refunded, 2 ),
						'chargeable' => number_format( $charge, 2 ),
						'message'    => $refundResult,
						'note'       => $noteHtml,
					)
				);
			}
		}

		wp_die();
	}

	/**
	 * Method for handling release action and call back
	 *
	 * @return WP_Error
	 */
	function altapayReleasePayment() {
		$orderID  = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$order    = new WC_Order( $orderID );
		$txnID    = $order->get_transaction_id();
		$captured = 0;
		$reserved = 0;
		$refunded = 0;
		$api      = new AltapayMerchantAPI(
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
		try {
			$payment  = $api->getPayment( $txnID );
			$payments = $payment->getPayments();
			foreach ( $payments as $pay ) {
				$reserved = $pay->getReservedAmount();
				$captured = $pay->getCapturedAmount();
				$refunded = $pay->getRefundedAmount();
			}

			if ( $captured == 0 && $refunded == 0 ) {
				$releaseResult = $api->releaseReservation( $txnID );
				if ( $releaseResult->wasSuccessful() ) {
					$order->update_status( 'cancelled' );
					update_post_meta( $orderID, '_released', true );
					$order->add_order_note( __( 'Order released: "The order has been released"', 'altapay' ) );
					echo wp_json_encode(
						array(
							'status'  => 'ok',
							'message' => 'Payment Released',
						)
					);
				}
			} elseif ( $captured == $refunded && $refunded == $reserved || $refunded == $reserved ) {
				$order->update_status( 'refunded' );
				$releaseResult = $api->releaseReservation( $txnID );
				if ( ! $releaseResult->wasSuccessful() ) {
					$order->add_order_note( __( 'Release failed: ' . $releaseResult->getMerchantErrorMessage(), 'altapay' ) );
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
					echo wp_json_encode(
						array(
							'status'  => 'error',
							'message' => $releaseResult->getMerchantErrorMessage(),
						)
					);
				}
			}
		} catch ( Exception $e ) {
			return WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
		}
		wp_die();
	}

	$objTokenControl = new Core\AltapayTokenControl();
	$objTokenControl->registerHooks();

	$objOrderStatus = new Core\AltapayOrderStatus();
	$objOrderStatus->registerHooks();
}

/**
 * Perform functionality required during plugin activation
 */
function altapayPluginActivation() {
	Core\AltapayPluginInstall::createPluginTables();
}

register_activation_hook( __FILE__, 'altapayPluginActivation' );
// Make sure plugins are loaded before running gateway
add_action( 'plugins_loaded', 'init_altapay_settings', 0 );
