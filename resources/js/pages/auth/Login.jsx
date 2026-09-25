import React from 'react';
import { Mail } from 'lucide-react';
import Checkbox from '@/components/forms/Checkbox';
import Input from '@/components/forms/Input';
import PasswordInput from '@/components/forms/PasswordInput';
import Button from '@/components/ui/Button';
import AuthLayout from '@/layouts/AuthLayout';
import { useAuth } from '@/hooks/useAuth';

export default function Login() {
    const { csrf, errors, old } = useAuth();

    return (
        <AuthLayout title="Sign in" subtitle="Use your Bough Digital account to continue.">
            <form className="form-stack" method="post" action="/login">
                <input type="hidden" name="_token" value={csrf} />
                <Input icon={Mail} name="email" type="email" label="Email" defaultValue={old.email || ''} autoComplete="email" error={errors.email?.[0]} />
                <PasswordInput error={errors.password?.[0]} />
                <div className="between-row">
                    <Checkbox name="remember_me" defaultChecked={Boolean(old.remember_me)}>Remember me</Checkbox>
                    <a className="link" href="/forgot-password">Forgot password?</a>
                </div>
                <Button type="submit" full>Sign in</Button>
            </form>
        </AuthLayout>
    );
}
