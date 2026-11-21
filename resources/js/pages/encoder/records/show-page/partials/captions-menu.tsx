import { Captions, CaptionsOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { languageName } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

type Props = {
    /** Transcript languages with captions: the original first, then translations. */
    languages: string[];
};

const OFF = 'off';

/**
 * The player's caption switcher: off, the original, or any finished
 * translation.
 */
export default function CaptionsMenu({ languages }: Props) {
    const captionLanguage = useWorkspaceStore((state) => state.captionLanguage);
    const setCaptionLanguage = useWorkspaceStore(
        (state) => state.setCaptionLanguage,
    );
    const on = captionLanguage !== null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={on ? 'size-8 text-brand' : 'size-8'}
                    disabled={languages.length === 0}
                    aria-label="Captions"
                    title="Captions"
                >
                    {on ? (
                        <Captions aria-hidden />
                    ) : (
                        <CaptionsOff aria-hidden />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuLabel className="text-xs">
                    Captions
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup
                    value={captionLanguage ?? OFF}
                    onValueChange={(value) =>
                        setCaptionLanguage(value === OFF ? null : value)
                    }
                >
                    <DropdownMenuRadioItem value={OFF}>
                        Off
                    </DropdownMenuRadioItem>
                    {languages.map((language, index) => (
                        <DropdownMenuRadioItem key={language} value={language}>
                            {languageName(language)}
                            {index === 0 && (
                                <span className="ml-auto text-xs text-muted-foreground">
                                    original
                                </span>
                            )}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
