import React, { useState } from 'react';
import type { WizardData } from '../../types';

interface StepOpenHouseDetailsProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepOpenHouseDetails: React.FC<StepOpenHouseDetailsProps> = ({
  data,
  updateData,
  onNext,
  onBack,
}) => {
  const [isLooking, setIsLooking] = useState(false);

  const lookupProperty = async () => {
    if (!data.propertyAddress) return;

    setIsLooking(true);
    try {
      const response = await fetch(`${window.frsLeadPages.restUrl}property/lookup`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.frsLeadPages.nonce,
        },
        body: JSON.stringify({ address: data.propertyAddress }),
      });

      const result = await response.json();

      if (result.data) {
        updateData('propertyPrice', result.data.price || '');
        updateData('propertyBeds', result.data.beds || '');
        updateData('propertyBaths', result.data.baths || '');
        updateData('propertySqft', result.data.sqft || '');
        if (result.data.photos?.length) {
          updateData('propertyPhotos', result.data.photos);
        }
      }
    } catch (error) {
      console.error('Property lookup failed:', error);
    } finally {
      setIsLooking(false);
    }
  };

  const canProceed = data.propertyAddress && data.propertyPrice;

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">Property Details</h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Enter the address and we'll pull the details for you
      </p>

      {/* Address lookup */}
      <div className="mb-6">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Property Address
        </label>
        <div className="flex gap-2">
          <input
            type="text"
            value={data.propertyAddress}
            onChange={(e) => updateData('propertyAddress', e.target.value)}
            placeholder="123 Main St, City, CA 94000"
            className="flex-1 px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500 transition-colors"
          />
          <button
            onClick={lookupProperty}
            disabled={isLooking || !data.propertyAddress}
            className="px-5 py-3 text-sm font-semibold text-white bg-gradient-to-r from-sky-500 to-sky-600 rounded-xl disabled:opacity-50 whitespace-nowrap"
          >
            {isLooking ? 'Finding...' : 'Find'}
          </button>
        </div>
      </div>

      {/* Property details (shown after lookup or manual entry) */}
      {(data.propertyPrice || data.propertyBeds || data.propertyBaths || data.propertySqft) && (
        <div className="animate-fade-in">
          <div className="bg-sky-50/50 rounded-xl p-4 mb-4 border border-sky-100">
            <div className="text-xs font-semibold text-sky-700 uppercase tracking-wide mb-3">
              Property Details
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Price
              </label>
              <input
                type="text"
                value={data.propertyPrice}
                onChange={(e) => updateData('propertyPrice', e.target.value)}
                placeholder="$0,000,000"
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Sq Ft
              </label>
              <input
                type="text"
                value={data.propertySqft}
                onChange={(e) => updateData('propertySqft', e.target.value)}
                placeholder="0,000"
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Beds
              </label>
              <input
                type="text"
                value={data.propertyBeds}
                onChange={(e) => updateData('propertyBeds', e.target.value)}
                placeholder="0"
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Baths
              </label>
              <input
                type="text"
                value={data.propertyBaths}
                onChange={(e) => updateData('propertyBaths', e.target.value)}
                placeholder="0"
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Open House Date
              </label>
              <input
                type="date"
                value={data.openHouseDate}
                onChange={(e) => updateData('openHouseDate', e.target.value)}
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                Time
              </label>
              <input
                type="time"
                value={data.openHouseTime}
                onChange={(e) => updateData('openHouseTime', e.target.value)}
                className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500"
              />
            </div>
          </div>
        </div>
      )}

      {/* Manual entry prompt */}
      {!data.propertyPrice && !isLooking && (
        <div className="text-center py-4">
          <p className="text-sm text-slate-500 mb-3">Can't find your property?</p>
          <button
            onClick={() => {
              updateData('propertyPrice', '');
              updateData('propertyBeds', '');
              updateData('propertyBaths', '');
              updateData('propertySqft', '');
            }}
            className="px-6 py-2.5 text-sm font-semibold text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition-colors border border-sky-200"
          >
            Enter details manually
          </button>
        </div>
      )}

      <div className="flex gap-3 mt-6">
        <button
          onClick={onBack}
          className="px-6 py-4 text-[15px] font-semibold text-slate-500 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors"
        >
          ← Back
        </button>
        <button
          onClick={onNext}
          disabled={!canProceed}
          className="flex-1 py-4 text-[15px] font-semibold text-white bg-gradient-to-r from-sky-500 to-sky-600 rounded-xl transition-all shadow-[0_4px_14px_rgba(14,165,233,0.3)] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
