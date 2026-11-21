import { useEffect } from 'react';
import { isTypingTarget } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';

const JUMP_SECONDS = 5;

/**
 * Editor keys for the workspace (PROJECT_SPEC §9): Space / K play or pause,
 * J / L jump back or forward 5 s. Ignored while typing or inside a menu or
 * dialog. Space on a focused button plays/pauses instead of pressing the
 * button again, as in editing software.
 */
export function usePlaybackShortcuts() {
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.defaultPrevented ||
                event.repeat ||
                event.ctrlKey ||
                event.metaKey ||
                event.altKey ||
                isTypingTarget(event.target)
            ) {
                return;
            }

            const { player, togglePlay, seek } = useWorkspaceStore.getState();

            if (!player) {
                return;
            }

            switch (event.key) {
                case ' ':
                case 'k':
                case 'K':
                    event.preventDefault();
                    togglePlay();
                    break;
                case 'j':
                case 'J':
                    event.preventDefault();
                    seek(player.currentTime - JUMP_SECONDS);
                    break;
                case 'l':
                case 'L':
                    event.preventDefault();
                    seek(
                        Math.min(
                            player.duration || Infinity,
                            player.currentTime + JUMP_SECONDS,
                        ),
                    );
                    break;
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}
