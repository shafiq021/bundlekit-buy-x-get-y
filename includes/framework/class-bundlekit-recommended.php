<?php
defined( 'ABSPATH' ) || exit;

/**
 * Recommended Plugins tab.
 *
 * Rule (per spec): only show plugins the client has NOT installed.
 * Installed/active plugins are filtered out here — they are listed
 * on the About tab instead, under "Installed BundleKit add-ons".
 *
 * Each entry:
 *  slug         — unique key, also used by the AJAX installer
 *  name         — display name
 *  description  — one line
 *  plugin       — plugin file path, e.g. bundlekit-fixed-bundle/bundlekit-fixed-bundle.php
 *  download_url — zip URL to install from (GitHub release zip for now;
 *                 empty string = "coming soon", no install button)
 *  type         — bundlekit-addon | standalone
 */
class BundleKit_Recommended {

	public static function all() {
		$items = array(
			array(
				'slug'         => 'bundlekit-fixed-bundle',
				'name'         => __( 'BundleKit – Fixed Bundle', 'bundlekit' ),
				'description'  => __( 'Sell a fixed group of products as one kit, at a fixed price or with a % discount. Ported from Smart Bundles.', 'bundlekit' ),
				'plugin'       => 'bundlekit-fixed-bundle/bundlekit-fixed-bundle.php',
				'download_url' => 'https://github.com/shafiq021/BundleKit-Fixed-Bundle/archive/refs/heads/main.zip',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-buy-x-get-y',
				'name'         => __( 'BundleKit – Buy X Get Y / BOGO', 'bundlekit' ),
				'description'  => __( 'Buy X, get Y free (or at a discount). Cheapest item free, cart rules, and per-product rules.', 'bundlekit' ),
				'plugin'       => 'bundlekit-buy-x-get-y/bundlekit-buy-x-get-y.php',
				'download_url' => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-multipack',
				'name'         => __( 'BundleKit – Multipack', 'bundlekit' ),
				'description'  => __( 'Quantity packs for one product: 2-pack, 3-pack, 5-pack with tiered pricing.', 'bundlekit' ),
				'plugin'       => 'bundlekit-multipack/bundlekit-multipack.php',
				'download_url' => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-mix-match',
				'name'         => __( 'BundleKit – Mix & Match / BYOB', 'bundlekit' ),
				'description'  => __( 'Let customers build their own bundle: pick any X products from a category for one price.', 'bundlekit' ),
				'plugin'       => 'bundlekit-mix-match/bundlekit-mix-match.php',
				'download_url' => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'bundlekit-build-a-box',
				'name'         => __( 'BundleKit – Build-a-Box', 'bundlekit' ),
				'description'  => __( 'Fill a box: choose products until the box is full, with a progress bar and a fixed box price.', 'bundlekit' ),
				'plugin'       => 'bundlekit-build-a-box/bundlekit-build-a-box.php',
				'download_url' => '',
				'type'         => 'bundlekit-addon',
			),
			array(
				'slug'         => 'woocommerce-stock-inquiry',
				'name'         => __( 'WooCommerce Stock Inquiry', 'bundlekit' ),
				'description'  => __( 'Let customers ask about stock on out-of-stock products. A standalone plugin by the same developer.', 'bundlekit' ),
				'plugin'       => 'woocommerce-stock-inquiry/woocommerce-stock-inquiry.php',
				'download_url' => '',
				'type'         => 'standalone',
			),
		);
		return apply_filters( 'bundlekit_recommended_plugins', $items );
	}

	public static function get_by_slug( $slug ) {
		foreach ( self::all() as $item ) {
			if ( $item['slug'] === $slug ) {
				return $item;
			}
		}
		return null;
	}

	public static function is_installed( $item ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $item['plugin'] ] );
	}

	public static function is_active( $item ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $item['plugin'] );
	}

	/** Only plugins the client has NOT installed. */
	public static function not_installed() {
		return array_values(
			array_filter(
				self::all(),
				function ( $item ) {
					return ! self::is_installed( $item );
				}
			)
		);
	}

	public static function render() {
		$items = self::not_installed();
		?>
		<div class="bundlekit-card">
			<h2><?php esc_html_e( 'Recommended Plugins', 'bundlekit' ); ?></h2>
			<p><?php esc_html_e( 'These are Shafiq Khan’s other plugins. Only plugins you have not installed are shown here — installed ones appear on the About tab.', 'bundlekit' ); ?></p>
			<?php if ( empty( $items ) ) : ?>
				<p><strong><?php esc_html_e( 'All recommended plugins are already installed. Nice — you have the full set.', 'bundlekit' ); ?></strong></p>
			<?php else : ?>
				<div class="bundlekit-grid">
					<?php foreach ( $items as $item ) : ?>
						<div class="bundlekit-plugin-card" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
							<h3><?php echo esc_html( $item['name'] ); ?></h3>
							<p><?php echo esc_html( $item['description'] ); ?></p>
							<p><span class="bundlekit-badge"><?php echo 'bundlekit-addon' === $item['type'] ? esc_html__( 'BundleKit add-on', 'bundlekit' ) : esc_html__( 'Standalone plugin', 'bundlekit' ); ?></span></p>
							<?php if ( ! empty( $item['download_url'] ) ) : ?>
								<button type="button" class="button button-primary bundlekit-install-btn" data-slug="<?php echo esc_attr( $item['slug'] ); ?>"><?php esc_html_e( 'Install & Activate', 'bundlekit' ); ?></button>
								<span class="bundlekit-install-status" aria-live="polite"></span>
							<?php else : ?>
								<button type="button" class="button" disabled><?php esc_html_e( 'Coming soon', 'bundlekit' ); ?></button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
