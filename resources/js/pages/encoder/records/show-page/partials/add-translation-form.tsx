import { Languages } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useStartTranslation } from '@/hooks/use-translations';
import { getErrorMessage } from '@/lib/utilities';
import type { Translation, TranslationLanguage } from '@/types/records';

type Props = {
    recordId: string;
    /** Languages not translated yet (and not the original's). */
    languages: TranslationLanguage[];
    onStarted: (translation: Translation) => void;
};

/**
 * Pick a language and translate the transcript into it. The lines are sent
 * to the AI provider (OpenAI); timings stay those of the original.
 */
export default function AddTranslationForm({
    recordId,
    languages,
    onStarted,
}: Props) {
    const [language, setLanguage] = useState('');
    const start = useStartTranslation(recordId);

    const submit = () =>
        start.mutate(language, {
            onSuccess: (translation) => {
                setLanguage('');
                onStarted(translation);
                toast.success('Translating… it appears here when ready.');
            },
            onError: (error) =>
                toast.error(
                    getErrorMessage(error, 'The translation could not start.'),
                ),
        });

    return (
        <div className="grid gap-2 rounded-lg bg-white/5 p-3">
            <div className="flex gap-2">
                <Select value={language} onValueChange={setLanguage}>
                    <SelectTrigger
                        className="min-w-0 flex-1"
                        aria-label="Language to translate into"
                    >
                        <SelectValue placeholder="Add a language" />
                    </SelectTrigger>
                    <SelectContent>
                        {languages.map((item) => (
                            <SelectItem key={item.code} value={item.code}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    size="sm"
                    className="h-9"
                    disabled={language === '' || start.isPending}
                    onClick={submit}
                >
                    <Languages aria-hidden />
                    Translate
                </Button>
            </div>
            <p className="text-xs text-neutral-500">
                The transcript text is sent to OpenAI to translate. Timings,
                speakers and line breaks stay those of the original.
            </p>
        </div>
    );
}
