import React from 'react';
import { Mail } from 'lucide-react';
import Input from '@/components/forms/Input';
import Button from '@/components/ui/Button';
import AuthLayout from '@/layouts/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export default function ForgotPassword() {
    const { csrf, errors, old } = useAuth();

    return (
        <AuthLayout title="Reset your password" subtitle="Enter your email and we will send a secure reset link.">
            <form className="form-stack" method="post" action="/forgot-password">
                <input type="hidden" name="_token" value={csrf} />
                <Input icon={Mail} name="email" type="email" label="Email" defaultValue={old.email || ''} autoComplete="email" error={errors.email?.[0]} />
                <Button type="submit" full>Send reset link</Button>
                <a className="link text-center" href="/login">Back to login</a>
            </form>
        </AuthLayout>
    );
}
