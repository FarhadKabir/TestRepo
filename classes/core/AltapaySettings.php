<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

use Altapay\Helpers;
use Altapay\Helpers\Traits\AltapayMaster;
use WC_Order;

require_once dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'altapay' . DIRECTORY_SEPARATOR . 'altapay-php-sdk' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'AltapayMerchantAPI.class.php';

class AltapaySettings {

	use AltapayMaster;

	/**
	 * AltapaySettings constructor.
	 */
	public function __construct() {
		// Load localization files
		add_action( 'init', array( $this, 'altapayLocalizationInit' ) );
		// Add admin menu
		add_action( 'admin_menu', array( $this, 'altapaySettingsMenu' ), 60 );
		// Register settings
		add_action( 'admin_init', array( $this, 'altapayRegisterSettings' ) );
		// Add settings link on plugin page
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'addActionLinks' ) );
		// Order completed interceptor:
		add_action( 'woocommerce_order_status_completed', array( $this, 'altapayOrderStatusCompleted' ) );

		add_action( 'admin_notices', array( $this, 'loginError' ) );
		add_action( 'admin_notices', array( $this, 'captureFailed' ) );
		add_action( 'admin_notices', array( $this, 'captureWarning' ) );
	}

	/**
	 * @param int $orderID
	 * @return void
	 * @throws AltapayMerchantAPIException
	 */
	public function altapayOrderStatusCompleted( $orderID ) {
		$this->startSession();
		// Load order
		$order = new WC_Order( $orderID );
		$txnID = $order->get_transaction_id();

		if ( ! $txnID ) {
			return;
		}

		$api = $this->apiLogin();
		if ( $api instanceof WP_Error ) {
			$_SESSION['altapay_login_error'] = $api->get_error_message();
			echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';

			return;
		}

		$payment = $api->getPayment( $txnID );
		if ( ! $payment ) {
			return;
		}

		$payments = $payment->getPayments();
		$pay      = $payments[0];

		if ( $pay->getCapturedAmount() > 0 ) {
			$this->saveCaptureWarning( 'This order was already fully or partially captured: ' . $orderID );
		} else { // Order wasn't captured and must be captured now.
			$amount        = $pay->getReservedAmount(); // Amount to capture.
			$salesTax      = $order->get_total_tax();
			$orderLines    = array( array() );
			$captureResult = $api->captureReservation( $txnID, $amount, $orderLines, $salesTax );

			if ( ! $captureResult->wasSuccessful() ) {
				$order->add_order_note(
					__(
						'Capture failed: ' . $captureResult->getMerchantErrorMessage(),
						'Altapay'
					)
				); // log to history.
				$this->saveCaptureFailedMessage(
					'Capture failed for order ' . $orderID . ': ' . $captureResult->getMerchantErrorMessage()
				);

				return;
			}

			update_post_meta( $orderID, '_captured', true );
			$order->add_order_note( __( 'Order captured: amount: ' . $amount, 'Altapay' ) );
		}
	}


	/**
	 * Starts the session
	 *
	 * @return void
	 */
	public function startSession() {
		if ( session_id() === '' ) {
			session_start();
		}
	}

	/**
	 * @param string $newMessage
	 * @return void
	 */
	public function saveCaptureFailedMessage( $newMessage ) {
		$message = '';
		if ( isset( $_SESSION['altapay_capture_failed'] ) ) {
			$message = $_SESSION['altapay_capture_failed'] . '<br/>';
		}

		$_SESSION['altapay_capture_failed'] = $message . $newMessage;
	}

	/**
	 * @param string $newMessage
	 * @return void
	 */
	public function saveCaptureWarning( $newMessage ) {
		if ( isset( $_SESSION['altapay_capture_warning'] ) ) {
			$message = $_SESSION['altapay_capture_warning'] . '<br/>';
		} else {
			$message = '';
		}

		$_SESSION['altapay_capture_warning'] = $message . $newMessage;
	}

	/**
	 * Displays login error message
	 *
	 * @return void
	 */
	public function loginError() {
		$this->showUserMessage( 'altapay_login_error', 'error', 'Could not login to the Merchant API: ' );
	}

	/**
	 * @param string $field
	 * @param string $type
	 * @param string $message
	 * @return void
	 */
	public function showUserMessage( $field, $type, $message = '' ) {
		$this->startSession();

		if ( ! isset( $_SESSION[ $field ] ) ) {
			return;
		}

		echo "<div class='$type notice'> <p>$message $_SESSION[$field]</p> </div>";

		unset( $_SESSION[ $field ] );
	}

	/**
	 * Displays failed capture message
	 *
	 * @return void
	 */
	public function captureFailed() {
		$this->showUserMessage( 'altapay_capture_failed', 'error' );
	}

	/**
	 * Displays warning message against capture request
	 *
	 * @return void
	 */
	public function captureWarning() {
		$this->showUserMessage( 'altapay_capture_warning', 'update-nag' );
	}

	/**
	 * @param array $links
	 * @return array
	 */
	public function addActionLinks( $links ) {
		$newLink = array(
			'<a href="' . admin_url( 'admin.php?page=altapay-settings' ) . '">Settings</a>',
		);

		return array_merge( $links, $newLink );
	}


	/**
	 * Loads language file with language specifics
	 *
	 * @return void
	 */
	public function altapayLocalizationInit() {
		 load_plugin_textdomain( 'altapay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add AltaPay settings option in plugins menu
	 *
	 * @return void
	 */
	public function altapaySettingsMenu() {
		add_submenu_page(
			'woocommerce',
			'AltaPay Settings',
			'AltaPay Settings',
			'manage_options',
			'altapay-settings',
			array( $this, 'altapaySettings' )
		);
	}

	/**
	 * Register AltaPay specific settings group including url and api login credentials
	 *
	 * @return void
	 */
	public function altapayRegisterSettings() {
		register_setting( 'altapay-settings-group', 'altapay_gateway_url' );
		register_setting( 'altapay-settings-group', 'altapay_username' );
		register_setting( 'altapay-settings-group', 'altapay_password' );
		register_setting( 'altapay-settings-group', 'altapay_payment_page' );
		register_setting(
			'altapay-settings-group',
			'altapay_terminals_enabled',
			array( $this, 'encodeTerminalsData' )
		);
	}

	/**
	 * Encode the data before saving
	 *
	 * @param array $val
	 * @return string
	 */
	public function encodeTerminalsData( $val ) {
		if ( $val ) {
			$val = wp_json_encode( $val );
		}

		return $val;
	}


	/**
	 * AltaPay settings page with actions and controls
	 *
	 * @return void
	 * @throws AltapayMerchantAPIException
	 */
	public function altapaySettings() {
		$terminals         = false;
		$disabledTerminals = array();
		$enabledTerminals  = array();
		$gatewayURL        = esc_attr( get_option( 'altapay_gateway_url' ) );
		$username          = get_option( 'altapay_username' );
		$password          = get_option( 'altapay_password' );
		$paymentPage       = esc_attr( get_option( 'altapay_payment_page' ) );
		$terminalDetails   = get_option( 'altapay_terminals' );
		$terminalsEnabled  = get_option( 'altapay_terminals_enabled' );

		if ( $terminalDetails ) {
			$terminals = json_decode( get_option( 'altapay_terminals' ) );
		}
		if ( $terminalsEnabled ) {
			$enabledTerminals = json_decode( get_option( 'altapay_terminals_enabled' ) );
		}
		$terminalInfo = json_decode( get_option( 'altapay_terminals' ) );

		if ( is_array( $terminalInfo ) ) {
			foreach ( $terminalInfo as $term ) {
				// The key is the terminal name
				if ( ! in_array( $term->key, $enabledTerminals ) ) {
					array_push( $disabledTerminals, $term->key );
				}
			}
		}

		$pluginDir = plugin_dir_path( __FILE__ );
		// Directory for the terminals
		$terminalDir = $pluginDir . '/../../terminals/';
		// Temp dir in case the one from above is not writable
		$tmpDir = sys_get_temp_dir();

		foreach ( $disabledTerminals as $disabledTerm ) {
			$disabledTerminalFileName = $disabledTerm . '.class.php';
			$path                     = $terminalDir . $disabledTerminalFileName;
			$tmpPath                  = $tmpDir . '/' . $disabledTerminalFileName;
			// Check if there is a terminal created so it can  be removed
			if ( file_exists( $path ) ) {
				unlink( $path );
			} elseif ( file_exists( $tmpPath ) ) {
				unlink( $tmpPath );
			}
		}

		if ( isset( $_REQUEST['settings-updated'] ) ) {
			$this->refreshTerminals();
		}
		?>

		<div class="wrap" style="margin-top:2%;">
			<h1><?php esc_html_e( 'AltaPay Settings', 'altapay' ); ?></h1>
			<?php
			if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
				wp_die( sprintf( 'AltaPay for WooCommerce requires PHP 5.4 or higher. You’re still on %s.', PHP_VERSION ) );
			} else {
				$blade = new Helpers\AltapayHelpers();
				echo $blade->loadBladeLibrary()->run(
					'forms.adminSettings',
					array(
						'gatewayURL'       => $gatewayURL,
						'username'         => $username,
						'password'         => $password,
						'paymentPage'      => $paymentPage,
						'terminals'        => $terminals,
						'enabledTerminals' => $enabledTerminals,
					)
				);
				?>
				<script>
					jQuery(document).ready(function ($) {
						jQuery('#create_altapay_payment_page').unbind().on('click', function (e) {
							var data = {
								'action': 'create_altapay_payment_page',
							};
							// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
							jQuery.post(ajaxurl, data, function (response) {
								result = jQuery.parseJSON(response);
								if (result.status == 'ok') {
									jQuery('#altapay_payment_page').val(result.page_id);
									jQuery('#payment-page-msg').text(result.message);
									jQuery('#create_altapay_payment_page').attr('disabled', 'disabled');
								} else {
									jQuery('#payment-page-msg').text(result.message);
								}
							});

						});
					});
				</script>

				<?php
				if ( $gatewayURL && $username ) {
					$this->altapayRefreshConnectionForm();
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Method for refreshing terminals on AltaPay settings page
	 *
	 * @return void
	 * @throws AltapayMerchantAPIException
	 */
	function refreshTerminals() {
		$api = $this->apiLogin();
		if ( $api instanceof WP_Error ) {
			$_SESSION['altapay_login_error'] = $api->get_error_message();
			echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
			// Delete terminals and enabled terminals from database
			update_option( 'altapay_terminals', '' );
			update_option( 'altapay_terminals_enabled', '' );
			?>
			<script>
				setTimeout("location.reload()", 1500);
			</script>
			<?php
			return;
		}
		echo '<p><b>' . __( 'Connection OK !', 'altapay' ) . '</b></p>';
		// Get list of terminals information
		$terminalInfo = $api->getTerminals();
		$terminals    = array();
		$terms        = $terminalInfo->getTerminals();

		foreach ( $terms as $terminal ) {
			$terminals[] = array(
				'key'    => str_replace( array( ' ', '-' ), '_', $terminal->getTitle() ),
				'name'   => $terminal->getTitle(),
				'nature' => $terminal->getNature(),
			);
		}

		update_option( 'altapay_terminals', wp_json_encode( $terminals ) );
		?>
		<script>
			setTimeout("location.reload()", 1500);
		</script>
		<?php
	}

	/**
	 * Form with refresh connection button on AltaPay page
	 *
	 * @return void
	 */
	private function altapayRefreshConnectionForm() {
		$terminals = get_option( 'altapay_terminals' );
		if ( ! $terminals ) {
			?>
			<p><?php esc_html_e( 'Terminals missing, please click - Refresh connection', 'altapay' ); ?></p>
		<?php } else { ?>
			<p><?php esc_html_e( 'Click below to re-create terminal data', 'altapay' ); ?></p>
		<?php } ?>
		<form method="post" action="#refresh_connection">
			<input type="hidden" value="true" name="refresh_connection">
			<input type="submit" value="<?php esc_html_e( 'Refresh connection', 'altapay' ); ?>" name="refresh-connection"
				   class="button" style="color: #006064; border-color: #006064;">
		</form>
		<?php
		// TODO Make use of WordPress notice and error handling
		// Test connection
		if ( isset( $_POST['refresh_connection'] ) ) {
			$this->refreshTerminals();
		}
	}

}
