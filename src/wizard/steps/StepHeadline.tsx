import React from 'react';
import type { WizardData } from '../../types';

// Headline options by page type
const headlineOptions: Record<string, string[]> = {
  open_house: [
    'Welcome!',
    "You're Invited",
    'Come On In',
    'Thanks for Visiting',
    'Open House',
  ],
  customer_spotlight: {
    first_time: [
      'First-Time Homebuyer?',
      'Ready to Stop Renting?',
      'Your First Home Awaits',
      'Make Your Move',
    ],
    veteran: [
      'Thank You for Your Service',
      'VA Loan Benefits',
      'Heroes Deserve Homes',
      'Veteran Home Buying',
    ],
    investor: [
      'Build Your Portfolio',
      'Smart Investing Starts Here',
      'Real Estate Opportunities',
      'Grow Your Wealth',
    ],
    refinance: [
      'Lower Your Payment',
      'Time to Refinance?',
      'Unlock Your Equity',
      'Better Rate, Better Life',
    ],
    move_up: [
      'Ready for More Space?',
      'Time to Upgrade',
      'Your Next Chapter',
      'Growing Family?',
    ],
    downsizer: [
      'Right-Size Your Life',
      'Simplify Your Living',
      'Your Next Chapter',
      'Smart Downsizing',
    ],
  } as unknown as string[],
  special_event: [
    "You're Invited!",
    'Join Us',
    'Save Your Seat',
    'Free Event',
    "Don't Miss Out",
  ],
  mortgage_calculator: [
    'What Can You Afford?',
    'Calculate Your Payment',
    'Estimate Your Mortgage',
    'See Your Numbers',
  ],
};

interface StepHeadlineProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepHeadline: React.FC<StepHeadlineProps> = ({
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

  const getHeadlines = (): string[] => {
    if (data.pageType === 'customer_spotlight' && data.spotlightType) {
      const spotlightHeadlines = headlineOptions.customer_spotlight as unknown as Record<string, string[]>;
      return spotlightHeadlines[data.spotlightType] || spotlightHeadlines.first_time;
    }
    return (headlineOptions[data.pageType || 'open_house'] as string[]) || [];
  };

  const getDefaultSubheadline = (): string => {
    switch (data.pageType) {
      case 'open_house':
        return 'Sign in to tour this beautiful home';
      case 'customer_spotlight':
        return "Let us show you what's possible";
      case 'special_event':
        return 'Reserve your free spot today';
      case 'mortgage_calculator':
        return 'See your estimated monthly payment in seconds';
      default:
        return '';
    }
  };

  const headlines = getHeadlines();
  const accentColor = getAccentColor();

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">
        Set Your Message
      </h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Choose or write your headline
      </p>

      {/* Headline */}
      <div className="mb-6">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Headline
        </label>
        <div className="flex flex-wrap gap-2 mb-3">
          {headlines.map((h, i) => (
            <button
              key={i}
              onClick={() => updateData('headline', h)}
              className="px-4 py-2 rounded-full text-sm transition-all"
              style={{
                border:
                  data.headline === h
                    ? `2px solid ${accentColor}`
                    : '2px solid #e2e8f0',
                background:
                  data.headline === h ? `${accentColor}15` : '#fff',
                color: data.headline === h ? accentColor : '#64748b',
              }}
            >
              {h}
            </button>
          ))}
        </div>
        <input
          type="text"
          value={data.headline}
          onChange={(e) => updateData('headline', e.target.value)}
          placeholder="Or write your own..."
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
          style={{ '--focus-color': accentColor } as React.CSSProperties}
          onFocus={(e) => (e.target.style.borderColor = accentColor)}
          onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
        />
      </div>

      {/* Subheadline */}
      <div className="mb-6">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Subheadline
        </label>
        <input
          type="text"
          value={data.subheadline}
          onChange={(e) => updateData('subheadline', e.target.value)}
          placeholder={getDefaultSubheadline()}
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
          onFocus={(e) => (e.target.style.borderColor = accentColor)}
          onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
        />
      </div>

      {/* Button Text */}
      <div className="mb-6">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Button Text
        </label>
        <input
          type="text"
          value={data.buttonText}
          onChange={(e) => updateData('buttonText', e.target.value)}
          placeholder="Sign In"
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none transition-colors"
          onFocus={(e) => (e.target.style.borderColor = accentColor)}
          onBlur={(e) => (e.target.style.borderColor = '#e2e8f0')}
        />
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
          disabled={!data.headline}
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
