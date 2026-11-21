import {
    CheckCircle2,
    Circle,
    CircleSlash,
    Loader2,
    XCircle,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    PresetName,
    PresetRules,
    ProcessingStatus,
    StageState,
    StageStatus,
} from '@/types/records';
import RetryProcessingDialog from './retry-processing-dialog';

const ICONS: Record<
    StageStatus,
    { icon: typeof Circle; className: string; label: string }
> = {
    pending: { icon: Circle, className: 'text-neutral-500', label: 'Waiting' },
    running: {
        icon: Loader2,
        className: 'animate-spin text-brand',
        label: 'Running',
    },
    done: { icon: CheckCircle2, className: 'text-emerald-400', label: 'Done' },
    failed: { icon: XCircle, className: 'text-red-400', label: 'Failed' },
    skipped: {
        icon: CircleSlash,
        className: 'text-neutral-500',
        label: 'Skipped',
    },
};

type Props = {
    recordId: string;
    stages: StageState[];
    processingStatus: ProcessingStatus;
    presets: PresetRules[];
    initialPreset: PresetName;
    canEdit: boolean;
};

/**
 * A render-queue style strip with live progress per stage (updated over
 * Reverb). Hidden once every stage finished cleanly; stays visible when
 * something failed or was skipped, with the reason on hover and below, and
 * a retry for editors once processing has failed.
 */
export default function PipelineProgress({
    recordId,
    stages,
    processingStatus,
    presets,
    initialPreset,
    canEdit,
}: Props) {
    const problems = stages.filter(
        (stage) => stage.status === 'failed' || stage.status === 'skipped',
    );

    if (
        stages.length === 0 ||
        (processingStatus === 'ready' && problems.length === 0)
    ) {
        return null;
    }

    const overall =
        stages.reduce(
            (sum, stage) =>
                sum +
                (stage.status === 'running'
                    ? stage.progress
                    : stage.status === 'pending'
                      ? 0
                      : 100),
            0,
        ) / stages.length;
    const failure = stages.find((stage) => stage.status === 'failed');

    return (
        <section
            aria-label="Processing progress"
            className="border-b border-white/10 bg-neutral-900/80 px-3 py-2"
        >
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                <div className="flex min-w-40 items-center gap-2">
                    <span className="text-[11px] font-semibold tracking-wider text-neutral-400 uppercase">
                        {processingStatus === 'failed'
                            ? 'Finished with problems'
                            : processingStatus === 'ready'
                              ? 'Finished'
                              : 'Processing'}
                    </span>
                    <div
                        className="h-1 w-24 overflow-hidden rounded-full bg-white/10"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={Math.round(overall)}
                        aria-label="Overall progress"
                    >
                        <div
                            className="h-full bg-brand transition-[width]"
                            style={{ width: `${overall}%` }}
                        />
                    </div>
                </div>
                <ol className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    {stages.map((stage) => {
                        const {
                            icon: Icon,
                            className,
                            label,
                        } = ICONS[stage.status];

                        return (
                            <li
                                key={stage.stage}
                                className="flex items-center gap-1.5 text-xs"
                                title={stage.message ?? label}
                            >
                                <Icon
                                    className={cn('size-3.5', className)}
                                    aria-label={label}
                                />
                                <span
                                    className={
                                        stage.status === 'pending'
                                            ? 'text-neutral-500'
                                            : 'text-neutral-200'
                                    }
                                >
                                    {stage.label}
                                </span>
                                {stage.status === 'running' &&
                                    stage.progress > 0 && (
                                        <span className="text-neutral-400 tabular-nums">
                                            {stage.progress}%
                                        </span>
                                    )}
                            </li>
                        );
                    })}
                </ol>
                {processingStatus === 'failed' && canEdit && (
                    <div className="ml-auto">
                        <RetryProcessingDialog
                            recordId={recordId}
                            presets={presets}
                            initialPreset={initialPreset}
                        />
                    </div>
                )}
            </div>
            {(failure ?? problems[0])?.message && (
                <p
                    className={cn(
                        'mt-1 text-xs break-words',
                        failure ? 'text-red-400' : 'text-neutral-400',
                    )}
                >
                    {(failure ?? problems[0]).label}:{' '}
                    {(failure ?? problems[0]).message}
                </p>
            )}
        </section>
    );
}
