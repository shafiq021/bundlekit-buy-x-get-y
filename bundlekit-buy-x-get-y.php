<?php
/**
 * Plugin Name: BundleKit – Buy X Get Y
 * Plugin URI: https://github.com/shafiq021/BundleKit-Buy-X-Get-Y
 * Description: Buy X Get Y add-on for BundleKit. Create global offers: buy X units (or spend $Y) and get a reward — a specific product free, a percentage or fixed discount, or the cheapest / most-expensive item free. Auto-add or let the customer pick the gift. Requires WooCommerce.
 * Version: 1.0.4
 * Author: Shafiq Khan
 * Author URI: https://www.linkedin.com/in/shafiqkhan21/
 * Text Domain: bundlekit-buy-x-get-y
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * NOTE — no separate framework plugin (WPClever model):
 * The BundleKit framework lives inside this plugin at
 * includes/framework/ and is loaded by the line below. It is NOT a
 * separate plugin, it never appears in the Plugins list, and the user
 * never installs it. Every BundleKit add-on ships the same framework
 * library; only the first loaded copy runs, the rest skip theirs.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BUNDLEKIT_BUY_X_GET_Y_VERSION' ) ) {
	return;
}

define( 'BUNDLEKIT_BUY_X_GET_Y_VERSION', '1.0.4' );
define( 'BUNDLEKIT_BUY_X_GET_Y_FILE', __FILE__ );
define( 'BUNDLEKIT_BUY_X_GET_Y_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLEKIT_BUY_X_GET_Y_URL', plugin_dir_url( __FILE__ ) );

// Load the embedded BundleKit framework (shared admin: About Developer /
// Recommended Plugins). If another BundleKit add-on already loaded its
// copy, this file returns immediately and that copy is used instead.
require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/framework/bundlekit-framework.php';

/**
 * If the old separate "BundleKit" plugin (from Fixed Bundle v1.1.0) is
 * still installed and active, tell the admin it can now be safely
 * deleted — the framework is built into this add-on. This is a
 * one-time clean-up notice, not an error.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'bundlekit/bundlekit.php' ) ) {
			return;
		}
		echo '<div class="notice notice-info is-dismissible"><p><strong>BundleKit – Buy X Get Y:</strong> the BundleKit framework is now built into this plugin, so the separate <strong>BundleKit</strong> plugin is no longer needed. You can safely deactivate and delete it from Plugins — this add-on will keep working on its built-in copy.</p></div>';
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BUNDLEKIT_BUY_X_GET_Y_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BUNDLEKIT_BUY_X_GET_Y_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>BundleKit – Buy X Get Y</strong> needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}

		require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/class-bundlekit-buy-x-get-y.php';
		require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/class-bundlekit-buy-x-get-y-styling.php';
		require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/class-bundlekit-buy-x-get-y-admin.php';
		require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/class-bundlekit-buy-x-get-y-pricing.php';
		require_once BUNDLEKIT_BUY_X_GET_Y_DIR . 'includes/class-bundlekit-buy-x-get-y-frontend.php';

		BundleKit_Buy_X_Get_Y::init();
		BundleKit_Buy_X_Get_Y_Admin::init();
		BundleKit_Buy_X_Get_Y_Pricing::init();
		BundleKit_Buy_X_Get_Y_Frontend::init();
	},
	25
);

// Register with the BundleKit framework (shows on About tab as an installed add-on).
add_filter(
	'bundlekit_addons',
	function ( $addons ) {
		$addons[] = array(
			'slug'    => 'bundlekit-buy-x-get-y',
			'name'    => 'BundleKit – Buy X Get Y',
			'version' => BUNDLEKIT_BUY_X_GET_Y_VERSION,
			'file'    => BUNDLEKIT_BUY_X_GET_Y_FILE,
		);
		return $addons;
	}
);

// Register the Buy X Get Y global page (BundleKit > Buy X Get Y):
// Offers (global rules), Settings (global on/off), Styling Controls
// (global styling) and Custom CSS. The framework only adds the
// submenu if this class is loaded, so the page never appears without
// the add-on itself.
add_filter(
	'bundlekit_addon_pages',
	function ( $pages ) {
		$pages[] = array(
			'slug'   => 'bundlekit-buy-x-get-y',
			'title'  => __( 'Buy X Get Y', 'bundlekit-buy-x-get-y' ),
			'render' => array( 'BundleKit_Buy_X_Get_Y_Admin', 'global_page' ),
		);
		return $pages;
	}
);
