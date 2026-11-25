import React from 'react';
import { createRoot } from 'react-dom/client';
import Installer from '@/pages/install/Installer';
import '../css/app.css';
import '../css/installer.css';

// Separate entry from app.jsx: the installer runs before there is a session,
// user, workspace or app key, so it can't use the portal's providers.
createRoot(document.getElementById('installer')).render(
    <React.StrictMode>
        <Installer config={window.__BOUGH_INSTALLER__ || {}} />
    </React.StrictMode>,
);
