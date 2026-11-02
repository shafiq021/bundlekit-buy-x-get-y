<?php
defined( 'ABSPATH' ) || exit;

/**
 * Buy X Get Y — offer model, trigger matching and reward maths.
 *
 * v1.0.0 scope: offers are GLOBAL rules (BundleKit > Buy X Get Y >
 * Offers). One offer = one trigger + one reward. The product editor
 * gets no new tabs — everything is global.
 *
 * Trigger types:
 *   products   — buy X units of specific products
 *   categories — buy X units from specific product categories
 *   tags       — buy X units from specific product tags
 *   min_spend  — spend at least $Y (subtotal of trigger-eligible lines)
 *
 * Reward types:
 *   free               — a specific product, qty N, price 0
 *   percent            — a specific product, qty N, % off its price
 *   fixed              — a specific product, qty N, $ off per unit
 *   cheapest_free      — the cheapest N trigger-eligible lines go free
 *   most_expensive_free— the most-expensive N trigger-eligible lines go free
 */
class BundleKit_Buy_X_Get_Y {

	public static function init() {
		// Nothing to hook here — config/matching are used by Admin / Pricing / Frontend.
	}

	/**
	 * Global on/off (BundleKit > Buy X Get Y > Settings). Default on:
	 * turning it off hides every BOGO widget and stops all offer
	 * evaluation site-wide; the offer setup is kept.
	 */
	public static function enabled() {
		return 'no' !== get_option( 'bundlekit_buy_x_get_y_enabled', 'yes' );
	}

	/**
	 * Coupon stacking mode (BundleKit > Buy X Get Y > Settings):
	 * 'block' (default) = while any reward is active, other promo
	 * codes are rejected with a notice; 'allow' = coupons stay usable.
	 */
	public static function coupon_mode() {
		$mode = get_option( 'bundlekit_buy_x_get_y_coupon_mode', 'block' );
		return in_array( $mode, array( 'block', 'allow' ), true ) ? $mode : 'block';
	}

	/** Trigger types: key => label. */
	public static function trigger_types() {
		return array(
			'products'   => __( 'Specific products', 'bundlekit-buy-x-get-y' ),
			'categories' => __( 'Product categories', 'bundlekit-buy-x-get-y' ),
			'tags'       => __( 'Product tags', 'bundlekit-buy-x-get-y' ),
			'min_spend'  => __( 'Minimum spend', 'bundlekit-buy-x-get-y' ),
		);
	}

	/** Reward types: key => label. */
	public static function reward_types() {
		return array(
			'free'                => __( 'Free product', 'bundlekit-buy-x-get-y' ),
			'percent'             => __( 'Percentage discount', 'bundlekit-buy-x-get-y' ),
			'fixed'               => __( 'Fixed amount off', 'bundlekit-buy-x-get-y' ),
			'cheapest_free'       => __( 'Cheapest item free', 'bundlekit-buy-x-get-y' ),
			'most_expensive_free' => __( 'Most expensive item free', 'bundlekit-buy-x-get-y' ),
		);
	}

	/** Offer statuses: key => label. */
	public static function offer_statuses() {
		return array(
			'active'    => __( 'Active', 'bundlekit-buy-x-get-y' ),
			'draft'     => __( 'Draft', 'bundlekit-buy-x-get-y' ),
			'scheduled' => __( 'Scheduled', 'bundlekit-buy-x-get-y' ),
		);
	}

