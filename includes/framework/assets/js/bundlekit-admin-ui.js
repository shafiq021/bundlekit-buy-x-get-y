/**
 * BundleKit admin UI — shared behaviour for every add-on's global pages.
 *
 * Framework 1.0.4. Dims layout-specific cards ([data-bk-group]) inside a
 * [data-bk-ui] page root whenever the picked layout ([data-bk-layout])
 * does not match. The layout <select> carries [data-bk-layout-select];
 * changing it updates the root and re-dims — no inline styles, the
 * .is-dimmed class (and the .bk-lock-note inside the card) does the work.
 * Pure vanilla JS: no jQuery dependency.
 */
(function () {
	'use strict';

	function syncRoot(root) {
		var layout = root.getAttribute('data-bk-layout') || 'style1';
		var cards = root.querySelectorAll('[data-bk-group]');
		for (var i = 0; i < cards.length; i++) {
			var dim = cards[i].getAttribute('data-bk-group') !== layout;
			cards[i].classList.toggle('is-dimmed', dim);
			if (dim) {
				cards[i].setAttribute('aria-disabled', 'true');
			} else {
				cards[i].removeAttribute('aria-disabled');
			}
		}
	}

	function init() {
		var roots = document.querySelectorAll('[data-bk-ui]');
		for (var r = 0; r < roots.length; r++) {
			(function (root) {
				syncRoot(root);
				var selects = root.querySelectorAll('select[data-bk-layout-select]');
				for (var s = 0; s < selects.length; s++) {
					selects[s].addEventListener('change', function () {
						root.setAttribute('data-bk-layout', this.value);
						syncRoot(root);
					});
				}
			})(roots[r]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
