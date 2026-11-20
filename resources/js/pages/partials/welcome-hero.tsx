import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import RecordPreview from './record-preview';

type Props = {
    canRegister: boolean;
};

export default function WelcomeHero({ canRegister }: Props) {
    const { auth } = usePage().props;

    return (
        <section className="relative overflow-hidden">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)] bg-[size:48px_48px] opacity-60"
            />
            <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:py-24">
                <div className="space-y-7">
                    <p className="inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand-muted px-3 py-1 text-xs font-medium text-brand">
                        <span className="size-1.5 rounded-full bg-brand" />
                        Video archive and metadata
                    </p>
                    <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        Every recording described, preserved and easy to find.
                    </h1>
                    <p className="max-w-xl text-lg text-pretty text-muted-foreground">
                        Videos are transcribed and described automatically, then
                        checked by an archivist before they are published.
                        Browse events, programmes and places with confidence.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard()}>
                                    Go to your dashboard
                                    <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                {canRegister && (
                                    <Button asChild size="lg">
                                        <Link href={register()}>
                                            Create a free account
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild size="lg" variant="outline">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <RecordPreview />
            </div>
        </section>
    );
}
