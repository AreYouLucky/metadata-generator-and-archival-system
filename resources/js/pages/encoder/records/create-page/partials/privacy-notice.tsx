import { Info } from 'lucide-react';
import type { AiProviders } from '@/types/records';

type Props = {
    /** Undefined while loading; null fields when the AI service can't be reached. */
    providers: AiProviders | undefined;
    hasUrls: boolean;
    diarization: boolean;
    visualAnalysis: boolean;
};

const HOSTED: Record<string, string> = {
    openai: 'OpenAI',
    assemblyai: 'AssemblyAI',
};

/**
 * Says what leaves this organisation's machines (PROJECT_SPEC §14, privacy):
 * the audio when a hosted transcription or speaker provider is configured,
 * and always the transcript text (and key frames) for the metadata.
 */
export default function PrivacyNotice({
    providers,
    hasUrls,
    diarization,
    visualAnalysis,
}: Props) {
    const transcriber = providers?.transcription
        ? HOSTED[providers.transcription]
        : undefined;
    const speakerService =
        diarization && providers?.diarization && !transcriber
            ? HOSTED[providers.diarization]
            : undefined;

    const audio =
        providers === undefined || providers.transcription === null
            ? 'Audio is transcribed by the service your administrator configured.'
            : transcriber
              ? 'The audio is sent to ' + transcriber + ' to be transcribed.'
              : "Audio is transcribed on this organisation's server.";

    return (
        <p className="mt-auto flex gap-2 rounded-md bg-muted/60 p-3 text-xs text-muted-foreground">
            <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
            <span>
                {hasUrls && 'Web addresses are downloaded by this server. '}
                {audio}{' '}
                {speakerService &&
                    'To identify speakers, the audio is also sent to ' +
                        speakerService +
                        '. '}
                The transcript text (not the video) is then sent to OpenAI to
                write titles, descriptions and tags
                {visualAnalysis
                    ? ', with small copies of the key frames to describe what is shown.'
                    : '.'}
            </span>
        </p>
    );
}
