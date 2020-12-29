<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

class AltapayPluginInstall {

	/**
	 * Create required tables at the time of plugin installation
	 *
	 * @return void
	 */
	public static function createPluginTables() {
		global $wpdb;
		$tableName      = $wpdb->prefix . 'altapayCreditCardDetails';
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            userID varchar(200) DEFAULT '' NOT NULL,
            cardBrand varchar(200) DEFAULT '' NOT NULL,
            creditCardNumber varchar(200) DEFAULT '' NOT NULL,
            cardExpiryDate varchar(200) DEFAULT '' NOT NULL,
            ccToken varchar(200) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charsetCollate;";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

