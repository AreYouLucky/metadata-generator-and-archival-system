import { create } from 'zustand';

export type InspectorTab = 'metadata' | 'chapters' | 'credits';

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
    setPlayer: (player: HTMLVideoElement | null) => void;
    setPlayback: (currentTime: number, paused: boolean) => void;
    setDuration: (duration: number) => void;
    togglePlay: () => void;
    setActiveIndex: (index: number) => void;
    setFollowPlayback: (follow: boolean) => void;
    setInspectorTab: (tab: InspectorTab) => void;
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
    seek: (seconds) => {
        const player = get().player;

        if (player) {
            player.currentTime = Math.max(0, seconds);
            set({ currentTime: player.currentTime, followPlayback: true });
        }
    },
    reset: () => set(initial),
}));
