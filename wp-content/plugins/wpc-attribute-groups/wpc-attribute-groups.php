<?php
/*
Plugin Name: WPC Smart Attribute Groups for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Smart Attribute Groups give you the possibility to display product attributes in separate groups.
Version: 1.1.6
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-attribute-groups
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.7
WC requires at least: 3.0
WC tested up to: 9.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCAG_VERSION' ) && define( 'WPCAG_VERSION', '1.1.6' );
! defined( 'WPCAG_LITE' ) && define( 'WPCAG_LITE', __FILE__ );
! defined( 'WPCAG_FILE' ) && define( 'WPCAG_FILE', __FILE__ );
! defined( 'WPCAG_URI' ) && define( 'WPCAG_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCAG_DIR' ) && define( 'WPCAG_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCAG_REVIEWS' ) && define( 'WPCAG_REVIEWS', 'https://wordpress.org/support/plugin/wpc-attribute-groups/reviews/?filter=5' );
! defined( 'WPCAG_SUPPORT' ) && define( 'WPCAG_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcpq&utm_campaign=wporg' );
! defined( 'WPCAG_CHANGELOG' ) && define( 'WPCAG_CHANGELOG', 'https://wordpress.org/plugins/wpc-attribute-groups/#developers' );
! defined( 'WPCAG_DISCUSSION' ) && define( 'WPCAG_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-attribute-groups' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCAG_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcag_init' ) ) {
	add_action( 'plugins_loaded', 'wpcag_init', 11 );

	function wpcag_init() {
		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcag_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcag' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcag {
				public function __construct() {
					require_once 'includes/class-backend.php';
					require_once 'includes/class-frontend.php';
				}
			}

			new WPCleverWpcag();
		}
	}
}

if ( ! function_exists( 'wpcag_notice_wc' ) ) {
	function wpcag_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Smart Attribute Groups</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
