import React, { useState, useEffect } from 'react';
import type { WizardData, PageType, FormQuestions } from '../types';
import { StepLoanOfficer } from './steps/StepLoanOfficer';
import { StepPageType } from './steps/StepPageType';
import { StepOpenHouseDetails } from './steps/StepOpenHouseDetails';
import { StepSpotlightType } from './steps/StepSpotlightType';
import { StepEventDetails } from './steps/StepEventDetails';
import { StepCalculatorDetails } from './steps/StepCalculatorDetails';
import { StepChooseImage } from './steps/StepChooseImage';
import { StepHeadline } from './steps/StepHeadline';
import { StepFormQuestions } from './steps/StepFormQuestions';
import { StepRealtorInfo } from './steps/StepRealtorInfo';

interface GenerationStationWizardProps {
  initialType?: string;
  initialLoId?: string;
  showHeader?: boolean;
}

/**
 * Get URL parameters from SureDash portal
 * Expected params:
 * - realtor_id, realtor_name, realtor_email, realtor_phone
 * - type (page type)
 * - lo_id (pre-selected loan officer)
 */
const getUrlParams = () => {
  const params = new URLSearchParams(window.location.search);
  return {
    realtorId: params.get('realtor_id') || '',
    realtorName: params.get('realtor_name') || '',
    realtorEmail: params.get('realtor_email') || '',
    realtorPhone: params.get('realtor_phone') || '',
    realtorLicense: params.get('realtor_license') || '',
    pageType: params.get('type') || '',
    loId: params.get('lo_id') || '',
  };
};

const defaultFormQuestions: FormQuestions = {
  workingWithAgent: true,
  preApproved: true,
  interestedInPreApproval: true,
  timeframe: true,
  currentSituation: false,
  firstTimeBuyer: false,
  veteran: false,
  guests: false,
  priceRange: false,
  comments: true,
};

const initialWizardData: WizardData = {
  loanOfficer: null,
  pageType: null,
  propertyAddress: '',
  propertyPrice: '',
  propertyBeds: '',
  propertyBaths: '',
  propertySqft: '',
  propertyPhotos: [],
  selectedPhoto: null,
  openHouseDate: '',
  openHouseTime: '',
  spotlightType: null,
  customerName: '',
  customerPhoto: null,
  closeDate: '',
  customerQuote: '',
  customerStory: '',
  customerWin: '',
  loanType: '',
  downPayment: '',
  specialProgram: '',
  eventType: null,
  eventName: '',
  eventDate: '',
  eventStartTime: '',
  eventEndTime: '',
  eventVenue: '',
  eventAddress: '',
  eventDescription: '',
  eventImage: null,
  isVirtual: false,
  virtualLink: '',
  headline: '',
  subheadline: '',
  buttonText: 'Sign In',
  consentText: 'By signing in, you agree to receive communications about this property and financing options.',
  formQuestions: defaultFormQuestions,
  realtorName: '',
  realtorPhoto: null,
  realtorPhone: '',
  realtorEmail: '',
  realtorLicense: '',
};

