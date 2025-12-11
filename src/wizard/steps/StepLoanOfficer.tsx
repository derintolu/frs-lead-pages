import React, { useState, useEffect, useRef } from 'react';
import type { LoanOfficer } from '../../types';

interface StepLoanOfficerProps {
  selectedLO: LoanOfficer | null;
  onSelect: (lo: LoanOfficer) => void;
  onNext: () => void;
}

export const StepLoanOfficer: React.FC<StepLoanOfficerProps> = ({
  selectedLO,
  onSelect,
  onNext,
}) => {
  const [loanOfficers, setLoanOfficers] = useState<LoanOfficer[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [error, setError] = useState<string | null>(null);
  const searchTimeoutRef = useRef<number | null>(null);

  useEffect(() => {
    fetchLoanOfficers();
  }, []);

  const fetchLoanOfficers = async (search?: string) => {
    setIsLoading(true);
    setError(null);
    try {
      const url = new URL(`${window.frsLeadPages.restUrl}loan-officers`);
      if (search) {
        url.searchParams.set('search', search);
      }

      const response = await fetch(url.toString(), {
        headers: {
          'X-WP-Nonce': window.frsLeadPages.nonce,
        },
      });

      if (!response.ok) {
        throw new Error(`Failed to fetch loan officers: ${response.statusText}`);
      }

      const data = await response.json();
      setLoanOfficers(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Failed to fetch loan officers:', error);
      setError('Failed to load loan officers. Please try again.');
      setLoanOfficers([]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchQuery(value);

    // Clear existing timeout
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    // Debounce search
    searchTimeoutRef.current = window.setTimeout(() => {
      fetchLoanOfficers(value);
    }, 300);
  };

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, []);

  return (
    <div>
      <h2 className="text-2xl font-bold text-slate-900 mb-2">Partner Up</h2>
      <p className="text-[15px] text-slate-500 mb-6">
        Select a loan officer to co-brand this page
      </p>

      {/* Search */}
      <div className="mb-6">
        <input
          type="text"
          value={searchQuery}
          onChange={handleSearch}
          placeholder="Search by name..."
          className="w-full px-4 py-3 text-[15px] border-2 border-slate-200 rounded-xl outline-none focus:border-sky-500 transition-colors"
        />
      </div>

      {/* LO List */}
      <div className="space-y-3 max-h-[400px] overflow-y-auto pr-2">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <div className="text-center">
              <div className="inline-block w-8 h-8 border-4 border-slate-200 border-t-sky-500 rounded-full animate-spin mb-3"></div>
              <p className="text-sm text-slate-400">Loading loan officers...</p>
            </div>
          </div>
        ) : error ? (
          <div className="text-center py-8">
            <div className="text-4xl mb-3">⚠️</div>
            <p className="text-slate-600 mb-2">{error}</p>
            <button
              onClick={() => fetchLoanOfficers()}
              className="text-sm text-sky-500 hover:underline font-medium"
            >
              Try again
            </button>
          </div>
        ) : loanOfficers.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-4xl mb-3">🔍</div>
            <p className="text-slate-600">
              {searchQuery ? 'No loan officers match your search' : 'No loan officers found'}
            </p>
            {searchQuery && (
              <button
                onClick={() => {
                  setSearchQuery('');
                  fetchLoanOfficers();
                }}
                className="mt-3 text-sm text-sky-500 hover:underline font-medium"
              >
                Clear search
              </button>
            )}
          </div>
        ) : (
          loanOfficers.map((lo) => (
            <div
              key={lo.id}
              onClick={() => onSelect(lo)}
              className={`flex items-center gap-4 p-4 rounded-xl cursor-pointer transition-all hover:shadow-md ${
                selectedLO?.id === lo.id
                  ? 'border-2 border-sky-500 bg-sky-50 shadow-sm'
                  : 'border-2 border-slate-200 hover:border-sky-300'
              }`}
            >
              {lo.photo ? (
                <img
                  src={lo.photo}
                  alt={lo.name}
                  className="w-14 h-14 rounded-full object-cover bg-slate-100"
                />
              ) : (
                <div className="w-14 h-14 rounded-full bg-slate-200 flex items-center justify-center text-2xl">
                  👤
                </div>
              )}
              <div className="flex-1 min-w-0">
                <div className="text-[16px] font-semibold text-slate-900 truncate">
                  {lo.name}
                </div>
                {lo.title && (
                  <div className="text-sm text-slate-500 truncate">{lo.title}</div>
                )}
                {lo.nmls && (
                  <div className="text-sm text-slate-400">NMLS #{lo.nmls}</div>
                )}
              </div>
              {selectedLO?.id === lo.id && (
                <div className="w-6 h-6 rounded-full bg-sky-500 flex items-center justify-center flex-shrink-0">
                  <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                  </svg>
                </div>
              )}
            </div>
          ))
        )}
      </div>

      <p className="text-sm text-slate-400 mt-4">
        Your loan officer's info and branding will appear on the page.{' '}
        <a href="#" className="text-sky-500 hover:underline">
          View our team
        </a>
      </p>

      <button
        onClick={onNext}
        disabled={!selectedLO}
        className="w-full mt-6 py-4 text-[15px] font-semibold text-white bg-gradient-to-r from-sky-500 to-sky-600 rounded-xl transition-all shadow-[0_4px_14px_rgba(14,165,233,0.3)] hover:shadow-[0_6px_20px_rgba(14,165,233,0.4)] disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Continue →
      </button>
    </div>
  );
};
