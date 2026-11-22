import 'leaflet/dist/leaflet.css';
import type { LatLngTuple } from 'leaflet';
import { CircleMarker, MapContainer, TileLayer } from 'react-leaflet';
import MapPointEvents from './map-point-events';

type Props = {
    latitude: number | null;
    longitude: number | null;
    disabled: boolean;
    onPick: (latitude: number, longitude: number) => void;
};

/** The Philippines, when no point is set yet. */
const PHILIPPINES: LatLngTuple = [12.8797, 121.774];

/**
 * OpenStreetMap tiles by default (owner's choice; DECISIONS.md). OSM asks
 * busy or public sites to use their own tiles: set VITE_MAP_TILE_URL and
 * VITE_MAP_ATTRIBUTION to switch provider without code changes.
 */
const TILE_URL =
    import.meta.env.VITE_MAP_TILE_URL ??
    'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION =
    import.meta.env.VITE_MAP_ATTRIBUTION ??
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * Pick the recording's GPS position: click the map to drop the pin. Loaded
 * lazily, so the map library downloads only when the Location tab opens.
 * The pin is a vector circle (Leaflet's image icon breaks under bundlers).
 */
export default function LocationMap({
    latitude,
    longitude,
    disabled,
    onPick,
}: Props) {
    const point: LatLngTuple | null =
        latitude !== null && longitude !== null ? [latitude, longitude] : null;

    return (
        <MapContainer
            center={point ?? PHILIPPINES}
            zoom={point ? 15 : 5}
            scrollWheelZoom
            className="isolate z-0 h-64 w-full rounded-md ring-1 ring-white/10"
            style={{ cursor: disabled ? 'default' : 'crosshair' }}
        >
            <TileLayer url={TILE_URL} attribution={ATTRIBUTION} />
            {point && (
                <CircleMarker
                    center={point}
                    radius={8}
                    pathOptions={{
                        color: '#ffffff',
                        weight: 2,
                        fillColor: '#0284c7',
                        fillOpacity: 1,
                    }}
                />
            )}
            <MapPointEvents point={point} disabled={disabled} onPick={onPick} />
        </MapContainer>
    );
}
