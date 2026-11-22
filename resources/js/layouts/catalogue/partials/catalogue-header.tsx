import { Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppearanceToggle from '@/components/all/appearance-toggle';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard, login, logout, register } from '@/routes';
import { browse, home } from '@/routes/catalogue';
import { edit as profile } from '@/routes/profile';

/**
 * The public catalogue's header: name, browse, a search box (to /browse?q=),
 * the appearance toggle and the account links.
 */
export default function CatalogueHeader() {
    const { auth, name, canRegister } = usePage().props;
    const [query, setQuery] = useState('');
    const isStaff =
        auth.user?.role === 'admin' || auth.user?.role === 'encoder';

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(browse.url(), query.trim() ? { q: query.trim() } : {});
    };

    return (
        <header className="sticky top-0 z-40 border-b bg-background/85 backdrop-blur-xl">
            <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6">
                <Link
                    href={home()}
                    className="flex min-w-0 items-center gap-2.5 font-semibold"
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                        <AppLogoIcon className="size-4 fill-current" />
                    </span>
                    <span className="hidden truncate text-sm md:inline">
                        {name}
                    </span>
                </Link>

                <nav aria-label="Catalogue" className="hidden sm:block">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={browse()}>Browse</Link>
                    </Button>
                </nav>

                <form
                    onSubmit={search}
                    role="search"
                    className="relative ml-auto w-full max-w-xs"
                >
                    <Search
                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        type="search"
                        value={query}
                        maxLength={100}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search the archive"
                        aria-label="Search the archive"
                        className="pl-8"
                    />
                </form>

                <div className="flex shrink-0 items-center gap-1">
                    <AppearanceToggle />
                    {auth.user ? (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="hidden sm:inline-flex"
                                asChild
                            >
                                <Link href={isStaff ? dashboard() : profile()}>
                                    {isStaff ? 'Workspace' : 'Account'}
                                </Link>
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={logout()} as="button">
                                    Log out
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            {canRegister && (
                                <Button
                                    size="sm"
                                    className="hidden bg-brand text-brand-foreground hover:bg-brand/90 sm:inline-flex"
                                    asChild
                                >
                                    <Link href={register()}>Sign up</Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
