import React from 'react';
import type { WizardData } from '../../types';

interface StepPreviewPublishProps {
  data: WizardData;
  pageUrl: string;
  onEdit: (step: number) => void;
}

export const StepPreviewPublish: React.FC<StepPreviewPublishProps> = ({
  data,
  pageUrl,
  // onEdit available for edit functionality
  onEdit: _onEdit,
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

  const copyLink = () => {
    navigator.clipboard.writeText(pageUrl);
    // Could add toast notification here
  };

  const downloadQR = () => {
    // Generate QR code URL
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(pageUrl)}`;
    window.open(qrUrl, '_blank');
  };

  return (
    <div className="text-center">
      <div
        className="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
        style={{
          background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
          boxShadow: '0 8px 24px rgba(16, 185, 129, 0.3)',
        }}
      >
        <svg width="40" height="40" fill="white" viewBox="0 0 24 24">
          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
        </svg>
      </div>

      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        Your Page is Live!
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Share your page and start capturing leads
      </p>

      {/* Page URL */}
      <div className="mb-6 p-4 bg-slate-50 rounded-xl">
        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Page URL
        </div>
        <div className="flex items-center gap-2">
          <input
            type="text"
            value={pageUrl}
            readOnly
            className="flex-1 px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg text-slate-600"
          />
          <button
            onClick={copyLink}
            className="px-4 py-2 text-sm font-semibold text-white rounded-lg"
            style={{ background: accentColor }}
          >
            Copy
          </button>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="grid grid-cols-2 gap-3 mb-6">
        <a
          href={pageUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="py-3 px-4 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center gap-2"
        >
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
          </svg>
          View Page
        </a>
        <button
          onClick={downloadQR}
          className="py-3 px-4 text-sm font-semibold text-slate-700 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center gap-2"
        >
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
            <path d="M3 11h2v2H3v-2zm8-6h2v4h-2V5zm-2 6h4v4H9v-4zm6 0h2v2h-2v-2zm2-6h2v2h-2V5zm2 4h2v4h-2V9zM5 5h4v4H5V5zm0 8h4v4H5v-4zM15 5h4v4h-4V5z" />
          </svg>
          QR Code
        </button>
      </div>

      {/* Summary */}
      <div className="text-left p-4 bg-slate-50 rounded-xl">
        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
          What's Next
        </div>
        <ul className="space-y-2 text-sm text-slate-600">
          <li className="flex items-start gap-2">
            <span className="text-emerald-500">✓</span>
            Share the link on social media
          </li>
          <li className="flex items-start gap-2">
            <span className="text-emerald-500">✓</span>
            Print QR code for flyers and open house signs
          </li>
          <li className="flex items-start gap-2">
            <span className="text-emerald-500">✓</span>
            Monitor leads in your Generation Station dashboard
          </li>
        </ul>
      </div>

      <button
        onClick={() => window.location.reload()}
        className="w-full mt-6 py-4 text-[15px] font-semibold text-white rounded-xl transition-all"
        style={{
          background: accentColor,
          boxShadow: `0 4px 14px ${accentColor}4d`,
        }}
      >
        Create Another Page
      </button>
    </div>
  );
};
