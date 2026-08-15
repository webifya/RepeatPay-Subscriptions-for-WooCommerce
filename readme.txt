=== Subscribely – Recurring Billing for WooCommerce ===
Contributors: webifya
Tags: woocommerce, subscriptions, recurring payments, subscription products, renewal orders
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce subscriptions with renewals, payment recovery, trials, sign-up fees, and gateway-neutral invoices.

== Description ==

Subscribely – Recurring Billing for WooCommerce turns ordinary products into daily, weekly, monthly, or yearly subscriptions while keeping orders and checkout inside WooCommerce.

After the initial order is paid, Subscribely schedules each renewal, creates a normal WooCommerce renewal order, and gives the customer a clear subscription record in My Account. Renewal invoices work with any payment gateway enabled for that order. The plugin also handles failed-payment reminders, recovery, trials, sign-up fees, renewal limits, and renewal price snapshots.

Developed by Mahfuzar Rahman. Company website: https://www.webninjallc.com/

The free plugin uses customer-paid renewal invoices for broad gateway compatibility. Automatic off-session charging requires explicit saved-method support and is provided by the separately distributed Subscribely PRO add-on for compatible official Stripe, PayPal Payments, and Square gateways.

Features:

* Subscription product type with daily, weekly, monthly, or yearly billing.
* Action Scheduler integration, with WP-Cron fallback.
* Renewal orders using standard WooCommerce checkout.
* Customer Subscriptions page with renewal payment and cancellation actions.
* Subscription administration under WooCommerce.
* HPOS-compatible order access.
* Extension hook after renewal creation: `wfs_renewal_order_created`.
* Configurable failed-payment reminders and retry schedule.
* Past-due and on-hold subscription states.
* Automatic recovery when a late renewal is paid.
* Renewal price and currency snapshots for billing consistency.
* Free trials with configurable duration.
* One-time sign-up fees.
* Fixed renewal limits with automatic expiration.
* Optional per-product text before or after the displayed subscription price.

= Upgrade to Subscribely PRO =

The separately distributed Subscribely PRO add-on provides automatic supported-gateway renewals, retry handling with invoice fallback, customer pause and resume, early renewal, advanced administration, and protected subscriber downloads with limits and expiry. No PRO implementation is included or locked inside this free plugin.

Subscribely PRO is currently $69.99 per year. Learn more at https://webninjallc.com/plugins/subscribely/

== Installation ==

1. Install and activate WooCommerce.
2. Upload this plugin folder to `/wp-content/plugins/`.
3. Activate Subscribely – Recurring Billing for WooCommerce.
4. Create a product and choose "Subscription" as its product type.
5. Set its price and billing interval, then publish it.

== Frequently Asked Questions ==

= Can customers use any WooCommerce payment gateway? =

Yes. Customer-paid renewal invoices can use any gateway enabled for the renewal order. Automatic renewals require a compatible saved payment method and supported PRO gateway integration.

= Does the Free edition automatically charge customers? =

The Free edition creates scheduled renewal orders and customer-paid invoices. Subscribely PRO can automatically charge compatible saved methods through supported official Stripe, PayPal Payments, and Square gateways.

= What happens when a renewal payment fails? =

Subscribely records the failed renewal, schedules configured reminders, and updates the subscription lifecycle. A late paid renewal can recover the subscription automatically.

= Can I offer free trials or charge a sign-up fee? =

Yes. Subscription products can include a free trial, one-time sign-up fee, and optional renewal limit.

= Can customers manage subscriptions from My Account? =

Yes. Customers can view subscriptions and pay renewal orders. PRO adds merchant-controlled pause, resume, early renewal, and premium detail screens.

= Can I sell downloadable subscription products? =

Yes, with Subscribely PRO. PRO protects WooCommerce downloads, applies limits and expiry, resets eligible access after renewal, and revokes access when entitlement ends.

= Does Subscribely support HPOS? =

Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage and uses WooCommerce order APIs.

= What information is shared with Web Ninja LLC? =

The free plugin shares a compatibility profile only after an administrator explicitly selects the opt-in checkbox under WooCommerce > Subscription settings. It never sends orders, customer records, payment details, or subscription records. See External services below for the complete disclosure. Disabling permission sends an erasure request and stops future sharing.

== External services ==

Subscribely can optionally connect to a Web Ninja LLC service at `https://www.webninjallc.com/wp-json/wnlm/v1/site-profile`. This connection is disabled by default and is not required for any subscription feature.

When an administrator explicitly opts in, the plugin sends the website URL and name, administrator email address, WordPress and PHP versions, active theme name, locale, environment type, multisite status, plugin version, and a random installation identifier. The service uses this compatibility profile to improve updates, compatibility, and support. It is sent when consent is enabled, after this plugin is updated, and weekly while consent remains enabled.

When consent is disabled, the plugin sends the website URL and installation identifier with consent set to false so the stored profile can be erased. It then removes the local installation identifier and stops scheduled sharing. Orders, customer records, payment details, and subscription records are never sent.

Service and privacy information: https://www.webninjallc.com/plugins/subscribely/

== Development ==

Public development repository: https://github.com/webifya/Subscribely-Recurring-Billing-for-WooCommerce

The distributed PHP is the human-readable source. The plugin has no compiled, minified, or externally loaded executable code and requires no build process.

== Changelog ==

= 0.5.8 =
* Added a contextual, dismissible review request after 14 days of actual subscription use.
* Added original WordPress.org plugin icon and banner artwork to the development repository.
* Kept direct, untracked upgrade links limited to relevant plugin administration locations and clarified the separate add-on model.

= 0.5.7 =
* Prepared the plugin for WordPress.org review with transparent external-service code and documentation.
* Added suggested privacy-policy text for local subscription records and optional compatibility-profile sharing.
* Added explicit product-save authorization checks and aligned the translation domain with the expected directory slug.
* Clarified that Subscribely PRO is a separately distributed add-on and that no premium implementation is locked in the free plugin.

= 0.5.6 =
* Added optional per-product prefix and suffix text for subscription price displays.

= 0.5.5 =
* Simplified plugin-row metadata by retaining Documentation and removing duplicate FAQ and Plugin details links.

= 0.5.4 =
* Routed all Free-edition PRO promotions through the Subscribely sales page.

= 0.5.3 =
* Simplified the optional site-profile sharing message for clearer update and compatibility guidance.

= 0.5.2 =
* Prevent duplicate legacy and renamed plugin copies from redefining bootstrap constants or registering duplicate subscription hooks.

= 0.5.1 =
* Added an explicit opt-in setting for consent-based site profile sharing with Web Ninja LLC License Manager.
* Added weekly profile refreshes and automatic profile removal when consent is withdrawn.
* Site profiles never include orders, customers, payments, or subscription records.
* Added Settings and Go PRO links on the Plugins screen.
* Updated the annual PRO offer to $69.99 and expanded the upgrade presentation.
* Improved the description, FAQ, documentation, and details metadata.

= 0.5.0 =
* Rebranded the public plugin as Subscribely – Recurring Billing for WooCommerce.
* Updated plugin metadata, administration copy, documentation, and translation domain.
* Preserved all existing `wfs_` subscription records, hooks, schedules, and product types for seamless upgrades.

Earlier release history is available in the public development repository.
