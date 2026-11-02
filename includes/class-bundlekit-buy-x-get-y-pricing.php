<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cart behaviour for Buy X Get Y.
 *
 * Offers are evaluated on cart totals calculation. For each active,
 * in-date offer the trigger is checked against the cart contents and
 * the reward is applied:
 *
 *  - Specific-product rewards (free / percent / fixed) become their
 *    own cart lines, keyed by offer id
 *    (`bundlekit_bogo_offer`), so "Offer A reward" and "Offer B
 *    reward" never merge. The unit price is overridden at cart time
 *    (0 for free, or the discounted price) — the same price-at-cart
 *    pattern Fixed Bundle uses. No coupon codes are created.
 *  - Cheapest / most-expensive free marks the qualifying trigger
 *    lines (`bundlekit_bogo_line_reward`) and zeroes their unit
 *    price. Marks are recomputed every totals run, so they
 *    self-heal when the cart changes (auto-remove).
 *
 * Rules (spec §2.4):
 *  - Auto-remove: when the trigger stops being met, the reward line
 *    is removed automatically.
 *  - Quantity lock: the customer cannot raise a reward line above the
 *    earned amount; lowering it marks the line declined and the
 *    engine never bumps it back up. Removing the line entirely
 *    declines the offer until the trigger quantities change.
 *  - No chaining: a reward line never counts as a trigger for
 *    another offer.
 *  - A ticked Fixed Bundle box wins: its bundle lines never count as
 *    BOGO triggers. Buy in Packs pack lines DO count (spec decision).
 *  - Rounding: per line, half-up, to the store currency's minor unit.
 *  - Guests: everything runs on the WooCommerce session, including
 *    the gift-choice flag for "customer picks" offers.
 */
class BundleKit_Buy_X_Get_Y_Pricing {

