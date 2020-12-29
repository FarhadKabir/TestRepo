<?php
/**
 * Plugin Name: Altapay for WooCommerce - Payments less complicated
 * Plugin URI: https://www.altapay.com/knowledge-base/omni-channel/integration-manuals/
 * Description: Payment Gateway to use with WordPress WooCommerce
 * Author: AltaPay
 * Author URI: https://www.altapay.com/knowledge-base/omni-channel/
 * Version: 3.2.0
 * Name: SDM_Altapay
 * WC requires at least: 3.0.0
 * WC tested up to: 4.8.0
 *
 * @package Altapay
 */

use Altapay\Classes\Core;
use Altapay\Classes\Util;
use Altapay\Helpers;
use Altapay\Api\Payments\CaptureReservation;
use Altapay\Exceptions\ResponseHeaderException;
use Altapay\Response\CaptureReservationResponse;
use Altapay\Api\Payments\RefundCapturedReservation;
use Altapay\Api\Payments\ReleaseReservation;
use Altapay\Response\ReleaseReservationResponse;
use Altapay\Api\Others\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Include the autoloader so we can dynamically include the rest of the classes.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

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

	$objTokenControl = new Core\AltapayTokenControl();
	$objTokenControl->registerHooks();

	$objOrderStatus = new Core\AltapayOrderStatus();
	$objOrderStatus->registerHooks();
}

