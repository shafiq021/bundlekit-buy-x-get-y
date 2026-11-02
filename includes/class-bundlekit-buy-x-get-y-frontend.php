<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend for Buy X Get Y:
 *  - product-page offer banner ("Buy 2, get 1 free — add 2 to unlock")
 *  - cart progress bar toward the next reward
 *  - gift picker (inline in the cart, popup, or mini-cart) for
 *    "customer picks" offers
 *  - styling output as CSS custom properties (--bxgy-*) plus the
 *    bxgy-no-* display classes (class-based show/hide only)
 *  - the Custom CSS textarea, printed once per page
 */
class BundleKit_Buy_X_Get_Y_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_before_add_to_cart_form', array( __CLASS__, 'banner' ), 15 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'cart_widgets' ), 5 );
		add_action( 'woocommerce_after_mini_cart', array( __CLASS__, 'mini_cart_picker' ) );
		add_action( 'wp_footer', array( __CLASS__, 'picker_popup' ) );
		add_action( 'wp_head', array( __CLASS__, 'custom_css' ), 99 );
	}

	public static function assets() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
			return;
		}
		if ( ! ( is_product() || is_cart() || is_checkout() ) ) {
			return;
		}
		wp_enqueue_style( 'bundlekit-buy-x-get-y-frontend', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/css/frontend.css', array(), BUNDLEKIT_BUY_X_GET_Y_VERSION );
		wp_enqueue_script( 'bundlekit-buy-x-get-y-frontend', BUNDLEKIT_BUY_X_GET_Y_URL . 'assets/js/frontend.js', array( 'jquery' ), BUNDLEKIT_BUY_X_GET_Y_VERSION, true );
		wp_localize_script(
			'bundlekit-buy-x-get-y-frontend',
			'bundlekitBogo',
			array(
				'ajaxUrl' => WC_AJAX::get_endpoint( 'bundlekit_bogo_gift' ),
				'nonce'   => wp_create_nonce( 'bundlekit_bogo_gift' ),
			)
		);
	}

	/**
	 * Inline style attribute with every --bxgy-* variable. Keys in
	 * skip_zero_keys() (0 = unset) are omitted, mirroring the PHP
	 * side — and the preview JS mirrors this list too.
	 */
	public static function style_attr() {
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		$skip   = BundleKit_Buy_X_Get_Y_Styling::skip_zero_keys();
		$out    = array();
		foreach ( BundleKit_Buy_X_Get_Y_Styling::all_controls() as $spec ) {
			if ( '' === $spec['css_var'] ) {
				continue;
			}
			$key   = $spec['key'];
			$value = isset( $values[ $key ] ) ? $values[ $key ] : '';
			if ( in_array( $key, $skip, true ) && 0 === (int) $value ) {
				continue;
			}
			$css = BundleKit_Buy_X_Get_Y_Styling::css_var_value( $spec, $value );
			if ( '' !== $css ) {
				$out[] = $spec['css_var'] . ':' . $css;
			}
		}
		return $out ? ' style="' . esc_attr( implode( ';', $out ) ) . '"' : '';
	}

	/** Wrapper classes: base + display classes from show/hide. */
	public static function widget_classes( $extra = '' ) {
		$classes = array_merge(
			array( 'bundlekit-bogo' ),
			BundleKit_Buy_X_Get_Y::display_classes()
		);
		if ( '' !== $extra ) {
			$classes[] = $extra;
		}
		return implode( ' ', array_map( 'sanitize_html_class', $classes ) );
	}

	/* ================= product-page banner ================= */

	public static function banner() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product ) {
			return;
		}
		$product_id = (int) $product->get_id();
		foreach ( BundleKit_Buy_X_Get_Y::active_offers() as $offer ) {
			if ( BundleKit_Buy_X_Get_Y::product_triggers_offer( $product_id, $offer ) ) {
				echo self::render_banner( $offer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * One offer banner.
	 */
	public static function render_banner( $offer ) {
		$progress = BundleKit_Buy_X_Get_Y_Pricing::offer_progress( $offer );
		$headline = BundleKit_Buy_X_Get_Y::banner_headline( $offer );
		if ( 'min_spend' === $offer['trigger_type'] ) {
			$sub = $progress['remaining'] > 0
				/* translators: %s: remaining amount */
				? sprintf( __( 'Add %s more to unlock your reward.', 'bundlekit-buy-x-get-y' ), wc_price( $progress['remaining'] ) )
				: __( 'Reward unlocked — it will appear in your cart.', 'bundlekit-buy-x-get-y' );
		} else {
			$sub = $progress['remaining'] > 0
				/* translators: %d: remaining quantity */
				? sprintf( _n( 'Add %d more to unlock your reward.', 'Add %d more to unlock your reward.', (int) $progress['remaining'], 'bundlekit-buy-x-get-y' ), (int) $progress['remaining'] )
				: __( 'Reward unlocked — it will appear in your cart.', 'bundlekit-buy-x-get-y' );
		}
		// Reward product name for specific-product rewards.
		$reward_name = '';
		if ( in_array( $offer['reward_type'], array( 'free', 'percent', 'fixed' ), true ) && $offer['reward_product_id'] ) {
			$rp = wc_get_product( $offer['reward_product_id'] );
			if ( $rp ) {
				$reward_name = $rp->get_name();
			}
		}
		ob_start();
		?>
		<div class="<?php echo esc_attr( self::widget_classes( 'bxgy-banner' ) ); ?>"<?php echo self::style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<span class="bxgy-banner-icon" aria-hidden="true">🎁</span>
			<div class="bxgy-banner-text">
				<strong class="bxgy-banner-headline"><?php echo wp_kses_post( $headline ); ?><?php echo '' !== $reward_name ? ': ' . esc_html( $reward_name ) : ''; ?></strong>
				<span class="bxgy-banner-sub"><?php echo wp_kses_post( $sub ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ================= cart widgets ================= */

	public static function cart_widgets() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() || ! is_cart() ) {
			return;
		}
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		foreach ( BundleKit_Buy_X_Get_Y::active_offers() as $offer ) {
			$progress = BundleKit_Buy_X_Get_Y_Pricing::offer_progress( $offer );
			if ( $progress['times'] > 0 ) {
				// Qualifies: customer-picks offers need the picker.
				if ( 'customer' === $offer['who_chooses'] && in_array( $offer['reward_type'], array( 'free', 'percent', 'fixed' ), true ) ) {
					if ( ! BundleKit_Buy_X_Get_Y_Pricing::offer_has_reward_line( $offer['id'] ) ) {
						if ( 'popup' === $values['picker_layout'] ) {
							echo self::render_picker_button( $offer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo self::render_picker( $offer, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
				}
				continue;
			}
			// Not there yet: progress bar toward the next reward.
			if ( $progress['need'] > 0 ) {
				echo self::render_progress( $offer, $progress ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Progress bar toward the next reward.
	 */
	public static function render_progress( $offer, $progress ) {
		$pct = $progress['need'] > 0 ? min( 100, round( $progress['met'] / $progress['need'] * 100 ) ) : 0;
		if ( 'min_spend' === $offer['trigger_type'] ) {
			/* translators: %s: remaining amount */
			$text = sprintf( __( 'Add %s more to unlock your reward', 'bundlekit-buy-x-get-y' ), wc_price( $progress['remaining'] ) );
		} else {
			/* translators: %d: remaining quantity */
			$text = sprintf( _n( 'Add %d more to unlock your reward', 'Add %d more to unlock your reward', (int) $progress['remaining'], 'bundlekit-buy-x-get-y' ), (int) $progress['remaining'] );
		}
		ob_start();
		?>
		<div class="<?php echo esc_attr( self::widget_classes( 'bxgy-progress' ) ); ?>"<?php echo self::style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bxgy-progress-text"><?php echo wp_kses_post( $text ); ?></div>
			<div class="bxgy-progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr( $pct ); ?>" aria-valuemin="0" aria-valuemax="100">
				<div class="bxgy-progress-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ================= gift picker ================= */

	/**
	 * Offers currently waiting for a gift choice.
	 *
	 * @return array<int,array>
	 */
	public static function picker_offers() {
		$out = array();
		if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
			return $out;
		}
		foreach ( BundleKit_Buy_X_Get_Y::active_offers() as $offer ) {
			if ( 'customer' !== $offer['who_chooses'] ) {
				continue;
			}
			if ( ! in_array( $offer['reward_type'], array( 'free', 'percent', 'fixed' ), true ) ) {
				continue;
			}
			$progress = BundleKit_Buy_X_Get_Y_Pricing::offer_progress( $offer );
			if ( $progress['times'] <= 0 ) {
				continue;
			}
			if ( BundleKit_Buy_X_Get_Y_Pricing::offer_has_reward_line( $offer['id'] ) ) {
				continue;
			}
			$out[] = $offer;
		}
		return $out;
	}

	/**
	 * The gift picker: reward product, variation dropdown for variable
	 * products, "Add gift" button. $compact = mini-cart rendering.
	 */
	public static function render_picker( $offer, $compact = false ) {
		$values  = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		$texts   = BundleKit_Buy_X_Get_Y_Styling::default_texts();
		$title   = '' !== $values['picker_title'] ? $values['picker_title'] : $texts['picker_title'];
		$btn     = '' !== $values['picker_button_text'] ? $values['picker_button_text'] : $texts['picker_button_text'];
		$product = wc_get_product( $offer['reward_product_id'] );
		if ( ! $product ) {
			return '';
		}
		$variations = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_available_variations() as $v ) {
				if ( empty( $v['is_purchasable'] ) || empty( $v['is_in_stock'] ) ) {
					continue;
				}
				$variations[] = array(
					'id'    => (int) $v['variation_id'],
					'label' => wp_strip_all_tags( $v['price_html'] ) . ' — ' . implode( ', ', $v['attributes'] ),
				);
			}
			if ( ! $variations ) {
				return '';
			}
		}
		$offer_name = '' !== $offer['name'] ? $offer['name'] : __( 'Buy X Get Y', 'bundlekit-buy-x-get-y' );
		ob_start();
		?>
		<div class="<?php echo esc_attr( self::widget_classes( 'bxgy-picker' . ( $compact ? ' bxgy-picker--compact' : '' ) ) ); ?>"<?php echo self::style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-offer-id="<?php echo esc_attr( $offer['id'] ); ?>">
			<div class="bxgy-picker-head">
				<span class="bxgy-picker-icon" aria-hidden="true">🎁</span>
				<div>
					<strong class="bxgy-picker-title"><?php echo esc_html( $title ); ?></strong>
					<span class="bxgy-picker-offer"><?php echo esc_html( $offer_name ); ?></span>
				</div>
			</div>
			<div class="bxgy-picker-body">
				<span class="bxgy-picker-product"><?php echo esc_html( $product->get_name() ); ?></span>
				<?php if ( $variations ) : ?>
					<select class="bxgy-picker-variation" aria-label="<?php esc_attr_e( 'Choose a variation', 'bundlekit-buy-x-get-y' ); ?>">
						<?php foreach ( $variations as $v ) : ?>
							<option value="<?php echo esc_attr( $v['id'] ); ?>"><?php echo esc_html( $v['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="button" class="bxgy-picker-button" data-bxgy-pick><?php echo esc_html( $btn ); ?></button>
				<span class="bxgy-picker-note" aria-live="polite"></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** "Choose your free gift" button for the popup layout. */
	public static function render_picker_button( $offer ) {
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		$texts  = BundleKit_Buy_X_Get_Y_Styling::default_texts();
		$title  = '' !== $values['picker_title'] ? $values['picker_title'] : $texts['picker_title'];
		ob_start();
		?>
		<div class="<?php echo esc_attr( self::widget_classes( 'bxgy-picker-launch' ) ); ?>"<?php echo self::style_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<button type="button" class="bxgy-picker-button" data-bxgy-open-picker="<?php echo esc_attr( $offer['id'] ); ?>">🎁 <?php echo esc_html( $title ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Popup overlays for the popup picker layout (cart page only).
	 */
	public static function picker_popup() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() || ! is_cart() ) {
			return;
		}
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		if ( 'popup' !== $values['picker_layout'] ) {
			return;
		}
		foreach ( self::picker_offers() as $offer ) {
			?>
			<div class="bxgy-overlay" data-bxgy-overlay="<?php echo esc_attr( $offer['id'] ); ?>" hidden>
				<div class="bxgy-overlay-backdrop" data-bxgy-close-picker></div>
				<div class="bxgy-overlay-dialog" role="dialog" aria-modal="true">
					<button type="button" class="bxgy-overlay-close" data-bxgy-close-picker aria-label="<?php esc_attr_e( 'Close', 'bundlekit-buy-x-get-y' ); ?>">×</button>
					<?php echo self::render_picker( $offer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
			<?php
		}
	}

	/** Compact picker inside the mini-cart. */
	public static function mini_cart_picker() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
			return;
		}
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		if ( empty( $values['show_mini_cart'] ) ) {
			return;
		}
		foreach ( self::picker_offers() as $offer ) {
			echo self::render_picker( $offer, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/* ================= custom CSS ================= */

	/**
	 * The Custom CSS textarea, printed once on BOGO pages.
	 */
	public static function custom_css() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
			return;
		}
		if ( ! ( is_product() || is_cart() || is_checkout() ) ) {
			return;
		}
		$css = trim( (string) get_option( 'bundlekit_buy_x_get_y_custom_css', '' ) );
		if ( '' === $css ) {
			return;
		}
		echo "<style id=\"bundlekit-buy-x-get-y-custom-css\">\n" . wp_strip_all_tags( $css ) . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
