// Strings are translated in PHP (DashboardPage::strings()) and passed in via
// wootowerDashboardConfig.i18n; these English copies are only the fallback
// for when the bundle runs without that config (e.g. `npm run dev`).
const FALLBACK = {
  title: 'WooTower Dashboard',
  connecting: 'Connecting to WooTower...',
  connected: 'Connected to the backend.',
  connectionError: 'Could not reach the WooTower backend.',
  overview: 'Overview',
  notifiedOrders: 'New order notifications sent',
  statusChanges: 'Status changes made via bot',
  awaitingAction: 'Awaiting action',
  totalRevenue: 'Total revenue',
  completionRate: 'Order completion rate',
  bestSeller: 'Best-selling product',
  moreWithPro: 'More with Pro',
  products: 'Products',
  orderManagement: 'Full Order Management',
  customers: 'Customers',
  analytics: 'Analytics',
  proFeatureTitle: 'This is a Pro feature',
  proFeatureBody:
    'Upgrade to WooTower Pro to unlock this feature, along with full product, order and customer management.',
  close: 'Close',
  upgrade: 'Upgrade to Pro',
};

const DEFAULT_UPGRADE_URL = 'https://wootower.pro/buy';

function getConfig() {
  return window.wootowerDashboardConfig || {};
}

export function t(key) {
  return (getConfig().i18n || {})[key] || FALLBACK[key] || key;
}

export function isRtl() {
  return Boolean(getConfig().isRtl);
}

export function upgradeUrl() {
  return getConfig().upgradeUrl || DEFAULT_UPGRADE_URL;
}
