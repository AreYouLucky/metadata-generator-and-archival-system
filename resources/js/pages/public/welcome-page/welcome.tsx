import { Head } from "@inertiajs/react";
import WelcomeArchival from "./partials/welcome-archival";
import WelcomeBrowse from "./partials/welcome-browse";
import WelcomeFooter from "./partials/welcome-footer";
import WelcomeGenerated from "./partials/welcome-generated";
import WelcomeHeader from "./partials/welcome-header";
import WelcomeHero from "./partials/welcome-hero";
import WelcomeSteps from "./partials/welcome-steps";

type Props = {
    canRegister: boolean;
};

export default function Welcome({ canRegister }: Props) {
    return (
        <>
            <Head title="Video archive" />
            <div
                data-scroll-snap
                className="min-h-svh w-full bg-background px-4 pb-4 text-foreground sm:px-6 sm:pb-6 lg:px-10 lg:pb-10"
            >
                <WelcomeHeader canRegister={canRegister} />
                <main className="flex flex-col gap-4 lg:gap-6">
                    {/* The folds stick only within this wrapper, so the last
                        one scrolls away before the call to action arrives. */}
                    <div className="flex flex-col gap-4 lg:gap-6">
                        <WelcomeHero />
                        <WelcomeSteps />
                        <WelcomeGenerated />
                        <WelcomeBrowse />
                        <WelcomeArchival />
                    </div>
                </main>
                <WelcomeFooter canRegister={canRegister} />
            </div>
        </>
    );
}
