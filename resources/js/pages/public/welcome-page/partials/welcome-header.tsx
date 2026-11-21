import { Link, usePage } from '@inertiajs/react';
import AppearanceToggle from '@/components/all/appearance-toggle';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';
import { WELCOME_SECTIONS } from './welcome-sections';

type Props = {
    canRegister: boolean;
};

export default function WelcomeHeader({ canRegister }: Props) {
    const { auth, name } = usePage().props;

    return (
        <header className="sticky top-0 z-50 animate-in bg-background pt-3 pb-3 duration-700 fill-mode-both fade-in slide-in-from-top-4 lg:pt-4 lg:pb-4">
            <div className="flex h-16 items-center justify-between gap-4 rounded-2xl border border-border/70 bg-background/80 px-4 shadow-sm backdrop-blur-xl sm:px-6">
                <Link
                    href={home()}
                    className="group flex min-w-0 items-center gap-2.5 font-semibold"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand text-brand-foreground shadow-md shadow-brand/30 transition-transform duration-300 group-hover:-rotate-6">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="truncate text-sm sm:text-base">
                        {name}
                    </span>
                </Link>

                <nav
                    aria-label="Sections"
                    className="hidden items-center gap-1 text-sm lg:flex"
                >
                    {WELCOME_SECTIONS.map((section) => (
                        <a
                            key={section.id}
                            href={`#${section.id}`}
                            className="rounded-lg px-3 py-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            {section.label}
                        </a>
                    ))}
                </nav>

                <div className="flex shrink-0 items-center gap-2">
                    <AppearanceToggle />
                    {auth.user ? (
                        <Button asChild size="sm">
                            <Link href={dashboard()}>Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={login()}>Log in</Link>
                            </Button>
                            {canRegister && (
                                <Button
                                    asChild
                                    size="sm"
                                    className="hidden bg-brand text-brand-foreground hover:bg-brand/90 sm:inline-flex"
                                >
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
