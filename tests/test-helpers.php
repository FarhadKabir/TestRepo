<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/helpers/AltapayHelpers.php';


class AltapayHelpersTest extends TestCase {

	/**
	 * Test when shipping shipping country provided
	 *
	 * @return void
	 */
	public function testSetShippingMethodWithShippingCountry() {
		$classImport  = new AltapayHelpers();
		$customerInfo = array(
			'shipping_country' => 'America',
			'shipping_city'    => 'London',
			'shipping_address' => 'Boulevard',
			'shipping_postal'  => 'Main Embrose Hall',
			'shipping_region'  => 'Malwana Pur',
			'billing_country'  => 'Jeddah',
			'billing_city'     => 'Maldives',
			'billing_address'  => 'Scheme 3',
			'billing_region'   => 'Pindi',
			'billing_postal'   => 'Rawal',
		);

		$result = array(
			'shipping_country' => 'America',
			'shipping_city'    => 'London',
			'shipping_address' => 'Boulevard',
			'shipping_postal'  => 'Main Embrose Hall',
			'shipping_region'  => 'Malwana Pur',
			'billing_country'  => 'Jeddah',
			'billing_city'     => 'Maldives',
			'billing_address'  => 'Scheme 3',
			'billing_region'   => 'Pindi',
			'billing_postal'   => 'Rawal',
		);
		$this->assertEquals( $result, $classImport->setShippingAddress( $customerInfo ) );
	}

	/**
	 * Test when shipping country is not provided
	 *
	 * @return void
	 */
	public function testSetShippingMethodWithMissingShippingCountry() {
		 $classImport = new AltapayHelpers();
		$customerInfo = array(
			'shipping_country' => '',
			'shipping_city'    => 'London',
			'shipping_address' => 'Boulevard',
			'shipping_postal'  => 'Main Embrose Hall',
			'shipping_region'  => 'Malwana Pur',
			'billing_country'  => 'Jeddah',
			'billing_city'     => 'Maldives',
			'billing_address'  => 'Scheme 3',
			'billing_region'   => 'Pindi',
			'billing_postal'   => 'Rawal',
		);

		$result = array(
			'shipping_country' => 'Jeddah',
			'shipping_city'    => 'Maldives',
			'shipping_address' => 'Scheme 3',
			'shipping_postal'  => 'Rawal',
			'shipping_region'  => 'Pindi',
			'billing_country'  => 'Jeddah',
			'billing_city'     => 'Maldives',
			'billing_address'  => 'Scheme 3',
			'billing_region'   => 'Pindi',
			'billing_postal'   => 'Rawal',
		);

		$this->assertEquals( $result, $classImport->setShippingAddress( $customerInfo ) );
	}

	/**
	 * Test when billing country is missing
	 *
	 * @return void
	 */
	public function testSetShippingMethodWithMissingBillingCountry() {
		$classImport  = new AltapayHelpers();
		$customerInfo = array(
			'shipping_country' => '',
			'shipping_city'    => 'London',
			'shipping_address' => 'Boulevard',
			'shipping_postal'  => 'Main Embrose Hall',
			'shipping_region'  => 'Malwana Pur',
			'billing_country'  => '',
			'billing_city'     => 'Maldives',
			'billing_address'  => 'Scheme 3',
			'billing_region'   => 'Pindi',
			'billing_postal'   => 'Rawal',
		);

		$error  = $classImport->setShippingAddress( $customerInfo );
		$result = 'Shipping country is required';
		$this->assertEquals( $result, $error->get_error_message() );
	}
}