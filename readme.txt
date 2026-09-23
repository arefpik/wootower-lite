=== WooTower ===
Contributors: wootower
Tags: woocommerce, telegram, orders, notifications, dashboard
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage your WooCommerce store from Telegram: get new order notifications and change order status without logging into wp-admin.

== Description ==

WooTower connects your WooCommerce store to a Telegram bot so you can keep an eye on
orders without opening your WordPress dashboard.

**Free / Lite features:**

* Instant Telegram notification when a new order comes in, with the order total, customer and items.
* Change an order's status directly from Telegram, using the inline buttons on the notification.
* Works on hosts where Telegram is blocked (e.g. inside Iran): an optional proxy used for Telegram
  API calls only, plus a one-click connection test that explains exactly what's wrong.
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

= My site is hosted in Iran. Will it work? =

Yes. Hosts inside Iran usually can't reach api.telegram.org directly, so the settings page has a
**Telegram Proxy** option (http, https, socks5 or socks5h) that is used for Telegram API calls
only — every other request WordPress makes is unaffected. Use the **Test Connection** button to
check, before saving, whether your server reaches Telegram directly or through the proxy,
whether the token is valid, whether Telegram can deliver updates to your site, and whether the
bot can message your chat.

= Can I use more than one Telegram chat? =

Not in the Free version — one admin Chat ID is supported. Multi-admin access with
per-user permissions is a WooTower Pro feature.

= Why can't I click the Pro features? =

They're shown so you know what's available, but they're intentionally inactive in
the Free version. Clicking one shows an upgrade prompt instead of running the feature.

== Changelog ==

= 0.2.0 =
* New: optional Telegram proxy (http, https, socks5, socks5h) for hosts that can't reach
  api.telegram.org directly — applied to Telegram API calls only.
* New: Test Connection button on the settings page — checks reachability (direct or via the
  proxy), the bot token, webhook delivery and a test message to the admin chat, with a
  plain-language fix for each failure. Tests unsaved form values.
* Tested with WordPress 7.1 and WooCommerce 11.

= 0.1.0 =
* Initial MVP: new-order Telegram notifications, order status change from Telegram,
  wp-admin settings page, and a dashboard skeleton with Pro features shown (locked).
