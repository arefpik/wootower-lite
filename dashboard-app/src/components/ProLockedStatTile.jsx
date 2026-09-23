import { useState } from 'react';
import ProBadge from './ProBadge.jsx';
import ProUpsellModal from './ProUpsellModal.jsx';

/**
 * Same tile shape as StatTile, but the value is masked and clicking it opens
 * the upsell modal instead of showing a real (Pro-only) number.
 */
export default function ProLockedStatTile({ label }) {
  const [isModalOpen, setModalOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => setModalOpen(true)}
        className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-start transition hover:border-amber-300 hover:bg-amber-50"
      >
        <div className="flex items-start justify-between gap-2">
          <p className="text-sm text-[#52514e]">{label}</p>
          <ProBadge />
        </div>
        <p className="mt-1 text-3xl font-semibold tracking-widest text-gray-300">•••</p>
      </button>
      <ProUpsellModal isOpen={isModalOpen} onClose={() => setModalOpen(false)} />
    </>
  );
}
