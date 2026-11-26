import type { AuthLayoutProps } from "@/types";
import AuthHeader from "./partials/auth-header";
import AuthShowcase from "./partials/auth-showcase";

/**
 * Login, registration and the other account pages, in the welcome page's
 * style: a white page with the looping clip on a strip of film (a short band
 * above the form on small screens) and the form in a bordered white card.
 *
 * The card sets `--color-background` to white so pieces painted with it (the
 * passkey divider's label) blend into the card.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
    clip,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col text-foreground">
            <AuthHeader />

            <div className="grid flex-1 grid-cols-1 grid-rows-[auto_1fr] lg:grid-rows-1 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                <AuthShowcase clip={clip} />

                <main className="flex flex-col px-4 pt-2 pb-8 sm:px-6 lg:py-20 lg:pr-10 lg:pl-4">
                    <div className="flex flex-1 items-start justify-center lg:items-center">
                        <div className="w-full max-w-lg animate-in space-y-8 rounded-2xl border bg-white px-6 py-8 shadow-xl shadow-black/5 duration-700 fill-mode-both [--color-background:#ffffff] fade-in slide-in-from-bottom-4 sm:px-10 sm:py-13">
                            <div className="space-y-1.5">
                                <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                    {title}
                                </h1>
                                <p className="text-sm text-pretty text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
