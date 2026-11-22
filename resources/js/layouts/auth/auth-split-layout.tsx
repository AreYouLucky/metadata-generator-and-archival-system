import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import BrandLogo from '@/components/all/brand-logo';
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

            <div className="relative flex flex-col overflow-hidden px-4 py-6 sm:px-10 lg:p-10">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-40 -right-40 size-96 rounded-full bg-brand/10 blur-3xl"
                />

                <div className="relative flex items-center justify-between gap-4">
                    <Link
                        href={home()}
                        className="flex min-w-0 items-center gap-2.5 font-semibold lg:hidden"
                    >
                        <BrandLogo
                            variant="compact"
                            alt={name}
                            className="h-7"
                        />
                    </Link>
                    <div className="ml-auto flex items-center gap-2">
                        <Link
                            href={home()}
                            className="group flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-4 transition-transform group-hover:-translate-x-0.5" />
                            Back to home
                        </Link>
                    </div>
                </div>

                <div className="relative flex flex-1 items-center justify-center py-10">
                    <div className="w-full max-w-md animate-in space-y-8 duration-700 fill-mode-both fade-in slide-in-from-bottom-4">
                        <div className="space-y-2">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-base text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>

                <p className="relative text-center text-xs text-muted-foreground">
                    Recordings are published only after review.
                </p>
            </div>
        </div>
    );
}
