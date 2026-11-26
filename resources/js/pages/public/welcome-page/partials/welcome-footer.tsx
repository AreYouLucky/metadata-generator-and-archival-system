import { Link, usePage } from '@inertiajs/react';
import BrandLogo from '@/components/all/brand-logo';
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
            <div className="grid grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-[2fr_1fr_1fr]">
                <div className="col-span-2 max-w-sm space-y-3 lg:col-span-1">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 font-semibold"
                    >
                        <BrandLogo
                            variant="horizontal"
                            alt={name}
                            className="h-11"
                        />
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        Every moment is meant to be remembered. We help you
                        describe it, protect it and find it again, years from
                        now.
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
                <p>Nothing is shared until a person has reviewed it.</p>
            </div>
        </footer>
    );
}
