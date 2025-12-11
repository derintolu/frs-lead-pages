import React, { useState, useEffect } from 'react';
import type { PageData, LeadFormData } from '../types';

interface LeadPageTemplateProps {
  pageId: string;
}

export const LeadPageTemplate: React.FC<LeadPageTemplateProps> = ({ pageId }) => {
  const [step, setStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [pageData, setPageData] = useState<PageData | null>(null);
  const [focusedField, setFocusedField] = useState<string | null>(null);
  const [formData, setFormData] = useState<LeadFormData>({
    fullName: '',
    email: '',
    phone: '',
    workingWithAgent: null,
    preApproved: null,
    interestedInPreApproval: null,
    timeframe: '',
    currentSituation: '',
    firstTimeBuyer: null,
    veteran: null,
    guests: '0',
    priceRange: '',
    comments: '',
  });

  const totalSteps = 4;

  // Load page data from WordPress
  useEffect(() => {
    const wpData = window.frsLeadPages?.pageData;
    if (wpData) {
      setPageData(wpData);
    } else if (pageId) {
      fetchPageData(pageId);
    }
  }, [pageId]);

  const fetchPageData = async (id: string) => {
    try {
      const response = await fetch(
        `${window.frsLeadPages.restUrl}pages/${id}`,
        {
          headers: {
            'X-WP-Nonce': window.frsLeadPages.nonce,
          },
        }
      );
      const data = await response.json();
      setPageData(data);
    } catch (error) {
      console.error('Failed to fetch page data:', error);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleRadio = (field: keyof LeadFormData, value: boolean | string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const nextStep = () => {
    if (step < totalSteps) {
      setStep(step + 1);
    } else if (step === 3) {
      handleSubmit();
    }
  };

  const prevStep = () => step > 1 && setStep(step - 1);

  const handleSubmit = async () => {
    if (!pageData) return;

    setIsSubmitting(true);
    setSubmitError(null);

    try {
      const response = await fetch(
        `${window.frsLeadPages.restUrl}pages/${pageData.id}/submit`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.frsLeadPages.nonce,
          },
          body: JSON.stringify(formData),
        }
      );

      if (response.ok) {
        setStep(4); // Success step
      } else {
        const errorData = await response.json().catch(() => ({ message: 'Submission failed' }));
        setSubmitError(errorData.message || 'Something went wrong. Please try again.');
      }
    } catch (error) {
      console.error('Submission error:', error);
      setSubmitError('Unable to submit. Please check your connection and try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Validation
  const canProceedStep1 = formData.fullName && formData.email && formData.phone;
  const canProceedStep2 = formData.workingWithAgent !== null && formData.preApproved !== null;

  if (!pageData) {
    return (
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '100vh',
        background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
        fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
      }}>
        <div style={{
          display: 'flex',
          flexDirection: 'column' as const,
          alignItems: 'center',
          gap: '16px'
        }}>
          <svg
            width="48"
            height="48"
            fill="none"
            stroke="#0ea5e9"
            strokeWidth="2"
            viewBox="0 0 24 24"
            style={{
              animation: 'spin 1s linear infinite'
            }}
          >
            <circle cx="12" cy="12" r="10" opacity="0.25" />
            <path d="M4 12a8 8 0 018-8" strokeLinecap="round" />
          </svg>
          <div style={{
            color: '#94a3b8',
            fontSize: '15px',
            fontWeight: '500'
          }}>Loading page...</div>
        </div>
        <style>{`
          @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
        `}</style>
      </div>
    );
  }

  // Styles
  const labelStyle: React.CSSProperties = {
    display: 'block',
    fontSize: '13px',
    fontWeight: '600',
    color: '#334155',
    marginBottom: '8px',
    textTransform: 'uppercase',
    letterSpacing: '0.03em'
  };

  const getInputStyle = (fieldName: string, hasError: boolean = false): React.CSSProperties => ({
    width: '100%',
    padding: '14px 16px',
    fontSize: '15px',
    border: hasError
      ? '2px solid #ef4444'
      : focusedField === fieldName
        ? '2px solid #0ea5e9'
        : '2px solid #e2e8f0',
    borderRadius: '10px',
    outline: 'none',
    transition: 'all 0.2s ease',
    boxSizing: 'border-box',
    background: '#fff',
    boxShadow: focusedField === fieldName
      ? '0 0 0 3px rgba(14, 165, 233, 0.1)'
      : 'none',
  });

  const buttonStyle: React.CSSProperties = {
    width: '100%',
    padding: '16px',
    fontSize: '15px',
    fontWeight: '600',
    color: 'white',
    background: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
    border: 'none',
    borderRadius: '10px',
    cursor: 'pointer',
    marginTop: '24px',
    transition: 'all 0.2s ease',
    boxShadow: '0 4px 14px rgba(14,165,233,0.3)',
    position: 'relative' as const,
  };

  const backButtonStyle: React.CSSProperties = {
    padding: '16px 24px',
    fontSize: '15px',
    fontWeight: '600',
    color: '#64748b',
    background: '#f1f5f9',
    border: 'none',
    borderRadius: '10px',
    cursor: 'pointer',
    marginTop: '24px',
    transition: 'all 0.2s ease'
  };

  const radioStyle: React.CSSProperties = {
    flex: 1,
    padding: '14px 20px',
    fontSize: '14px',
    fontWeight: '500',
    color: '#64748b',
    background: '#f8fafc',
    border: '2px solid #e2e8f0',
    borderRadius: '8px',
    cursor: 'pointer',
    transition: 'all 0.2s ease',
    textAlign: 'center' as const,
  };

  const radioSelectedStyle: React.CSSProperties = {
    ...radioStyle,
    color: '#0ea5e9',
    background: '#f0f9ff',
    borderColor: '#0ea5e9',
    boxShadow: '0 0 0 3px rgba(14, 165, 233, 0.1)',
  };

  // Get hero image URL
  const heroImage = pageData.heroImageUrl || 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1600&q=80';

  // Content for each step - LEFT SIDE (70%)
  const renderLeftContent = () => {
    switch (step) {
      case 1:
        return (
          <>
            {/* Hero with image */}
            <div style={{
              position: 'absolute',
              inset: 0,
              backgroundImage: `url(${heroImage})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center'
            }} />
            <div style={{
              position: 'absolute',
              inset: 0,
              background: 'linear-gradient(to top, rgba(15,23,42,0.9) 0%, rgba(15,23,42,0.3) 50%, rgba(15,23,42,0.1) 100%)'
            }} />
            <div style={{
              position: 'relative',
              height: '100%',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'flex-end',
              padding: '48px'
            }}>
              {/* Price badge */}
              {pageData.pageType === 'open_house' && pageData.propertyPrice && (
                <div style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '12px',
                  background: 'rgba(255,255,255,0.1)',
                  backdropFilter: 'blur(20px)',
                  border: '1px solid rgba(255,255,255,0.2)',
                  borderRadius: '16px',
                  padding: '20px 28px',
                  marginBottom: '16px',
                  width: 'fit-content'
                }}>
                  <div style={{
                    width: '44px',
                    height: '44px',
                    borderRadius: '12px',
                    background: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    boxShadow: '0 4px 14px rgba(14,165,233,0.4)'
                  }}>
                    <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                      <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                    </svg>
                  </div>
                  <div>
                    <div style={{
                      fontSize: '36px',
                      fontWeight: '700',
                      color: 'white',
                      letterSpacing: '-0.03em',
                      lineHeight: 1
                    }}>
                      {pageData.propertyPrice}
                    </div>
                    <div style={{
                      fontSize: '15px',
                      color: 'rgba(255,255,255,0.7)',
                      marginTop: '4px'
                    }}>
                      {pageData.propertyAddress}
                    </div>
                  </div>
                </div>
              )}

              {/* Event info badge */}
              {pageData.pageType === 'special_event' && (
                <div style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '12px',
                  background: 'rgba(255,255,255,0.1)',
                  backdropFilter: 'blur(20px)',
                  border: '1px solid rgba(255,255,255,0.2)',
                  borderRadius: '16px',
                  padding: '20px 28px',
                  marginBottom: '16px',
                  width: 'fit-content'
                }}>
                  <div style={{
                    width: '44px',
                    height: '44px',
                    borderRadius: '12px',
                    background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    boxShadow: '0 4px 14px rgba(245,158,11,0.4)'
                  }}>
                    <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                      <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
                    </svg>
                  </div>
                  <div>
                    <div style={{
                      fontSize: '28px',
                      fontWeight: '700',
                      color: 'white',
                      letterSpacing: '-0.03em',
                      lineHeight: 1
                    }}>
                      {pageData.eventName || "You're Invited!"}
                    </div>
                    <div style={{
                      fontSize: '15px',
                      color: 'rgba(255,255,255,0.7)',
                      marginTop: '4px'
                    }}>
                      {pageData.eventDate} {pageData.eventTimeStart && `at ${pageData.eventTimeStart}`}
                    </div>
                  </div>
                </div>
              )}

              {/* Customer Spotlight badge */}
              {pageData.pageType === 'customer_spotlight' && (
                <div style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '12px',
                  background: 'rgba(255,255,255,0.1)',
                  backdropFilter: 'blur(20px)',
                  border: '1px solid rgba(255,255,255,0.2)',
                  borderRadius: '16px',
                  padding: '20px 28px',
                  marginBottom: '16px',
                  width: 'fit-content'
                }}>
                  <div style={{
                    width: '44px',
                    height: '44px',
                    borderRadius: '12px',
                    background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    boxShadow: '0 4px 14px rgba(16,185,129,0.4)'
                  }}>
                    <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                      <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                  </div>
                  <div>
                    <div style={{
                      fontSize: '28px',
                      fontWeight: '700',
                      color: 'white',
                      letterSpacing: '-0.03em',
                      lineHeight: 1
                    }}>
                      {pageData.headline || 'Success Story'}
                    </div>
                    <div style={{
                      fontSize: '15px',
                      color: 'rgba(255,255,255,0.7)',
                      marginTop: '4px'
                    }}>
                      {pageData.subheadline || 'See how we helped a first-time buyer'}
                    </div>
                  </div>
                </div>
              )}

              {/* Mortgage Calculator badge */}
              {pageData.pageType === 'mortgage_calculator' && (
                <div style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '12px',
                  background: 'rgba(255,255,255,0.1)',
                  backdropFilter: 'blur(20px)',
                  border: '1px solid rgba(255,255,255,0.2)',
                  borderRadius: '16px',
                  padding: '20px 28px',
                  marginBottom: '16px',
                  width: 'fit-content'
                }}>
                  <div style={{
                    width: '44px',
                    height: '44px',
                    borderRadius: '12px',
                    background: 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    boxShadow: '0 4px 14px rgba(139,92,246,0.4)'
                  }}>
                    <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                      <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                  </div>
                  <div>
                    <div style={{
                      fontSize: '28px',
                      fontWeight: '700',
                      color: 'white',
                      letterSpacing: '-0.03em',
                      lineHeight: 1
                    }}>
                      {pageData.headline || 'What Can You Afford?'}
                    </div>
                    <div style={{
                      fontSize: '15px',
                      color: 'rgba(255,255,255,0.7)',
                      marginTop: '4px'
                    }}>
                      {pageData.subheadline || 'Calculate your monthly payment'}
                    </div>
                  </div>
                </div>
              )}

              {/* Stats bar for Open House */}
              {pageData.pageType === 'open_house' && (
                <div style={{
                  display: 'flex',
                  gap: '32px'
                }}>
                  {pageData.propertyBeds && (
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                      <span style={{
                        fontSize: '13px',
                        color: 'rgba(255,255,255,0.5)',
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                        marginBottom: '4px'
                      }}>Beds</span>
                      <span style={{
                        fontSize: '24px',
                        fontWeight: '600',
                        color: 'white'
                      }}>{pageData.propertyBeds}</span>
                    </div>
                  )}
                  {pageData.propertyBaths && (
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                      <span style={{
                        fontSize: '13px',
                        color: 'rgba(255,255,255,0.5)',
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                        marginBottom: '4px'
                      }}>Baths</span>
                      <span style={{
                        fontSize: '24px',
                        fontWeight: '600',
                        color: 'white'
                      }}>{pageData.propertyBaths}</span>
                    </div>
                  )}
                  {pageData.propertySqft && (
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                      <span style={{
                        fontSize: '13px',
                        color: 'rgba(255,255,255,0.5)',
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                        marginBottom: '4px'
                      }}>Sq Ft</span>
                      <span style={{
                        fontSize: '24px',
                        fontWeight: '600',
                        color: 'white'
                      }}>{pageData.propertySqft}</span>
                    </div>
                  )}
                </div>
              )}
            </div>
          </>
        );

      case 2:
        return (
          <>
            <div style={{
              position: 'absolute',
              inset: 0,
              backgroundImage: `url(${heroImage})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              filter: 'blur(8px) brightness(0.4)',
              transform: 'scale(1.1)'
            }} />
            <div style={{
              position: 'relative',
              height: '100%',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              alignItems: 'center',
              padding: '48px',
              textAlign: 'center'
            }}>
              <div style={{
                background: 'rgba(255,255,255,0.1)',
                backdropFilter: 'blur(20px)',
                border: '1px solid rgba(255,255,255,0.2)',
                borderRadius: '24px',
                padding: '48px',
                maxWidth: '500px'
              }}>
                <div style={{
                  width: '64px',
                  height: '64px',
                  borderRadius: '16px',
                  background: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 24px',
                  boxShadow: '0 4px 14px rgba(14,165,233,0.4)'
                }}>
                  <svg width="32" height="32" fill="white" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                  </svg>
                </div>
                <h2 style={{
                  fontSize: '28px',
                  fontWeight: '700',
                  color: 'white',
                  marginBottom: '12px'
                }}>Almost There!</h2>
                <p style={{
                  fontSize: '16px',
                  color: 'rgba(255,255,255,0.7)',
                  lineHeight: 1.6
                }}>
                  Just a few quick questions to help us serve you better and connect you with the right resources.
                </p>
              </div>
            </div>
          </>
        );

      case 3:
        return (
          <>
            <div style={{
              position: 'absolute',
              inset: 0,
              backgroundImage: `url(${heroImage})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              filter: 'blur(12px) brightness(0.35)',
              transform: 'scale(1.1)'
            }} />
            <div style={{
              position: 'relative',
              height: '100%',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              alignItems: 'center',
              padding: '48px',
              textAlign: 'center'
            }}>
              <div style={{
                background: 'rgba(255,255,255,0.1)',
                backdropFilter: 'blur(20px)',
                border: '1px solid rgba(255,255,255,0.2)',
                borderRadius: '24px',
                padding: '48px',
                maxWidth: '500px'
              }}>
                <div style={{
                  width: '64px',
                  height: '64px',
                  borderRadius: '16px',
                  background: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 24px',
                  boxShadow: '0 4px 14px rgba(14,165,233,0.4)'
                }}>
                  <svg width="32" height="32" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                  </svg>
                </div>
                <h2 style={{
                  fontSize: '28px',
                  fontWeight: '700',
                  color: 'white',
                  marginBottom: '12px'
                }}>One Last Thing</h2>
                <p style={{
                  fontSize: '16px',
                  color: 'rgba(255,255,255,0.7)',
                  lineHeight: 1.6
                }}>
                  Any questions or comments? We're here to help make your home journey smooth and stress-free.
                </p>
              </div>
            </div>
          </>
        );

      case 4:
        return (
          <>
            <div style={{
              position: 'absolute',
              inset: 0,
              background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)'
            }} />
            <div style={{
              position: 'relative',
              height: '100%',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              alignItems: 'center',
              padding: '48px',
              textAlign: 'center'
            }}>
              <div style={{
                width: '100px',
                height: '100px',
                borderRadius: '50%',
                background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: '32px',
                boxShadow: '0 8px 32px rgba(16,185,129,0.4)'
              }}>
                <svg width="48" height="48" fill="white" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
              </div>
              <h2 style={{
                fontSize: '36px',
                fontWeight: '700',
                color: 'white',
                marginBottom: '16px'
              }}>You're All Set!</h2>
              <p style={{
                fontSize: '18px',
                color: 'rgba(255,255,255,0.7)',
                lineHeight: 1.6,
                maxWidth: '400px'
              }}>
                Thank you for signing in. We'll be in touch shortly with more information.
              </p>
            </div>
          </>
        );

      default:
        return null;
    }
  };

  // Form content for each step - RIGHT SIDE (30%)
  const renderFormContent = () => {
    switch (step) {
      case 1:
        return (
          <>
            <h2 style={{
              fontSize: '26px',
              fontWeight: '700',
              color: '#0f172a',
              marginBottom: '8px',
              lineHeight: 1.2
            }}>{pageData.headline || 'Welcome!'}</h2>
            <p style={{
              fontSize: '15px',
              color: '#64748b',
              marginBottom: '32px',
              lineHeight: 1.5
            }}>{pageData.subheadline || 'Sign in to continue'}</p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
              <div>
                <label style={labelStyle}>Full Name *</label>
                <input
                  type="text"
                  name="fullName"
                  value={formData.fullName}
                  onChange={handleInputChange}
                  onFocus={() => setFocusedField('fullName')}
                  onBlur={() => setFocusedField(null)}
                  placeholder="John Smith"
                  style={getInputStyle('fullName')}
                  autoComplete="name"
                />
              </div>
              <div>
                <label style={labelStyle}>Email Address *</label>
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  onFocus={() => setFocusedField('email')}
                  onBlur={() => setFocusedField(null)}
                  placeholder="john@email.com"
                  style={getInputStyle('email')}
                  autoComplete="email"
                />
              </div>
              <div>
                <label style={labelStyle}>Phone Number *</label>
                <input
                  type="tel"
                  name="phone"
                  value={formData.phone}
                  onChange={handleInputChange}
                  onFocus={() => setFocusedField('phone')}
                  onBlur={() => setFocusedField(null)}
                  placeholder="(555) 123-4567"
                  style={getInputStyle('phone')}
                  autoComplete="tel"
                />
              </div>
            </div>

            <button
              onClick={nextStep}
              disabled={!canProceedStep1}
              style={{
                ...buttonStyle,
                opacity: canProceedStep1 ? 1 : 0.5,
                cursor: canProceedStep1 ? 'pointer' : 'not-allowed',
                transform: canProceedStep1 ? 'none' : 'scale(0.99)',
              }}
              onMouseEnter={(e) => {
                if (canProceedStep1) {
                  e.currentTarget.style.transform = 'translateY(-2px)';
                  e.currentTarget.style.boxShadow = '0 6px 20px rgba(14,165,233,0.4)';
                }
              }}
              onMouseLeave={(e) => {
                if (canProceedStep1) {
                  e.currentTarget.style.transform = 'translateY(0)';
                  e.currentTarget.style.boxShadow = '0 4px 14px rgba(14,165,233,0.3)';
                }
              }}
            >
              Continue
            </button>

            <p style={{
              fontSize: '12px',
              color: '#94a3b8',
              textAlign: 'center' as const,
              marginTop: '16px',
              lineHeight: 1.4
            }}>
              Your information is secure and will never be shared
            </p>
          </>
        );

      case 2:
        return (
          <>
            <h2 style={{
              fontSize: '26px',
              fontWeight: '700',
              color: '#0f172a',
              marginBottom: '8px',
              lineHeight: 1.2
            }}>Quick Questions</h2>
            <p style={{
              fontSize: '15px',
              color: '#64748b',
              marginBottom: '32px',
              lineHeight: 1.5
            }}>Help us serve you better</p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
              {/* Working with agent */}
              {pageData.pageType === 'open_house' && (
                <div>
                  <label style={labelStyle}>Are you working with an agent?</label>
                  <div style={{ display: 'flex', gap: '12px', marginTop: '10px' }}>
                    <button
                      onClick={() => handleRadio('workingWithAgent', true)}
                      style={formData.workingWithAgent === true ? radioSelectedStyle : radioStyle}
                      onMouseEnter={(e) => {
                        if (formData.workingWithAgent !== true) {
                          e.currentTarget.style.borderColor = '#cbd5e1';
                          e.currentTarget.style.background = '#f1f5f9';
                        }
                      }}
                      onMouseLeave={(e) => {
                        if (formData.workingWithAgent !== true) {
                          e.currentTarget.style.borderColor = '#e2e8f0';
                          e.currentTarget.style.background = '#f8fafc';
                        }
                      }}
                    >Yes</button>
                    <button
                      onClick={() => handleRadio('workingWithAgent', false)}
                      style={formData.workingWithAgent === false ? radioSelectedStyle : radioStyle}
                      onMouseEnter={(e) => {
                        if (formData.workingWithAgent !== false) {
                          e.currentTarget.style.borderColor = '#cbd5e1';
                          e.currentTarget.style.background = '#f1f5f9';
                        }
                      }}
                      onMouseLeave={(e) => {
                        if (formData.workingWithAgent !== false) {
                          e.currentTarget.style.borderColor = '#e2e8f0';
                          e.currentTarget.style.background = '#f8fafc';
                        }
                      }}
                    >No</button>
                  </div>
                </div>
              )}

              {/* Pre-approved */}
              <div>
                <label style={labelStyle}>Are you pre-approved for financing?</label>
                <div style={{ display: 'flex', gap: '12px', marginTop: '10px' }}>
                  <button
                    onClick={() => handleRadio('preApproved', true)}
                    style={formData.preApproved === true ? radioSelectedStyle : radioStyle}
                    onMouseEnter={(e) => {
                      if (formData.preApproved !== true) {
                        e.currentTarget.style.borderColor = '#cbd5e1';
                        e.currentTarget.style.background = '#f1f5f9';
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (formData.preApproved !== true) {
                        e.currentTarget.style.borderColor = '#e2e8f0';
                        e.currentTarget.style.background = '#f8fafc';
                      }
                    }}
                  >Yes</button>
                  <button
                    onClick={() => handleRadio('preApproved', false)}
                    style={formData.preApproved === false ? radioSelectedStyle : radioStyle}
                    onMouseEnter={(e) => {
                      if (formData.preApproved !== false) {
                        e.currentTarget.style.borderColor = '#cbd5e1';
                        e.currentTarget.style.background = '#f1f5f9';
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (formData.preApproved !== false) {
                        e.currentTarget.style.borderColor = '#e2e8f0';
                        e.currentTarget.style.background = '#f8fafc';
                      }
                    }}
                  >No</button>
                </div>
              </div>

              {/* Show if not pre-approved */}
              {formData.preApproved === false && (
                <div style={{
                  animation: 'fadeIn 0.3s ease-in',
                }}>
                  <label style={labelStyle}>Interested in getting pre-approved?</label>
                  <div style={{ display: 'flex', gap: '12px', marginTop: '10px' }}>
                    <button
                      onClick={() => handleRadio('interestedInPreApproval', true)}
                      style={formData.interestedInPreApproval === true ? radioSelectedStyle : radioStyle}
                      onMouseEnter={(e) => {
                        if (formData.interestedInPreApproval !== true) {
                          e.currentTarget.style.borderColor = '#cbd5e1';
                          e.currentTarget.style.background = '#f1f5f9';
                        }
                      }}
                      onMouseLeave={(e) => {
                        if (formData.interestedInPreApproval !== true) {
                          e.currentTarget.style.borderColor = '#e2e8f0';
                          e.currentTarget.style.background = '#f8fafc';
                        }
                      }}
                    >Yes</button>
                    <button
                      onClick={() => handleRadio('interestedInPreApproval', false)}
                      style={formData.interestedInPreApproval === false ? radioSelectedStyle : radioStyle}
                      onMouseEnter={(e) => {
                        if (formData.interestedInPreApproval !== false) {
                          e.currentTarget.style.borderColor = '#cbd5e1';
                          e.currentTarget.style.background = '#f1f5f9';
                        }
                      }}
                      onMouseLeave={(e) => {
                        if (formData.interestedInPreApproval !== false) {
                          e.currentTarget.style.borderColor = '#e2e8f0';
                          e.currentTarget.style.background = '#f8fafc';
                        }
                      }}
                    >No</button>
                  </div>
                </div>
              )}

              {/* Timeframe */}
              <div>
                <label style={labelStyle}>When are you looking to buy?</label>
                <select
                  name="timeframe"
                  value={formData.timeframe}
                  onChange={handleInputChange}
                  onFocus={() => setFocusedField('timeframe')}
                  onBlur={() => setFocusedField(null)}
                  style={getInputStyle('timeframe')}
                >
                  <option value="">Select timeframe</option>
                  <option value="asap">As soon as possible</option>
                  <option value="1-3">1-3 months</option>
                  <option value="3-6">3-6 months</option>
                  <option value="browsing">Just browsing</option>
                </select>
              </div>
            </div>

            <div style={{ display: 'flex', gap: '12px' }}>
              <button
                onClick={prevStep}
                style={backButtonStyle}
                onMouseEnter={(e) => {
                  e.currentTarget.style.background = '#e2e8f0';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.background = '#f1f5f9';
                }}
              >Back</button>
              <button
                onClick={nextStep}
                disabled={!canProceedStep2}
                style={{
                  ...buttonStyle,
                  flex: 1,
                  opacity: canProceedStep2 ? 1 : 0.5,
                  cursor: canProceedStep2 ? 'pointer' : 'not-allowed'
                }}
                onMouseEnter={(e) => {
                  if (canProceedStep2) {
                    e.currentTarget.style.transform = 'translateY(-2px)';
                    e.currentTarget.style.boxShadow = '0 6px 20px rgba(14,165,233,0.4)';
                  }
                }}
                onMouseLeave={(e) => {
                  if (canProceedStep2) {
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.boxShadow = '0 4px 14px rgba(14,165,233,0.3)';
                  }
                }}
              >
                Continue
              </button>
            </div>
          </>
        );

      case 3:
        return (
          <>
            <h2 style={{
              fontSize: '26px',
              fontWeight: '700',
              color: '#0f172a',
              marginBottom: '8px',
              lineHeight: 1.2
            }}>Anything Else?</h2>
            <p style={{
              fontSize: '15px',
              color: '#64748b',
              marginBottom: '32px',
              lineHeight: 1.5
            }}>Optional - but we'd love to hear from you</p>

            <div>
              <label style={labelStyle}>Comments or Questions</label>
              <textarea
                name="comments"
                value={formData.comments}
                onChange={handleInputChange}
                onFocus={() => setFocusedField('comments')}
                onBlur={() => setFocusedField(null)}
                placeholder="Any questions about this property or financing options?"
                rows={5}
                style={{
                  ...getInputStyle('comments'),
                  resize: 'none' as const,
                  minHeight: '140px',
                  fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
                }}
              />
            </div>

            {submitError && (
              <div style={{
                marginTop: '16px',
                padding: '12px 16px',
                background: '#fef2f2',
                border: '1px solid #fecaca',
                borderRadius: '8px',
                color: '#991b1b',
                fontSize: '14px',
                lineHeight: 1.5,
                display: 'flex',
                alignItems: 'center',
                gap: '8px'
              }}>
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                {submitError}
              </div>
            )}

            <div style={{ display: 'flex', gap: '12px' }}>
              <button
                onClick={prevStep}
                style={backButtonStyle}
                disabled={isSubmitting}
                onMouseEnter={(e) => {
                  if (!isSubmitting) {
                    e.currentTarget.style.background = '#e2e8f0';
                  }
                }}
                onMouseLeave={(e) => {
                  if (!isSubmitting) {
                    e.currentTarget.style.background = '#f1f5f9';
                  }
                }}
              >Back</button>
              <button
                onClick={handleSubmit}
                disabled={isSubmitting}
                style={{
                  ...buttonStyle,
                  flex: 1,
                  opacity: isSubmitting ? 0.7 : 1,
                  cursor: isSubmitting ? 'not-allowed' : 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: '8px'
                }}
                onMouseEnter={(e) => {
                  if (!isSubmitting) {
                    e.currentTarget.style.transform = 'translateY(-2px)';
                    e.currentTarget.style.boxShadow = '0 6px 20px rgba(14,165,233,0.4)';
                  }
                }}
                onMouseLeave={(e) => {
                  if (!isSubmitting) {
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.boxShadow = '0 4px 14px rgba(14,165,233,0.3)';
                  }
                }}
              >
                {isSubmitting && (
                  <svg
                    width="18"
                    height="18"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    viewBox="0 0 24 24"
                    style={{
                      animation: 'spin 1s linear infinite'
                    }}
                  >
                    <circle cx="12" cy="12" r="10" opacity="0.25" />
                    <path d="M4 12a8 8 0 018-8" strokeLinecap="round" />
                  </svg>
                )}
                {isSubmitting ? 'Submitting...' : (pageData.buttonText || 'Submit')}
              </button>
            </div>

            <p style={{
              fontSize: '12px',
              color: '#94a3b8',
              textAlign: 'center' as const,
              marginTop: '16px',
              lineHeight: 1.5
            }}>
              {pageData.consentText || 'By submitting, you agree to receive communications about this property and financing options.'}
            </p>

            <style>{`
              @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
              }
              @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
              }
            `}</style>
          </>
        );

      case 4:
        return (
          <>
            <div style={{
              textAlign: 'center' as const,
              padding: '32px 0'
            }}>
              <div style={{
                width: '80px',
                height: '80px',
                borderRadius: '50%',
                background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 24px',
                boxShadow: '0 8px 24px rgba(16,185,129,0.35)',
                animation: 'successPulse 2s ease-in-out infinite'
              }}>
                <svg width="40" height="40" fill="white" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
              </div>
              <h2 style={{
                fontSize: '28px',
                fontWeight: '700',
                color: '#0f172a',
                marginBottom: '12px',
                lineHeight: 1.2
              }}>Thank You!</h2>
              <p style={{
                fontSize: '16px',
                color: '#64748b',
                marginBottom: '28px',
                lineHeight: 1.5
              }}>Your information has been received</p>
            </div>

            <div style={{
              background: 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)',
              border: '1px solid #bae6fd',
              borderRadius: '12px',
              padding: '24px',
              marginBottom: '24px'
            }}>
              <p style={{
                fontSize: '13px',
                fontWeight: '600',
                color: '#0369a1',
                marginBottom: '16px',
                textTransform: 'uppercase' as const,
                letterSpacing: '0.05em'
              }}>What happens next:</p>
              <ul style={{
                margin: 0,
                padding: 0,
                listStyle: 'none'
              }}>
                {[
                  'You\'ll receive a confirmation email',
                  'Our team will reach out within 24 hours',
                  'We\'ll answer any questions you have'
                ].map((item, index) => (
                  <li
                    key={index}
                    style={{
                      display: 'flex',
                      alignItems: 'flex-start',
                      gap: '12px',
                      color: '#0c4a6e',
                      fontSize: '14px',
                      lineHeight: 1.6,
                      marginBottom: index < 2 ? '12px' : '0'
                    }}
                  >
                    <svg
                      width="20"
                      height="20"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                      style={{
                        flexShrink: 0,
                        marginTop: '2px',
                        color: '#0ea5e9'
                      }}
                    >
                      <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    {item}
                  </li>
                ))}
              </ul>
            </div>

            {pageData.loanOfficer && (
              <div style={{
                background: '#f8fafc',
                border: '1px solid #e2e8f0',
                borderRadius: '12px',
                padding: '20px',
                marginBottom: '24px'
              }}>
                <p style={{
                  fontSize: '13px',
                  fontWeight: '600',
                  color: '#64748b',
                  marginBottom: '12px',
                  textTransform: 'uppercase' as const,
                  letterSpacing: '0.05em'
                }}>Your Loan Officer</p>
                <div style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '12px'
                }}>
                  <img
                    src={pageData.loanOfficer.photo}
                    alt={pageData.loanOfficer.name}
                    style={{
                      width: '48px',
                      height: '48px',
                      borderRadius: '50%',
                      objectFit: 'cover' as const,
                      border: '2px solid #0ea5e9'
                    }}
                  />
                  <div>
                    <div style={{
                      fontSize: '15px',
                      fontWeight: '600',
                      color: '#0f172a',
                      marginBottom: '2px'
                    }}>{pageData.loanOfficer.name}</div>
                    <div style={{
                      fontSize: '13px',
                      color: '#64748b'
                    }}>NMLS #{pageData.loanOfficer.nmls}</div>
                  </div>
                </div>
              </div>
            )}

            <button
              style={{
                ...buttonStyle,
                background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
                boxShadow: '0 4px 14px rgba(15,23,42,0.3)'
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.transform = 'translateY(-2px)';
                e.currentTarget.style.boxShadow = '0 6px 20px rgba(15,23,42,0.4)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = 'translateY(0)';
                e.currentTarget.style.boxShadow = '0 4px 14px rgba(15,23,42,0.3)';
              }}
            >
              Close Window
            </button>

            <style>{`
              @keyframes successPulse {
                0%, 100% { transform: scale(1); box-shadow: 0 8px 24px rgba(16,185,129,0.35); }
                50% { transform: scale(1.05); box-shadow: 0 12px 32px rgba(16,185,129,0.45); }
              }
            `}</style>
          </>
        );

      default:
        return null;
    }
  };

  return (
    <div style={{
      height: '100vh',
      width: '100vw',
      display: 'flex',
      fontFamily: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
      overflow: 'hidden'
    }}>

      {/* Left Column - 70% - Content Area */}
      <div style={{
        width: '70%',
        height: '100%',
        position: 'relative',
        overflow: 'hidden',
        transition: 'all 0.5s ease-in-out'
      }}>
        {renderLeftContent()}
      </div>

      {/* Right Column - 30% - Team + Form */}
      <div style={{
        width: '30%',
        height: '100%',
        background: '#ffffff',
        display: 'flex',
        flexDirection: 'column',
        borderLeft: '1px solid #e2e8f0'
      }}>

        {/* Team Cards - Fixed at top */}
        <div style={{
          padding: '20px 24px',
          background: 'linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%)',
          borderBottom: '1px solid #e2e8f0'
        }}>
          {/* LO Card */}
          {pageData.loanOfficer && (
            <div style={{
              display: 'flex',
              alignItems: 'center',
              gap: '14px',
              marginBottom: pageData.realtor ? '16px' : '0',
              padding: '12px',
              background: '#fff',
              borderRadius: '10px',
              border: '1px solid #e2e8f0',
              transition: 'all 0.2s ease'
            }}>
              <div style={{
                position: 'relative' as const
              }}>
                <img
                  src={pageData.loanOfficer.photo}
                  alt={pageData.loanOfficer.name}
                  style={{
                    width: '56px',
                    height: '56px',
                    borderRadius: '50%',
                    objectFit: 'cover' as const,
                    border: '3px solid #0ea5e9',
                    boxShadow: '0 2px 8px rgba(14,165,233,0.2)'
                  }}
                />
                <div style={{
                  position: 'absolute' as const,
                  bottom: '-2px',
                  right: '-2px',
                  width: '20px',
                  height: '20px',
                  background: 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                  borderRadius: '50%',
                  border: '2px solid white',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center'
                }}>
                  <svg width="10" height="10" fill="white" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                  </svg>
                </div>
              </div>
              <div style={{ flex: 1 }}>
                <div style={{
                  fontSize: '15px',
                  fontWeight: '600',
                  color: '#0f172a',
                  marginBottom: '2px'
                }}>{pageData.loanOfficer.name}</div>
                <div style={{
                  fontSize: '13px',
                  color: '#64748b',
                  marginBottom: '2px'
                }}>{pageData.loanOfficer.title || 'Loan Officer'}</div>
                <div style={{
                  fontSize: '11px',
                  color: '#94a3b8',
                  fontWeight: '500'
                }}>NMLS #{pageData.loanOfficer.nmls}</div>
              </div>
            </div>
          )}

          {/* Realtor Card */}
          {pageData.realtor && (
            <div style={{
              display: 'flex',
              alignItems: 'center',
              gap: '14px',
              padding: '12px',
              background: '#fff',
              borderRadius: '10px',
              border: '1px solid #e2e8f0',
              transition: 'all 0.2s ease'
            }}>
              <div style={{
                position: 'relative' as const
              }}>
                <img
                  src={pageData.realtor.photo}
                  alt={pageData.realtor.name}
                  style={{
                    width: '56px',
                    height: '56px',
                    borderRadius: '50%',
                    objectFit: 'cover' as const,
                    border: '3px solid #f59e0b',
                    boxShadow: '0 2px 8px rgba(245,158,11,0.2)'
                  }}
                />
                <div style={{
                  position: 'absolute' as const,
                  bottom: '-2px',
                  right: '-2px',
                  width: '20px',
                  height: '20px',
                  background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                  borderRadius: '50%',
                  border: '2px solid white',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center'
                }}>
                  <svg width="10" height="10" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                  </svg>
                </div>
              </div>
              <div style={{ flex: 1 }}>
                <div style={{
                  fontSize: '15px',
                  fontWeight: '600',
                  color: '#0f172a',
                  marginBottom: '2px'
                }}>{pageData.realtor.name}</div>
                <div style={{
                  fontSize: '13px',
                  color: '#64748b',
                  marginBottom: '2px'
                }}>{pageData.realtor.title || 'Realtor'}</div>
                <div style={{
                  fontSize: '11px',
                  color: '#94a3b8',
                  fontWeight: '500'
                }}>{pageData.realtor.license}</div>
              </div>
            </div>
          )}
        </div>

        {/* Progress indicator */}
        {step < 4 && (
          <div style={{
            padding: '20px 24px',
            borderBottom: '1px solid #e2e8f0',
            background: '#fafbfc'
          }}>
            <div style={{
              fontSize: '11px',
              fontWeight: '600',
              color: '#64748b',
              marginBottom: '10px',
              textAlign: 'center' as const,
              textTransform: 'uppercase' as const,
              letterSpacing: '0.05em'
            }}>Step {step} of {totalSteps - 1}</div>
            <div style={{
              display: 'flex',
              gap: '6px'
            }}>
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  style={{
                    flex: 1,
                    height: '6px',
                    borderRadius: '3px',
                    background: i <= step
                      ? 'linear-gradient(90deg, #0ea5e9 0%, #0284c7 100%)'
                      : '#e2e8f0',
                    transition: 'all 0.3s ease',
                    boxShadow: i <= step ? '0 2px 4px rgba(14,165,233,0.2)' : 'none',
                  }}
                />
              ))}
            </div>
          </div>
        )}

        {/* Form Area - Scrollable */}
        <div
          style={{
            flex: 1,
            padding: '28px 24px',
            overflowY: 'auto' as const,
            overflowX: 'hidden' as const
          }}
          className="form-scrollable"
        >
          {renderFormContent()}
        </div>

        <style>{`
          .form-scrollable::-webkit-scrollbar {
            width: 8px;
          }
          .form-scrollable::-webkit-scrollbar-track {
            background: #f1f5f9;
          }
          .form-scrollable::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
          }
          .form-scrollable::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
          }
        `}</style>

        {/* Logos - Fixed at bottom */}
        <div style={{
          padding: '20px 24px',
          borderTop: '1px solid #e2e8f0',
          background: 'linear-gradient(to top, #f8fafc 0%, #ffffff 100%)',
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          gap: '20px',
          boxShadow: '0 -2px 10px rgba(0,0,0,0.02)'
        }}>
          <div style={{
            fontSize: '13px',
            fontWeight: '700',
            color: '#c4a052',
            letterSpacing: '0.08em'
          }}>CENTURY 21</div>
          <div style={{
            width: '1px',
            height: '24px',
            background: 'linear-gradient(to bottom, transparent 0%, #cbd5e1 50%, transparent 100%)'
          }} />
          <div style={{
            fontSize: '13px',
            fontWeight: '600',
            color: '#0ea5e9',
            letterSpacing: '0.02em'
          }}>21st Century Lending</div>
        </div>

      </div>
    </div>
  );
};
