import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, store, update } from '@/routes/records/scenes';
import { store as storeThumbnail } from '@/routes/records/thumbnail';
import type { Archive } from '@/types/archive';
import type { Scene, Thumbnail, Visuals } from '@/types/visuals';

/** One of: a key frame, a time in the video ("use current frame"), an image. */
export type ThumbnailChoice =
    | { frameId: number }
    | { timeSec: number }
    | { image: File };

export type SceneInput = {
    start: number;
    end: number;
    label: string;
    description: string;
};

const byStart = (first: Scene, second: Scene) => first.start - second.start;

/** Apply a change to the named scenes in the Visuals and Archive props. */
function replaceScenes(change: (scenes: Scene[]) => Scene[]) {
    router.replaceProp('visuals', (current: unknown) => {
        const visuals = current as Visuals;

        return { ...visuals, scenes: change(visuals.scenes) };
    });
    router.replaceProp('archive', (current: unknown) => {
        const archive = current as Archive;

        return {
            ...archive,
            segments: {
                ...archive.segments,
                scenes: change(archive.segments.scenes),
            },
        };
    });
}

/**
 * Choose the record's thumbnail. The new one replaces the `visuals` prop's
 * thumbnail, so no reload is needed.
 */
export function useSetThumbnail(recordId: string) {
    return useMutation({
        mutationFn: async (choice: ThumbnailChoice) => {
            let body: FormData | Record<string, number>;

            if ('image' in choice) {
                body = new FormData();
                body.append('image', choice.image);
            } else {
                body =
                    'frameId' in choice
                        ? { frame_id: choice.frameId }
                        : {
                              time_sec:
                                  Math.round(choice.timeSec * 1000) / 1000,
                          };
            }

            return (
                await http.post<{ thumbnail: Thumbnail }>(
                    storeThumbnail.url(recordId),
                    body,
                )
            ).data.thumbnail;
        },
        onSuccess: (thumbnail) =>
            router.replaceProp('visuals', (current: unknown) => ({
                ...(current as Visuals),
                thumbnail,
            })),
    });
}

/** Group shots into a named scene. */
export function useCreateScene(recordId: string) {
    return useMutation({
        mutationFn: async (input: SceneInput) =>
            (await http.post<Scene>(store.url(recordId), input)).data,
        onSuccess: (scene) =>
            replaceScenes((scenes) => [...scenes, scene].sort(byStart)),
    });
}

/** Rename, describe or regroup a scene (the whole scene is sent). */
export function useUpdateScene(recordId: string) {
    return useMutation({
        mutationFn: async ({
            sceneId,
            ...input
        }: SceneInput & { sceneId: number }) =>
            (
                await http.patch<Scene>(
                    update.url({ record: recordId, scene: sceneId }),
                    input,
                )
            ).data,
        onSuccess: (saved) =>
            replaceScenes((scenes) =>
                scenes
                    .map((scene) => (scene.id === saved.id ? saved : scene))
                    .sort(byStart),
            ),
    });
}

export function useDeleteScene(recordId: string) {
    return useMutation({
        mutationFn: async (sceneId: number) => {
            await http.delete(
                destroy.url({ record: recordId, scene: sceneId }),
            );

            return sceneId;
        },
        onSuccess: (sceneId) =>
            replaceScenes((scenes) =>
                scenes.filter((scene) => scene.id !== sceneId),
            ),
    });
}
