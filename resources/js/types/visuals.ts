/**
 * Visual analysis (Phase 5): the workspace's `visuals` prop
 * (App\Services\Records\VisualsPresenter) and the `visual` block of
 * PROJECT_SPEC §10. Face counts only; nothing identifies people.
 */

export type DetectedObject = {
    label: string;
    confidence: number;
};

/** A key frame with what the visual stages found in it. */
export type VisualFrame = {
    id: number;
    time_sec: number;
    /** The frame image, served behind the record policy. */
    url: string;
    is_scene_start: boolean;
    description: string | null;
    ocr_text: string | null;
    objects: DetectedObject[] | null;
    face_count: number | null;
};

/** A shot detected by the `scenes` stage. */
export type Shot = {
    id: number;
    start: number;
    end: number;
    tc_in: string | null;
    tc_out: string | null;
};

/** A named group of shots made by an encoder. */
export type Scene = Shot & {
    label: string | null;
    description: string | null;
};

export type Thumbnail = {
    url: string;
    /** Set when a key frame was chosen. */
    frame_id: number | null;
    /** Where in the video it was taken; null for an uploaded image. */
    time_sec: number | null;
};

export type Visuals = {
    frames: VisualFrame[];
    shots: Shot[];
    scenes: Scene[];
    thumbnail: Thumbnail | null;
};
