import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import './styles/index.css';

// Mount wizard if element exists
const wizardRoot = document.getElementById('frs-lead-pages-wizard');
if (wizardRoot) {
  const type = wizardRoot.dataset.type || '';
  const loId = wizardRoot.dataset.loId || '';
  const showHeader = wizardRoot.dataset.showHeader !== 'false';

  ReactDOM.createRoot(wizardRoot).render(
    <React.StrictMode>
      <App
        mode="wizard"
        initialType={type}
        initialLoId={loId}
        showHeader={showHeader}
      />
    </React.StrictMode>
  );
}

// Mount template if element exists
const templateRoot = document.getElementById('frs-lead-pages-template');
if (templateRoot) {
  const pageId = templateRoot.dataset.pageId || '';

  ReactDOM.createRoot(templateRoot).render(
    <React.StrictMode>
      <App mode="template" pageId={pageId} />
    </React.StrictMode>
  );
}
