import React from 'react';
import type { WizardData } from '../../types';

interface StepCalculatorDetailsProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepCalculatorDetails: React.FC<StepCalculatorDetailsProps> = ({
  // data and updateData available for future calculator config options
  data: _data,
  updateData: _updateData,
  onNext,
  onBack,
}) => {
  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        Calculator Setup
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Configure your mortgage calculator page
      </p>

      <div className="space-y-4">
        {/* Default values for calculator */}
        <div className="p-4 bg-violet-50 rounded-xl border border-violet-200">
          <div className="flex items-center gap-3 mb-2">
            <span className="text-2xl">🧮</span>
            <span className="font-semibold text-slate-900">
              Interactive Calculator
            </span>
          </div>
          <p className="text-sm text-slate-600">
            Your page will include an interactive mortgage calculator that lets
            visitors estimate their monthly payment based on home price, down
            payment, and interest rate.
          </p>
        </div>

        <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
          <div className="flex items-center gap-3 mb-2">
            <span className="text-2xl">📋</span>
            <span className="font-semibold text-slate-900">Lead Capture Form</span>
          </div>
          <p className="text-sm text-slate-600">
            After using the calculator, visitors can submit their info to get a
            personalized rate quote from you.
          </p>
        </div>

        <div className="p-4 bg-slate-50 rounded-xl border border-slate-200">
          <div className="flex items-center gap-3 mb-2">
            <span className="text-2xl">👥</span>
            <span className="font-semibold text-slate-900">Co-Branded</span>
          </div>
          <p className="text-sm text-slate-600">
            Your loan officer's info will appear alongside yours, making it easy
            for prospects to reach out.
          </p>
        </div>
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
          className="flex-1 py-4 text-[15px] font-semibold text-white bg-gradient-to-r from-violet-500 to-violet-600 rounded-xl transition-all shadow-[0_4px_14px_rgba(139,92,246,0.3)]"
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
