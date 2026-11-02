<?php
defined( 'ABSPATH' ) || exit;

/**
 * BundleKit – Buy X Get Y — schema-driven styling system.
 *
 * ONE schema array defines every styling control. The admin page, the
 * save handler, the live preview and the frontend CSS-variable output
 * all loop over this same array, so a control cannot exist in one
 * place and be missing in another.
 *
 * Adding a control = adding one $add() line below.
 *
 * Spec per control:
 *   key      Option array key.
 *   label    Admin label.
 *   type     color|number|font|weight|select|checkbox|text|textarea.
 *   default  Default value. '' for checkbox means unchecked.
 *            Clearing any other control restores its default.
 *   css_var  CSS custom property, e.g. --bxgy-banner-bg. '' = no CSS
 *            output (behavioral control: picker layout, labels).
 *   group    Group slug; groups render as collapsible cards in the
 *            order of styling_groups().
 *   unit     Appended on output for number types ('px', '').
 *   min/max  Clamp for number types.
 *   advanced If true, the control lives in the group's "Advanced"
 *            toggle instead of the main list.
 *   essential If true, the control also appears in the "Essentials"
 *            card at the top of the Styling tab.
 *   options  value => label pairs for select types.
 */
class BundleKit_Buy_X_Get_Y_Styling {

	/**
	 * Collapsible admin groups, in display order.
	 *
	 * @return array<string,string> slug => label.
	 */
	public static function styling_groups() {
		return array(
			'banner'     => __( 'Offer Banner', 'bundlekit-buy-x-get-y' ),
			'progress'   => __( 'Progress Bar', 'bundlekit-buy-x-get-y' ),
			'picker'     => __( 'Gift Picker', 'bundlekit-buy-x-get-y' ),
			'reward'     => __( 'Reward Line', 'bundlekit-buy-x-get-y' ),
			'typography' => __( 'Typography', 'bundlekit-buy-x-get-y' ),
			'container'  => __( 'Container', 'bundlekit-buy-x-get-y' ),
		);
	}

