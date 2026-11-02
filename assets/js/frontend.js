/* BundleKit – Buy X Get Y — frontend (1.0.0).
 * Gift picker submit: POST the choice to the wc-ajax endpoint, then
 * reload so the totals run adds the reward line. Popup open/close.
 */
(function ($) {
	'use strict';

	function data() {
		return window.bundlekitBogo || {};
	}

	$(document).on('click', '[data-bxgy-pick]', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $picker = $btn.closest('[data-offer-id]');
		var offerId = $picker.data('offer-id');
		var variationId = $picker.find('.bxgy-picker-variation').val() || 0;
		var $note = $picker.find('.bxgy-picker-note');
		$btn.prop('disabled', true);
		$note.text('');
		$.post(data().ajaxUrl, {
			offer_id: offerId,
			variation_id: variationId,
			nonce: data().nonce
		}, function (res) {
			if (res && res.success) {
				window.location.reload();
			} else {
				$note.text('Something went wrong — please try again.');
				$btn.prop('disabled', false);
			}
		}).fail(function () {
			$note.text('Something went wrong — please try again.');
			$btn.prop('disabled', false);
		});
	});

	$(document).on('click', '[data-bxgy-open-picker]', function (e) {
		e.preventDefault();
		var offerId = $(this).data('bxgy-open-picker');
		$('[data-bxgy-overlay="' + offerId + '"]').prop('hidden', false);
	});

	$(document).on('click', '[data-bxgy-close-picker]', function (e) {
		e.preventDefault();
		$(this).closest('[data-bxgy-overlay]').prop('hidden', true);
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			$('[data-bxgy-overlay]').prop('hidden', true);
		}
	});
})(jQuery);