	/** Re-entrancy guard: add_to_cart()/set_quantity() inside the
	 *  totals run trigger nested calculate_totals() calls. */
	private static $evaluating = false;

	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'evaluate_offers' ), 9999 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 3 );
		add_action( 'woocommerce_applied_coupon', array( __CLASS__, 'maybe_block_coupon' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'record_usage' ), 10, 1 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'cart_item_price' ), 10, 3 );
		// Gift-choice endpoint for "customer picks" offers (guests included).
		add_action( 'wc_ajax_bundlekit_bogo_gift', array( __CLASS__, 'gift_choice' ) );
	}

	/* ================= evaluation ================= */

	public static function evaluate_offers( $cart ) {
		if ( self::$evaluating ) {
			return;
		}
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		self::$evaluating = true;
		try {
			if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
				self::strip_all( $cart );
				self::set_reward_active( false );
				return;
			}
			// Clear cheapest/most-expensive marks from the previous
			// run; they are recomputed below. Specific-product reward
			// lines (bundlekit_bogo_offer) are kept and synced.
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( isset( $cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] ) ) {
					unset( $cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] );
				}
			}
			$any_reward = false;
			foreach ( BundleKit_Buy_X_Get_Y::active_offers() as $offer ) {
				if ( self::evaluate_offer( $cart, $offer ) ) {
					$any_reward = true;
				}
			}
			// Price loop: apply the overrides computed above.
			foreach ( $cart->get_cart() as $key => $item ) {
				if ( isset( $item['bundlekit_bogo_price'] ) ) {
					$item['data']->set_price( (float) $item['bundlekit_bogo_price'] );
				} elseif ( isset( $cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] ) ) {
					$item['data']->set_price( 0.0 );
				}
			}
			self::set_reward_active( $any_reward );
		} finally {
			self::$evaluating = false;
		}
	}

	/**
	 * Evaluate one offer against the cart. Returns true when the
	 * offer granted a reward in this run.
	 */
	private static function evaluate_offer( $cart, $offer ) {
		$offer_id = $offer['id'];

		// Eligible lines: everything except our own reward lines (no
		// chaining), ticked Fixed Bundle lines (the bundle box wins),
		// and lines already rewarded by an earlier offer this run.
		$lines = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) ) {
				continue;
			}
			if ( isset( $item['bundlekit_bundle_price'] ) ) {
				continue;
			}
			if ( isset( $cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] ) ) {
				continue;
			}
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$parent_id    = $variation_id && isset( $item['data'] ) ? (int) $item['data']->get_parent_id() : $product_id;
			$lines[ $key ] = array(
				'qty'          => (int) $item['quantity'],
				'unit'         => self::line_unit_price( $item ),
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'cats'         => $parent_id ? wc_get_product_term_ids( $parent_id, 'product_cat' ) : array(),
				'tags'         => $parent_id ? wc_get_product_term_ids( $parent_id, 'product_tag' ) : array(),
			);
		}

		// Trigger contribution.
		$contributing = array();
		$trigger_qty  = 0;
		$spend        = 0.0;
		foreach ( $lines as $key => $line ) {
			if ( self::line_matches_trigger( $line, $offer ) ) {
				$contributing[ $key ] = $line;
				$trigger_qty         += $line['qty'];
				$spend               += $line['unit'] * $line['qty'];
			}
		}

		$times = 0;
		if ( 'min_spend' === $offer['trigger_type'] ) {
			if ( $offer['min_spend'] > 0 ) {
				$times = (int) floor( $spend / $offer['min_spend'] );
			}
		} elseif ( $offer['trigger_qty'] > 0 ) {
			$times = (int) floor( $trigger_qty / $offer['trigger_qty'] );
		}
		if ( $offer['max_per_cart'] > 0 ) {
			$times = min( $times, $offer['max_per_cart'] );
		}
		if ( $offer['max_per_customer'] > 0 ) {
			$used  = self::customer_usage( $offer_id );
			$times = min( $times, max( 0, $offer['max_per_customer'] - $used ) );
		}
		$times = max( 0, $times );

		// Signature used to detect "trigger quantities changed" for
		// the decline logic.
		$qty_sig = 'min_spend' === $offer['trigger_type'] ? (int) round( $spend ) : $trigger_qty;

		if ( $times <= 0 ) {
			// No longer qualifies: auto-remove the reward line and
			// forget any decline (a fresh trigger starts fresh).
			self::remove_offer_line( $cart, $offer_id );
			self::clear_state( $offer_id );
			return false;
		}

		if ( in_array( $offer['reward_type'], array( 'free', 'percent', 'fixed' ), true ) ) {
			return self::sync_product_reward( $cart, $offer, $times, $qty_sig );
		}
		return self::sync_line_reward( $cart, $offer, $times, $contributing );
	}

	/**
	 * The effective unit price of a cart line for trigger maths:
	 * the pack price for Buy in Packs lines (spec: pack lines count
	 * at their discounted price), else the product's current price.
	 */
	private static function line_unit_price( $item ) {
		if ( isset( $item['bundlekit_pack_price'] ) ) {
			return (float) $item['bundlekit_pack_price'];
		}
		return isset( $item['data'] ) ? (float) $item['data']->get_price() : 0.0;
	}

	/** Does one eligible line contribute to the offer's trigger? */
	private static function line_matches_trigger( $line, $offer ) {
		switch ( $offer['trigger_type'] ) {
			case 'products':
				return in_array( $line['product_id'], $offer['trigger_products'], true )
					|| in_array( $line['variation_id'], $offer['trigger_products'], true );
			case 'categories':
				return (bool) array_intersect( $line['cats'], $offer['trigger_cats'] );
			case 'tags':
				return (bool) array_intersect( $line['tags'], $offer['trigger_tags'] );
			case 'min_spend':
				return true;
		}
		return false;
	}

	/* ================= specific-product rewards ================= */

	/**
	 * Sync the keyed reward line for free/percent/fixed offers.
	 * Returns true when a reward is present after the sync.
	 */
	private static function sync_product_reward( $cart, $offer, $times, $qty_sig ) {
		$offer_id = $offer['id'];
		$earned   = $offer['reward_qty'] * $times;

		$line_key = null;
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) && $item['bundlekit_bogo_offer'] === $offer_id ) {
				$line_key = $key;
				break;
			}
		}

		$state    = self::get_state( $offer_id );
		$declined = ! empty( $state['declined'] ) && isset( $state['declined_at_qty'] ) && (int) $state['declined_at_qty'] === (int) $qty_sig;
		if ( ! $declined && ! empty( $state['declined'] ) ) {
			// Trigger quantities changed since the decline — fresh start.
			$state['declined'] = false;
			self::save_state( $offer_id, $state );
		}

		if ( null === $line_key ) {
			// No reward line. If we had one last run, the customer
			// removed it → declined until the trigger changes.
			if ( ! empty( $state['key'] ) ) {
				self::save_state( $offer_id, array( 'key' => null, 'declined' => true, 'declined_at_qty' => $qty_sig ) );
				return false;
			}
			if ( $declined ) {
				return false;
			}
			$choice = null;
			if ( 'customer' === $offer['who_chooses'] ) {
				$choice = self::get_choice( $offer_id );
				if ( ! $choice ) {
					return false; // The gift picker handles this case.
				}
			}
			$product_id   = $choice ? (int) $choice['product_id'] : $offer['reward_product_id'];
			$variation_id = $choice ? (int) $choice['variation_id'] : $offer['reward_variation_id'];
			$product      = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return false;
			}
			$price   = BundleKit_Buy_X_Get_Y::reward_unit_price( $offer, $product );
			$new_key = $cart->add_to_cart(
				$product_id,
				$earned,
				$variation_id,
				array(),
				array(
					'bundlekit_bogo_offer'   => $offer_id,
					'bundlekit_bogo_earned'  => $earned,
					'bundlekit_bogo_price'   => $price,
					'bundlekit_bogo_regular' => (float) $product->get_regular_price(),
				)
			);
			if ( ! $new_key || ! isset( $cart->cart_contents[ $new_key ] ) ) {
				return false;
			}
			// The nested totals run was guarded, so price it directly.
			$cart->cart_contents[ $new_key ]['data']->set_price( $price );
			self::save_state( $offer_id, array( 'key' => $new_key, 'declined' => false, 'declined_at_qty' => 0 ) );
			if ( 'customer' === $offer['who_chooses'] ) {
				self::clear_choice( $offer_id );
			}
			// Save the session now: add_to_cart's own session save ran
			// inside the guarded nested totals, before this meta was set.
			if ( WC()->session ) {
				WC()->session->save_data();
			}
			return true;
		}

		// The line exists — quantity lock.
		$item        = $cart->cart_contents[ $line_key ];
		$qty         = (int) $item['quantity'];
		$prev_earned = isset( $item['bundlekit_bogo_earned'] ) ? (int) $item['bundlekit_bogo_earned'] : 0;
		if ( $qty > $earned ) {
			// Customer raised it above the earned amount: clamp back.
			$cart->set_quantity( $line_key, $earned, false );
			$qty = $earned;
		} elseif ( $qty < $earned ) {
			if ( ! empty( $item['bundlekit_bogo_declined'] ) ) {
				// Already declined — leave it.
			} elseif ( $qty < $prev_earned ) {
				// Lowered since the last run → declined.
				$cart->cart_contents[ $line_key ]['bundlekit_bogo_declined'] = 1;
				self::save_state( $offer_id, array( 'key' => $line_key, 'declined' => true, 'declined_at_qty' => $qty_sig ) );
				return true;
			} else {
				// Earned grew and the customer never touched the line:
				// bump it up to the new earned amount.
				$cart->set_quantity( $line_key, $earned, false );
				$qty = $earned;
			}
		}
		$cart->cart_contents[ $line_key ]['bundlekit_bogo_earned'] = $earned;
		// Re-price in case the product price changed.
		$price = BundleKit_Buy_X_Get_Y::reward_unit_price( $offer, $item['data'] );
		$cart->cart_contents[ $line_key ]['bundlekit_bogo_price'] = $price;
		self::save_state(
			$offer_id,
			array(
				'key'             => $line_key,
				'declined'        => ! empty( $cart->cart_contents[ $line_key ]['bundlekit_bogo_declined'] ),
				'declined_at_qty' => $qty_sig,
			)
		);
		return true;
	}

	/** Remove the offer's reward line, if any. */
	private static function remove_offer_line( $cart, $offer_id ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) && $item['bundlekit_bogo_offer'] === $offer_id ) {
				$cart->remove_cart_item( $key );
			}
		}
	}

	/* ================= cheapest / most-expensive free ================= */

	/**
	 * Mark the cheapest/most-expensive N trigger lines free. Whole
	 * lines only in v1.0.0: a line larger than the remaining free
	 * count is skipped (it cannot be split).
	 */
	private static function sync_line_reward( $cart, $offer, $times, $contributing ) {
		$n = $offer['reward_qty'] * $times;
		if ( $n <= 0 || empty( $contributing ) ) {
			return false;
		}
		$sorted = $contributing;
		uasort(
			$sorted,
			function ( $a, $b ) use ( $offer ) {
				if ( $a['unit'] === $b['unit'] ) {
					return 0;
				}
				$cmp = $a['unit'] < $b['unit'] ? -1 : 1;
				return 'cheapest_free' === $offer['reward_type'] ? $cmp : -$cmp;
			}
		);
		$remaining = $n;
		$marked    = 0;
		foreach ( $sorted as $key => $line ) {
			if ( $remaining <= 0 ) {
				break;
			}
			if ( $line['qty'] <= $remaining ) {
				$cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] = $offer['id'];
				$remaining -= $line['qty'];
				$marked++;
			}
		}
		return $marked > 0;
	}

	/* ================= session / state ================= */

	private static function session_state() {
		if ( ! WC()->session ) {
			return array();
		}
		$state = WC()->session->get( 'bundlekit_bogo_state' );
		return is_array( $state ) ? $state : array();
	}

	private static function get_state( $offer_id ) {
		$state = self::session_state();
		return isset( $state[ $offer_id ] ) && is_array( $state[ $offer_id ] ) ? $state[ $offer_id ] : array();
	}

	private static function save_state( $offer_id, $data ) {
		if ( ! WC()->session ) {
			return;
		}
		$state                = self::session_state();
		$state[ $offer_id ]   = $data;
		WC()->session->set( 'bundlekit_bogo_state', $state );
	}

	private static function clear_state( $offer_id ) {
		if ( ! WC()->session ) {
			return;
		}
		$state = self::session_state();
		unset( $state[ $offer_id ] );
		WC()->session->set( 'bundlekit_bogo_state', $state );
	}

	private static function get_choice( $offer_id ) {
		if ( ! WC()->session ) {
			return null;
		}
		$choice = WC()->session->get( 'bundlekit_bogo_choice_' . $offer_id );
		return is_array( $choice ) ? $choice : null;
	}

	private static function clear_choice( $offer_id ) {
		if ( WC()->session ) {
			WC()->session->set( 'bundlekit_bogo_choice_' . $offer_id, null );
		}
	}

	private static function set_reward_active( $active ) {
		if ( WC()->session ) {
			WC()->session->set( 'bundlekit_bogo_reward_active', $active ? 1 : 0 );
		}
	}

	/** Was any reward active as of the last cart calculation? */
	public static function reward_active() {
		if ( ! WC()->session ) {
			return false;
		}
		return (bool) WC()->session->get( 'bundlekit_bogo_reward_active' );
	}

	/**
	 * Remove every BOGO trace from the cart (kill-switch path).
	 */
	private static function strip_all( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) ) {
				$cart->remove_cart_item( $key );
				continue;
			}
			if ( isset( $cart->cart_contents[ $key ] ) ) {
				unset( $cart->cart_contents[ $key ]['bundlekit_bogo_line_reward'] );
				unset( $cart->cart_contents[ $key ]['bundlekit_bogo_price'] );
			}
		}
		if ( WC()->session ) {
			WC()->session->set( 'bundlekit_bogo_state', array() );
		}
	}

	/* ================= per-customer usage ================= */

	/** How many times this customer already used the offer. */
	private static function customer_usage( $offer_id ) {
		$key = 'bundlekit_bogo_used_' . $offer_id;
		if ( is_user_logged_in() ) {
			return (int) get_user_meta( get_current_user_id(), $key, true );
		}
		if ( WC()->session ) {
			return (int) WC()->session->get( $key );
		}
		return 0;
	}

	/**
	 * Count one use per offer per order that carried its reward.
	 */
	public static function record_usage( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$used = array();
		foreach ( $order->get_items() as $item ) {
			$offer_id = $item->get_meta( '_bundlekit_bogo_offer' );
			if ( $offer_id ) {
				$used[ $offer_id ] = true;
			}
		}
		if ( ! $used ) {
			return;
		}
		$user_id = $order->get_customer_id();
		foreach ( array_keys( $used ) as $offer_id ) {
			$key = 'bundlekit_bogo_used_' . $offer_id;
			if ( $user_id ) {
				$count = (int) get_user_meta( $user_id, $key, true );
				update_user_meta( $user_id, $key, $count + 1 );
			} elseif ( WC()->session ) {
				$count = (int) WC()->session->get( $key );
				WC()->session->set( $key, $count + 1 );
			}
		}
	}

	/* ================= gift choice endpoint ================= */

	/**
	 * Store the customer's gift choice in the WooCommerce session.
	 * Called by frontend.js when the gift picker is submitted.
	 */
	public static function gift_choice() {
		if ( ! BundleKit_Buy_X_Get_Y::enabled() ) {
			wp_send_json_error();
		}
		$offer_id = isset( $_POST['offer_id'] ) ? sanitize_key( wp_unslash( $_POST['offer_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $offer_id || ! wp_verify_nonce( $nonce, 'bundlekit_bogo_gift' ) || ! WC()->session ) {
			wp_send_json_error();
		}
		$offer = null;
		foreach ( BundleKit_Buy_X_Get_Y::active_offers() as $o ) {
			if ( $o['id'] === $offer_id ) {
				$offer = $o;
				break;
			}
		}
		if ( ! $offer || 'customer' !== $offer['who_chooses'] ) {
			wp_send_json_error();
		}
		$product = wc_get_product( $offer['reward_product_id'] );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error();
		}
		$variation_id = 0;
		if ( $product->is_type( 'variable' ) ) {
			$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$variation    = wc_get_product( $variation_id );
			if ( ! $variation || (int) $variation->get_parent_id() !== (int) $product->get_id() || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				wp_send_json_error();
			}
		}
		WC()->session->set(
			'bundlekit_bogo_choice_' . $offer_id,
			array(
				'product_id'   => (int) $product->get_id(),
				'variation_id' => $variation_id,
			)
		);
		if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
			// Make the session real for guests, same as the Buy in
			// Packs pack flag: without a cookie the choice would be
			// lost with the request.
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->save_data();
		wp_send_json_success();
	}

	/* ================= coupon stacking ================= */

	/**
	 * While a reward is active and stacking is set to "block", reject
	 * other promo codes with an explanatory notice (spec F17).
	 */
	public static function maybe_block_coupon( $coupon_code ) {
		if ( 'block' !== BundleKit_Buy_X_Get_Y::coupon_mode() ) {
			return;
		}
		if ( ! self::reward_active() ) {
			return;
		}
		if ( ! WC()->cart ) {
			return;
		}
		WC()->cart->remove_coupon( $coupon_code );
		wc_add_notice( __( 'Promo codes cannot be combined with an active Buy X Get Y reward.', 'bundlekit-buy-x-get-y' ), 'notice' );
	}

	/* ================= display / order meta ================= */

	public static function get_cart_item_from_session( $cart_item, $values ) {
		foreach ( array( 'bundlekit_bogo_offer', 'bundlekit_bogo_earned', 'bundlekit_bogo_declined', 'bundlekit_bogo_price', 'bundlekit_bogo_regular', 'bundlekit_bogo_line_reward' ) as $k ) {
			if ( isset( $values[ $k ] ) ) {
				$cart_item[ $k ] = $values[ $k ];
			}
		}
		return $cart_item;
	}

	/** Find an offer by id (for labels on reward lines). */
	private static function find_offer( $offer_id ) {
		foreach ( BundleKit_Buy_X_Get_Y::get_offers() as $offer ) {
			if ( $offer['id'] === $offer_id ) {
				return $offer;
			}
		}
		return null;
	}

	public static function item_data( $item_data, $cart_item ) {
		// Server-side show/hide: when the reward badge is off, no row
		// is added at all (the preview uses the bxgy-no-reward-badge
		// class on its sample instead).
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		if ( empty( $values['show_reward_badge'] ) ) {
			return $item_data;
		}
		$offer_id = null;
		$label    = '';
		if ( isset( $cart_item['bundlekit_bogo_offer'] ) ) {
			$offer_id = $cart_item['bundlekit_bogo_offer'];
			$label    = __( 'Free gift', 'bundlekit-buy-x-get-y' );
		} elseif ( isset( $cart_item['bundlekit_bogo_line_reward'] ) ) {
			$offer_id = $cart_item['bundlekit_bogo_line_reward'];
			$label    = __( 'Cheapest free', 'bundlekit-buy-x-get-y' );
		}
		if ( null === $offer_id ) {
			return $item_data;
		}
		$offer = self::find_offer( $offer_id );
		$name  = $offer ? $offer['name'] : __( 'Buy X Get Y', 'bundlekit-buy-x-get-y' );
		if ( '' === $name ) {
			$name = __( 'Buy X Get Y', 'bundlekit-buy-x-get-y' );
		}
		$item_data[] = array(
			'key'   => __( 'Offer', 'bundlekit-buy-x-get-y' ),
			/* translators: 1: reward label, 2: offer name */
			'value' => sprintf( __( '%1$s — %2$s', 'bundlekit-buy-x-get-y' ), $label, $name ),
		);
		return $item_data;
	}

	/**
	 * Struck regular price on reward lines: "<del>$20.00</del>
	 * <ins>Free</ins>". Spec §2.3: reward lines show the regular
	 * price struck through with the reward price.
	 */
	public static function cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		$is_reward = isset( $cart_item['bundlekit_bogo_offer'] ) || isset( $cart_item['bundlekit_bogo_line_reward'] );
		if ( ! $is_reward ) {
			return $price_html;
		}
		$regular = isset( $cart_item['bundlekit_bogo_regular'] ) ? (float) $cart_item['bundlekit_bogo_regular'] : 0.0;
		if ( $regular <= 0 && isset( $cart_item['data'] ) ) {
			$regular = (float) $cart_item['data']->get_regular_price();
		}
		if ( $regular <= 0 ) {
			return $price_html;
		}
		$unit = isset( $cart_item['data'] ) ? (float) $cart_item['data']->get_price() : 0.0;
		$sale = $unit <= 0
			? '<span class="bxgy-free">' . esc_html__( 'Free', 'bundlekit-buy-x-get-y' ) . '</span>'
			: $price_html;
		return wc_format_sale_price( wc_price( $regular ), $sale );
	}

	public static function order_item_meta( $item, $cart_item_key, $values ) {
		$offer_id = null;
		if ( isset( $values['bundlekit_bogo_offer'] ) ) {
			$offer_id = $values['bundlekit_bogo_offer'];
		} elseif ( isset( $values['bundlekit_bogo_line_reward'] ) ) {
			$offer_id = $values['bundlekit_bogo_line_reward'];
		}
		if ( null === $offer_id ) {
			return;
		}
		$offer = self::find_offer( $offer_id );
		$name  = $offer && '' !== $offer['name'] ? $offer['name'] : __( 'Buy X Get Y', 'bundlekit-buy-x-get-y' );
		$item->add_meta_data( '_bundlekit_bogo_offer', $offer_id, true );
		$item->add_meta_data( __( 'BOGO offer', 'bundlekit-buy-x-get-y' ), $name, true );
	}

	/* ================= frontend helpers ================= */

	/**
	 * Progress of one offer against the current cart, for the banner
	 * and progress bar. Returns met/need/times/remaining.
	 *
	 * @return array{met:float,need:float,times:int,remaining:float}
	 */
	public static function offer_progress( $offer ) {
		$out = array( 'met' => 0, 'need' => 0, 'times' => 0, 'remaining' => 0 );
		if ( ! WC()->cart ) {
			return $out;
		}
		$trigger_qty = 0;
		$spend       = 0.0;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) || isset( $item['bundlekit_bundle_price'] ) ) {
				continue;
			}
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$parent_id    = $variation_id && isset( $item['data'] ) ? (int) $item['data']->get_parent_id() : $product_id;
			$line         = array(
				'qty'          => (int) $item['quantity'],
				'unit'         => self::line_unit_price( $item ),
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'cats'         => $parent_id ? wc_get_product_term_ids( $parent_id, 'product_cat' ) : array(),
				'tags'         => $parent_id ? wc_get_product_term_ids( $parent_id, 'product_tag' ) : array(),
			);
			if ( self::line_matches_trigger( $line, $offer ) ) {
				$trigger_qty += $line['qty'];
				$spend       += $line['unit'] * $line['qty'];
			}
		}
		if ( 'min_spend' === $offer['trigger_type'] ) {
			$need = max( 0, (float) $offer['min_spend'] );
			$out  = array(
				'met'       => $spend,
				'need'      => $need,
				'times'     => $need > 0 ? (int) floor( $spend / $need ) : 0,
				'remaining' => max( 0, $need - $spend ),
			);
		} else {
			$need = max( 1, (int) $offer['trigger_qty'] );
			$out  = array(
				'met'       => $trigger_qty,
				'need'      => $need,
				'times'     => (int) floor( $trigger_qty / $need ),
				'remaining' => max( 0, $need - ( $trigger_qty % $need ) ) % $need,
			);
		}
		if ( $offer['max_per_cart'] > 0 ) {
			$out['times'] = min( $out['times'], $offer['max_per_cart'] );
		}
		return $out;
	}

	/**
	 * Does the cart hold an active (non-declined) reward line for the
	 * offer? Drives gift-picker visibility.
	 */
	public static function offer_has_reward_line( $offer_id ) {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item['bundlekit_bogo_offer'] ) && $item['bundlekit_bogo_offer'] === $offer_id && empty( $item['bundlekit_bogo_declined'] ) ) {
				return true;
			}
		}
		return false;
	}
}