	/**
	 * The full controls schema.
	 *
	 * @return array<int,array>
	 */
	public static function styling_schema() {
		$s = array();
		$add = function ( $key, $label, $type, $group, $args = array() ) use ( &$s ) {
			$s[] = array_merge(
				array(
					'key'       => $key,
					'label'     => $label,
					'type'      => $type,
					'default'   => '',
					'css_var'   => '',
					'group'     => $group,
					'unit'      => '',
					'min'       => null,
					'max'       => null,
					'advanced'  => false,
					'essential' => false,
					'options'   => array(),
				),
				$args
			);
		};
		$px = array( 'unit' => 'px' );

		/* ============ Offer banner ============ */
		$add( 'show_banner', __( 'Show offer banner', 'bundlekit-buy-x-get-y' ), 'checkbox', 'banner', array( 'default' => 'yes' ) );
		$add( 'banner_bg', __( 'Banner background', 'bundlekit-buy-x-get-y' ), 'color', 'banner', array( 'css_var' => '--bxgy-banner-bg', 'default' => '#f0fdf4', 'essential' => true ) );
		$add( 'banner_border_color', __( 'Banner border color', 'bundlekit-buy-x-get-y' ), 'color', 'banner', array( 'css_var' => '--bxgy-banner-border-color', 'default' => '#bbf7d0', 'advanced' => true ) );
		$add( 'banner_radius', __( 'Banner corner radius', 'bundlekit-buy-x-get-y' ), 'number', 'banner', array( 'css_var' => '--bxgy-banner-radius', 'default' => '8', 'min' => 0, 'max' => 60 ) + $px );
		$add( 'banner_padding', __( 'Banner padding', 'bundlekit-buy-x-get-y' ), 'number', 'banner', array( 'css_var' => '--bxgy-banner-padding', 'default' => '12', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'banner_headline_size', __( 'Headline size', 'bundlekit-buy-x-get-y' ), 'number', 'banner', array( 'css_var' => '--bxgy-banner-headline-size', 'default' => '15', 'min' => 8, 'max' => 60, 'essential' => true ) + $px );
		$add( 'banner_headline_weight', __( 'Headline weight', 'bundlekit-buy-x-get-y' ), 'weight', 'banner', array( 'css_var' => '--bxgy-banner-headline-weight', 'default' => '700' ) );
		$add( 'banner_headline_color', __( 'Headline color', 'bundlekit-buy-x-get-y' ), 'color', 'banner', array( 'css_var' => '--bxgy-banner-headline-color', 'default' => '#14532d', 'essential' => true ) );
		$add( 'banner_sub_size', __( 'Sub-text size', 'bundlekit-buy-x-get-y' ), 'number', 'banner', array( 'css_var' => '--bxgy-banner-sub-size', 'default' => '13', 'min' => 8, 'max' => 40, 'advanced' => true ) + $px );
		$add( 'banner_sub_color', __( 'Sub-text color', 'bundlekit-buy-x-get-y' ), 'color', 'banner', array( 'css_var' => '--bxgy-banner-sub-color', 'default' => '#166534', 'advanced' => true ) );

		/* ============ Progress bar ============ */
		$add( 'show_progress', __( 'Show cart progress bar', 'bundlekit-buy-x-get-y' ), 'checkbox', 'progress', array( 'default' => 'yes' ) );
		$add( 'progress_track_bg', __( 'Track background', 'bundlekit-buy-x-get-y' ), 'color', 'progress', array( 'css_var' => '--bxgy-progress-track-bg', 'default' => '#e5e7eb' ) );
		$add( 'progress_fill_bg', __( 'Fill color', 'bundlekit-buy-x-get-y' ), 'color', 'progress', array( 'css_var' => '--bxgy-progress-fill-bg', 'default' => '#16a34a', 'essential' => true ) );
		$add( 'progress_height', __( 'Bar height', 'bundlekit-buy-x-get-y' ), 'number', 'progress', array( 'css_var' => '--bxgy-progress-height', 'default' => '8', 'min' => 2, 'max' => 40 ) + $px );
		$add( 'progress_radius', __( 'Bar corner radius', 'bundlekit-buy-x-get-y' ), 'number', 'progress', array( 'css_var' => '--bxgy-progress-radius', 'default' => '999', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'progress_text_color', __( 'Text color', 'bundlekit-buy-x-get-y' ), 'color', 'progress', array( 'css_var' => '--bxgy-progress-text-color', 'default' => '#374151' ) );
		$add( 'progress_text_size', __( 'Text size', 'bundlekit-buy-x-get-y' ), 'number', 'progress', array( 'css_var' => '--bxgy-progress-text-size', 'default' => '13', 'min' => 8, 'max' => 40, 'advanced' => true ) + $px );

		/* ============ Gift picker ============ */
		$add( 'show_picker', __( 'Show gift picker', 'bundlekit-buy-x-get-y' ), 'checkbox', 'picker', array( 'default' => 'yes' ) );
		$add( 'picker_layout', __( 'Picker layout', 'bundlekit-buy-x-get-y' ), 'select', 'picker', array( 'default' => 'inline', 'essential' => true, 'options' => array( 'inline' => __( 'Inline (in the cart)', 'bundlekit-buy-x-get-y' ), 'popup' => __( 'Popup', 'bundlekit-buy-x-get-y' ) ) ) );
		$add( 'show_mini_cart', __( 'Show picker in mini-cart', 'bundlekit-buy-x-get-y' ), 'checkbox', 'picker', array( 'default' => 'yes' ) );
		$add( 'picker_title', __( 'Picker title', 'bundlekit-buy-x-get-y' ), 'text', 'picker', array( 'default' => '' ) );
		$add( 'picker_bg', __( 'Picker background', 'bundlekit-buy-x-get-y' ), 'color', 'picker', array( 'css_var' => '--bxgy-picker-bg', 'default' => '#ffffff' ) );
		$add( 'picker_border_color', __( 'Picker border color', 'bundlekit-buy-x-get-y' ), 'color', 'picker', array( 'css_var' => '--bxgy-picker-border-color', 'default' => '#e5e7eb', 'advanced' => true ) );
		$add( 'picker_radius', __( 'Picker corner radius', 'bundlekit-buy-x-get-y' ), 'number', 'picker', array( 'css_var' => '--bxgy-picker-radius', 'default' => '8', 'min' => 0, 'max' => 60 ) + $px );
		$add( 'picker_text_color', __( 'Picker text color', 'bundlekit-buy-x-get-y' ), 'color', 'picker', array( 'css_var' => '--bxgy-picker-text-color', 'default' => '#111827' ) );
		$add( 'picker_button_bg', __( 'Button background', 'bundlekit-buy-x-get-y' ), 'color', 'picker', array( 'css_var' => '--bxgy-picker-button-bg', 'default' => '#16a34a', 'essential' => true ) );
		$add( 'picker_button_text_color', __( 'Button text color', 'bundlekit-buy-x-get-y' ), 'color', 'picker', array( 'css_var' => '--bxgy-picker-button-text-color', 'default' => '#ffffff' ) );
		$add( 'picker_button_radius', __( 'Button corner radius', 'bundlekit-buy-x-get-y' ), 'number', 'picker', array( 'css_var' => '--bxgy-picker-button-radius', 'default' => '6', 'min' => 0, 'max' => 60, 'advanced' => true ) + $px );
		$add( 'picker_button_text', __( 'Button text', 'bundlekit-buy-x-get-y' ), 'text', 'picker', array( 'default' => '' ) );

		/* ============ Reward line ============ */
		$add( 'show_reward_badge', __( 'Show reward badge', 'bundlekit-buy-x-get-y' ), 'checkbox', 'reward', array( 'default' => 'yes' ) );
		$add( 'reward_badge_bg', __( 'Badge background', 'bundlekit-buy-x-get-y' ), 'color', 'reward', array( 'css_var' => '--bxgy-reward-badge-bg', 'default' => '#16a34a' ) );
		$add( 'reward_badge_text_color', __( 'Badge text color', 'bundlekit-buy-x-get-y' ), 'color', 'reward', array( 'css_var' => '--bxgy-reward-badge-text-color', 'default' => '#ffffff' ) );
		$add( 'reward_price_color', __( 'Reward price color', 'bundlekit-buy-x-get-y' ), 'color', 'reward', array( 'css_var' => '--bxgy-reward-price-color', 'default' => '#16a34a', 'advanced' => true ) );
		$add( 'reward_struck_color', __( 'Struck price color', 'bundlekit-buy-x-get-y' ), 'color', 'reward', array( 'css_var' => '--bxgy-reward-struck-color', 'default' => '#9ca3af', 'advanced' => true ) );

		/* ============ Typography ============ */
		$add( 'base_font', __( 'Base font family', 'bundlekit-buy-x-get-y' ), 'font', 'typography', array( 'css_var' => '--bxgy-base-font', 'default' => '' ) );

		/* ============ Container ============ */
		$add( 'widget_max_width', __( 'Max width', 'bundlekit-buy-x-get-y' ), 'number', 'container', array( 'css_var' => '--bxgy-widget-max-width', 'default' => '0', 'min' => 0, 'max' => 2000, 'advanced' => true ) + $px );
		$add( 'margin_top', __( 'Margin top', 'bundlekit-buy-x-get-y' ), 'number', 'container', array( 'css_var' => '--bxgy-margin-top', 'default' => '0', 'min' => 0, 'max' => 120 ) + $px );
		$add( 'margin_bottom', __( 'Margin bottom', 'bundlekit-buy-x-get-y' ), 'number', 'container', array( 'css_var' => '--bxgy-margin-bottom', 'default' => '12', 'min' => 0, 'max' => 120 ) + $px );

		return $s;
	}

	/**
	 * All controls (single schema in 1.0.0 — no sub-schemas).
	 *
	 * @return array<int,array>
	 */
	public static function all_controls() {
		return self::styling_schema();
	}

	/**
	 * Group order for the schema.
	 *
	 * @return array<string,string>
	 */
	public static function all_groups() {
		$groups = self::styling_groups();
		// Custom CSS lives on its own tab, not in the Styling tab groups.
		$groups['custom_css'] = __( 'Custom CSS', 'bundlekit-buy-x-get-y' );
		return $groups;
	}

	/**
	 * Sanitize one control value. '' is only preserved for checkbox
	 * (unchecked); every other type falls back to its default.
	 *
	 * @param array $spec Control spec.
	 * @param mixed $raw  Raw input.
	 * @return string
	 */
	public static function sanitize_value( $spec, $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( 'checkbox' === $spec['type'] ) {
			return 'yes' === $raw ? 'yes' : '';
		}
		if ( '' === $raw ) {
			$raw = (string) $spec['default'];
		}
		switch ( $spec['type'] ) {
			case 'color':
				$v = sanitize_hex_color( $raw );
				return $v ? $v : (string) $spec['default'];
			case 'number':
				$v = is_numeric( $raw ) ? (float) $raw : (float) $spec['default'];
				if ( null !== $spec['min'] ) {
					$v = max( (float) $spec['min'], $v );
				}
				if ( null !== $spec['max'] ) {
					$v = min( (float) $spec['max'], $v );
				}
				return (string) ( (float) $v === (float) (int) $v ? (int) $v : round( $v, 2 ) );
			case 'font':
				$stacks = BundleKit_Buy_X_Get_Y::font_stacks();
				return isset( $stacks[ $raw ] ) ? $raw : (string) $spec['default'];
			case 'weight':
				return in_array( $raw, array( '400', '500', '600', '700', '800' ), true ) ? $raw : (string) $spec['default'];
			case 'select':
				return isset( $spec['options'][ $raw ] ) ? $raw : (string) $spec['default'];
			case 'textarea':
				return wp_strip_all_tags( $raw );
			default: // text.
				$v = sanitize_text_field( $raw );
				return '' !== $v ? $v : (string) $spec['default'];
		}
	}

	/**
	 * Format a sanitized value for CSS variable output.
	 *
	 * @param array  $spec  Control spec.
	 * @param string $value Sanitized value.
	 * @return string Empty when nothing should be output.
	 */
	public static function css_var_value( $spec, $value ) {
		if ( '' === $spec['css_var'] ) {
			return '';
		}
		if ( 'checkbox' === $spec['type'] ) {
			// Checked = default look (no variable); unchecked hides.
			return 'yes' === $value ? '' : 'none';
		}
		if ( 'number' === $spec['type'] ) {
			return '' === $value ? '' : $value . $spec['unit'];
		}
		if ( 'font' === $spec['type'] ) {
			$stacks = BundleKit_Buy_X_Get_Y::font_stacks();
			return isset( $stacks[ $value ] ) ? $stacks[ $value ] : '';
		}
		return $value;
	}

	/**
	 * The effective styling values: stored array → sanitize →
	 * defaults. One loop, the single source of truth for admin,
	 * preview and frontend.
	 *
	 * @return array<string,string>
	 */
	public static function styling_values() {
		$stored = get_option( 'bundlekit_buy_x_get_y_styling', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( self::all_controls() as $spec ) {
			$key = $spec['key'];
			if ( 'checkbox' === $spec['type'] ) {
				$raw = array_key_exists( $key, $stored ) ? $stored[ $key ] : $spec['default'];
			} else {
				$raw = isset( $stored[ $key ] ) && '' !== $stored[ $key ] ? $stored[ $key ] : $spec['default'];
			}
			$values[ $key ] = self::sanitize_value( $spec, $raw );
		}
		return $values;
	}

	/**
	 * Keys where 0 means "unset" and must not be output — mirrored in
	 * the live preview JS via the localized skipZero list (the
	 * 1.5.2 lesson: PHP omits these, so the JS must too).
	 *
	 * @return array<int,string>
	 */
	public static function skip_zero_keys() {
		return array( 'widget_max_width' );
	}

	/**
	 * Default picker title / button text (used when the text controls
	 * are empty).
	 */
	public static function default_texts() {
		return array(
			'picker_title'       => __( 'Choose your free gift', 'bundlekit-buy-x-get-y' ),
			'picker_button_text' => __( 'Add gift', 'bundlekit-buy-x-get-y' ),
		);
	}
}
