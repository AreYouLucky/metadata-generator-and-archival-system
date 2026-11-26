import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({
    title = '',
    description = '',
    clip,
    children,
}: AuthLayoutProps) {
    return (
        <AuthLayoutTemplate title={title} description={description} clip={clip}>
            {children}
        </AuthLayoutTemplate>
    );
}
