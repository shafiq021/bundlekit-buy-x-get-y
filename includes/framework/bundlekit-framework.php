<?php
/**
 * BundleKit framework — embedded library, NOT a plugin.
 *
 * This is the WPClever model the user asked for: the framework is built
 * into every BundleKit add-on, so the Plugins list shows only the add-on
 * itself — there is no separate "BundleKit" plugin to install, activate
 * or update. The framework code is shared in the sense that every add-on
 * ships the same library, and only one copy ever runs:
 *
 *   - The first BundleKit add-on WordPress loads defines BUNDLEKIT_VERSION
 *     and boots the admin (About Developer / Recommended Plugins, once).
 *   - Any later add-on sees the constant already defined and skips its
 *     own copy — its features still register through the
 *     `bundlekit_addons` filter, and the shared admin still lists them.
 *   - If the first add-on is deactivated, the next add-on's copy boots
 *     instead on the following request. No separate plugin is ever needed.
 *
 * Source of truth for this library lives in ~/workspace/bundlekit/bundlekit/
 * (the old standalone plugin folder). When the framework changes, copy it
 * into each add-on's includes/framework/ and bump BUNDLEKIT_FRAMEWORK_VERSION.
 * The framework version below is independent of any single add-on's version.
 */

defined( 'ABSPATH' ) || exit;

// Shared admin UI helpers (Settings / Styling Controls / Display markup
// for every add-on's global pages). Loaded from EVERY copy, before the
// single-boot guard below: the function_exists() guards inside the file
// make double-loading safe, and this way a newer add-on still gets the
// helpers even when an older add-on's framework booted first.
require_once plugin_dir_path( __FILE__ ) . 'includes/bundlekit-admin-ui.php';

// Already loaded by another BundleKit add-on — use that copy, load nothing twice.
if ( defined( 'BUNDLEKIT_VERSION' ) ) {
	return;
}

define( 'BUNDLEKIT_FRAMEWORK_VERSION', '1.0.4' );
define( 'BUNDLEKIT_VERSION', BUNDLEKIT_FRAMEWORK_VERSION );
define( 'BUNDLEKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLEKIT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Is WooCommerce active?
 *
 * Wrapped in function_exists(): PHP reports a duplicate top-level
 * function as a COMPILE-time fatal in the included file — before the
 * `defined( 'BUNDLEKIT_VERSION' )` guard below ever runs. So when two
 * BundleKit add-ons are active, the second copy's file would fatal on
 * this declaration no matter what the guard says. A conditional
 * declaration is evaluated at runtime, so the guard works as intended.
 */
if ( ! function_exists( 'bundlekit_woocommerce_active' ) ) {
	function bundlekit_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

/**
 * Registered BundleKit add-ons.
 * Add-ons register via the `bundlekit_addons` filter:
 *   [ 'slug' => 'bundlekit-fixed-bundle', 'name' => '...', 'version' => '1.0.0', 'file' => __FILE__ ]
 *
 * @return array[]
 *
 * Same function_exists() note as bundlekit_woocommerce_active()
 * above: without it, two active add-ons fatal at compile time.
 */
if ( ! function_exists( 'bundlekit_registered_addons' ) ) {
	function bundlekit_registered_addons() {
	$addons = apply_filters( 'bundlekit_addons', array() );
	$out    = array();
	foreach ( (array) $addons as $addon ) {
		if ( empty( $addon['slug'] ) || empty( $addon['name'] ) ) {
			continue;
		}
		$out[ sanitize_key( $addon['slug'] ) ] = array(
			'slug'    => sanitize_key( $addon['slug'] ),
			'name'    => sanitize_text_field( $addon['name'] ),
			'version' => isset( $addon['version'] ) ? sanitize_text_field( $addon['version'] ) : '',
			'file'    => isset( $addon['file'] ) ? $addon['file'] : '',
		);
	}
	return $out;
	}
}

// Boot the shared admin (WooCommerce > BundleKit, 2 tabs) once, only if
// WooCommerce is active. HPOS/Blocks compatibility is declared by the
// add-on plugin file itself, not by this library.
add_action(
	'plugins_loaded',
	function () {
		if ( ! bundlekit_woocommerce_active() ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><strong>BundleKit</strong> needs WooCommerce to be active.</p></div>';
				}
			);
			return;
		}

		require_once BUNDLEKIT_DIR . 'class-bundlekit-admin.php';
		require_once BUNDLEKIT_DIR . 'class-bundlekit-recommended.php';

		BundleKit_Admin::init();
	},
	20
);
