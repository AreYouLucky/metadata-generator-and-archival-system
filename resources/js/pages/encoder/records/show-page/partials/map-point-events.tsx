import type { LatLngTuple } from 'leaflet';
import { useEffect } from 'react';
import { useMap, useMapEvents } from 'react-leaflet';

type Props = {
    point: LatLngTuple | null;
    disabled: boolean;
    onPick: (latitude: number, longitude: number) => void;
};

/** About 10 cm on the ground: more digits would only be noise. */
const round = (value: number) => Math.round(value * 1_000_000) / 1_000_000;

/**
 * Inside the map: a click drops the pin there, and a pin set from outside
 * (typed coordinates, a GPS tag, "Use my location") is brought into view.
 */
export default function MapPointEvents({ point, disabled, onPick }: Props) {
    const map = useMap();

    useMapEvents({
        click: (event) => {
            if (!disabled) {
                onPick(round(event.latlng.lat), round(event.latlng.lng));
            }
        },
    });

    const latitude = point?.[0];
    const longitude = point?.[1];

    useEffect(() => {
        if (latitude === undefined || longitude === undefined) {
            return;
        }

        if (!map.getBounds().contains([latitude, longitude])) {
            map.setView([latitude, longitude], Math.max(map.getZoom(), 15));
        }
    }, [map, latitude, longitude]);

    return null;
}
