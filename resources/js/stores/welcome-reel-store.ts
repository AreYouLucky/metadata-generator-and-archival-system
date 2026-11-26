import { create } from 'zustand';

type WelcomeReelState = {
    /**
     * The welcome page frame currently in view (0 = hero), in both the reel
     * and the stacked layout, or -1 before the reel has measured itself.
     */
    activeFrame: number;
    setActiveFrame: (frame: number) => void;
};

/**
 * Shares the reel's position with the welcome header. Only the frame index
 * lives here; the continuous progress is a CSS variable (`--reel-progress`
 * on <html>), so scrolling never re-renders React.
 */
export const useWelcomeReelStore = create<WelcomeReelState>((set) => ({
    activeFrame: -1,
    setActiveFrame: (activeFrame) => set({ activeFrame }),
}));
