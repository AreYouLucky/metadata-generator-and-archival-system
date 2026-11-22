import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, home, login, register } from '@/routes';
import { WELCOME_SECTIONS } from './welcome-sections';

type Props = {
    canRegister: boolean;
};

const LINK_CLASS =
    'text-muted-foreground transition-colors hover:text-foreground';

/**
 * Page footer. Positioned (`relative`) so it paints above the sticky folds.
 */
export default function WelcomeFooter({ canRegister }: Props) {
    const { auth, name } = usePage().props;

    return (
        <footer className="relative mt-4 snap-end rounded-3xl border bg-card p-6 sm:p-10 lg:mt-6 lg:scroll-mb-10">
            <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]">
                <div className="max-w-sm space-y-3">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 font-semibold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        {name}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        A video archive where every recording is described,
                        checked by people and preserved for the long term.
                    </p>
                </div>

                <nav aria-label="Explore" className="space-y-3 text-sm">
                    <h2 className="font-medium">Explore</h2>
                    <ul className="space-y-2">
                        {WELCOME_SECTIONS.map((section) => (
                            <li key={section.id}>
                                <a
                                    href={`#${section.id}`}
                                    className={LINK_CLASS}
                                >
                                    {section.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <nav aria-label="Account" className="space-y-3 text-sm">
                    <h2 className="font-medium">Account</h2>
                    <ul className="space-y-2">
                        {auth.user ? (
                            <li>
                                <Link href={dashboard()} className={LINK_CLASS}>
                                    Dashboard
                                </Link>
                            </li>
                        ) : (
                            <>
                                <li>
                                    <Link href={login()} className={LINK_CLASS}>
                                        Log in
                                    </Link>
                                </li>
                                {canRegister && (
                                    <li>
                                        <Link
                                            href={register()}
                                            className={LINK_CLASS}
                                        >
                                            Create account
                                        </Link>
                                    </li>
                                )}
                            </>
                        )}
                    </ul>
                </nav>
            </div>

            <div className="mt-10 flex flex-col gap-2 border-t pt-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <p>
                    © {new Date().getFullYear()} {name}
                </p>
                <p>Recordings are published only after review.</p>
            </div>
        </footer>
    );
}
