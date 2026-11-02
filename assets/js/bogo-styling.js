/* BundleKit – Buy X Get Y — Styling Controls live preview (1.0.0).
 * Schema-driven: every control updates the matching CSS variable on
 * ALL preview widgets instantly, using the localized
 * bundlekitBogoStyling.varMap (key -> {var, type, unit}).
 * Clearing a control restores its schema default (localized as
 * bundlekitBogoStyling.defaults) — exactly like the real widget.
 * Show/hide checkboxes toggle the same bxgy-no-* classes the frontend
 * uses (localized as bundlekitBogoStyling.classToggles).
 * 0 means "unset" for the localized skipZero keys (mirrors PHP
 * style_attr()); without this the preview collapses.
 */
(function ($) {
	'use strict';

	function data() {
		return window.bundlekitBogoStyling || {};
	}

	function cssValue(key, spec, raw) {
		var d = data();
		raw = (raw === undefined || raw === null) ? '' : raw.toString().trim();
		if ('' === raw) {
			raw = (d.defaults && d.defaults[key] !== undefined) ? d.defaults[key].toString() : '';
		}
		if ('' === raw) { return { remove: true }; }
		// 0 means "unset" for these keys (mirrors PHP style_attr()).
		var skipZero = d.skipZero || [];
		if (skipZero.indexOf(key) !== -1) {
			var zn = parseFloat(raw);
			if (!isNaN(zn) && zn === 0) { return { remove: true }; }
		}
		if ('number' === spec.type) {
			var n = parseFloat(raw);
			if (isNaN(n)) { return { remove: true }; }
			return { value: (n === Math.floor(n) ? n : n) + (spec.unit || '') };
		}
		if ('font' === spec.type) {
			var fonts = d.fonts || {};
			return fonts[raw] ? { value: fonts[raw] } : { remove: true };
		}
		return { value: raw };
	}

	// Checkbox toggles render without an id; fall back to the name.
	function fieldInput(key) {
		var $input = $('#bk_style_' + key);
		if (!$input.length) {
			$input = $('[name="bk_style[' + key + ']"]');
		}
		return $input;
	}

	function refresh() {
		var d = data();
		var map = d.varMap || {};
		var toggles = d.classToggles || {};
		var $previews = $('[data-bundlekit-bogo]');
		if (!$previews.length) { return; }
		$.each(map, function (key, spec) {
			var $input = fieldInput(key);
			var out = cssValue(key, spec, $input.val());
			$previews.each(function () {
				if (out.remove) {
					this.style.removeProperty(spec.var);
				} else {
					this.style.setProperty(spec.var, out.value);
				}
			});
		});
		// Show/hide checkboxes -> bxgy-no-* classes on the previews.
		$.each(toggles, function (key, cls) {
			var $input = fieldInput(key);
			var on = $input.length ? $input.is(':checked') : true;
			$previews.each(function () {
				$(this).toggleClass(cls, !on);
			});
		});
		syncBehavioral();
	}

	// Controls with no CSS variable that still change the preview's
	// markup: picker title and button text.
	function syncBehavioral() {
		var $box = $('[data-bundlekit-bogo-preview]');
		if (!$box.length) { return; }
		function val(key) {
			var $i = fieldInput(key);
			return $i.length ? $i.val() : null;
		}
		var title = val('picker_title');
		if (title) { $box.find('.bxgy-picker-title').text(title); }
		var btn = val('picker_button_text');
		if (btn) { $box.find('.bxgy-picker-button').text(btn); }
	}

	$(document).on('input change', '.bundlekit-styling-controls input, .bundlekit-styling-controls select, .bundlekit-styling-controls textarea', function () {
		refresh();
	});

	$(function () {
		// WP color picker on the color controls; refresh on pick/clear.
		if ($.fn.wpColorPicker) {
			$('.bundlekit-color-field').wpColorPicker({
				change: function () { setTimeout(refresh, 10); },
				clear: function () { setTimeout(refresh, 10); }
			});
		}
		refresh();
	});
})(jQuery);
