// Loan Officer
export interface LoanOfficer {
  id: number;
  name: string;
  title: string;
  nmls: string;
  phone: string;
  email: string;
  photo: string;
}

// Realtor
export interface Realtor {
  id: number;
  name: string;
  title: string;
  license: string;
  phone: string;
  email: string;
  photo: string;
}

// Page Types
export type PageType = 'open_house' | 'customer_spotlight' | 'special_event' | 'mortgage_calculator';

// Spotlight Types
export type SpotlightType =
  | 'first_time'
  | 'veteran'
  | 'investor'
  | 'refinance'
  | 'move_up'
  | 'downsizer';

// Event Types
export type EventType =
  | 'homebuyer_seminar'
  | 'client_appreciation'
  | 'networking'
  | 'community'
  | 'open_house_event'
  | 'other';

// Form Question Configuration
export interface FormQuestions {
  workingWithAgent: boolean;
  preApproved: boolean;
  interestedInPreApproval: boolean;
  timeframe: boolean;
  currentSituation: boolean;
  firstTimeBuyer: boolean;
  veteran: boolean;
  guests: boolean;
  priceRange: boolean;
  comments: boolean;
}

// Wizard Data State
export interface WizardData {
  // Step 0: Loan Officer
  loanOfficer: LoanOfficer | null;

  // Step 1-2: Page Type & Details
  pageType: PageType | null;

  // Open House specific
  propertyAddress: string;
  propertyPrice: string;
  propertyBeds: string;
  propertyBaths: string;
  propertySqft: string;
  propertyPhotos: string[];
  selectedPhoto: string | null;
  openHouseDate: string;
  openHouseTime: string;

  // Customer Spotlight specific
  spotlightType: SpotlightType | null;
  customerName: string;
  customerPhoto: string | null;
  closeDate: string;
  customerQuote: string;
  customerStory: string;
  customerWin: string;
  loanType: string;
  downPayment: string;
  specialProgram: string;

  // Special Event specific
  eventType: EventType | null;
  eventName: string;
  eventDate: string;
  eventStartTime: string;
  eventEndTime: string;
  eventVenue: string;
  eventAddress: string;
  eventDescription: string;
  eventImage: string | null;
  isVirtual: boolean;
  virtualLink: string;

  // Headline & Subheadline
  headline: string;
  subheadline: string;
  buttonText: string;
  consentText: string;

  // Form Questions
  formQuestions: FormQuestions;

  // Realtor Info (current user)
  realtorName: string;
  realtorPhoto: string | null;
  realtorPhone: string;
  realtorEmail: string;
  realtorLicense: string;
}

// Lead Form Data (visitor submission)
export interface LeadFormData {
  fullName: string;
  email: string;
  phone: string;
  workingWithAgent: boolean | null;
  preApproved: boolean | null;
  interestedInPreApproval: boolean | null;
  timeframe: string;
  currentSituation: string;
  firstTimeBuyer: boolean | null;
  veteran: boolean | null;
  guests: string;
  priceRange: string;
  comments: string;
}

// Page Data (from WordPress)
export interface PageData {
  id: number;
  title: string;
  url: string;
  pageType: PageType;
  loanOfficerId: number;
  realtorId: number;
  // Open House
  propertyAddress: string;
  propertyPrice: string;
  propertyBeds: string;
  propertyBaths: string;
  propertySqft: string;
  // Hero
  heroImageUrl: string;
  // Content
  headline: string;
  subheadline: string;
  buttonText: string;
  consentText: string;
  // Event
  eventName: string;
  eventDate: string;
  eventTimeStart: string;
  eventTimeEnd: string;
  eventVenue: string;
  eventAddress: string;
  // Spotlight
  spotlightType: SpotlightType | null;
  // Form
  formQuestions: FormQuestions;
  // Team
  loanOfficer: LoanOfficer | null;
  realtor: Realtor | null;
}

// WordPress Localized Data
export interface FrsLeadPagesConfig {
  ajaxUrl: string;
  restUrl: string;
  nonce: string;
  pluginUrl: string;
  isLoggedIn: boolean;
  currentUser: Realtor | null;
  pageData: PageData | null;
}

// Declare global
declare global {
  interface Window {
    frsLeadPages: FrsLeadPagesConfig;
  }
}
