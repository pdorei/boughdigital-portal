import React from 'react';
import { Mail } from 'lucide-react';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Button from '@/components/ui/Button';
import AuthLayout from '@/layouts/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export default function ResetPassword() {
    const { csrf, errors, old, resetEmail, resetToken } = useAuth();

    return (
        <AuthLayout title="Choose a new password" subtitle="Set a secure password for your account.">
            <form className="form-stack" method="post" action="/reset-password">
                <input type="hidden" name="_token" value={csrf} />
                <input type="hidden" name="token" value={resetToken || ''} />
                <Input icon={Mail} name="email" type="email" label="Email" defaultValue={old.email || resetEmail || ''} autoComplete="email" error={errors.email?.[0]} />
                <PasswordInput name="password" label="New password" autoComplete="new-password" error={errors.password?.[0]} />
                <PasswordInput name="password_confirmation" label="Confirm new password" autoComplete="new-password" error={errors.password_confirmation?.[0]} />
                <Button type="submit" full>Reset password</Button>
            </form>
        </AuthLayout>
    );
}
