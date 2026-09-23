import { useState } from 'react';
import ProBadge from './ProBadge.jsx';
import ProUpsellModal from './ProUpsellModal.jsx';

/**
 * Renders a Pro-only nav item as visible but locked: clicking it never runs
 * the real feature, only opens the upsell modal. Free must never actually
 * enable Pro functionality, only show that it exists.
 */
export default function ProLockedFeature({ label }) {
  const [isModalOpen, setModalOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => setModalOpen(true)}
        className="flex w-full items-center justify-between rounded-md px-3 py-2 text-start text-sm text-gray-400 hover:bg-gray-100"
      >
        <span>{label}</span>
        <ProBadge />
      </button>
      <ProUpsellModal isOpen={isModalOpen} onClose={() => setModalOpen(false)} />
    </>
  );
}
