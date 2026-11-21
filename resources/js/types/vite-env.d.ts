/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME?: string;
    readonly VITE_REVERB_APP_KEY?: string;
    readonly VITE_REVERB_HOST?: string;
    readonly VITE_REVERB_PORT?: string;
    readonly VITE_REVERB_SCHEME?: string;
    /** Map tiles for the GPS picker (default: OpenStreetMap). Not a secret. */
    readonly VITE_MAP_TILE_URL?: string;
    readonly VITE_MAP_ATTRIBUTION?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
