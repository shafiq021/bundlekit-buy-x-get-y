<?php
/**
 * BundleKit admin UI helpers — shared markup for every add-on's global
 * pages (Settings, Styling Controls, Display), so all add-ons render the
 * same pages in the same style.
 *
 * Framework 1.0.4. Loaded once, from the first BundleKit add-on that
 * boots (see bundlekit-framework.php). Every helper is wrapped in
 * function_exists() — the same compile-time-fatal guard as the other
 * framework functions — so a second bundled copy can never redeclare them.
 *
 * Styling lives in assets/css/bundlekit-admin-ui.css; behaviour in
 * assets/js/bundlekit-admin-ui.js. No inline CSS anywhere here.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'bundlekit_ui_head' ) ) {
	/**
	 * Page header: logo badge, title, subtitle.
	 */
	function bundlekit_ui_head( $logo_icon, $title, $subtitle ) {
		?>
		<div class="bk-head">
			<span class="bk-logo"><span class="dashicons <?php echo esc_attr( $logo_icon ); ?>"></span></span>
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $subtitle ); ?></p>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_tabs' ) ) {
	/**
	 * Tab navigation.
	 *
	 * @param string $page_slug Menu slug, e.g. 'bundlekit-fixed-bundle'.
	 * @param array  $tabs      key => array( 'label' => ..., 'icon' => 'dashicons-...' ).
	 * @param string $current   Active tab key.
	 */
	function bundlekit_ui_tabs( $page_slug, $tabs, $current ) {
		?>
		<nav class="bk-tabs">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $key ) ); ?>" class="bk-tab<?php echo $key === $current ? ' is-active' : ''; ?>">
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_section_title' ) ) {
	/**
	 * H2 + description block, e.g. the Display page heading.
	 */
	function bundlekit_ui_section_title( $title, $desc = '' ) {
		?>
		<div class="bk-section-title">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $desc ) : ?>
				<p><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_card_open' ) ) {
	/**
	 * Open a white section card. $extra_class adds classes to the card
	 * (e.g. 'bk-card--wide'); $attrs is an already-escaped attribute
	 * string built by the caller (e.g. data-bk-group="style2").
	 */
	function bundlekit_ui_card_open( $icon, $title, $subtitle = '', $extra_class = '', $attrs = '' ) {
		$class = 'bk-card' . ( '' !== $extra_class ? ' ' . $extra_class : '' );
		?>
		<div class="<?php echo esc_attr( $class ); ?>"<?php echo $attrs ? ' ' . $attrs : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="bk-card-head">
				<span class="bk-card-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
				<div>
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( '' !== $subtitle ) : ?>
						<p><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_card_close' ) ) {
	function bundlekit_ui_card_close() {
		echo '</div>';
	}
}

if ( ! function_exists( 'bundlekit_ui_lock_note' ) ) {
	/**
	 * "Enable Style 2 to customize these options" note. Hidden unless
	 * the card has .is-dimmed (see the stylesheet).
	 */
	function bundlekit_ui_lock_note( $text ) {
		?>
		<p class="bk-lock-note"><span class="dashicons dashicons-lock"></span> <?php echo esc_html( $text ); ?></p>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_toggle' ) ) {
	/**
	 * iOS-style toggle switch. Same name/value/checked contract as a
	 * plain checkbox, so save handlers are unchanged.
	 */
	function bundlekit_ui_toggle( $name, $label, $checked ) {
		?>
		<label class="bk-toggle">
			<input type="checkbox" class="bk-toggle-input" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( $checked ); ?> />
			<span class="bk-toggle-track" aria-hidden="true"></span>
			<span class="bk-toggle-label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_save_button' ) ) {
	/**
	 * Primary blue submit button with a save icon.
	 */
	function bundlekit_ui_save_button( $label ) {
		?>
		<p class="bk-actions">
			<button type="submit" class="bk-btn bk-btn-primary"><span class="dashicons dashicons-saved"></span> <?php echo esc_html( $label ); ?></button>
		</p>
		<?php
	}
}

if ( ! function_exists( 'bundlekit_ui_style_field' ) ) {
	/**
	 * One styling control (color / number+px / font / weight), in the
	 * new card design. Input IDs and names are the caller's
	 * responsibility and are kept exactly as the live-preview scripts
	 * expect them (e.g. bundlekit_style_<key>).
	 *
	 * @param string $id     Input id.
	 * @param string $name   Input name.
	 * @param array  $spec   array( 'type' => 'color|number|font|weight', 'label' => ... ).
	 * @param string $value  Current (sanitized) value; '' = default look.
	 * @param array  $fonts  Font key => stack, for the font select.
	 * @param string $domain Text domain for the helper strings.
	 */
	function bundlekit_ui_style_field( $id, $name, $spec, $value, $fonts, $domain ) {
		?>
		<div class="bk-field">
			<label class="bk-field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $spec['label'] ); ?></label>
			<?php if ( 'color' === $spec['type'] ) : ?>
				<div class="bk-color-wrap">
					<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="#000000" class="bk-input bundlekit-color-field" data-default-color="" />
				</div>
				<p class="bk-field-desc"><?php esc_html_e( 'Pick a color, or clear it for the default look.', $domain ); ?></p>
			<?php elseif ( 'number' === $spec['type'] ) : ?>
				<div class="bk-input-group">
					<input type="number" min="0" step="any" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="bk-input" />
					<span class="bk-suffix">px</span>
				</div>
				<p class="bk-field-desc"><?php esc_html_e( 'Pixels. Empty = default.', $domain ); ?></p>
			<?php elseif ( 'font' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select">
					<option value=""><?php esc_html_e( 'Theme default', $domain ); ?></option>
					<?php foreach ( (array) $fonts as $key => $stack ) : ?>
						<?php if ( '' === $key ) { continue; } ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( ucfirst( $key ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'weight' === $spec['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="bk-select">
					<option value=""><?php esc_html_e( 'Theme default', $domain ); ?></option>
					<?php foreach ( array( '400' => 'Normal (400)', '500' => 'Medium (500)', '600' => 'Semi-bold (600)', '700' => 'Bold (700)', '800' => 'Extra bold (800)' ) as $w => $w_label ) : ?>
						<option value="<?php echo esc_attr( $w ); ?>" <?php selected( $value, (string) $w ); ?>><?php echo esc_html( $w_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
		<?php
	}
}
