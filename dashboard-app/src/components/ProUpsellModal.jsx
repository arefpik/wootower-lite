import { t, upgradeUrl } from '../utils/i18n.js';

export default function ProUpsellModal({ isOpen, onClose }) {
  if (!isOpen) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h2 className="text-lg font-semibold text-gray-900">{t('proFeatureTitle')}</h2>
        <p className="mt-2 text-sm text-gray-500">
          {t('proFeatureBody')}
        </p>
        <p className="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          🎁 {t('liteDiscount')}
        </p>
        <div className="mt-6 flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            {t('close')}
          </button>
          <a
            href={upgradeUrl()}
            target="_blank"
            rel="noreferrer"
            className="rounded-md bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600"
          >
            {t('upgrade')}
          </a>
        </div>
      </div>
    </div>
  );
}