/**
 * Add the Gateway to WooCommerce.
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
			$tokenStatus = '';
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
	$order = new WC_Order( $post->ID );
	$txnID = $order->get_transaction_id();

	if ( $txnID ) {
		$settings = new Core\AltapaySettings();
		$login    = $settings->altapayApiLogin();

		if ( ! $login || is_wp_error( $login ) ) {
			echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
			return;
		}

		$auth = $settings->getAuth();
		$api  = new Payments( $auth );
		$api->setTransaction( $txnID );
		$payments = $api->call();

		if ( $payments ) {
			foreach ( $payments as $pay ) {
				$reserved = $pay->ReservedAmount;
				$captured = $pay->CapturedAmount;
				$refunded = $pay->RefundedAmount;
				$status   = $pay->TransactionStatus;

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
	$settings    = new Core\AltapaySettings();
	$orderID     = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	$amount      = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : '';

	if ( ! $orderID || ! $amount ) {
		wp_send_json_error( array( 'error' => 'error' ) );
	}

	// Load order
	$order = new WC_Order( $orderID );
	$txnID = $order->get_transaction_id();
	if ( $txnID ) {

		$login = $settings->altapayApiLogin();
		if ( ! $login ) {
			wp_send_json_error( array( 'error' => 'Could not login to the Merchant API:' ) );
		} elseif ( is_wp_error( $login ) ) {
			wp_send_json_error( array( 'error' => wp_kses_post( $login->get_error_message() ) ) );
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
		}

		try {
			$api = new CaptureReservation( $settings->getAuth() );
			$api->setAmount( round( $amount, 2 ) );
			$api->setOrderLines( $orderLines );
			$api->setTransaction( $txnID );
			$response = $api->call();
		} catch ( InvalidArgumentException $e ) {
			error_log( 'Response header exception ' . $e->getMessage() );
		} catch ( ResponseHeaderException $e ) {
			error_log( 'Response header exception ' . $e->getMessage() );
		} catch ( \Exception $e ) {
			error_log( 'Response header exception ' . $e->getMessage() );
		}

		if ( $response->Result !== 'Success' ) {
			wp_send_json_error( array( 'error' => __( 'Could not capture reservation' ) ) );
		}

		$rawResponse = $api->getRawResponse();
		$charge      = 0;
		$reserved    = 0;
		$captured    = 0;
		$refunded    = 0;

		if ( $rawResponse ) {
			$body = $rawResponse->getBody();
			// Update comments if capture fail
			$xml = new SimpleXMLElement( $body );
			if ( (string) $xml->Body->Result === 'Error' || (string) $xml->Body->Result === 'Failed' ) {
				// log to history
				$order->add_order_note( __( 'Capture failed: ' . (string) $xml->Body->MerchantErrorMessage, 'Altapay' ) );
				wp_send_json_error( array( 'error' => (string) $xml->Body->MerchantErrorMessage ) );
			}

			$reserved = (float) $xml->Body->Transactions->Transaction->ReservedAmount;
			$captured = (float) $xml->Body->Transactions->Transaction->CapturedAmount;
			$refunded = (float) $xml->Body->Transactions->Transaction->RefundedAmount;
			$charge   = $reserved - $captured - $refunded;
		}

		if ( $charge <= 0 ) {
			$charge = 0.00;
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

		wp_send_json_success(
			array(
				'captured'   => $captured,
				'reserved'   => $reserved,
				'refunded'   => $refunded,
				'chargeable' => round( $charge, 2 ),
				'note'       => $noteHtml,
			)
		);
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
	$settings           = new Core\AltapaySettings();
	$orderLines         = array( array() );
	$wcRefundOrderLines = array( array() );
	$orderID            = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
	$amount             = (float) $_POST['amount'];

	if ( ! $orderID || ! $amount ) {
		wp_send_json_error( array( 'error' => 'error' ) );
	}

	// Load order
	$order = new WC_Order( $orderID );
	$txnID = $order->get_transaction_id();
	if ( $txnID ) {
		$login = $settings->altapayApiLogin();
		if ( ! $login ) {
			wp_send_json_error( array( 'error' => 'Could not login to the Merchant API:' ) );
		} elseif ( is_wp_error( $login ) ) {
			wp_send_json_error( array( 'error' => wp_kses_post( $login->get_error_message() ) ) );
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
			$orderLines         = $utilMethods->createOrderLines( $order, $selectedProducts );
			$wcRefundOrderLines = $utilMethods->createOrderLines( $order, $selectedProducts, true );
		}

		// Refund the amount OR release if a refund is not possible
		$releaseFlag = false;
		$refundFlag  = false;
		$auth        = $settings->getAuth();
		$error       = '';

		if ( get_post_meta( $orderID, '_captured', true ) || get_post_meta( $orderID, '_refunded', true ) || $order->get_remaining_refund_amount() > 0 ) {

			$api = new RefundCapturedReservation( $auth );
			$api->setAmount( round( $amount ) );
			$api->setOrderLines( $orderLines );
			$api->setTransaction( $txnID );

			try {
				$response = $api->call();
				if ( $response->Result === 'Success' ) {
					// Restock the items
					$refundOperation = wc_create_refund(
						array(
							'amount'         => $amount,
							'reason'         => null,
							'order_id'       => $orderID,
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
					update_post_meta( $orderID, '_refunded', true );
					$refundFlag = true;
				} else {
					$error = $response->MerchantErrorMessage;
				}
			} catch ( ResponseHeaderException $e ) {
				$error = 'Response header exception ' . $e->getMessage();
			} catch ( \Exception $e ) {
				$error = 'Response header exception ' . $e->getMessage();
			}
		} elseif ( $order->get_remaining_refund_amount() == 0 ) {

			try {
				$api = new ReleaseReservation( $auth );
				$api->setTransaction( $txnID );
				/** @var ReleaseReservationResponse $response */
				$response = $api->call();
				if ( $response->Result === 'Success' ) {
					$releaseFlag = true;
					$refundFlag  = true;
					update_post_meta( $orderID, '_released', true );
				} else {
					$error = $response->MerchantErrorMessage;
				}
			} catch ( ResponseHeaderException $e ) {
				$error = 'Response header exception ' . $e->getMessage();
			}
		}

		if ( ! $refundFlag ) {
			$order->add_order_note( __( 'Refund failed: ' . $error, 'altapay' ) );
			wp_send_json_error( array( 'error' => $error ) );
		} else {
			$reserved = 0;
			$captured = 0;
			$refunded = 0;
			$api      = new Payments( $auth );
			$api->setTransaction( $txnID );
			$payments = $api->call();

			if ( $payments ) {
				foreach ( $payments as $pay ) {
					$reserved += $pay->ReservedAmount;
					$captured += $pay->CapturedAmount;
					$refunded += $pay->RefundedAmount;
				}
			}

			$charge = $reserved - $captured - $refunded;
			if ( $charge <= 0 ) {
				$charge = 0.00;
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
			wp_send_json_success(
				array(
					'captured'   => $captured,
					'reserved'   => $reserved,
					'refunded'   => $refunded,
					'chargeable' => round( $charge, 2 ),
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
	$settings = new Core\AltapaySettings();
	$captured = 0;
	$reserved = 0;
	$refunded = 0;

	$login = $settings->altapayApiLogin();
	if ( ! $login ) {
		wp_send_json_error( array( 'error' => 'Could not login to the Merchant API:' ) );
	} elseif ( is_wp_error( $login ) ) {
		wp_send_json_error( array( 'error' => wp_kses_post( $login->get_error_message() ) ) );
	}

	$auth = $settings->getAuth();
	$api  = new Payments( $auth );
	$api->setTransaction( $txnID );

	try {
		$payments = $api->call();
		foreach ( $payments as $pay ) {
			$reserved += $pay->ReservedAmount;
			$captured += $pay->CapturedAmount;
			$refunded += $pay->RefundedAmount;
		}

		if ( $captured === 0 && $refunded === 0 ) {
			$orderStatus = 'cancelled';
		} elseif ( $captured == $refunded && $refunded == $reserved || $refunded == $reserved ) {
			$orderStatus = 'refunded';
		} else {
			$orderStatus = 'processing';
		}

		$api = new ReleaseReservation( $auth );
		$api->setTransaction( $txnID );
		$response = $api->call();

		if ( $response->Result === 'Success' ) {
			$order->update_status( $orderStatus );
			if ( $orderStatus === 'cancelled' ) {
				update_post_meta( $orderID, '_released', true );
				$order->add_order_note( __( 'Order released: "The order has been released"', 'altapay' ) );
				wp_send_json_success( array( 'message' => 'Payment Released' ) );
			}
		} else {
			$order->add_order_note( __( 'Release failed: ' . $response->MerchantErrorMessage, 'altapay' ) );
			wp_send_json_error( array( 'error' => $response->MerchantErrorMessage ) );
		}
	} catch ( Exception $e ) {
		return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
	}
	wp_die();
}

/**
 * Perform functionality required during plugin activation
 */
function altapayPluginActivation() {
	Core\AltapayPluginInstall::createPluginTables();
}

register_activation_hook( __FILE__, 'altapayPluginActivation' );
add_action( 'add_meta_boxes', 'altapayAddMetaBoxes' );
add_action( 'wp_ajax_altapay_capture', 'altapayCaptureCallback' );
add_action( 'wp_ajax_altapay_refund', 'altapayRefundCallback' );
add_action( 'wp_ajax_altapay_release_payment', 'altapayReleasePayment' );
add_action( 'admin_footer', 'altapayActionJavascript' );
add_action( 'altapay_checkout_order_review', 'woocommerceOrderReview' );
add_action( 'wp_ajax_create_altapay_payment_page', 'createAltapayPaymentPageCallback' );
add_filter( 'template_include', 'altapay_page_template', 99 );
add_action( 'plugins_loaded', 'init_altapay_settings', 0 );
