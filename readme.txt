=== WooTower ===
Contributors: wootower
Tags: woocommerce, telegram, orders, notifications, dashboard
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage your WooCommerce store from Telegram: get new order notifications and change order status without logging into wp-admin.

== Description ==

WooTower connects your WooCommerce store to a Telegram bot so you can keep an eye on
orders without opening your WordPress dashboard.

**Free / Lite features:**

* Instant Telegram notification when a new order comes in, with the order total, customer and items.
* Change an order's status directly from Telegram, using the inline buttons on the notification.
* A wp-admin dashboard with live order stats — notifications sent, status changes made via
  the bot, and orders still pending action — plus a preview of what's available in WooTower Pro.

Full product, order and customer management, multi-admin access, analytics and more
are part of WooTower Pro. Pro features are visible in both Telegram and the dashboard,
clearly marked, but are never active in the free version.

== Installation ==

1. Download the latest `wootower.zip` from the [Releases page](https://github.com/arefpik/wootower-lite/releases),
   then in wp-admin go to **Plugins > Add New > Upload Plugin** and upload it — or
   unzip it and upload the `wootower` folder to `/wp-content/plugins/` over (S)FTP.
2. Activate the plugin. WooCommerce must already be installed and active.
3. In wp-admin, go to **WooTower > Settings**.
4. Create a Telegram bot with [@BotFather](https://t.me/BotFather) and paste its token
   into the **Telegram Bot Token** field.
5. Message your bot once, then get your numeric Chat ID (for example via
   [@userinfobot](https://t.me/userinfobot)) and paste it into the **Admin Chat ID** field.
6. Save. WooTower automatically registers the Telegram webhook and bot commands — no
   manual webhook setup is needed.

== Frequently Asked Questions ==

= Does my server need to be reachable from the internet? =

Yes. Telegram delivers updates to your site via a webhook, so your WordPress site
needs a publicly reachable HTTPS URL for the webhook to work.

= Can I use more than one Telegram chat? =

Not in the Free version — one admin Chat ID is supported. Multi-admin access with
per-user permissions is a WooTower Pro feature.

= Why can't I click the Pro features? =

They're shown so you know what's available, but they're intentionally inactive in
the Free version. Clicking one shows an upgrade prompt instead of running the feature.

== Changelog ==

= 0.1.0 =
* Initial MVP: new-order Telegram notifications, order status change from Telegram,
  wp-admin settings page, and a dashboard skeleton with Pro features shown (locked).
