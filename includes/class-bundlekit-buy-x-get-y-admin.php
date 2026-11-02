<?php
defined( 'ABSPATH' ) || exit;

/**
 * BundleKit – Buy X Get Y — admin.
 *
 * One global page (BundleKit > Buy X Get Y) with four tabs: Offers
 * (the global offer rules), Settings (kill-switch + coupon
 * stacking), Styling Controls (schema-driven, live preview) and
 * Custom CSS. No product-editor tabs — offers are global only.
 */
class BundleKit_Buy_X_Get_Y_Admin {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'global_save' ) );
		add_action( 'wp_ajax_bundlekit_bogo_variations', array( __CLASS__, 'ajax_variations' ) );
	}

	public static function admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'bundlekit_page_bundlekit-buy-x-get-y' !== $screen->id ) {
			return;
		}
		// Shared BundleKit admin UI (framework 1.0.4): the Settings /
		// Styling Controls design system. Each add-on loads its own
		// bundled copy — no cross-plugin dependency.
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'bundlekit-admin-ui', BUNDLEKIT_BUY_X_GET_Y_URL . 'includes/framework/assets/css/bundlekit-admin-ui.css', array(), BUNDLEKIT_FRAMEWORK_VERSION );
		wp_enqueue_script( 'bundlekit-admin-ui', BUNDLEKIT_BUY_X_GET_Y_URL . 'includes/framework/assets/js/bundlekit-admin-ui.js', array(), BUNDLEKIT_FRAMEWORK_VERSION, true );
		// Offers repeater + product search.
		wp_enqueue_style( 'bundlekit-buy-x-get-y-admin', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/css/admin.css', array(), BUNDLEKIT_BUY_X_GET_Y_VERSION );
		wp_enqueue_script( 'bundlekit-buy-x-get-y-admin', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/js/admin.js', array( 'jquery' ), BUNDLEKIT_BUY_X_GET_Y_VERSION, true );
		wp_localize_script(
			'bundlekit-buy-x-get-y-admin',
			'bundlekitBogoAdmin',
			array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'searchNonce'     => wp_create_nonce( 'search-products' ),
				'variationNonce'  => wp_create_nonce( 'bundlekit_bogo_admin' ),
				'confirmRemove'   => __( 'Remove this offer? This cannot be undone.', 'bundlekit-buy-x-get-y' ),
				'noVariationText' => __( 'No variation (parent product)', 'bundlekit-buy-x-get-y' ),
			)
		);
		// Styling Controls: the frontend CSS so the live preview looks
		// like the real widgets, the WP color picker, and the preview
		// script.
		wp_enqueue_style( 'bundlekit-buy-x-get-y-frontend', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_BUY_X_GET_Y_VERSION );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'bundlekit-buy-x-get-y-styling', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/js/bogo-styling.js', array( 'jquery', 'wp-color-picker' ), BUNDLEKIT_BUY_X_GET_Y_VERSION, true );
		$styling_controls = BundleKit_Buy_X_Get_Y_Styling::all_controls();
		$var_map          = array();
		$class_toggles    = array(
			'show_banner'       => 'bxgy-no-banner',
			'show_progress'     => 'bxgy-no-progress',
			'show_picker'       => 'bxgy-no-picker',
			'show_reward_badge' => 'bxgy-no-reward-badge',
			'show_mini_cart'    => 'bxgy-no-mini-cart',
		);
		$defaults = array();
		foreach ( $styling_controls as $spec ) {
			$defaults[ $spec['key'] ] = (string) $spec['default'];
			if ( '' !== $spec['css_var'] ) {
				$var_map[ $spec['key'] ] = array(
					'var'  => $spec['css_var'],
					'type' => $spec['type'],
					'unit' => $spec['unit'],
				);
			}
		}
		wp_localize_script(
			'bundlekit-buy-x-get-y-styling',
			'bundlekitBogoStyling',
			array(
				'varMap'       => $var_map,
				'classToggles' => $class_toggles,
				'defaults'     => $defaults,
				'fonts'        => BundleKit_Buy_X_Get_Y::font_stacks(),
				// Keys where 0 means "unset": mirrors the $skip list in
				// BundleKit_Buy_X_Get_Y_Frontend::style_attr().
				'skipZero'     => BundleKit_Buy_X_Get_Y_Styling::skip_zero_keys(),
			)
		);
	}

	/* ================= global page ================= */

	public static function global_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bundlekit-buy-x-get-y' ) );
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'offers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = in_array( $tab, array( 'offers', 'settings', 'styling', 'css' ), true ) ? $tab : 'offers';
		$tabs = array(
			'offers'   => array( 'label' => __( 'Offers', 'bundlekit-buy-x-get-y' ), 'icon' => 'dashicons-gift' ),
			'settings' => array( 'label' => __( 'Settings', 'bundlekit-buy-x-get-y' ), 'icon' => 'dashicons-admin-generic' ),
			'styling'  => array( 'label' => __( 'Styling Controls', 'bundlekit-buy-x-get-y' ), 'icon' => 'dashicons-art' ),
			'css'      => array( 'label' => __( 'Custom CSS', 'bundlekit-buy-x-get-y' ), 'icon' => 'dashicons-editor-code' ),
		);
		?>
		<div class="wrap">
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'bundlekit-buy-x-get-y' ); ?></p></div>
			<?php endif; ?>
			<div class="bk-ui" data-bk-ui>
				<?php
				bundlekit_ui_head(
					'dashicons-gift',
					__( 'Buy X Get Y', 'bundlekit-buy-x-get-y' ),
					__( 'Global BOGO offers. Each offer is a trigger (buy X / spend $Y) plus a reward (free product, discount, or cheapest/most-expensive free). Offers apply site-wide — there are no per-product settings. Turning Buy X Get Y off hides every widget and stops all offer evaluation; your offers are kept.', 'bundlekit-buy-x-get-y' )
				);
				bundlekit_ui_tabs( 'bundlekit-buy-x-get-y', $tabs, $tab );
				?>
				<?php if ( 'offers' === $tab ) : ?>
					<?php self::render_offers_tab(); ?>
				<?php elseif ( 'settings' === $tab ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=settings' ) ); ?>">
						<?php wp_nonce_field( 'bundlekit_buy_x_get_y_global', 'bundlekit_buy_x_get_y_global_nonce' ); ?>
						<div class="bk-cards bk-cards--narrow">
							<?php bundlekit_ui_card_open( 'dashicons-admin-generic', __( 'General', 'bundlekit-buy-x-get-y' ), __( 'Master switch for Buy X Get Y.', 'bundlekit-buy-x-get-y' ) ); ?>
								<?php bundlekit_ui_toggle( 'bundlekit_buy_x_get_y_enabled', __( 'Evaluate offers and show BOGO widgets', 'bundlekit-buy-x-get-y' ), BundleKit_Buy_X_Get_Y::enabled() ); ?>
								<p class="bk-field-desc"><?php esc_html_e( 'Untick to turn Buy X Get Y off for the whole site. No banner, progress bar or gift picker will show and no reward will be added. Your offers are kept, so ticking this again restores everything.', 'bundlekit-buy-x-get-y' ); ?></p>
							<?php bundlekit_ui_card_close(); ?>
							<?php bundlekit_ui_card_open( 'dashicons-tickets-alt', __( 'Coupon stacking', 'bundlekit-buy-x-get-y' ), __( 'What happens to other promo codes while a BOGO reward is active.', 'bundlekit-buy-x-get-y' ) ); ?>
								<p class="bk-field">
									<label class="bk-field-label" for="bundlekit_buy_x_get_y_coupon_mode"><?php esc_html_e( 'Promo codes', 'bundlekit-buy-x-get-y' ); ?></label>
									<select id="bundlekit_buy_x_get_y_coupon_mode" name="bundlekit_buy_x_get_y_coupon_mode" class="bk-select">
										<option value="block" <?php selected( BundleKit_Buy_X_Get_Y::coupon_mode(), 'block' ); ?>><?php esc_html_e( 'Block promo codes while a reward is active', 'bundlekit-buy-x-get-y' ); ?></option>
										<option value="allow" <?php selected( BundleKit_Buy_X_Get_Y::coupon_mode(), 'allow' ); ?>><?php esc_html_e( 'Allow promo codes alongside rewards', 'bundlekit-buy-x-get-y' ); ?></option>
									</select>
								</p>
							<?php bundlekit_ui_card_close(); ?>
							<?php bundlekit_ui_card_open( 'dashicons-sort', __( 'Evaluation order', 'bundlekit-buy-x-get-y' ), '' ); ?>
								<p class="bk-field-desc"><?php esc_html_e( 'When several offers match a cart, each applies in turn: lowest Priority number first; ties are broken by offer age (oldest first). A reward line never counts as a trigger for another offer, and a ticked Fixed Bundle box always wins on its product.', 'bundlekit-buy-x-get-y' ); ?></p>
							<?php bundlekit_ui_card_close(); ?>
						</div>
						<?php bundlekit_ui_save_button( __( 'Save settings', 'bundlekit-buy-x-get-y' ) ); ?>
					</form>
				<?php elseif ( 'css' === $tab ) : ?>
					<?php self::render_custom_css_tab(); ?>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=styling' ) ); ?>">
						<?php wp_nonce_field( 'bundlekit_buy_x_get_y_global', 'bundlekit_buy_x_get_y_global_nonce' ); ?>
						<div class="bk-bento">
							<div class="bk-bento-main">
								<p class="bk-field-desc"><?php esc_html_e( 'Style every BOGO widget. Colors use the color picker; clear a field to restore its default. The live preview on the right updates instantly.', 'bundlekit-buy-x-get-y' ); ?></p>
								<div class="bk-cards bundlekit-styling-controls">
									<?php self::render_styling_controls( BundleKit_Buy_X_Get_Y::global_style_values() ); ?>
								</div>
								<p class="bk-actions">
									<button type="submit" class="bk-btn bk-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save styling', 'bundlekit-buy-x-get-y' ); ?></button>
									<button type="submit" name="bk_reset_all" value="1" class="bk-btn" onclick="return confirm('<?php echo esc_js( __( 'Reset ALL styling controls to their defaults? This cannot be undone.', 'bundlekit-buy-x-get-y' ) ); ?>');"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset all', 'bundlekit-buy-x-get-y' ); ?></button>
								</p>
							</div>
							<div class="bk-preview">
								<div class="bk-preview-frame">
									<div class="bk-preview-head">
										<span class="dashicons dashicons-visibility"></span>
										<div>
											<h3><?php esc_html_e( 'Live preview', 'bundlekit-buy-x-get-y' ); ?></h3>
											<p><?php esc_html_e( 'This is how your BOGO widgets will look with the current styling.', 'bundlekit-buy-x-get-y' ); ?></p>
										</div>
									</div>
									<div class="bundlekit-preview-box" data-bundlekit-bogo-preview>
										<?php self::render_preview(); ?>
									</div>
									<p class="bk-field-desc"><?php esc_html_e( 'Sample banner, progress bar and gift picker. Change any control on the left and this preview updates instantly.', 'bundlekit-buy-x-get-y' ); ?></p>
								</div>
							</div>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ================= Offers tab ================= */

	public static function render_offers_tab() {
		$offers = BundleKit_Buy_X_Get_Y::get_offers();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=offers' ) ); ?>" id="bxgy-offers-form">
			<?php wp_nonce_field( 'bundlekit_buy_x_get_y_global', 'bundlekit_buy_x_get_y_global_nonce' ); ?>
			<div class="bk-cards">
				<div id="bxgy-offers-list">
					<?php foreach ( $offers as $i => $offer ) : ?>
						<?php self::render_offer_card( $offer, $i ); ?>
					<?php endforeach; ?>
				</div>
				<p>
					<button type="button" class="bk-btn" id="bxgy-add-offer"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add offer', 'bundlekit-buy-x-get-y' ); ?></button>
				</p>
			</div>
			<?php bundlekit_ui_save_button( __( 'Save offers', 'bundlekit-buy-x-get-y' ) ); ?>
		</form>
		<div id="bxgy-offer-template" hidden>
			<?php self::render_offer_card( BundleKit_Buy_X_Get_Y::sanitize_offer( array() ), '__INDEX__' ); ?>
		</div>
		<?php
	}

	/**
	 * One offer card in the Offers repeater.
	 *
	 * @param array $offer Sanitized offer.
	 * @param int|string $index Row index (or __INDEX__ for the JS template).
	 */
	public static function render_offer_card( $offer, $index ) {
		$p      = 'bundlekit_bogo_offers[' . $index . ']';
		$types  = BundleKit_Buy_X_Get_Y::trigger_types();
		$rtypes = BundleKit_Buy_X_Get_Y::reward_types();
		$status = BundleKit_Buy_X_Get_Y::offer_statuses();
		$title  = '' !== $offer['name'] ? $offer['name'] : __( 'New offer', 'bundlekit-buy-x-get-y' );
		?>
		<div class="bk-card bxgy-offer-card" data-offer-card>
			<div class="bk-card-head bxgy-offer-head">
				<span class="bk-card-icon"><span class="dashicons dashicons-gift"></span></span>
				<div>
					<h3 class="bxgy-offer-title"><?php echo esc_html( $title ); ?></h3>
					<p><?php echo wp_kses_post( BundleKit_Buy_X_Get_Y::offer_summary( $offer ) ); ?></p>
				</div>
				<button type="button" class="button bxgy-offer-remove" data-offer-remove aria-label="<?php esc_attr_e( 'Remove offer', 'bundlekit-buy-x-get-y' ); ?>">×</button>
			</div>
			<div class="bk-card-body">
				<input type="hidden" name="<?php echo esc_attr( $p ); ?>[id]" value="<?php echo esc_attr( $offer['id'] ); ?>" />
				<div class="bxgy-grid">
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Offer name', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="text" name="<?php echo esc_attr( $p ); ?>[name]" value="<?php echo esc_attr( $offer['name'] ); ?>" class="bk-input bxgy-offer-name" placeholder="<?php esc_attr_e( 'e.g. Weekend BOGO', 'bundlekit-buy-x-get-y' ); ?>" />
					</p>
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Status', 'bundlekit-buy-x-get-y' ); ?></label>
						<select name="<?php echo esc_attr( $p ); ?>[status]" class="bk-select" data-offer-status>
							<?php foreach ( $status as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $offer['status'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="bk-field" data-offer-dates <?php echo 'scheduled' === $offer['status'] ? '' : 'hidden'; ?>>
						<label class="bk-field-label"><?php esc_html_e( 'Start / end dates', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="date" name="<?php echo esc_attr( $p ); ?>[start]" value="<?php echo esc_attr( $offer['start'] ); ?>" class="bk-input" />
						<input type="date" name="<?php echo esc_attr( $p ); ?>[end]" value="<?php echo esc_attr( $offer['end'] ); ?>" class="bk-input" />
					</p>
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Priority', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[priority]" value="<?php echo esc_attr( $offer['priority'] ); ?>" min="0" step="1" class="bk-input small-text" />
						<span class="bk-field-desc"><?php esc_html_e( 'Lower runs first.', 'bundlekit-buy-x-get-y' ); ?></span>
					</p>
				</div>

				<h4 class="bxgy-section-title"><?php esc_html_e( 'Trigger — when the customer qualifies', 'bundlekit-buy-x-get-y' ); ?></h4>
				<div class="bxgy-grid">
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Trigger type', 'bundlekit-buy-x-get-y' ); ?></label>
						<select name="<?php echo esc_attr( $p ); ?>[trigger_type]" class="bk-select" data-trigger-type>
							<?php foreach ( $types as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $offer['trigger_type'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="bk-field" data-trigger-row="qty" <?php echo 'min_spend' === $offer['trigger_type'] ? 'hidden' : ''; ?>>
						<label class="bk-field-label"><?php esc_html_e( 'Buy this many (X)', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[trigger_qty]" value="<?php echo esc_attr( $offer['trigger_qty'] ); ?>" min="1" step="1" class="bk-input small-text" />
					</p>
					<p class="bk-field" data-trigger-row="min_spend" <?php echo 'min_spend' === $offer['trigger_type'] ? '' : 'hidden'; ?>>
						<label class="bk-field-label"><?php esc_html_e( 'Minimum spend', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[min_spend]" value="<?php echo esc_attr( $offer['min_spend'] ); ?>" min="0" step="any" class="bk-input" />
					</p>
				</div>
				<div data-trigger-row="products" <?php echo 'products' === $offer['trigger_type'] ? '' : 'hidden'; ?>>
					<?php self::product_picker( $p . '[trigger_products]', $offer['trigger_products'], true, __( 'Search products…', 'bundlekit-buy-x-get-y' ) ); ?>
				</div>
				<div data-trigger-row="categories" <?php echo 'categories' === $offer['trigger_type'] ? '' : 'hidden'; ?>>
					<?php self::term_multiselect( $p . '[trigger_cats]', 'product_cat', $offer['trigger_cats'], __( 'Product categories', 'bundlekit-buy-x-get-y' ) ); ?>
				</div>
				<div data-trigger-row="tags" <?php echo 'tags' === $offer['trigger_type'] ? '' : 'hidden'; ?>>
					<?php self::term_multiselect( $p . '[trigger_tags]', 'product_tag', $offer['trigger_tags'], __( 'Product tags', 'bundlekit-buy-x-get-y' ) ); ?>
				</div>

				<h4 class="bxgy-section-title"><?php esc_html_e( 'Reward — what the customer gets', 'bundlekit-buy-x-get-y' ); ?></h4>
				<div class="bxgy-grid">
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Reward type', 'bundlekit-buy-x-get-y' ); ?></label>
						<select name="<?php echo esc_attr( $p ); ?>[reward_type]" class="bk-select" data-reward-type>
							<?php foreach ( $rtypes as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $offer['reward_type'], $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Reward quantity', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[reward_qty]" value="<?php echo esc_attr( $offer['reward_qty'] ); ?>" min="1" step="1" class="bk-input small-text" />
					</p>
					<p class="bk-field" data-reward-row="percent" <?php echo 'percent' === $offer['reward_type'] ? '' : 'hidden'; ?>>
						<label class="bk-field-label"><?php esc_html_e( 'Discount %', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[reward_percent]" value="<?php echo esc_attr( $offer['reward_percent'] ); ?>" min="0" max="100" step="any" class="bk-input small-text" />
					</p>
					<p class="bk-field" data-reward-row="fixed" <?php echo 'fixed' === $offer['reward_type'] ? '' : 'hidden'; ?>>
						<label class="bk-field-label"><?php esc_html_e( 'Amount off (per unit)', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[reward_fixed]" value="<?php echo esc_attr( $offer['reward_fixed'] ); ?>" min="0" step="any" class="bk-input" />
					</p>
				</div>
				<div data-reward-row="product" <?php echo in_array( $offer['reward_type'], array( 'free', 'percent', 'fixed' ), true ) ? '' : 'hidden'; ?>>
					<?php self::product_picker( $p . '[reward_product_id]', $offer['reward_product_id'] ? array( $offer['reward_product_id'] ) : array(), false, __( 'Search the reward product…', 'bundlekit-buy-x-get-y' ) ); ?>
					<p class="bk-field" data-reward-variation-wrap>
						<label class="bk-field-label"><?php esc_html_e( 'Reward variation', 'bundlekit-buy-x-get-y' ); ?></label>
						<select name="<?php echo esc_attr( $p ); ?>[reward_variation_id]" class="bk-select" data-reward-variation>
							<?php echo self::variation_options( $offer['reward_product_id'], $offer['reward_variation_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</select>
					</p>
				</div>

				<h4 class="bxgy-section-title"><?php esc_html_e( 'Limits & delivery', 'bundlekit-buy-x-get-y' ); ?></h4>
				<div class="bxgy-grid">
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Max per cart (0 = unlimited)', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[max_per_cart]" value="<?php echo esc_attr( $offer['max_per_cart'] ); ?>" min="0" step="1" class="bk-input small-text" />
					</p>
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Max per customer (0 = unlimited)', 'bundlekit-buy-x-get-y' ); ?></label>
						<input type="number" name="<?php echo esc_attr( $p ); ?>[max_per_customer]" value="<?php echo esc_attr( $offer['max_per_customer'] ); ?>" min="0" step="1" class="bk-input small-text" />
					</p>
					<p class="bk-field">
						<label class="bk-field-label"><?php esc_html_e( 'Who chooses the gift', 'bundlekit-buy-x-get-y' ); ?></label>
						<select name="<?php echo esc_attr( $p ); ?>[who_chooses]" class="bk-select">
							<option value="auto" <?php selected( $offer['who_chooses'], 'auto' ); ?>><?php esc_html_e( 'Auto-add to cart', 'bundlekit-buy-x-get-y' ); ?></option>
							<option value="customer" <?php selected( $offer['who_chooses'], 'customer' ); ?>><?php esc_html_e( 'Customer picks (gift picker)', 'bundlekit-buy-x-get-y' ); ?></option>
						</select>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Product search picker: a search box with AJAX results; picked
	 * products become chips with hidden inputs.
	 *
	 * @param string     $name     Base field name (array [] appended for multi).
	 * @param array|int  $selected Selected product id(s).
	 * @param bool       $multiple Allow several products.
	 * @param string     $placeholder Search box placeholder.
	 */
	public static function product_picker( $name, $selected, $multiple, $placeholder ) {
		$selected = array_filter( array_map( 'absint', (array) $selected ) );
		$field    = $multiple ? $name . '[]' : $name;
		?>
		<div class="bxgy-product-picker" data-product-picker data-multiple="<?php echo $multiple ? '1' : '0'; ?>" data-field="<?php echo esc_attr( $field ); ?>">
			<input type="text" class="bk-input bxgy-product-search" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" />
			<div class="bxgy-product-results" hidden></div>
			<div class="bxgy-product-selected">
				<?php foreach ( $selected as $id ) : ?>
					<?php
					$prod  = wc_get_product( $id );
					$label = $prod ? $prod->get_name() : sprintf( __( 'Product #%d', 'bundlekit-buy-x-get-y' ), $id );
					?>
					<span class="bxgy-chip"><?php echo esc_html( $label ); ?><button type="button" data-chip-remove aria-label="<?php esc_attr_e( 'Remove', 'bundlekit-buy-x-get-y' ); ?>">×</button><input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $id ); ?>" /></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Multi-select of taxonomy terms.
	 */
	public static function term_multiselect( $name, $taxonomy, $selected, $label ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		$count = count( $terms );
		// Where the terms come from, so a wrong-taxonomy mix-up is obvious.
		$source = 'product_tag' === $taxonomy ? __( 'Products → Tags', 'bundlekit-buy-x-get-y' ) : __( 'Products → Categories', 'bundlekit-buy-x-get-y' );
		?>
		<p class="bk-field">
			<label class="bk-field-label"><?php echo esc_html( $label ); ?> <span class="bk-muted">(<?php echo esc_html( $count ); ?>)</span></label>
			<?php if ( $count > 0 ) : ?>
				<select multiple name="<?php echo esc_attr( $name ); ?>[]" class="bk-select bxgy-terms" size="5">
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, array_map( 'intval', (array) $selected ), true ) ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="bk-field-desc"><?php printf( esc_html__( 'From %s. Hold Ctrl (Windows) or Cmd (Mac) to select more than one.', 'bundlekit-buy-x-get-y' ), esc_html( $source ) ); ?></span>
			<?php else : ?>
				<span class="bk-field-desc"><?php printf( esc_html__( 'No terms found. Create them under %s first, then reload this page.', 'bundlekit-buy-x-get-y' ), esc_html( $source ) ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Variation <option>s for the reward product picker.
	 */
	public static function variation_options( $product_id, $selected_id ) {
		$html  = '<option value="0"' . selected( (int) $selected_id, 0, false ) . '>' . esc_html__( 'No variation (parent product)', 'bundlekit-buy-x-get-y' ) . '</option>';
		$product = wc_get_product( absint( $product_id ) );
		if ( $product && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_available_variations() as $v ) {
				$label = implode( ', ', $v['attributes'] );
				if ( '' === $label ) {
					$label = sprintf( __( 'Variation #%d', 'bundlekit-buy-x-get-y' ), $v['variation_id'] );
				}
				$html .= '<option value="' . esc_attr( $v['variation_id'] ) . '"' . selected( (int) $selected_id, (int) $v['variation_id'], false ) . '>' . esc_html( $label ) . '</option>';
			}
		}
		return $html;
	}

	/**
	 * AJAX: purchasable variations of a variable product, for the
	 * reward variation dropdown.
	 */
	public static function ajax_variations() {
		check_ajax_referer( 'bundlekit_bogo_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product    = wc_get_product( $product_id );
		$out        = array();
		if ( $product && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_available_variations() as $v ) {
				if ( empty( $v['is_purchasable'] ) ) {
					continue;
				}
				$label = implode( ', ', $v['attributes'] );
				if ( '' === $label ) {
					$label = sprintf( __( 'Variation #%d', 'bundlekit-buy-x-get-y' ), $v['variation_id'] );
				}
				$out[] = array( 'id' => (int) $v['variation_id'], 'label' => $label );
			}
		}
		wp_send_json_success( $out );
	}

	/* ================= Styling Controls tab ================= */

	/**
	 * Schema-driven Styling Controls UI: one Essentials card, then one
	 * collapsible card per group in spec order. Input names are
	 * bk_style[<key>]; the save handler merges only posted keys.
	 *
	 * @param array<string,string> $values Sanitized values by key.
	 */
	public static function render_styling_controls( $values ) {
		$controls = BundleKit_Buy_X_Get_Y_Styling::all_controls();
		$groups   = BundleKit_Buy_X_Get_Y_Styling::all_groups();
		$fonts    = BundleKit_Buy_X_Get_Y::font_stacks();
		$icons    = array(
			'banner'     => 'dashicons-megaphone',
			'progress'   => 'dashicons-chart-bar',
			'picker'     => 'dashicons-gift',
			'reward'     => 'dashicons-money-alt',
			'typography' => 'dashicons-editor-textcolor',
			'container'  => 'dashicons-admin-appearance',
			'custom_css' => 'dashicons-editor-code',
		);
		?>
		<details class="bk-card bk-style-group" open>
			<summary class="bk-card-head bk-style-summary">
				<span class="bk-card-icon"><span class="dashicons dashicons-star-filled"></span></span>
				<div>
					<h3><?php esc_html_e( 'Essentials', 'bundlekit-buy-x-get-y' ); ?></h3>
					<p><?php esc_html_e( 'The controls you will reach for most. Everything else lives in the groups below.', 'bundlekit-buy-x-get-y' ); ?></p>
				</div>
			</summary>
			<div class="bk-style-fields">
				<?php
				foreach ( $controls as $spec ) {
					if ( ! empty( $spec['essential'] ) ) {
						self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
					}
				}
				?>
			</div>
		</details>
		<?php
		foreach ( $groups as $slug => $label ) {
			if ( 'custom_css' === $slug ) {
				continue; // Custom CSS has its own tab.
			}
			$in_group = array();
			foreach ( $controls as $spec ) {
				if ( $spec['group'] === $slug && empty( $spec['essential'] ) ) {
					$in_group[] = $spec;
				}
			}
			if ( ! $in_group ) {
				continue;
			}
			$main = array();
			$adv  = array();
			foreach ( $in_group as $spec ) {
				if ( ! empty( $spec['advanced'] ) ) {
					$adv[] = $spec;
				} else {
					$main[] = $spec;
				}
			}
			$icon = isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'dashicons-admin-generic';
			?>
			<details class="bk-card bk-style-group"<?php echo 'banner' === $slug ? ' open' : ''; ?>>
				<summary class="bk-card-head bk-style-summary">
					<span class="bk-card-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
					<div>
						<h3><?php echo esc_html( $label ); ?></h3>
					</div>
				</summary>
				<div class="bk-style-fields">
					<?php
					foreach ( $main as $spec ) {
						self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
					}
					if ( $adv ) :
						?>
						<details class="bk-style-advanced">
							<summary><?php esc_html_e( 'Advanced', 'bundlekit-buy-x-get-y' ); ?></summary>
							<div class="bk-style-fields">
								<?php
								foreach ( $adv as $spec ) {
									self::render_styling_field( $spec, isset( $values[ $spec['key'] ] ) ? $values[ $spec['key'] ] : $spec['default'], $fonts );
								}
								?>
							</div>
						</details>
						<?php
					endif;
					?>
				</div>
			</details>
			<?php
		}
	}

	/**
	 * One schema control → label + input. The wrapper carries
	 * data-key for the live preview script.
	 */
	public static function render_styling_field( $spec, $value, $fonts ) {
		$key    = $spec['key'];
		$id     = 'bk_style_' . $key;
		$name   = 'bk_style[' . $key . ']';
		$suffix = '';
		if ( 'number' === $spec['type'] && '' !== $spec['unit'] ) {
			$suffix = $spec['unit'];
		}
		?>
		<div class="bk-field bk-style-field" data-key="<?php echo esc_attr( $key ); ?>">
			<label class="bk-field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $spec['label'] ); ?></label>
			<?php if ( 'color' === $spec['type'] ) : ?>
				<div class="bk-color-wrap">
					<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input bundlekit-color-field" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" />
				</div>
			<?php elseif ( 'number' === $spec['type'] ) : ?>
				<div class="bk-input-group">
					<input type="number" <?php echo null !== $spec['min'] ? 'min="' . esc_attr( $spec['min'] ) . '"' : ''; ?> <?php echo null !== $spec['max'] ? 'max="' . esc_attr( $spec['max'] ) . '"' : ''; ?> step="any" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" data-unit="<?php echo esc_attr( $spec['unit'] ); ?>" />
					<?php if ( $suffix ) : ?><span class="bk-suffix"><?php echo esc_html( $suffix ); ?></span><?php endif; ?>
				</div>
			<?php elseif ( 'font' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( (array) $fonts as $fkey => $stack ) : ?>
						<?php if ( '' === $fkey ) { continue; } ?>
						<option value="<?php echo esc_attr( $fkey ); ?>" <?php selected( $value, $fkey ); ?>><?php echo esc_html( ucfirst( $fkey ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'weight' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( array( '400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semi-bold (600)', '700' => 'Bold (700)', '800' => 'Extra bold (800)' ) as $w => $w_label ) : ?>
						<option value="<?php echo esc_attr( $w ); ?>" <?php selected( $value, (string) $w ); ?>><?php echo esc_html( $w_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'select' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>">
					<?php foreach ( (array) $spec['options'] as $okey => $olabel ) : ?>
						<option value="<?php echo esc_attr( $okey ); ?>" <?php selected( $value, (string) $okey ); ?>><?php echo esc_html( $olabel ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'checkbox' === $spec['type'] ) : ?>
				<?php bundlekit_ui_toggle( $name, __( 'Enabled', 'bundlekit-buy-x-get-y' ), 'yes' === $value ); ?>
			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input" data-css-var="<?php echo esc_attr( $spec['css_var'] ); ?>" />
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Live preview: a sample banner, progress bar and gift picker.
	 * Static sample markup (no real products needed); the preview
	 * script updates its CSS variables and bxgy-no-* classes live.
	 */
	public static function render_preview() {
		$values = BundleKit_Buy_X_Get_Y::global_style_values();
		$texts  = BundleKit_Buy_X_Get_Y_Styling::default_texts();
		$title  = '' !== $values['picker_title'] ? $values['picker_title'] : $texts['picker_title'];
		$btn    = '' !== $values['picker_button_text'] ? $values['picker_button_text'] : $texts['picker_button_text'];
		$style  = BundleKit_Buy_X_Get_Y_Frontend::style_attr();
		$base   = 'bundlekit-bogo';
		$dc     = implode( ' ', BundleKit_Buy_X_Get_Y::display_classes() );
		?>
		<div class="<?php echo esc_attr( trim( $base . ' bxgy-banner ' . $dc ) ); ?>" data-bundlekit-bogo <?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<span class="bxgy-banner-icon" aria-hidden="true">🎁</span>
			<div class="bxgy-banner-text">
				<strong class="bxgy-banner-headline"><?php esc_html_e( 'Buy 2, get 1 free', 'bundlekit-buy-x-get-y' ); ?></strong>
				<span class="bxgy-banner-sub"><?php esc_html_e( 'Add 1 more to unlock your reward.', 'bundlekit-buy-x-get-y' ); ?></span>
			</div>
		</div>
		<div class="<?php echo esc_attr( trim( $base . ' bxgy-progress ' . $dc ) ); ?>" data-bundlekit-bogo <?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bxgy-progress-text"><?php esc_html_e( 'Add $12 more to unlock your reward', 'bundlekit-buy-x-get-y' ); ?></div>
			<div class="bxgy-progress-track"><div class="bxgy-progress-fill" style="width:60%"></div></div>
		</div>
		<div class="<?php echo esc_attr( trim( $base . ' bxgy-picker ' . $dc ) ); ?>" data-bundlekit-bogo <?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bxgy-picker-head">
				<span class="bxgy-picker-icon" aria-hidden="true">🎁</span>
				<div>
					<strong class="bxgy-picker-title"><?php echo esc_html( $title ); ?></strong>
					<span class="bxgy-picker-offer"><?php esc_html_e( 'Weekend BOGO', 'bundlekit-buy-x-get-y' ); ?></span>
				</div>
			</div>
			<div class="bxgy-picker-body">
				<span class="bxgy-picker-product"><?php esc_html_e( 'Sample reward product', 'bundlekit-buy-x-get-y' ); ?></span>
				<button type="button" class="bxgy-picker-button"><?php echo esc_html( $btn ); ?></button>
			</div>
		</div>
		<?php
	}

	/* ================= Custom CSS tab ================= */

	public static function render_custom_css_tab() {
		$css = get_option( 'bundlekit_buy_x_get_y_custom_css', '' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=css' ) ); ?>">
			<?php wp_nonce_field( 'bundlekit_buy_x_get_y_global', 'bundlekit_buy_x_get_y_global_nonce' ); ?>
			<?php bundlekit_ui_section_title( __( 'Custom CSS', 'bundlekit-buy-x-get-y' ), __( 'Raw CSS printed only on pages where BOGO widgets render. Use the --bxgy-* variables to restyle anything, e.g. .bundlekit-bogo { --bxgy-banner-radius: 0; }. Be careful: invalid CSS can break the widget look.', 'bundlekit-buy-x-get-y' ) ); ?>
			<div class="bk-cards">
				<?php bundlekit_ui_card_open( 'dashicons-editor-code', __( 'Custom CSS', 'bundlekit-buy-x-get-y' ), '', 'bk-card--wide' ); ?>
					<textarea name="bundlekit_buy_x_get_y_custom_css" class="bk-input bk-textarea" rows="12" style="width:100%;font-family:monospace;" placeholder="/* CSS */"><?php echo esc_textarea( $css ); ?></textarea>
				<?php bundlekit_ui_card_close(); ?>
			</div>
			<?php bundlekit_ui_save_button( __( 'Save custom CSS', 'bundlekit-buy-x-get-y' ) ); ?>
		</form>
		<?php
	}

	/* ================= save ================= */

	public static function global_save() {
		if ( ! isset( $_POST['bundlekit_buy_x_get_y_global_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bundlekit_buy_x_get_y_global_nonce'] ) ), 'bundlekit_buy_x_get_y_global' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'offers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = in_array( $tab, array( 'offers', 'settings', 'styling', 'css' ), true ) ? $tab : 'offers';

		if ( 'offers' === $tab ) {
			$offers = BundleKit_Buy_X_Get_Y::sanitize_offers_input(
				isset( $_POST['bundlekit_bogo_offers'] ) ? wp_unslash( $_POST['bundlekit_bogo_offers'] ) : array() // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			);
			update_option( 'bundlekit_buy_x_get_y_offers', $offers );
		} elseif ( 'settings' === $tab ) {
			update_option( 'bundlekit_buy_x_get_y_enabled', isset( $_POST['bundlekit_buy_x_get_y_enabled'] ) ? 'yes' : 'no' );
			$mode = isset( $_POST['bundlekit_buy_x_get_y_coupon_mode'] ) ? sanitize_key( wp_unslash( $_POST['bundlekit_buy_x_get_y_coupon_mode'] ) ) : 'block';
			update_option( 'bundlekit_buy_x_get_y_coupon_mode', in_array( $mode, array( 'block', 'allow' ), true ) ? $mode : 'block' );
		} elseif ( 'css' === $tab ) {
			$css = isset( $_POST['bundlekit_buy_x_get_y_custom_css'] ) ? wp_unslash( $_POST['bundlekit_buy_x_get_y_custom_css'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			update_option( 'bundlekit_buy_x_get_y_custom_css', is_string( $css ) ? wp_strip_all_tags( $css ) : '' );
		} else {
			// "Reset all": wipe the styling array back to schema defaults.
			if ( isset( $_POST['bk_reset_all'] ) ) {
				update_option( 'bundlekit_buy_x_get_y_styling', array() );
				wp_safe_redirect( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=' . $tab . '&saved=1' ) );
				exit;
			}
			// Styling: one option array for every control. Only posted
			// keys are updated. Checkbox controls post only when checked.
			$posted = isset( $_POST['bk_style'] ) && is_array( $_POST['bk_style'] ) ? wp_unslash( $_POST['bk_style'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$stored = get_option( 'bundlekit_buy_x_get_y_styling', array() );
			$stored = is_array( $stored ) ? $stored : array();
			foreach ( BundleKit_Buy_X_Get_Y_Styling::all_controls() as $spec ) {
				$key = $spec['key'];
				if ( 'checkbox' === $spec['type'] ) {
					$stored[ $key ] = isset( $posted[ $key ] ) ? 'yes' : '';
				} elseif ( array_key_exists( $key, $posted ) ) {
					$raw           = is_string( $posted[ $key ] ) ? $posted[ $key ] : '';
					$stored[ $key ] = BundleKit_Buy_X_Get_Y_Styling::sanitize_value( $spec, $raw );
				}
			}
			update_option( 'bundlekit_buy_x_get_y_styling', $stored );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=bundlekit-buy-x-get-y&tab=' . $tab . '&saved=1' ) );
		exit;
	}
}
