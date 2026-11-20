import { Head } from '@inertiajs/react';
import WelcomeArchival from './partials/welcome-archival';
import WelcomeBrowse from './partials/welcome-browse';
import WelcomeFooter from './partials/welcome-footer';
import WelcomeHeader from './partials/welcome-header';
import WelcomeHero from './partials/welcome-hero';
import WelcomeSteps from './partials/welcome-steps';

type Props = {
    canRegister: boolean;
};

export default function Welcome({ canRegister }: Props) {
    return (
        <>
            <Head title="Video archive" />
            <div className="min-h-svh bg-background text-foreground">
                <WelcomeHeader canRegister={canRegister} />
                <main>
                    <WelcomeHero canRegister={canRegister} />
                    <WelcomeSteps />
                    <WelcomeBrowse />
                    <WelcomeArchival />
                </main>
                <WelcomeFooter />
            </div>
        </>
    );
}
