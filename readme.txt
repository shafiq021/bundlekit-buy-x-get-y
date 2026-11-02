=== BundleKit – Buy X Get Y ===
Contributors: shafiq021
Tags: woocommerce, bogo, buy one get one, buy x get y, free gift, discount
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 10.0
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create global Buy X Get Y offers: buy X units (or spend $Y) and get a reward — a specific product free, a percentage or fixed discount, or the cheapest / most-expensive item free. Auto-add the reward or let the customer pick their gift.

== Description ==

BundleKit – Buy X Get Y is an add-on for the BundleKit framework (built in — there is no separate BundleKit plugin to install).

Create offers under **BundleKit > Buy X Get Y > Offers**. One offer = one trigger + one reward:

* **Triggers** — buy X units of specific products, of products in specific categories or tags, or spend at least $Y.
* **Rewards** — a specific product free, a specific product at % off or $ off, or the cheapest / most-expensive item in the cart free.
* **Delivery** — auto-add the reward to the cart, or let the customer pick their gift (gift picker in the cart, as inline or popup, plus mini-cart).
* **Scheduling** — Active, Draft, or Scheduled with start and end dates; priority ordering when several offers match; per-cart and per-customer usage limits.

On the product page, shoppers see an offer banner ("Buy 2, get 1 free — add 2 to unlock"). In the cart, a progress bar shows how close they are to the next reward ("Add $12 more to unlock a free gift"). Reward lines show the regular price struck through with the reward price and the offer name.

Cart rules: rewards are keyed per offer so they never merge; removing the trigger products removes the reward automatically; the customer cannot raise a reward line above the earned amount (lowering it counts as declining); reward lines never trigger other offers; a ticked Fixed Bundle box always wins on its product; Buy in Packs pack lines count toward BOGO triggers. While a reward is active, other promo codes can be blocked (Settings).

Global controls live under **BundleKit > Buy X Get Y**: Offers, Settings (global on/off kill-switch, coupon stacking), Styling Controls (colors, sizes, radius, fonts — controls left, live preview right) and Custom CSS.

== Installation ==

1. Install and activate WooCommerce.
2. Upload `bundlekit-buy-x-get-y.zip` via Plugins > Add New > Upload Plugin, then Activate. Only this plugin appears in the Plugins list — the BundleKit framework is built in.
3. Go to BundleKit > Buy X Get Y > Offers > Add offer, set up your trigger and reward, Save.

== Frequently Asked Questions ==

= The offer banner does not show on the product page =
Check: (1) the offer status is Active (or Scheduled and inside its date window); (2) BundleKit > Buy X Get Y > Settings has "Enable Buy X Get Y" ticked; (3) the product matches the offer's trigger (specific products / categories / tags).

= The reward is not added to the cart =
Check the trigger is actually met (quantities, or spend for minimum-spend offers). For "Customer picks" offers, the reward appears only after the customer chooses their gift in the picker. Reward lines never count as triggers for other offers, and ticked Fixed Bundle lines never count as BOGO triggers.

= Can I run several offers at once? =
Yes. Each applies in priority order (lowest number first, oldest first on ties). Use per-cart and per-customer limits to control exposure.

= Do rewards work for guest customers? =
Yes — everything runs on the WooCommerce session, including gift choices.

== Changelog ==

= 1.0.4 =
* Fix: offers with the "Minimum spend" trigger showed "Buy 10, get 1 free" on the product banner. The headline now reads "Spend Rs 5,000, get 1 free" (and the matching variants for percent/fixed/cheapest/most-expensive rewards).
* Improvement: the Product categories / Product tags pickers now show how many terms were found, name where they come from (Products → Categories / Products → Tags), and show a clear message when none exist instead of an empty box.
* Fix (1.0.3): the offer banner and cart progress bar showed raw HTML (like <span class="woocommerce-Price-amount">…) instead of the formatted price. The price HTML from wc_price() was being escaped — it now renders correctly.
* Fix (1.0.2): the plugin zip now ships with the proper top-level folder (bundlekit-buy-x-get-y/), like the other BundleKit add-ons and as WordPress requires. The flat 1.0.0/1.0.1 zips installed into version-named folders, so replacing 1.0.0 with 1.0.1 could leave the install in a broken state where WooCommerce never saw the plugin's feature-compatibility declaration (HPOS / Cart & Checkout Blocks) — causing the "incompatible with currently enabled WooCommerce features" notice. Please deactivate and delete any old copy/copies first, then install this version fresh.
* Fix (1.0.1): the product search in the Offers tab never returned results. The search box sent the term via POST, but WooCommerce's product-search handler only reads the term from $_GET and silently dies otherwise — so no products ever appeared. The search now uses GET, like WooCommerce's own fields.

= 1.0.0 =
* First release: global BOGO offers with triggers (specific products, categories, tags, minimum spend) and rewards (free product, % off, $ off, cheapest free, most-expensive free).
* Auto-add or customer-picks gift delivery (inline picker, popup picker, mini-cart picker).
* Product-page offer banner, cart progress bar, reward lines with struck regular price and offer badge.
* Cart rules: keyed reward lines, auto-remove, quantity lock with decline handling, no chaining, Fixed Bundle conflict rule, Buy in Packs lines count toward triggers, per-line half-up rounding, optional promo-code blocking, per-cart and per-customer limits, scheduled offers.
* Admin: Offers repeater with product search, Settings, schema-driven Styling Controls with live preview and Reset all, Custom CSS tab.
