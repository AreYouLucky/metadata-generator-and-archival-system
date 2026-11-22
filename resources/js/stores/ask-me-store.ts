import { create } from 'zustand';

/** A page with a player for one record (the workspace or the watch page). */
export type AskMePage = {
    recordId: string;
    /** Play from this second on the page's own player. */
    seek: (seconds: number) => void;
};

type AskMeState = {
    /** The floating window is open (button, or A in the workspace). */
    open: boolean;
    /** The record page the person is on, if any: the chat's context. */
    page: AskMePage | null;
    setOpen: (open: boolean) => void;
    toggle: () => void;
    setPage: (page: AskMePage | null) => void;
};

/**
 * "Ask me" floats on every page, so its state lives outside the workspace
 * store (which resets when the workspace closes).
 */
export const useAskMeStore = create<AskMeState>((set) => ({
    open: false,
    page: null,
    setOpen: (open) => set({ open }),
    toggle: () => set((state) => ({ open: !state.open })),
    setPage: (page) => set({ page }),
}));
