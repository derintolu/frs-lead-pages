import React from 'react';
import type { PageType } from '../../types';

interface PageTypeOption {
  id: PageType;
  label: string;
  icon: string;
  description: string;
  color: string;
}

const pageTypes: PageTypeOption[] = [
  {
    id: 'open_house',
    label: 'Open House',
    icon: '🏠',
    description: 'Property tour sign-in page',
    color: '#0ea5e9',
  },
  {
    id: 'customer_spotlight',
    label: 'Customer Spotlight',
    icon: '🎯',
    description: 'Target a specific buyer type',
    color: '#10b981',
  },
  {
    id: 'special_event',
    label: 'Special Event',
    icon: '📅',
    description: 'Workshop or event RSVP',
    color: '#f59e0b',
  },
  {
    id: 'mortgage_calculator',
    label: 'Mortgage Calculator',
    icon: '🧮',
    description: 'Interactive calculator with lead capture',
    color: '#8b5cf6',
  },
];

interface StepPageTypeProps {
  selectedType: PageType | null;
  onSelect: (type: PageType) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepPageType: React.FC<StepPageTypeProps> = ({
  selectedType,
  onSelect,
  onNext,
  onBack,
}) => {
  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        What are you creating?
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Choose your landing page type
      </p>

      <div className="space-y-3">
        {pageTypes.map((type) => (
          <div
            key={type.id}
            onClick={() => onSelect(type.id)}
            className="flex items-center gap-4 p-5 rounded-xl cursor-pointer transition-all hover:shadow-md"
            style={{
              border:
                selectedType === type.id
                  ? `2px solid ${type.color}`
                  : '2px solid #e2e8f0',
              background:
                selectedType === type.id ? `${type.color}10` : '#fff',
            }}
          >
            <div className="text-3xl flex-shrink-0">{type.icon}</div>
            <div className="flex-1 min-w-0">
              <div className="text-[16px] font-semibold text-slate-900">
                {type.label}
              </div>
              <div className="text-sm text-slate-500">{type.description}</div>
            </div>
            {selectedType === type.id && (
              <div
                className="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                style={{ background: type.color }}
              >
                <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                </svg>
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
          className="flex-1 py-4 text-[15px] font-semibold text-white rounded-xl transition-all shadow-[0_4px_14px_rgba(14,165,233,0.3)] disabled:opacity-50 disabled:cursor-not-allowed"
          style={{
            background: selectedType
              ? `linear-gradient(135deg, ${pageTypes.find((t) => t.id === selectedType)?.color} 0%, ${pageTypes.find((t) => t.id === selectedType)?.color}cc 100%)`
              : 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
          }}
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
