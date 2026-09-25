import React, { useMemo } from 'react';
import { ChevronDown, KeyRound, LogOut, User } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';

function csrfInput(csrf) {
    return <input type="hidden" name="_token" value={csrf} />;
}

export default function UserMenu({ workspace, base }) {
    const { csrf, user } = useAuth();
    const { options } = useWorkspace();
    const initials = useMemo(() => (user?.name || 'User').split(' ').map((part) => part[0]).join('').slice(0, 2), [user?.name]);

    return (
        <div className="user-menu">
            <button className="btn btn-secondary" type="button" title="User menu">
                <span className="avatar">
                    {user?.avatar_url ? <img src={user.avatar_url} alt="" /> : initials}
                </span>
                <span className="hidden sm:inline">{user?.name || 'User'}</span>
                <ChevronDown size={16} />
            </button>
            <div className="user-menu-panel">
                <div className="user-card">
                    <div className="font-bold">{user?.name}</div>
                    <div className="muted truncate">{user?.email}</div>
                    <div className="muted mt-1 text-xs">{optionLabel(options.roles, user?.role)} / {workspace?.name || 'Platform'}</div>
                </div>
                <div className="p-2">
                    <a className="menu-link" href={`${base}/profile`}><User size={17} />Profile</a>
                    <a className="menu-link" href={`${base}/profile#password`}><KeyRound size={17} />Change Password</a>
                    <form method="post" action="/logout">
                        {csrfInput(csrf)}
                        <button className="menu-button" type="submit"><LogOut size={17} />Logout</button>
                    </form>
                </div>
            </div>
        </div>
    );
}