	/**
	 * Sanitize one offer. All ids are absint, quantities are whole
	 * units, money is a non-negative float, text is sanitized.
	 *
	 * @param array $offer Raw offer.
	 * @return array
	 */
	public static function sanitize_offer( $offer ) {
		$offer = is_array( $offer ) ? $offer : array();
		$g = function ( $key, $default = '' ) use ( $offer ) {
			return isset( $offer[ $key ] ) ? $offer[ $key ] : $default;
		};
		$ids = function ( $raw ) {
			$out = array();
			foreach ( (array) $raw as $id ) {
				$id = absint( $id );
				if ( $id > 0 ) {
					$out[] = $id;
				}
			}
			return array_values( array_unique( $out ) );
		};

		$trigger_type = (string) $g( 'trigger_type', 'products' );
		if ( ! isset( self::trigger_types()[ $trigger_type ] ) ) {
			$trigger_type = 'products';
		}
		$reward_type = (string) $g( 'reward_type', 'free' );
		if ( ! isset( self::reward_types()[ $reward_type ] ) ) {
			$reward_type = 'free';
		}
		$status = (string) $g( 'status', 'active' );
		if ( ! isset( self::offer_statuses()[ $status ] ) ) {
			$status = 'active';
		}
		$who = (string) $g( 'who_chooses', 'auto' );
		if ( ! in_array( $who, array( 'auto', 'customer' ), true ) ) {
			$who = 'auto';
		}

		return array(
			'id'                 => '' !== (string) $g( 'id', '' ) ? sanitize_key( (string) $g( 'id' ) ) : uniqid( 'offer_' ),
			'name'               => sanitize_text_field( (string) $g( 'name', '' ) ),
			'status'             => $status,
			'start'              => sanitize_text_field( (string) $g( 'start', '' ) ),
			'end'                => sanitize_text_field( (string) $g( 'end', '' ) ),
			'priority'           => max( 0, absint( $g( 'priority', 10 ) ) ),
			'trigger_type'       => $trigger_type,
			'trigger_products'   => $ids( $g( 'trigger_products', array() ) ),
			'trigger_cats'       => $ids( $g( 'trigger_cats', array() ) ),
			'trigger_tags'       => $ids( $g( 'trigger_tags', array() ) ),
			'trigger_qty'        => max( 1, absint( $g( 'trigger_qty', 1 ) ) ),
			'min_spend'          => max( 0, (float) $g( 'min_spend', 0 ) ),
			'reward_type'        => $reward_type,
			'reward_product_id'  => absint( $g( 'reward_product_id', 0 ) ),
			'reward_variation_id'=> absint( $g( 'reward_variation_id', 0 ) ),
			'reward_qty'         => max( 1, absint( $g( 'reward_qty', 1 ) ) ),
			'reward_percent'     => min( 100, max( 0, (float) $g( 'reward_percent', 0 ) ) ),
			'reward_fixed'       => max( 0, (float) $g( 'reward_fixed', 0 ) ),
			'max_per_cart'       => max( 0, absint( $g( 'max_per_cart', 0 ) ) ),
			'max_per_customer'   => max( 0, absint( $g( 'max_per_customer', 0 ) ) ),
			'who_chooses'        => $who,
		);
	}

	/**
	 * Sanitize the submitted Offers repeater rows.
	 *
	 * @param mixed $raw Raw $_POST rows.
	 * @return array<int,array>
	 */
	public static function sanitize_offers_input( $raw ) {
		$offers = array();
		if ( ! is_array( $raw ) ) {
			return $offers;
		}
		ksort( $raw );
		foreach ( array_values( $raw ) as $row ) {
			$offer = self::sanitize_offer( is_array( $row ) ? $row : array() );
			// Drop rows with no name and no trigger selection — they
			// are the empty "add offer" template row.
			if ( '' === $offer['name'] && empty( $offer['trigger_products'] ) && empty( $offer['trigger_cats'] ) && empty( $offer['trigger_tags'] ) && $offer['min_spend'] <= 0 ) {
				continue;
			}
			$offers[] = $offer;
		}
		return $offers;
	}

	/**
	 * All offers, sanitized, in saved order (oldest first).
	 *
	 * @return array<int,array>
	 */
	public static function get_offers() {
		$raw    = get_option( 'bundlekit_buy_x_get_y_offers', array() );
		$offers = array();
		foreach ( (array) $raw as $offer ) {
			$offers[] = self::sanitize_offer( $offer );
		}
		return $offers;
	}

