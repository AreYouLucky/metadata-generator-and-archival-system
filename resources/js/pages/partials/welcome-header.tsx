import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

type Props = {
    canRegister: boolean;
};

export default function WelcomeHeader({ canRegister }: Props) {
    const { auth, name } = usePage().props;

    return (
        <header className="sticky top-0 z-30 border-b border-border/60 bg-background/80 backdrop-blur">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link
                    href={home()}
                    className="flex min-w-0 items-center gap-2.5 font-semibold"
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand text-brand-foreground">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    <span className="truncate text-sm sm:text-base">
                        {name}
                    </span>
                </Link>

                <nav className="flex shrink-0 items-center gap-2">
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
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href={register()}>
                                        Create account
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </nav>
            </div>
        </header>
    );
}
