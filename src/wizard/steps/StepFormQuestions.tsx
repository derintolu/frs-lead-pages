import React from 'react';
import type { WizardData, FormQuestions, PageType } from '../../types';

interface QuestionOption {
  id: keyof FormQuestions;
  label: string;
  types: PageType[];
}

const questionOptions: QuestionOption[] = [
  { id: 'workingWithAgent', label: 'Are you working with an agent?', types: ['open_house'] },
  { id: 'preApproved', label: 'Are you pre-approved?', types: ['open_house', 'customer_spotlight', 'special_event', 'mortgage_calculator'] },
  { id: 'interestedInPreApproval', label: 'Interested in getting pre-approved?', types: ['open_house', 'customer_spotlight', 'special_event', 'mortgage_calculator'] },
  { id: 'timeframe', label: 'When are you looking to buy?', types: ['open_house', 'customer_spotlight'] },
  { id: 'currentSituation', label: 'Currently renting or own?', types: ['customer_spotlight', 'special_event'] },
  { id: 'firstTimeBuyer', label: 'Is this your first home?', types: ['open_house', 'customer_spotlight', 'special_event'] },
  { id: 'veteran', label: 'Are you a veteran?', types: ['customer_spotlight'] },
  { id: 'guests', label: 'Number of guests attending?', types: ['special_event'] },
  { id: 'priceRange', label: "What's your ideal price range?", types: ['customer_spotlight', 'mortgage_calculator'] },
  { id: 'comments', label: 'Comments or questions?', types: ['open_house', 'customer_spotlight', 'special_event', 'mortgage_calculator'] },
];

interface StepFormQuestionsProps {
  data: WizardData;
  toggleQuestion: (question: keyof FormQuestions) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepFormQuestions: React.FC<StepFormQuestionsProps> = ({
  data,
  toggleQuestion,
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

  const accentColor = getAccentColor();
  const relevantQuestions = questionOptions.filter((q) =>
    q.types.includes(data.pageType || 'open_house')
  );

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">Form Questions</h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Toggle which questions to ask visitors
      </p>

      {/* Required Fields */}
      <div className="mb-6">
        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
          Contact Info (always included)
        </div>
        <div className="space-y-2">
          {['Full Name', 'Email', 'Phone'].map((field) => (
            <div
              key={field}
              className="flex items-center gap-3 p-3 bg-slate-50 rounded-lg"
            >
              <div className="w-5 h-5 rounded bg-slate-300 flex items-center justify-center">
                <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                </svg>
              </div>
              <span className="text-sm text-slate-500">{field}</span>
              <span className="text-xs text-slate-400 ml-auto">Required</span>
            </div>
          ))}
        </div>
      </div>

      {/* Qualifying Questions */}
      <div>
        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
          Qualifying Questions
        </div>
        <div className="space-y-2">
          {relevantQuestions.map((q) => (
            <div
              key={q.id}
              onClick={() => toggleQuestion(q.id)}
              className="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-all"
              style={{
                background: data.formQuestions[q.id] ? `${accentColor}10` : '#fff',
                border: data.formQuestions[q.id]
                  ? `2px solid ${accentColor}`
                  : '2px solid #e2e8f0',
              }}
            >
              <div
                className="w-5 h-5 rounded flex items-center justify-center transition-colors"
                style={{
                  background: data.formQuestions[q.id] ? accentColor : '#fff',
                  border: data.formQuestions[q.id]
                    ? 'none'
                    : '2px solid #cbd5e1',
                }}
              >
                {data.formQuestions[q.id] && (
                  <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                  </svg>
                )}
              </div>
              <span className="text-sm text-slate-700">{q.label}</span>
            </div>
          ))}
        </div>
      </div>

      <p className="text-sm text-slate-400 mt-4">
        Leads who aren't pre-approved will be flagged for follow-up.
      </p>

      <div className="flex gap-3 mt-6">
        <button
          onClick={onBack}
          className="px-6 py-4 text-[15px] font-semibold text-slate-500 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors"
        >
          ← Back
        </button>
        <button
          onClick={onNext}
          className="flex-1 py-4 text-[15px] font-semibold text-white rounded-xl transition-all"
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
