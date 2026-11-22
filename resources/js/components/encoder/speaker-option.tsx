import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    /** Prefix for the checkbox id (the upload page and the retry dialog). */
    idPrefix: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
};

/**
 * Speaker identification (diarization, PROJECT_SPEC §6 pipeline options):
 * labels who speaks each transcript line (S1, S2…), to be named later.
 */
export default function SpeakerOption({
    idPrefix,
    checked,
    onChange,
    disabled = false,
}: Props) {
    return (
        <div className="flex items-start gap-2">
            <Checkbox
                id={`${idPrefix}-diarization`}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <Label
                htmlFor={`${idPrefix}-diarization`}
                className="grid gap-0.5 font-normal"
            >
                Identify speakers
                <span className="text-xs text-muted-foreground">
                    Labels who speaks each line (S1, S2…) so you can name them.
                    Adds a few minutes for long recordings.
                </span>
            </Label>
        </div>
    );
}
