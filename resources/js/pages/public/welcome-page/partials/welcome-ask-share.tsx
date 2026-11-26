import {
    MessageCircleQuestion,
    Scissors,
    Webhook,
    Youtube,
} from 'lucide-react';
import ChatPreview from './chat-preview';
import FoldHeading from './fold-heading';
import Reveal from './reveal';
import WelcomeFold from './welcome-fold';

const FEATURES = [
    {
        icon: MessageCircleQuestion,
        title: 'Ask me',
        text: 'Answers come only from the record’s own transcript, frames and metadata, with timestamps you can click.',
    },
    {
        icon: Scissors,
        title: 'Clips',
        text: 'Cut the best part straight from the master. No re-uploading, no editing software.',
    },
    {
        icon: Youtube,
        title: 'YouTube publishing',
        text: 'Connect a channel once, then publish approved videos with their title, description and tags.',
    },
    {
        icon: Webhook,
        title: 'Public API and exports',
        text: 'Token-secured API, signed webhooks, and exports to JSON, SRT, VTT, CSV and more.',
    },
];

/**
 * Chat with the archive, clips and the ways a record leaves the archive.
 */
export default function WelcomeAskShare() {
    return (
        <WelcomeFold id="ask-and-share" className="border bg-sky-50">
            <div className="relative grid grid-cols-1 items-center gap-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-16 xl:gap-24">
                <div>
                    <FoldHeading
                        eyebrow="Ask and share"
                        title="Ask a question. Jump straight to the moment."
                        description="The archive answers in plain words and points to the exact second it heard it. Then turn that moment into a clip and share it wherever it belongs."
                    />

                    <ul className="mt-8 grid grid-cols-1 gap-3 sm:mt-10 sm:grid-cols-2 sm:gap-4 fold-short:mt-6">
                        {FEATURES.map((feature, index) => (
                            <li key={feature.title}>
                                <Reveal delay={index * 100} className="h-full">
                                    <div className="group flex h-full gap-4 rounded-2xl border bg-background p-4 transition duration-300 hover:-translate-y-1 hover:border-brand/40 hover:shadow-lg hover:shadow-brand/10 sm:p-5">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-brand transition-colors duration-300 group-hover:bg-brand group-hover:text-brand-foreground">
                                            <feature.icon className="size-5" />
                                        </span>
                                        <div>
                                            <h3 className="font-semibold">
                                                {feature.title}
                                            </h3>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {feature.text}
                                            </p>
                                        </div>
                                    </div>
                                </Reveal>
                            </li>
                        ))}
                    </ul>
                </div>

                <Reveal delay={150}>
                    <ChatPreview />
                </Reveal>
            </div>
        </WelcomeFold>
    );
}
