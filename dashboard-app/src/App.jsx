import { useEffect, useState } from 'react';
import { apiGet } from './api/client.js';
import StatTile from './components/StatTile.jsx';
import StatTileSkeleton from './components/StatTileSkeleton.jsx';
import ProLockedStatTile from './components/ProLockedStatTile.jsx';
import ProLockedFeature from './components/ProLockedFeature.jsx';
import { formatCount } from './utils/formatNumber.js';

// Validated categorical palette (blue / orange / aqua) — see the dataviz
// skill's reference palette; identity is carried by the accent bar under
// each value, never by coloring the text itself.
const ACCENT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a'];

const PRO_STATS = ['Total revenue', 'Order completion rate', 'Best-selling product'];
const PRO_FEATURES = ['Products', 'Full Order Management', 'Customers', 'Analytics'];

export default function App() {
  const [connection, setConnection] = useState('loading');
  const [stats, setStats] = useState(null);

  useEffect(() => {
    apiGet('ping')
      .then(() => setConnection('connected'))
      .catch(() => setConnection('error'));

    apiGet('stats')
      .then(setStats)
      .catch(() => setConnection('error'));
  }, []);

  const freeStats = stats
    ? [
        { label: 'New order notifications sent', value: formatCount(stats.notified_orders) },
        { label: 'Status changes made via bot', value: formatCount(stats.status_changed_orders) },
        { label: 'Awaiting action', value: formatCount(stats.pending_orders) },
      ]
    : [];

  return (
    <div className="min-h-screen bg-[#f9f9f7] p-8">
      <header className="mb-8">
        <h1 className="text-2xl font-semibold text-[#0b0b0b]">WooTower Dashboard</h1>
        <p className="mt-1 text-sm text-[#52514e]">
          {connection === 'loading' && 'Connecting to WooTower...'}
          {connection === 'connected' && 'Connected to the backend.'}
          {connection === 'error' && 'Could not reach the WooTower backend.'}
        </p>
      </header>

      <section className="mb-10">
        <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-[#898781]">
          Overview
        </h2>
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
          {stats
            ? freeStats.map((stat, index) => (
                <StatTile
                  key={stat.label}
                  label={stat.label}
                  value={stat.value}
                  accent={ACCENT_COLORS[index % ACCENT_COLORS.length]}
                />
              ))
            : [0, 1, 2].map((index) => <StatTileSkeleton key={index} />)}
          {PRO_STATS.map((label) => (
            <ProLockedStatTile key={label} label={label} />
          ))}
        </div>
      </section>

      <section>
        <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-[#898781]">
          More with Pro
        </h2>
        <nav className="max-w-xs space-y-1">
          {PRO_FEATURES.map((feature) => (
            <ProLockedFeature key={feature} label={feature} />
          ))}
        </nav>
      </section>
    </div>
  );
}
