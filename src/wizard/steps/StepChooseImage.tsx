import React from 'react';
import type { WizardData } from '../../types';

// Sample stock images for each page type
const stockImages: Record<string, string[]> = {
  open_house: [
    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800&q=80',
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
    'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=800&q=80',
  ],
  customer_spotlight: [
    'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=800&q=80',
    'https://images.unsplash.com/photo-1560520031-3a4dc4e9de0c?w=800&q=80',
    'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=800&q=80',
    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800&q=80',
  ],
  special_event: [
    'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80',
    'https://images.unsplash.com/photo-1511578314322-379afb476865?w=800&q=80',
    'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800&q=80',
    'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?w=800&q=80',
  ],
  mortgage_calculator: [
    'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=800&q=80',
    'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=800&q=80',
    'https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=800&q=80',
    'https://images.unsplash.com/photo-1582407947304-fd86f028f716?w=800&q=80',
  ],
};

interface StepChooseImageProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepChooseImage: React.FC<StepChooseImageProps> = ({
  data,
  updateData,
  onNext,
  onBack,
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

  // Use property photos if available, otherwise use stock images
  const images =
    data.propertyPhotos.length > 0
      ? data.propertyPhotos
      : stockImages[data.pageType || 'open_house'];

  const accentColor = getAccentColor();

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
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
        updateData('selectedPhoto', result.url);
      }
    } catch (error) {
      console.error('Upload failed:', error);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        Choose Your Image
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Select the hero image for your page
      </p>

      <div className="grid grid-cols-2 gap-3">
        {images.map((img, i) => (
          <div
            key={i}
            onClick={() => updateData('selectedPhoto', img)}
            className="relative aspect-[16/10] rounded-xl overflow-hidden cursor-pointer transition-transform hover:scale-[1.02]"
            style={{
              border:
                data.selectedPhoto === img
                  ? `3px solid ${accentColor}`
                  : '3px solid transparent',
            }}
          >
            <img
              src={img}
              alt={`Option ${i + 1}`}
              className="w-full h-full object-cover"
            />
            {data.selectedPhoto === img && (
              <div
                className="absolute top-2 right-2 w-7 h-7 rounded-full flex items-center justify-center"
                style={{ background: accentColor }}
              >
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                </svg>
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Upload option */}
      <div className="mt-4 text-center">
        <label className="cursor-pointer text-sm font-semibold hover:underline" style={{ color: accentColor }}>
          + Upload your own image
          <input
            type="file"
            accept="image/*"
            onChange={handleUpload}
            className="hidden"
          />
        </label>
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
          disabled={!data.selectedPhoto}
          className="flex-1 py-4 text-[15px] font-semibold text-white rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          style={{
            background: `linear-gradient(135deg, ${accentColor} 0%, ${accentColor}cc 100%)`,
            boxShadow: `0 4px 14px ${accentColor}4d`,
          }}
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
