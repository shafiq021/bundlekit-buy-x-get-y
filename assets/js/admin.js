/* BundleKit – Buy X Get Y — Offers repeater + product search (1.0.0).
 *
 * - "Add offer" clones the hidden PHP-rendered template card,
 *   replacing __INDEX__ with the next row index.
 * - Offer name syncs to the card title; status/trigger/reward
 *   selects toggle their conditional rows inside the card.
 * - Product pickers: debounced AJAX product search
 *   (woocommerce_json_search_products_and_variations); picked
 *   products become chips with hidden inputs. Single pickers
 *   (reward product) refresh the variation dropdown via
 *   bundlekit_bogo_variations.
 */
(function ($) {
	'use strict';

	function data() {
		return window.bundlekitBogoAdmin || {};
	}

	/* ---------- offer cards ---------- */

	function nextIndex() {
		return $('#bxgy-offers-list [data-offer-card]').length;
	}

	$(document).on('click', '#bxgy-add-offer', function (e) {
		e.preventDefault();
		var html = $('#bxgy-offer-template').html();
		html = html.split('__INDEX__').join(nextIndex());
		$('#bxgy-offers-list').append(html);
	});

	$(document).on('click', '[data-offer-remove]', function (e) {
		e.preventDefault();
		if (!confirm(data().confirmRemove || 'Remove this offer?')) {
			return;
		}
		$(this).closest('[data-offer-card]').remove();
	});

	$(document).on('input', '.bxgy-offer-name', function () {
		var $card = $(this).closest('[data-offer-card]');
		var name = $(this).val().trim();
		$card.find('.bxgy-offer-title').text(name || 'New offer');
	});

	function syncCard($card) {
		var status = $card.find('[data-offer-status]').val();
		$card.find('[data-offer-dates]').prop('hidden', status !== 'scheduled');
		var trigger = $card.find('[data-trigger-type]').val();
		$card.find('[data-trigger-row]').each(function () {
			var row = $(this).data('trigger-row');
			var show = (row === trigger) || (row === 'qty' && trigger !== 'min_spend');
			$(this).prop('hidden', !show);
		});
		var reward = $card.find('[data-reward-type]').val();
		$card.find('[data-reward-row]').each(function () {
			var row = $(this).data('reward-row');
			var show = (row === 'product' && ['free', 'percent', 'fixed'].indexOf(reward) !== -1) ||
				(row === reward);
			$(this).prop('hidden', !show);
		});
	}

	$(document).on('change', '[data-offer-status], [data-trigger-type], [data-reward-type]', function () {
		syncCard($(this).closest('[data-offer-card]'));
	});
	$(function () {
		$('[data-offer-card]').each(function () { syncCard($(this)); });
	});

	/* ---------- product picker ---------- */

	var searchTimer = null;

	function pickerField($picker) {
		return $picker.data('field');
	}

	function addChip($picker, id, label) {
		var multiple = '1' === $picker.data('multiple').toString();
		var $sel = $picker.find('.bxgy-product-selected');
		if (!multiple) {
			$sel.empty();
		} else if ($sel.find('input[value="' + id + '"]').length) {
			return; // already picked
		}
		var $chip = $('<span class="bxgy-chip"></span>').text(label + ' ');
		$chip.append($('<button type="button" data-chip-remove aria-label="Remove">×</button>'));
		$chip.append($('<input type="hidden">').attr('name', pickerField($picker)).val(id));
		$sel.append($chip);
		$picker.trigger('bxgy:picked', [id]);
	}

	$(document).on('input', '.bxgy-product-search', function () {
		var $input = $(this);
		var $picker = $input.closest('[data-product-picker]');
		var $results = $picker.find('.bxgy-product-results');
		var term = $input.val().trim();
		clearTimeout(searchTimer);
		if (term.length < 2) {
			$results.prop('hidden', true).empty();
			return;
		}
		searchTimer = setTimeout(function () {
			// WooCommerce's search handler reads `term` from $_GET
			// only — a POST dies silently with no results.
			$.get(data().ajaxurl, {
				action: 'woocommerce_json_search_products_and_variations',
				security: data().searchNonce,
				term: term
			}, function (res) {
				$results.empty();
				if (!res || $.isEmptyObject(res)) {
					$results.prop('hidden', true);
					return;
				}
				$.each(res, function (id, label) {
					var $b = $('<button type="button"></button>').text(label).data('id', id).data('label', label);
					$results.append($b);
				});
				$results.prop('hidden', false);
			});
		}, 300);
	});

	$(document).on('click', '.bxgy-product-results button', function (e) {
		e.preventDefault();
		var $b = $(this);
		var $picker = $b.closest('[data-product-picker]');
		addChip($picker, $b.data('id'), $b.data('label'));
		$picker.find('.bxgy-product-search').val('');
		$picker.find('.bxgy-product-results').prop('hidden', true).empty();
	});

	$(document).on('click', '[data-chip-remove]', function (e) {
		e.preventDefault();
		var $chip = $(this).closest('.bxgy-chip');
		var $picker = $chip.closest('[data-product-picker]');
		$chip.remove();
		$picker.trigger('bxgy:picked', [0]);
	});

	// Hide results when clicking elsewhere.
	$(document).on('click', function (e) {
		if (!$(e.target).closest('[data-product-picker]').length) {
			$('.bxgy-product-results').prop('hidden', true);
		}
	});

	/* ---------- reward variation refresh ---------- */

	function refreshVariations($card, productId, selected) {
		var $select = $card.find('[data-reward-variation]');
		if (!$select.length) {
			return;
		}
		$select.prop('disabled', true);
		$.post(data().ajaxurl, {
			action: 'bundlekit_bogo_variations',
			nonce: data().variationNonce,
			product_id: productId
		}, function (res) {
			$select.empty();
			$select.append($('<option value="0"></option>').text(data().noVariationText || 'No variation'));
			if (res && res.success && res.data && res.data.length) {
				$.each(res.data, function (i, v) {
					$select.append($('<option></option>').attr('value', v.id).text(v.label));
				});
				$select.closest('[data-reward-variation-wrap]').show();
			} else {
				$select.closest('[data-reward-variation-wrap]').hide();
			}
			if (selected) {
				$select.val(selected);
			}
			$select.prop('disabled', false);
		});
	}

	$(document).on('bxgy:picked', '[data-product-picker]', function (e, id) {
		var $picker = $(this);
		var $card = $picker.closest('[data-offer-card]');
		// Only the reward product picker (single) drives variations.
		if ('1' === $picker.data('multiple').toString() || !$card.length) {
			return;
		}
		if ($picker.find('.bxgy-product-selected input').length && $picker.closest('[data-reward-row="product"]').length) {
			refreshVariations($card, id, 0);
		}
	});
})(jQuery);
