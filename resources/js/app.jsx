import React from 'react';
import { createRoot } from 'react-dom/client';
import Router from '@/router';
import { AuthProvider } from '@/context/AuthContext';
import { WorkspaceProvider } from '@/context/WorkspaceContext';
import '../css/app.css';

const bootstrap = window.__BOUGH_APP__ || {};

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <AuthProvider bootstrap={bootstrap}>
            <WorkspaceProvider bootstrap={bootstrap}>
                <Router />
            </WorkspaceProvider>
        </AuthProvider>
    </React.StrictMode>,
);
