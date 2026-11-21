import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import Reveal from './reveal';

type Props = {
    canRegister: boolean;
};

/**
 * Closing call to action. Positioned (`relative`) so it paints above the
 * sticky folds as it scrolls over them.
 */
export default function WelcomeCta({ canRegister }: Props) {
    const { auth } = usePage().props;

    return (
        <section className="relative overflow-hidden rounded-3xl bg-brand p-8 text-brand-foreground sm:p-10 lg:p-14">
            <div aria-hidden className="pointer-events-none absolute inset-0">
                <div className="absolute -top-20 -right-10 size-72 animate-float rounded-full border-[3rem] border-brand-foreground/10" />
                <div className="absolute -bottom-24 left-1/3 size-56 animate-float rounded-full bg-brand-foreground/10 [animation-delay:-2s]" />
            </div>

            <Reveal className="relative flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div className="max-w-2xl space-y-3">
                    <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                        Start exploring the archive
                    </h2>
                    <p className="text-lg text-brand-foreground/80">
                        Create a free viewer account to browse every published
                        recording, transcript and chapter.
                    </p>
                </div>

                <div className="flex flex-wrap gap-3">
                    {auth.user ? (
                        <Button
                            asChild
                            size="lg"
                            className="group h-12 bg-brand-foreground px-6 text-brand hover:bg-brand-foreground/90"
                        >
                            <Link href={dashboard()}>
                                Go to your dashboard
                                <ArrowRight className="transition-transform group-hover:translate-x-1" />
                            </Link>
                        </Button>
                    ) : (
                        <>
                            {canRegister && (
                                <Button
                                    asChild
                                    size="lg"
                                    className="group h-12 bg-brand-foreground px-6 text-brand hover:bg-brand-foreground/90"
                                >
                                    <Link href={register()}>
                                        Create a free account
                                        <ArrowRight className="transition-transform group-hover:translate-x-1" />
                                    </Link>
                                </Button>
                            )}
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-12 border-brand-foreground/30 bg-transparent px-6 text-brand-foreground hover:bg-brand-foreground/10 hover:text-brand-foreground"
                            >
                                <Link href={login()}>Log in</Link>
                            </Button>
                        </>
                    )}
                </div>
            </Reveal>
        </section>
    );
}
