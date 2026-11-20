import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import AuthShowcase from './partials/auth-showcase';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[1.1fr_1fr]">
            <AuthShowcase />

            <div className="flex flex-col px-4 py-8 sm:px-8">
                <Link
                    href={home()}
                    className="flex items-center gap-2.5 font-semibold lg:hidden"
                >
                    <span className="flex size-8 items-center justify-center rounded-md bg-brand text-brand-foreground">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="truncate text-sm">{name}</span>
                </Link>

                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-sm space-y-8">
                        <div className="space-y-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
