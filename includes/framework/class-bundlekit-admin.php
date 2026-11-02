<?php
defined( 'ABSPATH' ) || exit;

/**
 * BundleKit admin page — exactly two tabs, as specified:
 *  1. About Developer
 *  2. Recommended Plugins (only plugins the client has NOT installed)
 *
 * Admin CSS/JS is loaded only on this page (checked by $hook).
 */
class BundleKit_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_bundlekit_install_plugin', array( __CLASS__, 'ajax_install_plugin' ) );
	}

	public static function menu() {
		// Top-level menu (framework 1.0.1): BundleKit stands on its
		// own in the admin sidebar instead of sitting under
		// WooCommerce. The page slug stays 'bundlekit', so
		// admin.php?page=bundlekit (and its tab URLs) keep working.
		// Position 56 puts it right after WooCommerce in the menu.
		// Capability is unchanged (manage_woocommerce), so the same
		// people who could open it before still can.
		add_menu_page(
			__( 'BundleKit', 'bundlekit' ),
			__( 'BundleKit', 'bundlekit' ),
			'manage_woocommerce',
			'bundlekit',
			array( __CLASS__, 'page' ),
			'dashicons-archive',
			56
		);
		// Add-on pages (framework 1.0.2): each add-on may register
		// one submenu page under BundleKit. This builds the tree:
		// BundleKit > Fixed Bundle > Settings, Styling Controls —
		// and it scales, since the next add-on registers its own
		// page the same way. Pages come from the
		// `bundlekit_addon_pages` filter: slug, title, render
		// callback. A page whose render callback is not callable
		// (e.g. its add-on is not loaded) is skipped.
		foreach ( self::addon_pages() as $page ) {
			add_submenu_page(
				'bundlekit',
				$page['title'],
				$page['title'],
				'manage_woocommerce',
				$page['slug'],
				$page['render']
			);
		}
	}

	/**
	 * @return array<string,array{slug:string,title:string,render:callable}>
	 */
	public static function addon_pages() {
		$pages = apply_filters( 'bundlekit_addon_pages', array() );
		$out   = array();
		foreach ( (array) $pages as $page ) {
			if ( empty( $page['slug'] ) || empty( $page['title'] ) || ! is_callable( $page['render'] ) ) {
				continue;
			}
			$out[ sanitize_key( $page['slug'] ) ] = array(
				'slug'   => sanitize_key( $page['slug'] ),
				'title'  => sanitize_text_field( $page['title'] ),
				'render' => $page['render'],
			);
		}
		return $out;
	}

	public static function enqueue( $hook ) {
		// Top-level menu hook, plus any add-on submenu page hook
		// (bundlekit_page_<slug>).
		if ( 'toplevel_page_bundlekit' !== $hook && 0 !== strpos( $hook, 'bundlekit_page_' ) ) {
			return;
		}
		wp_register_style( 'bundlekit-base', BUNDLEKIT_URL . 'assets/css/base.css', array(), BUNDLEKIT_VERSION );
		wp_enqueue_style( 'bundlekit-base' );
		wp_register_style( 'bundlekit-admin', BUNDLEKIT_URL . 'assets/css/admin.css', array( 'bundlekit-base' ), BUNDLEKIT_VERSION );
		wp_enqueue_style( 'bundlekit-admin' );
		wp_register_script( 'bundlekit-admin', BUNDLEKIT_URL . 'assets/js/admin.js', array( 'jquery' ), BUNDLEKIT_VERSION, true );
		wp_localize_script(
			'bundlekit-admin',
			'bundlekitAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bundlekit_install_plugin' ),
				'i18n'    => array(
					'installing' => __( 'Installing…', 'bundlekit' ),
					'activating' => __( 'Activating…', 'bundlekit' ),
					'active'     => __( 'Active', 'bundlekit' ),
					'error'      => __( 'Install failed. Please try again.', 'bundlekit' ),
				),
			)
		);
		wp_enqueue_script( 'bundlekit-admin' );
	}

	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'about'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'about', 'recommended' ), true ) ? $tab : 'about';
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bundlekit' ) );
		}
		$tab = self::current_tab();
		?>
		<div class="wrap bundlekit-wrap">
			<h1><?php esc_html_e( 'BundleKit', 'bundlekit' ); ?> <span class="bundlekit-version"><?php echo esc_html( BUNDLEKIT_VERSION ); ?></span></h1>
			<p class="bundlekit-tagline"><?php esc_html_e( 'One lightweight framework. Every bundle feature is a separate small add-on.', 'bundlekit' ); ?></p>

			<nav class="nav-tab-wrapper bundlekit-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit&tab=about' ) ); ?>" class="nav-tab <?php echo 'about' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'About Developer', 'bundlekit' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit&tab=recommended' ) ); ?>" class="nav-tab <?php echo 'recommended' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Recommended Plugins', 'bundlekit' ); ?></a>
			</nav>

			<?php if ( 'about' === $tab ) : ?>
				<?php self::render_about(); ?>
			<?php else : ?>
				<?php BundleKit_Recommended::render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_about() {
		$addons = bundlekit_registered_addons();
		?>
		<div class="bundlekit-card">
			<h2><?php esc_html_e( 'About the developer', 'bundlekit' ); ?></h2>
			<p><strong>Shafiq Khan</strong> — <?php esc_html_e( 'WordPress Developer at InstaCare, Lahore, Pakistan.', 'bundlekit' ); ?></p>
			<p><?php esc_html_e( 'WordPress & WooCommerce · Elementor Pro · Custom Themes & Plugins · Performance & SEO · API Integrations', 'bundlekit' ); ?></p>
			<p>
				<a class="button" href="https://github.com/shafiq021" target="_blank" rel="noopener"><?php esc_html_e( 'GitHub', 'bundlekit' ); ?></a>
				<a class="button" href="https://www.linkedin.com/in/shafiqkhan21/" target="_blank" rel="noopener"><?php esc_html_e( 'LinkedIn', 'bundlekit' ); ?></a>
				<a class="button" href="https://github.com/shafiq021/Smart-Bundles-for-WooCommerce" target="_blank" rel="noopener"><?php esc_html_e( 'Smart Bundles on GitHub', 'bundlekit' ); ?></a>
			</p>
			<p class="description"><?php esc_html_e( 'BundleKit is built by Shafiq Khan. The framework itself is intentionally light — it adds no bundle logic on its own. Install only the add-ons you need.', 'bundlekit' ); ?></p>
		</div>

		<div class="bundlekit-card">
			<h2><?php esc_html_e( 'Installed BundleKit add-ons', 'bundlekit' ); ?></h2>
			<?php if ( empty( $addons ) ) : ?>
				<p><?php esc_html_e( 'No BundleKit add-ons are active yet. Open the Recommended Plugins tab to install one.', 'bundlekit' ); ?></p>
			<?php else : ?>
				<ul class="bundlekit-addon-list">
					<?php foreach ( $addons as $addon ) : ?>
						<li><strong><?php echo esc_html( $addon['name'] ); ?></strong> — <?php echo esc_html( $addon['version'] ); ?> <span class="bundlekit-badge bundlekit-badge-active"><?php esc_html_e( 'Active', 'bundlekit' ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'WooCommerce version:', 'bundlekit' ); ?> <?php echo esc_html( WC()->version ); ?> · <?php esc_html_e( 'BundleKit version:', 'bundlekit' ); ?> <?php echo esc_html( BUNDLEKIT_VERSION ); ?></p>
		</div>
		<?php
	}

	/**
	 * AJAX: install + activate a recommended plugin.
	 * Only allows slugs from the curated Recommended list — never an arbitrary slug/URL from the request.
	 */
	public static function ajax_install_plugin() {
		check_ajax_referer( 'bundlekit_install_plugin' );
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to install plugins.', 'bundlekit' ) ) );
		}
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$item = BundleKit_Recommended::get_by_slug( $slug );
		if ( ! $item || empty( $item['download_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This plugin cannot be installed from here yet.', 'bundlekit' ) ) );
		}
		if ( BundleKit_Recommended::is_installed( $item ) ) {
			$result = activate_plugin( $item['plugin'] );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'status' => 'active' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$installed = $upgrader->install( esc_url_raw( $item['download_url'] ) );
		if ( is_wp_error( $installed ) || ! $installed ) {
			wp_send_json_error( array( 'message' => is_wp_error( $installed ) ? $installed->get_error_message() : __( 'Install failed.', 'bundlekit' ) ) );
		}
		$result = activate_plugin( $item['plugin'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'status' => 'active' ) );
	}
}
