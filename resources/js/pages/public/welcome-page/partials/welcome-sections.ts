/**
 * The welcome page folds that the header and footer link to.
 */
export const WELCOME_SECTIONS = [
    { id: 'how-it-works', label: 'How it works' },
    { id: 'metadata', label: 'Metadata' },
    { id: 'browse', label: 'Browse' },
    { id: 'ask-and-share', label: 'Ask & share' },
    { id: 'preservation', label: 'Preservation' },
] as const;

/** The hero plus one frame per section. */
export const FRAME_COUNT = WELCOME_SECTIONS.length + 1;

/** A frame's number as the reel prints it: 0 → "01". */
export const frameLabel = (frame: number): string =>
    String(frame + 1).padStart(2, '0');
