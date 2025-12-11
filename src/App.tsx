import React from 'react';
import { GenerationStationWizard } from './wizard/GenerationStationWizard';
import { LeadPageTemplate } from './templates/LeadPageTemplate';

interface AppProps {
  mode: 'wizard' | 'template';
  initialType?: string;
  initialLoId?: string;
  showHeader?: boolean;
  pageId?: string;
}

export const App: React.FC<AppProps> = ({
  mode,
  initialType = '',
  initialLoId = '',
  showHeader = true,
  pageId = '',
}) => {
  if (mode === 'wizard') {
    return (
      <GenerationStationWizard
        initialType={initialType}
        initialLoId={initialLoId}
        showHeader={showHeader}
      />
    );
  }

  if (mode === 'template') {
    return <LeadPageTemplate pageId={pageId} />;
  }

  return null;
};
