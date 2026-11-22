import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    Circle,
    CircleSlash,
    Clock,
    Loader2,
    XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type {
    ProcessingStatus,
    StageState,
    StageStatus,
} from '@/types/records';

const STAGE_ICONS: Record<
    StageStatus,
    { icon: typeof Circle; className: string; label: string }
> = {
    pending: {
        icon: Circle,
        className: 'text-muted-foreground',
        label: 'Waiting',
    },
    running: {
        icon: Loader2,
        className: 'animate-spin text-brand',
        label: 'Running',
    },
    done: { icon: CheckCircle2, className: 'text-emerald-500', label: 'Done' },
    failed: { icon: XCircle, className: 'text-red-500', label: 'Failed' },
    skipped: {
        icon: CircleSlash,
        className: 'text-muted-foreground',
        label: 'Skipped',
    },
};

const STATUS: Record<
    ProcessingStatus,
    { label: string; icon: typeof Circle; className: string }
> = {
    queued: { label: 'Queued', icon: Clock, className: '' },
    processing: {
        label: 'Processing',
        icon: Loader2,
        className:
            '[&>svg:first-child]:animate-spin [&>svg:first-child]:text-brand',
    },
    ready: {
        label: 'Ready',
        icon: CheckCircle2,
        className: '[&>svg:first-child]:text-emerald-500',
    },
    failed: {
        label: 'Failed',
        icon: AlertTriangle,
        className: 'text-red-600 dark:text-red-400',
    },
    cancelled: { label: 'Cancelled', icon: XCircle, className: '' },
};

type Props = {
    stages: StageState[];
    processingStatus: ProcessingStatus;
};

/**
 * The pipeline in the app header: status and overall progress, with every
 * stage (live over Reverb) and any failure reason one click away.
 */
export default function ProcessingStatusMenu({
    stages,
    processingStatus,
}: Props) {
    const status = STATUS[processingStatus];
    const StatusIcon = status.icon;
    const overall = stages.length
        ? Math.round(
              stages.reduce(
                  (sum, stage) =>
                      sum +
                      (stage.status === 'running'
                          ? stage.progress
                          : stage.status === 'pending'
                            ? 0
                            : 100),
                  0,
              ) / stages.length,
          )
        : 0;
    const working =
        processingStatus === 'processing' || processingStatus === 'queued';
    const problems = stages.filter(
        (stage) => stage.status === 'failed' || stage.status === 'skipped',
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className={cn('gap-2', status.className)}
                    aria-label={`Processing: ${status.label}${working ? ` ${overall}%` : ''}. Show stages`}
                >
                    <StatusIcon aria-hidden />
                    <span>{status.label}</span>
                    {working && (
                        <>
                            <span
                                className="hidden h-1 w-16 overflow-hidden rounded-full bg-muted sm:block"
                                aria-hidden
                            >
                                <span
                                    className="block h-full bg-brand transition-[width]"
                                    style={{ width: `${overall}%` }}
                                />
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {overall}%
                            </span>
                        </>
                    )}
                    {!working && problems.length > 0 && (
                        <span className="text-xs text-muted-foreground">
                            {problems.length}{' '}
                            {problems.length === 1 ? 'issue' : 'issues'}
                        </span>
                    )}
                    <ChevronDown className="opacity-60" aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel>Processing</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <ol className="grid gap-1 p-1">
                    {stages.map((stage) => {
                        const {
                            icon: Icon,
                            className,
                            label,
                        } = STAGE_ICONS[stage.status];

                        return (
                            <li key={stage.stage} className="px-1 py-0.5">
                                <div className="flex items-center gap-2 text-sm">
                                    <Icon
                                        className={cn(
                                            'size-4 shrink-0',
                                            className,
                                        )}
                                        aria-label={label}
                                    />
                                    <span
                                        className={cn(
                                            'flex-1',
                                            stage.status === 'pending' &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {stage.label}
                                    </span>
                                    {stage.status === 'running' &&
                                        stage.progress > 0 && (
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {stage.progress}%
                                            </span>
                                        )}
                                </div>
                                {stage.message &&
                                    (stage.status === 'failed' ||
                                        stage.status === 'skipped') && (
                                        <p
                                            className={cn(
                                                'mt-0.5 ml-6 text-xs wrap-break-word',
                                                stage.status === 'failed'
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {stage.message}
                                        </p>
                                    )}
                            </li>
                        );
                    })}
                </ol>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