	/**
	 * Is the offer inside its status/date window? 'active' = yes,
	 * 'scheduled' = yes only between start and end (store timezone),
	 * 'draft' = never.
	 */
	public static function offer_is_live( $offer ) {
		if ( 'active' === $offer['status'] ) {
			return true;
		}
		if ( 'scheduled' !== $offer['status'] ) {
			return false;
		}
		$now = current_time( 'timestamp' );
		if ( '' !== $offer['start'] ) {
			$start = strtotime( $offer['start'] . ' 00:00:00' );
			if ( $start && $now < $start ) {
				return false;
			}
		}
		if ( '' !== $offer['end'] ) {
			$end = strtotime( $offer['end'] . ' 23:59:59' );
			if ( $end && $now > $end ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Live offers in evaluation order: priority ascending, ties =
	 * oldest offer first (saved order).
	 *
	 * @return array<int,array>
	 */
	public static function active_offers() {
		$offers = array();
		foreach ( self::get_offers() as $i => $offer ) {
			if ( self::offer_is_live( $offer ) ) {
				$offer['_order'] = $i;
				$offers[]        = $offer;
			}
		}
		usort(
			$offers,
			function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $a['priority'] - $b['priority'];
				}
				return $a['_order'] - $b['_order'];
			}
		);
		return $offers;
	}

	/**
	 * Does one product id match the offer's trigger selection?
	 * Used by the product-page banner.
	 */
	public static function product_triggers_offer( $product_id, $offer ) {
		$product_id = absint( $product_id );
		switch ( $offer['trigger_type'] ) {
			case 'products':
				return in_array( $product_id, $offer['trigger_products'], true );
			case 'categories':
				$cats = wc_get_product_term_ids( $product_id, 'product_cat' );
				return (bool) array_intersect( $cats, $offer['trigger_cats'] );
			case 'tags':
				$tags = wc_get_product_term_ids( $product_id, 'product_tag' );
				return (bool) array_intersect( $tags, $offer['trigger_tags'] );
			case 'min_spend':
				// Any purchasable product can contribute to spend.
				$product = wc_get_product( $product_id );
				return $product && $product->is_purchasable();
		}
		return false;
	}

	/**
	 * Human-readable offer summary for the admin list and banners,
	 * e.g. "Buy 2 of Sale items, get 1 free".
	 */
	public static function offer_summary( $offer ) {
		$types = self::trigger_types();
		$rtypes = self::reward_types();
		$trigger = isset( $types[ $offer['trigger_type'] ] ) ? $types[ $offer['trigger_type'] ] : $offer['trigger_type'];
		$reward  = isset( $rtypes[ $offer['reward_type'] ] ) ? $rtypes[ $offer['reward_type'] ] : $offer['reward_type'];
		if ( 'min_spend' === $offer['trigger_type'] ) {
			/* translators: 1: spend amount, 2: reward description */
			return sprintf( __( 'Spend %1$s, get %2$s', 'bundlekit-buy-x-get-y' ), wc_price( $offer['min_spend'] ), $reward );
		}
		/* translators: 1: trigger quantity, 2: trigger description, 3: reward description */
		return sprintf( __( 'Buy %1$d (%2$s), get %3$s', 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'], $trigger, $reward );
	}

	/**
	 * The banner headline for one offer on the product page, e.g.
	 * "Buy 2, get 1 free". Reward product name is appended by the
	 * frontend when the reward is a specific product.
	 */
	public static function banner_headline( $offer ) {
		if ( 'min_spend' === $offer['trigger_type'] ) {
			$spend = wc_price( $offer['min_spend'] );
			switch ( $offer['reward_type'] ) {
				case 'free':
					/* translators: 1: spend amount, 2: reward quantity */
					return sprintf( _n( 'Spend %1$s, get 1 free', 'Spend %1$s, get %2$d free', $offer['reward_qty'], 'bundlekit-buy-x-get-y' ), $spend, $offer['reward_qty'] );
				case 'percent':
					/* translators: 1: spend amount, 2: percent, 3: reward quantity */
					return sprintf( __( 'Spend %1$s, get %3$d at %2$d%% off', 'bundlekit-buy-x-get-y' ), $spend, (float) $offer['reward_percent'], $offer['reward_qty'] );
				case 'fixed':
					/* translators: 1: spend amount, 2: discount amount, 3: reward quantity */
					return sprintf( __( 'Spend %1$s, get %3$d with %2$s off', 'bundlekit-buy-x-get-y' ), $spend, wc_price( $offer['reward_fixed'] ), $offer['reward_qty'] );
				case 'cheapest_free':
					/* translators: %s: spend amount */
					return sprintf( __( 'Spend %s, get the cheapest free', 'bundlekit-buy-x-get-y' ), $spend );
				case 'most_expensive_free':
					/* translators: %s: spend amount */
					return sprintf( __( 'Spend %s, get the most expensive free', 'bundlekit-buy-x-get-y' ), $spend );
			}
			return '';
		}
		switch ( $offer['reward_type'] ) {
			case 'free':
				/* translators: %d: trigger quantity */
				return sprintf( _n( 'Buy %d, get 1 free', 'Buy %d, get %d free', $offer['reward_qty'], 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'], $offer['reward_qty'] );
			case 'percent':
				/* translators: 1: trigger quantity, 2: percent, 3: reward quantity */
				return sprintf( __( 'Buy %1$d, get %3$d at %2$d%% off', 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'], (float) $offer['reward_percent'], $offer['reward_qty'] );
			case 'fixed':
				/* translators: 1: trigger quantity, 2: amount, 3: reward quantity */
				return sprintf( __( 'Buy %1$d, get %3$d with %2$s off', 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'], wc_price( $offer['reward_fixed'] ), $offer['reward_qty'] );
			case 'cheapest_free':
				/* translators: %d: trigger quantity */
				return sprintf( __( 'Buy %d, get the cheapest free', 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'] );
			case 'most_expensive_free':
				/* translators: %d: trigger quantity */
				return sprintf( __( 'Buy %d, get the most expensive free', 'bundlekit-buy-x-get-y' ), $offer['trigger_qty'] );
		}
		return '';
	}

	/**
	 * Unit price for a specific-product reward line: the product's
	 * price with the reward discount applied, rounded per line
	 * half-up to the store's minor unit (spec F16 decision).
	 *
	 * @return float
	 */
	public static function reward_unit_price( $offer, $product ) {
		$price = $product ? (float) $product->get_price() : 0;
		switch ( $offer['reward_type'] ) {
			case 'free':
				return 0.0;
			case 'percent':
				$price = $price * ( 100 - $offer['reward_percent'] ) / 100;
				break;
			case 'fixed':
				$price = $price - $offer['reward_fixed'];
				break;
		}
		$price = max( 0, $price );
		return round( $price, wc_get_price_decimals() );
	}

	/**
	 * Font stacks offered for the banner / picker text. Keys are
	 * stored in options; the stack itself is only ever output from
	 * this allow-list, never from raw user input.
	 *
	 * @return array<string,string>
	 */
	public static function font_stacks() {
		return array(
			''          => '',
			'system'    => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'verdana'   => 'Verdana, Geneva, sans-serif',
			'trebuchet' => '"Trebuchet MS", Helvetica, sans-serif',
			'georgia'   => 'Georgia, serif',
			'times'     => '"Times New Roman", Times, serif',
			'mono'      => '"Courier New", Courier, monospace',
		);
	}

	/**
	 * Sanitized global styling values (BundleKit > Buy X Get Y >
	 * Styling Controls). The values every BOGO widget uses; an empty
	 * value means the built-in look.
	 *
	 * @return array<string,string>
	 */
	public static function global_style_values() {
		return BundleKit_Buy_X_Get_Y_Styling::styling_values();
	}

	/**
	 * CSS classes for hidden frontend elements, e.g. "bxgy-no-banner".
	 * Added to the BOGO widgets; frontend.css hides the matching part.
	 * The live preview uses the same classes. Show/hide state lives in
	 * the Styling Controls schema (checkbox controls, no CSS variable).
	 *
	 * @return array<int,string>
	 */
	public static function display_classes() {
		$values = BundleKit_Buy_X_Get_Y_Styling::styling_values();
		$map    = array(
			'show_banner'       => 'banner',
			'show_progress'     => 'progress',
			'show_picker'       => 'picker',
			'show_reward_badge' => 'reward-badge',
			'show_mini_cart'    => 'mini-cart',
		);
		$classes = array();
		foreach ( $map as $key => $suffix ) {
			if ( empty( $values[ $key ] ) ) {
				$classes[] = 'bxgy-no-' . $suffix;
			}
		}
		return $classes;
	}

	/** Back-compat: the schema lives on the Styling class. */
	public static function style_fields() {
		return BundleKit_Buy_X_Get_Y_Styling::all_controls();
	}

	/** Back-compat sanitizer. */
	public static function sanitize_style_value( $spec, $raw ) {
		return BundleKit_Buy_X_Get_Y_Styling::sanitize_value( $spec, $raw );
	}
}