export const GenerationStationWizard: React.FC<GenerationStationWizardProps> = ({
  initialType = '',
  // initialLoId can be used for pre-selecting a loan officer
  initialLoId: _initialLoId = '',
  showHeader = true,
}) => {
  const [step, setStep] = useState(1);
  const [wizardData, setWizardData] = useState<WizardData>(initialWizardData);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Pre-fill data from URL params (SureDash) or WordPress current user
  useEffect(() => {
    const urlParams = getUrlParams();
    const currentUser = window.frsLeadPages?.currentUser;

    // Priority: URL params > WordPress current user
    setWizardData((prev) => ({
      ...prev,
      // Realtor info from URL params or WordPress
      realtorName: urlParams.realtorName || currentUser?.name || '',
      realtorPhone: urlParams.realtorPhone || currentUser?.phone || '',
      realtorEmail: urlParams.realtorEmail || currentUser?.email || '',
      realtorLicense: urlParams.realtorLicense || currentUser?.license || '',
      realtorPhoto: currentUser?.photo || null,
    }));

    // Pre-select page type from URL or prop
    const pageType = urlParams.pageType || initialType;
    if (pageType && ['open_house', 'customer_spotlight', 'special_event', 'mortgage_calculator'].includes(pageType)) {
      setWizardData((prev) => ({
        ...prev,
        pageType: pageType as PageType,
      }));
      // Skip to step 2 if page type is pre-selected
      if (pageType) {
        // We don't auto-skip yet - LO selection still needed
      }
    }

    // Pre-select loan officer if provided in URL
    if (urlParams.loId) {
      // Fetch LO data and pre-select
      fetchLoanOfficer(urlParams.loId);
    }
  }, [initialType]);

  // Fetch a specific loan officer by ID
  const fetchLoanOfficer = async (loId: string) => {
    try {
      const response = await fetch(
        `${window.frsLeadPages.restUrl}loan-officers/${loId}`,
        {
          headers: {
            'X-WP-Nonce': window.frsLeadPages.nonce,
          },
        }
      );
      if (response.ok) {
        const lo = await response.json();
        if (lo && lo.id) {
          setWizardData((prev) => ({
            ...prev,
            loanOfficer: {
              id: lo.id,
              name: lo.name,
              nmls: lo.nmls || '',
              title: lo.title || 'Loan Officer',
              phone: lo.phone || '',
              email: lo.email || '',
              photo: lo.photo_url || lo.photo || '',
            },
          }));
        }
      }
    } catch (error) {
      console.error('Failed to fetch loan officer:', error);
    }
  };

  const updateData = <K extends keyof WizardData>(field: K, value: WizardData[K]) => {
    setWizardData((prev) => ({ ...prev, [field]: value }));
  };

  const toggleQuestion = (question: keyof FormQuestions) => {
    setWizardData((prev) => ({
      ...prev,
      formQuestions: {
        ...prev.formQuestions,
        [question]: !prev.formQuestions[question],
      },
    }));
  };

  const nextStep = () => setStep((prev) => prev + 1);
  const prevStep = () => setStep((prev) => Math.max(1, prev - 1));
  // goToStep can be used for edit functionality from preview - uncomment when needed
  // const goToStep = (targetStep: number) => setStep(targetStep);

  // Calculate total steps based on page type
  const getTotalSteps = (): number => {
    // All page types: 7 steps
    // 1. Choose LO
    // 2. Page Type
    // 3. Details (varies by type)
    // 4. Choose Image
    // 5. Headline
    // 6. Form Questions
    // 7. Your Info / Preview
    return 7;
  };

  const totalSteps = getTotalSteps();

  // Get step title
  const getStepTitle = (): string => {
    switch (step) {
      case 1:
        return 'Choose Loan Officer';
      case 2:
        return 'Page Type';
      case 3:
        return wizardData.pageType === 'open_house'
          ? 'Property Details'
          : wizardData.pageType === 'customer_spotlight'
          ? 'Spotlight Type'
          : wizardData.pageType === 'special_event'
          ? 'Event Details'
          : 'Calculator Setup';
      case 4:
        return 'Choose Image';
      case 5:
        return 'Headline & Message';
      case 6:
        return 'Form Questions';
      case 7:
        return 'Your Info';
      default:
        return '';
    }
  };

  // Get accent color based on page type
  const getAccentColor = (): string => {
    switch (wizardData.pageType) {
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

  // Handle page creation
  const handleCreatePage = async () => {
    setIsSubmitting(true);
    const urlParams = getUrlParams();

    try {
      const response = await fetch(`${window.frsLeadPages.restUrl}pages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.frsLeadPages.nonce,
        },
        body: JSON.stringify({
          pageType: wizardData.pageType,
          loanOfficerId: wizardData.loanOfficer?.id,
          // Realtor info (from URL params or form)
          realtorId: urlParams.realtorId || undefined,
          realtorName: wizardData.realtorName,
          realtorEmail: wizardData.realtorEmail,
          realtorPhone: wizardData.realtorPhone,
          realtorLicense: wizardData.realtorLicense,
          // Property details
          propertyAddress: wizardData.propertyAddress,
          propertyPrice: wizardData.propertyPrice,
          propertyBeds: wizardData.propertyBeds,
          propertyBaths: wizardData.propertyBaths,
          propertySqft: wizardData.propertySqft,
          // Page content
          heroImageUrl: wizardData.selectedPhoto,
          headline: wizardData.headline,
          subheadline: wizardData.subheadline,
          buttonText: wizardData.buttonText,
          consentText: wizardData.consentText,
          formQuestions: wizardData.formQuestions,
          // Type-specific
          spotlightType: wizardData.spotlightType,
          eventType: wizardData.eventType,
          eventName: wizardData.eventName,
          eventDate: wizardData.eventDate,
          eventTimeStart: wizardData.eventStartTime,
          eventTimeEnd: wizardData.eventEndTime,
          eventVenue: wizardData.eventVenue,
          eventAddress: wizardData.eventAddress,
        }),
      });

      const result = await response.json();

      if (result.success) {
        // Redirect to page or show success
        window.location.href = result.url;
      } else {
        console.error('Failed to create page:', result.error);
      }
    } catch (error) {
      console.error('Error creating page:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderStep = () => {
    switch (step) {
      case 1:
        return (
          <StepLoanOfficer
            selectedLO={wizardData.loanOfficer}
            onSelect={(lo) => updateData('loanOfficer', lo)}
            onNext={nextStep}
          />
        );

      case 2:
        return (
          <StepPageType
            selectedType={wizardData.pageType}
            onSelect={(type) => updateData('pageType', type)}
            onNext={nextStep}
            onBack={prevStep}
          />
        );

      case 3:
        if (wizardData.pageType === 'open_house') {
          return (
            <StepOpenHouseDetails
              data={wizardData}
              updateData={updateData}
              onNext={nextStep}
              onBack={prevStep}
            />
          );
        } else if (wizardData.pageType === 'customer_spotlight') {
          return (
            <StepSpotlightType
              selectedType={wizardData.spotlightType}
              onSelect={(type) => updateData('spotlightType', type)}
              onNext={nextStep}
              onBack={prevStep}
            />
          );
        } else if (wizardData.pageType === 'special_event') {
          return (
            <StepEventDetails
              data={wizardData}
              updateData={updateData}
              onNext={nextStep}
              onBack={prevStep}
            />
          );
        } else {
          return (
            <StepCalculatorDetails
              data={wizardData}
              updateData={updateData}
              onNext={nextStep}
              onBack={prevStep}
            />
          );
        }

      case 4:
        return (
          <StepChooseImage
            data={wizardData}
            updateData={updateData}
            onNext={nextStep}
            onBack={prevStep}
          />
        );

      case 5:
        return (
          <StepHeadline
            data={wizardData}
            updateData={updateData}
            onNext={nextStep}
            onBack={prevStep}
          />
        );

      case 6:
        return (
          <StepFormQuestions
            data={wizardData}
            toggleQuestion={toggleQuestion}
            onNext={nextStep}
            onBack={prevStep}
          />
        );

      case 7:
        return (
          <StepRealtorInfo
            data={wizardData}
            updateData={updateData}
            onBack={prevStep}
            onCreate={handleCreatePage}
            isSubmitting={isSubmitting}
          />
        );

      default:
        return null;
    }
  };

  const accentColor = getAccentColor();

  const pageTypeLabels: Record<PageType, string> = {
    open_house: 'Open House',
    customer_spotlight: 'Customer Spotlight',
    special_event: 'Special Event',
    mortgage_calculator: 'Mortgage Calculator',
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 font-sans">
      {/* Header */}
      {showHeader && (
        <div className="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-10 shadow-sm">
          <div className="max-w-[600px] mx-auto flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="text-2xl">🚀</span>
              <span className="text-lg font-bold text-slate-900">Generation Station</span>
            </div>
            {wizardData.pageType && (
              <span
                className="px-3 py-1.5 rounded-full text-sm font-semibold shadow-sm"
                style={{
                  background: `${accentColor}15`,
                  color: accentColor,
                  border: `1px solid ${accentColor}30`,
                }}
              >
                {pageTypeLabels[wizardData.pageType]}
              </span>
            )}
          </div>
        </div>
      )}

      {/* Progress Bar */}
      <div className="bg-white border-b border-slate-200 px-6 py-5 sticky top-16 z-10 shadow-sm">
        <div className="max-w-[600px] mx-auto">
          <div className="flex items-center justify-between mb-3">
            <span className="text-sm font-semibold text-slate-900">{getStepTitle()}</span>
            <span className="text-sm text-slate-500 font-medium">
              Step {step} of {totalSteps}
            </span>
          </div>
          <div className="flex gap-1.5">
            {Array.from({ length: totalSteps }, (_, i) => (
              <div
                key={i}
                className="flex-1 h-2 rounded-full transition-all duration-300"
                style={{
                  background: i < step ? accentColor : '#e2e8f0',
                }}
              />
            ))}
          </div>
        </div>
      </div>

      {/* Step Content */}
      <div className="max-w-[600px] mx-auto px-6 py-8">
        <div className="bg-white rounded-2xl p-8 shadow-lg border border-slate-200">
          {renderStep()}
        </div>
      </div>

      {/* LO Preview (sticky) */}
      {wizardData.loanOfficer && step > 1 && (
        <div className="fixed bottom-6 right-6 bg-white rounded-xl p-3 shadow-xl border border-slate-200 flex items-center gap-3 max-w-xs hover:shadow-2xl transition-shadow">
          {wizardData.loanOfficer.photo ? (
            <img
              src={wizardData.loanOfficer.photo}
              alt={wizardData.loanOfficer.name}
              className="w-12 h-12 rounded-full object-cover bg-slate-100"
            />
          ) : (
            <div className="w-12 h-12 rounded-full bg-sky-100 flex items-center justify-center text-xl">
              👤
            </div>
          )}
          <div className="min-w-0">
            <div className="text-xs text-slate-400 uppercase tracking-wide mb-0.5">
              Partnered with
            </div>
            <div className="text-sm font-semibold text-slate-900 truncate">
              {wizardData.loanOfficer.name}
            </div>
            {wizardData.loanOfficer.nmls && (
              <div className="text-xs text-slate-500">
                NMLS #{wizardData.loanOfficer.nmls}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
