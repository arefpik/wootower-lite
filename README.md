# 🤖 WooTower

**Run your WooCommerce store from Telegram — new order alerts, one-tap status changes, and a live stats dashboard, without a subscription.**

[![License: GPLv2](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-required-96588a.svg)](https://woocommerce.com)

---

## ✨ What it does

Every time someone buys something in your store, WooTower sends a Telegram message straight to you (or your team's group chat) — customer, phone, items, total, and any custom fields your products collect at checkout. Tap a button under the message to mark the order **Processing**, **Completed**, or whatever statuses you've configured — no need to open wp-admin at all.

On top of that, a dashboard right inside wp-admin shows you how the bot's been doing: notifications sent, status changes made from Telegram, and orders still waiting on you.

| | |
|---|---|
| 📦 **New order alerts** | Sent to Telegram the instant an order is placed — fully customizable message template |
| ✅ **One-tap status changes** | Inline buttons under the notification update the order without opening the browser |
| 👥 **Works in groups too** | Point it at a Telegram group/supergroup chat ID and your whole team sees every order |
| 🧩 **Custom fields, automatically** | Any per-product custom field captured at checkout (account IDs, gift messages, etc.) shows up in the message with zero extra setup |
| 📊 **Live dashboard stats** | Notifications sent, bot status-changes, and pending orders — right in wp-admin |
| 🔒 **Pro-ready** | Product/order/customer management, real sales analytics, and multi-admin access are visible but clearly marked — [WooTower Pro](#-wootower-pro) unlocks them |

---

## 🚀 Installation

**Option A — download and upload (recommended for store owners)**

1. Grab the latest `wootower.zip` from the [Releases page](https://github.com/arefpik/wootower-lite/releases).
2. In wp-admin: **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**.
3. Activate it. WooCommerce must already be installed and active.

**Option B — clone the repo (for developers)**

```bash
cd wp-content/plugins/
git clone https://github.com/arefpik/wootower-lite.git wootower
```

The dashboard's built assets are committed to the repo, so no build step is required just to run the plugin. You only need to rebuild `dashboard-app/` if you're modifying the dashboard's React source — see [Development](#-development) below.

---

## ⚙️ Setup

1. Open a chat with **[@BotFather](https://t.me/BotFather)** on Telegram, send `/newbot`, and follow the prompts. You'll get back a **bot token**.
2. Message your new bot at least once (or add it to a group), then grab your numeric **Chat ID** from **[@userinfobot](https://t.me/userinfobot)**.
3. In wp-admin, go to **WooTower → Settings**, paste in the bot token and Chat ID, and click **Save Settings**.

That's it — WooTower registers the Telegram webhook and bot commands automatically. No manual webhook configuration, no server-side setup.

> ⚠️ Your site needs a publicly reachable HTTPS URL for Telegram to deliver updates to it. This is true of virtually any real hosting, but won't work against `localhost`.

### Customizing the notification

The **New Order Message** field on the settings page accepts plain text plus placeholders — click one to insert it at the cursor:

| Placeholder | Resolves to |
|---|---|
| `{order_number}` | Order number |
| `{customer}` | Customer name |
| `{phone}` | Customer phone number |
| `{email}` | Customer email address |
| `{total}` | Order total |
| `{status}` | Order status |
| `{items}` | Every ordered item, with price and any custom fields it collected at checkout |

**Status Buttons** are fully configurable too — add as many label/status pairs as you want, in any order, and they'll appear as inline buttons under every new-order message.

---

## 🏗️ How it works

```
WooCommerce order placed
        │
        ▼
NotificationDispatcher ──renders template──▶ MessagingChannelInterface ──▶ TelegramChannel ──▶ Telegram API
        │
        ▼
StatsService records the event ──▶ wp-admin dashboard (React)

Telegram button tap
        │
        ▼
Webhook (secret-token verified) ──▶ updates order status ──▶ edits the message's keyboard in place
```

Messaging is built behind a `MessagingChannelInterface` adapter — Telegram is the only implementation today, but the core order/notification logic doesn't know that.

---

## 🛠️ Development

```bash
cd dashboard-app
npm install
npm run dev      # local dev server
npm run build    # rebuilds dashboard-app/build/, which is what wp-admin actually loads
```

- **PHP**: no Composer dependency — a small PSR-4-style autoloader in `wootower.php` maps the `WooTower\` namespace to `includes/`.
- **Dashboard**: React 18 + Vite (library build) + Tailwind CSS, mounted into a classic wp-admin page.
- **Tagging a release** (`git tag vX.Y.Z && git push --tags`) triggers a [GitHub Action](.github/workflows/release.yml) that rebuilds the dashboard and publishes a ready-to-upload `wootower.zip` on the Releases page.

---

## 💎 WooTower Pro

Product, order, and customer management from Telegram, real sales analytics (revenue, completion rate, best-sellers), and multi-admin access are part of **WooTower Pro** — a one-time purchase, licensed per domain. They're visible in both the dashboard and the bot so you know what's available, but never functional in the free version; tapping one just shows an upgrade prompt.

---

## 📄 License

GPLv2 or later — see [license text](https://www.gnu.org/licenses/gpl-2.0.html).
