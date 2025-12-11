import React from 'react';
import type { WizardData, EventType } from '../../types';

interface EventTypeOption {
  id: EventType;
  label: string;
  icon: string;
}

const eventTypes: EventTypeOption[] = [
  { id: 'homebuyer_seminar', label: 'Homebuyer Seminar', icon: '🎓' },
  { id: 'client_appreciation', label: 'Client Appreciation', icon: '🎉' },
  { id: 'networking', label: 'Networking Event', icon: '🤝' },
  { id: 'community', label: 'Community Event', icon: '🏘️' },
  { id: 'open_house_event', label: 'Open House Event', icon: '🏠' },
  { id: 'other', label: 'Other', icon: '📌' },
];

interface StepEventDetailsProps {
  data: WizardData;
  updateData: <K extends keyof WizardData>(field: K, value: WizardData[K]) => void;
  onNext: () => void;
  onBack: () => void;
}

export const StepEventDetails: React.FC<StepEventDetailsProps> = ({
  data,
  updateData,
  onNext,
  onBack,
}) => {
  const canProceed = data.eventType && data.eventName;

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">Event Details</h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Tell us about your event
      </p>

      {/* Event Type */}
      <div className="mb-6">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Event Type
        </label>
        <div className="flex flex-wrap gap-2">
          {eventTypes.map((type) => (
            <button
              key={type.id}
              onClick={() => updateData('eventType', type.id)}
              className={`px-4 py-2.5 rounded-lg text-sm font-medium transition-all ${
                data.eventType === type.id
                  ? 'border-2 border-amber-500 bg-amber-50 text-amber-700'
                  : 'border-2 border-slate-200 text-slate-500 hover:border-slate-300'
              }`}
            >
              {type.icon} {type.label}
            </button>
          ))}
        </div>
      </div>

      {/* Event Name */}
      <div className="mb-4">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Event Name
        </label>
        <input
          type="text"
          value={data.eventName}
          onChange={(e) => updateData('eventName', e.target.value)}
          placeholder="First-Time Homebuyer Workshop"
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500 transition-colors"
        />
      </div>

      {/* Date & Time */}
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div>
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Date
          </label>
          <input
            type="date"
            value={data.eventDate}
            onChange={(e) => updateData('eventDate', e.target.value)}
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Start Time
          </label>
          <input
            type="time"
            value={data.eventStartTime}
            onChange={(e) => updateData('eventStartTime', e.target.value)}
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            End Time
          </label>
          <input
            type="time"
            value={data.eventEndTime}
            onChange={(e) => updateData('eventEndTime', e.target.value)}
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
          />
        </div>
      </div>

      {/* Venue */}
      <div className="mb-4">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Venue Name
        </label>
        <input
          type="text"
          value={data.eventVenue}
          onChange={(e) => updateData('eventVenue', e.target.value)}
          placeholder="Century 21 Masters Office"
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
        />
      </div>

      <div className="mb-4">
        <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
          Venue Address
        </label>
        <input
          type="text"
          value={data.eventAddress}
          onChange={(e) => updateData('eventAddress', e.target.value)}
          placeholder="123 Main St, San Mateo, CA"
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
        />
      </div>

      {/* Virtual Toggle */}
      <div className="flex items-center gap-3 mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
        <button
          onClick={() => updateData('isVirtual', !data.isVirtual)}
          className={`relative w-12 h-6 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 ${
            data.isVirtual ? 'bg-amber-500' : 'bg-slate-300'
          }`}
          aria-label="Toggle virtual option"
        >
          <div
            className={`w-5 h-5 bg-white rounded-full shadow-sm transform transition-transform ${
              data.isVirtual ? 'translate-x-6' : 'translate-x-0.5'
            }`}
          />
        </button>
        <span className="text-sm font-medium text-slate-700">Include virtual option</span>
      </div>

      {data.isVirtual && (
        <div className="mb-4 animate-fade-in">
          <label className="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
            Virtual Link
          </label>
          <input
            type="url"
            value={data.virtualLink}
            onChange={(e) => updateData('virtualLink', e.target.value)}
            placeholder="https://zoom.us/..."
            className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-amber-500"
          />
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
          className="flex-1 py-4 text-[15px] font-semibold text-white bg-gradient-to-r from-amber-500 to-amber-600 rounded-xl transition-all shadow-[0_4px_14px_rgba(245,158,11,0.3)] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Continue →
        </button>
      </div>
    </div>
  );
};
