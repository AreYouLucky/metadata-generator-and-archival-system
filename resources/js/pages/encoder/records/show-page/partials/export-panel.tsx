import { Link } from '@inertiajs/react';
import { Download, Settings2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { exportQuery, useExportPreview } from '@/hooks/use-export-preview';
import type { ExportChoice } from '@/hooks/use-export-preview';
import { languageName } from '@/lib/utilities';
import { exportMethod } from '@/routes/records';
import { index as exportSchemasIndex } from '@/routes/export-schemas';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { ExportSchema } from '@/types/export-schemas';
import type {
    ExportFormatName,
    ExportFormatOption,
    Translation,
} from '@/types/records';

type Props = {
    recordId: string;
    heading: ReactNode;
    formats: ExportFormatOption[];
    schemas: ExportSchema[];
    originalLanguage: string | null;
    translations: Translation[];
    /** Changes when saved metadata or transcript lines change. */
    version: string;
};

/** Long transcripts make big exports; the preview shows the start. */
const PREVIEW_CHARS = 20_000;
const FULL_DOCUMENT = 'full';
const ORIGINAL = 'original';

/**
 * Pick a format, a custom schema (JSON) or a language (subtitles and text),
 * see a live preview, then download. Downloads are logged; previews aren't.
 */
export default function ExportPanel({
    recordId,
    heading,
    formats,
    schemas,
    originalLanguage,
    translations,
    version,
}: Props) {
    const open = useWorkspaceStore((state) => state.inspectorTab === 'export');
    const [choice, setChoice] = useState<ExportChoice>({
        format: 'json',
        schema: schemas.find((schema) => schema.is_default)?.id ?? null,
        language: null,
        timestamps: false,
    });
    const preview = useExportPreview(recordId, choice, version, open);
    const format = formats.find((item) => item.value === choice.format);
    const languages = translations.filter(
        (translation) => translation.status === 'done',
    );
    const takesLanguage = format?.transcript || choice.format === 'md';
    const change = (changes: Partial<ExportChoice>) =>
        setChoice((current) => ({ ...current, ...changes }));

    return (
        <EditorPanel title="Export" heading={heading}>
            <div className="@container flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                <div className="grid gap-3 @md:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="export-format" className="text-xs">
                            Format
                        </Label>
                        <Select
                            value={choice.format}
                            onValueChange={(value) =>
                                change({ format: value as ExportFormatName })
                            }
                        >
                            <SelectTrigger id="export-format">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {formats.map((item) => (
                                    <SelectItem
                                        key={item.value}
                                        value={item.value}
                                    >
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {choice.format === 'json' && (
                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label
                                    htmlFor="export-schema"
                                    className="text-xs"
                                >
                                    Schema
                                </Label>
                                <Link
                                    href={exportSchemasIndex()}
                                    className="inline-flex items-center gap-1 text-xs text-neutral-400 hover:text-foreground"
                                >
                                    <Settings2 className="size-3" aria-hidden />
                                    Manage
                                </Link>
                            </div>
                            <Select
                                value={
                                    choice.schema === null
                                        ? FULL_DOCUMENT
                                        : String(choice.schema)
                                }
                                onValueChange={(value) =>
                                    change({
                                        schema:
                                            value === FULL_DOCUMENT
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger id="export-schema">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={FULL_DOCUMENT}>
                                        Full record
                                    </SelectItem>
                                    {schemas.map((schema) => (
                                        <SelectItem
                                            key={schema.id}
                                            value={String(schema.id)}
                                        >
                                            {schema.name}
                                            {schema.is_default && ' (default)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {takesLanguage && (
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="export-language"
                                className="text-xs"
                            >
                                Language
                            </Label>
                            <Select
                                value={choice.language ?? ORIGINAL}
                                onValueChange={(value) =>
                                    change({
                                        language:
                                            value === ORIGINAL ? null : value,
                                    })
                                }
                            >
                                <SelectTrigger id="export-language">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ORIGINAL}>
                                        Original
                                        {originalLanguage &&
                                            ` (${languageName(originalLanguage)})`}
                                    </SelectItem>
                                    {languages.map((translation) => (
                                        <SelectItem
                                            key={translation.language}
                                            value={translation.language}
                                        >
                                            {languageName(translation.language)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {choice.format === 'txt' && (
                        <div className="flex items-center gap-2 self-end pb-2">
                            <Checkbox
                                id="export-timestamps"
                                checked={choice.timestamps}
                                onCheckedChange={(checked) =>
                                    change({ timestamps: checked === true })
                                }
                            />
                            <Label
                                htmlFor="export-timestamps"
                                className="text-xs"
                            >
                                Start time on each line
                            </Label>
                        </div>
                    )}
                </div>

                <div className="flex min-h-48 flex-1 flex-col overflow-hidden rounded-lg border border-white/10 bg-black/40">
                    <div className="flex h-8 shrink-0 items-center justify-between border-b border-white/10 px-3 text-[11px] tracking-wider text-neutral-500 uppercase">
                        Preview
                        {preview.isFetching && <Spinner className="size-3" />}
                    </div>
                    <pre className="min-h-0 flex-1 overflow-auto p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap text-neutral-300">
                        {preview.isError
                            ? 'The preview could not be loaded.'
                            : preview.data === undefined
                              ? ''
                              : preview.data === ''
                                ? 'Nothing to export yet.'
                                : preview.data.slice(0, PREVIEW_CHARS) +
                                  (preview.data.length > PREVIEW_CHARS
                                      ? '\n…'
                                      : '')}
                    </pre>
                </div>

                <div className="flex justify-end">
                    <Button size="sm" asChild>
                        {/* A plain link: the browser handles the download. */}
                        <a
                            href={exportMethod.url(recordId, {
                                query: exportQuery(choice),
                            })}
                            download
                        >
                            <Download aria-hidden />
                            Download {choice.format.toUpperCase()}
                        </a>
                    </Button>
                </div>
            </div>
        </EditorPanel>
    );
}
