import React from 'react';
import type { WizardData } from '../../types';

interface StepRealtorInfoProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onBack: () => void;
  onCreate: () => void;
  isSubmitting: boolean;
}

export const StepRealtorInfo: React.FC<StepRealtorInfoProps> = ({
  data,
  updateData,
  onBack,
  onCreate,
  isSubmitting,
}) => {
  const getAccentColor = () => {
    switch (data.pageType) {
      case 'customer_spotlight':
        return '#10b981';
      case 'special_event':
        return '#f59e0b';
      case 'mortgage_calculator':
        return '#8b5cf6';
      default:
        return '#0ea5e9';
    }
  };

  const accentColor = getAccentColor();

  const handlePhotoUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch(`${window.frsLeadPages.restUrl}upload`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.frsLeadPages.nonce,
        },
        body: formData,
      });

      const result = await response.json();
      if (result.success) {
        updateData('realtorPhoto', result.url);
      }
    } catch (error) {
      console.error('Upload failed:', error);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">Your Information</h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Add your details to the page
      </p>

      {/* Photo Upload */}
      <div className="flex items-center gap-4 mb-6">
        <div
          className="w-20 h-20 rounded-full flex items-center justify-center cursor-pointer overflow-hidden"
          style={{
            background: '#f1f5f9',
            border: '2px dashed #cbd5e1',
          }}
        >
          {data.realtorPhoto ? (
            <img
              src={data.realtorPhoto}
              alt="Your photo"
              className="w-full h-full object-cover"
            />
          ) : (
            <span className="text-2xl">📷</span>
          )}
        </div>
        <label
          className="text-sm font-semibold cursor-pointer hover:underline"
          style={{ color: accentColor }}
        >
          Upload Photo
          <input
            type="file"
            accept="image/*"
            onChange={handlePhotoUpload}
            className="hidden"
          />
        </label>
      </div>

      {/* Form Fields */}
      <div className="space-y-4">
        <div>
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Your Name
          </label>
          <input
            type="text"
            value={data.realtorName}
            onChange={(e) => updateData('realtorName', e.target.value)}
            placeholder="Sarah Mitchell"
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
            onFocus={(e) => (e.target.style.borderColor = accentColor)}
            onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
              Phone
            </label>
            <input
              type="tel"
              value={data.realtorPhone}
              onChange={(e) => updateData('realtorPhone', e.target.value)}
              placeholder="(415) 555-0123"
              className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
              onFocus={(e) => (e.target.style.borderColor = accentColor)}
              onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
              License #
            </label>
            <input
              type="text"
              value={data.realtorLicense}
              onChange={(e) => updateData('realtorLicense', e.target.value)}
              placeholder="DRE# 01234567"
              className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
              onFocus={(e) => (e.target.style.borderColor = accentColor)}
              onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
            />
          </div>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Email
          </label>
          <input
            type="email"
            value={data.realtorEmail}
            onChange={(e) => updateData('realtorEmail', e.target.value)}
            placeholder="sarah@c21masters.com"
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
            onFocus={(e) => (e.target.style.borderColor = accentColor)}
            onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
          />
        </div>
      </div>

      {/* LO Preview */}
      {data.loanOfficer && (
        <div className="mt-6 p-4 bg-slate-50 rounded-xl">
          <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
            Loan Officer (from Step 1)
          </div>
          <div className="flex items-center gap-3">
            <img
              src={data.loanOfficer.photo}
              alt={data.loanOfficer.name}
              className="w-12 h-12 rounded-full object-cover"
            />
            <div>
              <div className="text-sm font-semibold text-slate-900">
                {data.loanOfficer.name}
              </div>
              <div className="text-xs text-slate-500">
                NMLS #{data.loanOfficer.nmls}
              </div>
            </div>
          </div>
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
          onClick={onCreate}
          disabled={isSubmitting}
          className="flex-1 py-4 text-[15px] font-semibold text-white rounded-xl transition-all disabled:opacity-50"
          style={{
            background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            boxShadow: '0 4px 14px rgba(16, 185, 129, 0.3)',
          }}
        >
          {isSubmitting ? 'Creating...' : 'Create Page ✓'}
        </button>
      </div>
    </div>
  );
};
