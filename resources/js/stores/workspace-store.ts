import { create } from 'zustand';
import type { DescriptiveSuggestion } from '@/types/archive';

export type InspectorTab =
    | 'metadata'
    | 'archive'
    | 'chapters'
    | 'credits'
    | 'translations'
    | 'export';

/**
 * Workspace UI state shared by the player, transcript and metadata panels.
 * The player registers its <video> element; everything else seeks through it.
 */
type WorkspaceState = {
    player: HTMLVideoElement | null;
    currentTime: number;
    /** From the player once its metadata loads (falls back to the probed duration). */
    duration: number;
    paused: boolean;
    activeIndex: number;
    /** Auto-scroll the transcript to the playing line (off while the user scrolls). */
    followPlayback: boolean;
    /** Which inspector tab is showing (the frame-text tool opens Credits). */
    inspectorTab: InspectorTab;
    /** Language code of the caption track shown on the player; null = off. */
    captionLanguage: string | null;
    /** Bumped when a transcript line is edited, so caption tracks reload. */
    captionsVersion: number;
    shortcutsOpen: boolean;
    /** Every time display: the clock, or SMPTE timecode (TimecodeToggle). */
    timeFormat: 'clock' | 'timecode';
    /** The master's frame rate ("25/1") and start timecode, for timecode. */
    timecodeBase: { frameRate: string | null; start: string | null };
    /** "Fill with AI" is running for Archive → Descriptive. */
    descriptivePending: boolean;
    /** Suggestions that arrived; the Descriptive form applies and clears them. */
    descriptiveSuggestion: DescriptiveSuggestion | null;
    /** A line the E shortcut asked to edit; the line clears it once open. */
    editRequest: number | null;
    setPlayer: (player: HTMLVideoElement | null) => void;
    setPlayback: (currentTime: number, paused: boolean) => void;
    setDuration: (duration: number) => void;
    togglePlay: () => void;
    setActiveIndex: (index: number) => void;
    setFollowPlayback: (follow: boolean) => void;
    setInspectorTab: (tab: InspectorTab) => void;
    setCaptionLanguage: (language: string | null) => void;
    bumpCaptions: () => void;
    setShortcutsOpen: (open: boolean) => void;
    setTimeFormat: (format: 'clock' | 'timecode') => void;
    setTimecodeBase: (frameRate: string | null, start: string | null) => void;
    requestEdit: (segmentId: number | null) => void;
    setDescriptivePending: (pending: boolean) => void;
    setDescriptiveSuggestion: (fields: DescriptiveSuggestion | null) => void;
    seek: (seconds: number) => void;
    reset: () => void;
};

const initial = {
    player: null,
    currentTime: 0,
    duration: 0,
    paused: true,
    activeIndex: -1,
    followPlayback: true,
    inspectorTab: 'metadata' as InspectorTab,
    captionLanguage: null,
    captionsVersion: 0,
    shortcutsOpen: false,
    editRequest: null,
    descriptivePending: false,
    descriptiveSuggestion: null as DescriptiveSuggestion | null,
    timeFormat: 'clock' as 'clock' | 'timecode',
    timecodeBase: { frameRate: null, start: null } as {
        frameRate: string | null;
        start: string | null;
    },
};

export const useWorkspaceStore = create<WorkspaceState>((set, get) => ({
    ...initial,
    setPlayer: (player) => set({ player }),
    setPlayback: (currentTime, paused) => set({ currentTime, paused }),
    setDuration: (duration) => set({ duration }),
    togglePlay: () => {
        const player = get().player;

        if (!player) {
            return;
        }

        if (player.paused) {
            void player.play();
        } else {
            player.pause();
        }
    },
    setActiveIndex: (activeIndex) => set({ activeIndex }),
    setFollowPlayback: (followPlayback) => set({ followPlayback }),
    setInspectorTab: (inspectorTab) => set({ inspectorTab }),
    setCaptionLanguage: (captionLanguage) => set({ captionLanguage }),
    bumpCaptions: () =>
        set((state) => ({ captionsVersion: state.captionsVersion + 1 })),
    setShortcutsOpen: (shortcutsOpen) => set({ shortcutsOpen }),
    setTimeFormat: (timeFormat) => set({ timeFormat }),
    setTimecodeBase: (frameRate, start) =>
        set({ timecodeBase: { frameRate, start } }),
    requestEdit: (editRequest) => set({ editRequest }),
    setDescriptivePending: (descriptivePending) => set({ descriptivePending }),
    setDescriptiveSuggestion: (descriptiveSuggestion) =>
        set({ descriptiveSuggestion, descriptivePending: false }),
    seek: (seconds) => {
        const player = get().player;

        if (player) {
            player.currentTime = Math.max(0, seconds);
            set({ currentTime: player.currentTime, followPlayback: true });
        }
    },
    reset: () => set(initial),
}));
