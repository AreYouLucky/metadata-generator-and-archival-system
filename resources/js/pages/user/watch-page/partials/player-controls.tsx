import {
    Captions,
    CaptionsOff,
    Gauge,
    Maximize,
    Minimize,
    Pause,
    PictureInPicture2,
    Play,
    RotateCcw,
    RotateCw,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { PLAYBACK_RATES } from '@/hooks/use-video-player';
import type { VideoPlayer } from '@/hooks/use-video-player';
import { formatTimestamp, languageName } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import PlayerMenu from './player-menu';
import PlayerSeekBar from './player-seek-bar';
import PlayerVolume from './player-volume';

type Props = {
    player: VideoPlayer;
    video: HTMLVideoElement | null;
    captions: { language: string; is_original: boolean }[];
    chapters: { start: number; title: string }[];
    previews: { time_sec: number; url: string }[];
    chapterTitle: string | null;
    onScrubbingChange: (scrubbing: boolean) => void;
    onMenuOpenChange: (open: boolean) => void;
};

const SKIP_SECONDS = 10;

/**
 * The player's bottom bar: the timeline, then play, skip, volume, time and
 * chapter on the left and captions, speed, picture-in-picture and
 * fullscreen on the right.
 */
export default function PlayerControls({
    player,
    video,
    captions,
    chapters,
    previews,
    chapterTitle,
    onScrubbingChange,
    onMenuOpenChange,
}: Props) {
    const button = (
        label: string,
        onClick: () => void,
        icon: ReactNode,
        className?: string,
    ) => (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex size-9 shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none',
                className,
            )}
        >
            {icon}
        </button>
    );

    return (
        <div className="grid gap-1.5 bg-linear-to-t from-black/85 via-black/50 to-transparent px-3 pt-16 pb-2.5 sm:px-5 sm:pb-3.5">
            <PlayerSeekBar
                video={video}
                time={player.time}
                duration={player.duration}
                buffered={player.buffered}
                playing={player.playing}
                chapters={chapters}
                previews={previews}
                onSeek={player.seekTo}
                onScrubbingChange={onScrubbingChange}
            />

            <div className="flex items-center gap-0.5 sm:gap-1">
                {button(
                    player.playing ? 'Pause (k)' : 'Play (k)',
                    player.togglePlay,
                    player.playing ? (
                        <Pause className="size-5 fill-current" />
                    ) : (
                        <Play className="size-5 fill-current" />
                    ),
                )}
                {button(
                    `Back ${SKIP_SECONDS} seconds (j)`,
                    () => player.skip(-SKIP_SECONDS),
                    <RotateCcw className="size-[1.125rem]" />,
                    'hidden sm:inline-flex',
                )}
                {button(
                    `Forward ${SKIP_SECONDS} seconds (l)`,
                    () => player.skip(SKIP_SECONDS),
                    <RotateCw className="size-[1.125rem]" />,
                    'hidden sm:inline-flex',
                )}
                <PlayerVolume
                    volume={player.volume}
                    muted={player.muted}
                    onVolumeChange={player.setVolume}
                    onToggleMute={player.toggleMute}
                />

                <p className="ml-1.5 shrink-0 font-mono text-xs text-white tabular-nums sm:ml-2 sm:text-[0.8125rem]">
                    {formatTimestamp(player.time)}
                    <span className="text-white/55">
                        {' / '}
                        {formatTimestamp(player.duration)}
                    </span>
                </p>

                {chapterTitle && (
                    <p className="ml-3 hidden min-w-0 truncate text-sm text-white/85 md:block">
                        <span className="text-white/45">•</span>{' '}
                        {chapterTitle}
                    </p>
                )}

                <div className="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1">
                    {captions.length > 0 && (
                        <PlayerMenu
                            label="Captions (c)"
                            icon={
                                player.captions ? (
                                    <Captions className="size-5 text-sky-400" />
                                ) : (
                                    <CaptionsOff className="size-5" />
                                )
                            }
                            value={player.captions}
                            options={[
                                { value: null, label: 'Off' },
                                ...captions.map((caption) => ({
                                    value: caption.language,
                                    label: `${languageName(caption.language)}${caption.is_original ? ' (original)' : ''}`,
                                })),
                            ]}
                            onChange={player.setCaptions}
                            onOpenChange={onMenuOpenChange}
                        />
                    )}
                    <PlayerMenu
                        label="Playback speed"
                        icon={
                            <>
                                <Gauge className="size-5" />
                                {player.rate !== 1 && (
                                    <span className="text-xs font-medium tabular-nums">
                                        {player.rate}×
                                    </span>
                                )}
                            </>
                        }
                        value={player.rate}
                        options={PLAYBACK_RATES.map((rate) => ({
                            value: rate,
                            label: rate === 1 ? 'Normal' : `${rate}×`,
                        }))}
                        onChange={player.setRate}
                        onOpenChange={onMenuOpenChange}
                    />
                    {player.canPictureInPicture &&
                        button(
                            player.pictureInPicture
                                ? 'Exit picture-in-picture'
                                : 'Picture-in-picture',
                            player.togglePictureInPicture,
                            <PictureInPicture2 className="size-5" />,
                            'hidden sm:inline-flex',
                        )}
                    {button(
                        player.fullscreen
                            ? 'Exit fullscreen (f)'
                            : 'Fullscreen (f)',
                        player.toggleFullscreen,
                        player.fullscreen ? (
                            <Minimize className="size-5" />
                        ) : (
                            <Maximize className="size-5" />
                        ),
                    )}
                </div>
            </div>
        </div>
    );
}
