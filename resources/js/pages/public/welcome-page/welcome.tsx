import { Head } from "@inertiajs/react";
import AskMe from "@/components/all/ask-me";
import WelcomeArchival from "./partials/welcome-archival";
import WelcomeAskShare from "./partials/welcome-ask-share";
import WelcomeBrowse from "./partials/welcome-browse";
import WelcomeFooter from "./partials/welcome-footer";
import WelcomeGenerated from "./partials/welcome-generated";
import WelcomeHeader from "./partials/welcome-header";
import WelcomeHero from "./partials/welcome-hero";
import WelcomeReel from "./partials/welcome-reel";
import WelcomeSteps from "./partials/welcome-steps";

type Props = {
    canRegister: boolean;
};

export default function Welcome({ canRegister }: Props) {
    return (
        <>
            <Head title="Every moment, remembered" />
            <div
                data-scroll-snap
                className="min-h-svh w-full px-4 pb-4 text-foreground sm:px-6 sm:pb-6 lg:px-10 lg:pb-8"
            >
                <WelcomeHeader canRegister={canRegister} />
                <main>
                    <WelcomeReel>
                        <WelcomeHero />
                        <WelcomeSteps />
                        <WelcomeGenerated />
                        <WelcomeBrowse />
                        <WelcomeAskShare />
                        <WelcomeArchival />
                    </WelcomeReel>
                </main>
                <WelcomeFooter canRegister={canRegister} />
            </div>
            <AskMe />
        </>
    );
}
