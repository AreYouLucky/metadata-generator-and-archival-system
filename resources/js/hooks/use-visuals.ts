import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import {
    destroy as destroyFrame,
    store as storeFrame,
    update as updateFrame,
} from '@/routes/records/frames';
import { destroy, store, update } from '@/routes/records/scenes';
import { store as storeThumbnail } from '@/routes/records/thumbnail';
import type { Archive } from '@/types/archive';
import type { Scene, Thumbnail, VisualFrame, Visuals } from '@/types/visuals';

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

/** What an encoder may correct on a key frame. */
export type FrameInput = {
    description: string;
    ocr_text: string;
};

/** Apply a change to the key frames in the Visuals prop. */
function replaceFrames(change: (frames: VisualFrame[]) => VisualFrame[]) {
    router.replaceProp('visuals', (current: unknown) => {
        const visuals = current as Visuals;

        return { ...visuals, frames: change(visuals.frames) };
    });
}

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

/**
 * Correct a key frame's description and on-screen text. The saved frame
 * replaces the one in the `visuals` prop, so no reload is needed.
 */
export function useUpdateFrame(recordId: string) {
    return useMutation({
        mutationFn: async ({
            frameId,
            ...input
        }: FrameInput & { frameId: number }) =>
            (
                await http.patch<VisualFrame>(
                    updateFrame.url({ record: recordId, frame: frameId }),
                    input,
                )
            ).data,
        onSuccess: (saved) =>
            replaceFrames((frames) =>
                frames.map((frame) => (frame.id === saved.id ? saved : frame)),
            ),
    });
}

/**
 * Add a key frame at a time in the video (captured from the master by the
 * AI service, about a second), with an optional description.
 */
export function useAddFrame(recordId: string) {
    return useMutation({
        mutationFn: async (input: { timeSec: number; description?: string }) =>
            (
                await http.post<VisualFrame>(storeFrame.url(recordId), {
                    time_sec: Math.round(input.timeSec * 1000) / 1000,
                    description: input.description ?? null,
                })
            ).data,
        onSuccess: (frame) =>
            replaceFrames((frames) =>
                [...frames, frame].sort(
                    (first, second) => first.time_sec - second.time_sec,
                ),
            ),
    });
}

/** Remove a key frame and its image. */
export function useDeleteFrame(recordId: string) {
    return useMutation({
        mutationFn: async (frameId: number) => {
            await http.delete(
                destroyFrame.url({ record: recordId, frame: frameId }),
            );

            return frameId;
        },
        onSuccess: (frameId) =>
            replaceFrames((frames) =>
                frames.filter((frame) => frame.id !== frameId),
            ),
    });
}
