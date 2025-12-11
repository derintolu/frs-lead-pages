import React from 'react';
import type { SpotlightType } from '../../types';

interface SpotlightTypeOption {
  id: SpotlightType;
  label: string;
  icon: string;
}

const spotlightTypes: SpotlightTypeOption[] = [
  { id: 'first_time', label: 'First-Time Homebuyer', icon: '🏡' },
  { id: 'veteran', label: 'Veteran / VA Buyer', icon: '🎖️' },
  { id: 'investor', label: 'Real Estate Investor', icon: '📈' },
  { id: 'refinance', label: 'Refinance', icon: '💰' },
  { id: 'move_up', label: 'Move-Up Buyer', icon: '⬆️' },
  { id: 'downsizer', label: 'Downsizer', icon: '🏘️' },
];

interface StepSpotlightTypeProps {
  selectedType: SpotlightType | null;
  onSelect: (type: SpotlightType) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepSpotlightType: React.FC<StepSpotlightTypeProps> = ({
  selectedType,
  onSelect,
  onNext,
  onBack,
}) => {
  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        Who are you targeting?
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Choose the buyer type for this spotlight
      </p>

      <div className="grid grid-cols-2 gap-3">
        {spotlightTypes.map((type) => (
          <div
            key={type.id}
            onClick={() => onSelect(type.id)}
            className={`p-5 rounded-xl cursor-pointer text-center transition-all hover:shadow-md ${
              selectedType === type.id
                ? 'border-2 border-emerald-500 bg-emerald-50 shadow-sm'
                : 'border-2 border-slate-200 hover:border-emerald-300'
            }`}
          >
            <div className="text-3xl mb-2">{type.icon}</div>
            <div className="text-sm font-semibold text-slate-900">
              {type.label}
            </div>
            {selectedType === type.id && (
              <div className="mt-2 flex justify-center">
                <div className="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center">
                  <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                  </svg>
                </div>
              </div>
            )}
          </div>
        ))}
      </div>

      <div className="flex gap-3 mt-6">
        <button
          onClick={onBack}
          className="px-6 py-4 text-[15px] font-semibold text-slate-500 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors"
        >
          ← Back
        </button>
        <button
          onClick={onNext}
          disabled={!selectedType}
          className="flex-1 py-4 text-[15px] font-semibold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-xl transition-all shadow-[0_4px_14px_rgba(16,185,129,0.3)] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
