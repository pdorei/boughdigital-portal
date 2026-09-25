import React, { createContext, useMemo } from 'react';

export const AuthContext = createContext(null);

export function AuthProvider({ bootstrap, children }) {
    const value = useMemo(() => {
        const user = bootstrap.user || null;

        return {
            abilities: bootstrap.abilities || {},
            csrf: bootstrap.csrf || '',
            errors: bootstrap.errors || {},
            isAuthenticated: Boolean(user),
            old: bootstrap.old || {},
            resetEmail: bootstrap.resetEmail || null,
            resetToken: bootstrap.resetToken || null,
            role: user?.role || null,
            status: bootstrap.status || null,
            user,
        };
    }, [bootstrap]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
