import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type VisualAnalysisValues = {
    visualAnalysis: boolean;
    ocr: boolean;
    objectDetection: boolean;
};

export const DEFAULT_VISUAL_ANALYSIS: VisualAnalysisValues = {
    visualAnalysis: true,
    ocr: true,
    objectDetection: true,
};

type Props = {
    /** Prefix for the checkbox ids (the upload page and the retry dialog). */
    idPrefix: string;
    values: VisualAnalysisValues;
    onChange: (values: VisualAnalysisValues) => void;
    disabled?: boolean;
};

/**
 * The visual analysis choices (PROJECT_SPEC §6 pipeline options): shots,
 * key frames and descriptions, with on-screen text and objects as extras
 * that need it. Says what leaves the server.
 */
export default function VisualAnalysisOptions({
    idPrefix,
    values,
    onChange,
    disabled = false,
}: Props) {
    const set = (changes: Partial<VisualAnalysisValues>) =>
        onChange({ ...values, ...changes });
    const extras = [
        { key: 'ocr', label: 'Read the text on screen' },
        { key: 'objectDetection', label: 'Detect objects and count faces' },
    ] as const;

    return (
        <fieldset className="grid gap-2" disabled={disabled}>
            <legend className="mb-1 text-sm font-medium">
                Visual analysis
            </legend>
            <div className="flex items-start gap-2">
                <Checkbox
                    id={`${idPrefix}-visual`}
                    checked={values.visualAnalysis}
                    onCheckedChange={(checked) =>
                        set({ visualAnalysis: checked === true })
                    }
                />
                <Label
                    htmlFor={`${idPrefix}-visual`}
                    className="grid gap-0.5 font-normal"
                >
                    Find shots, key frames and thumbnails
                    <span className="text-xs text-muted-foreground">
                        Small copies of the key frames are sent to OpenAI to
                        describe them; what they show also informs the metadata.
                    </span>
                </Label>
            </div>
            {extras.map(({ key, label }) => (
                <div key={key} className="ml-6 flex items-center gap-2">
                    <Checkbox
                        id={`${idPrefix}-${key}`}
                        checked={values.visualAnalysis && values[key]}
                        disabled={!values.visualAnalysis}
                        onCheckedChange={(checked) =>
                            set({ [key]: checked === true })
                        }
                    />
                    <Label
                        htmlFor={`${idPrefix}-${key}`}
                        className="font-normal"
                    >
                        {label}
                    </Label>
                </div>
            ))}
        </fieldset>
    );
}
