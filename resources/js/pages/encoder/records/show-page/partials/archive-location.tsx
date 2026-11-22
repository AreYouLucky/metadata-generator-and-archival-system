import { Crosshair, MapPin, Sparkles, X } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { toast } from 'sonner';
import LocationPicker from '@/components/all/location-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useUpdateRecord } from '@/hooks/use-archive';
import type { Archive, SuggestedLocation } from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveSaveBar from './archive-save-bar';

// The map library loads only when this section is opened.
const LocationMap = lazy(() => import('./location-map'));

type Props = {
    recordId: string;
    archive: Archive;
    /** From the active AI generation, if it named a place. */
    suggestion: SuggestedLocation | null;
    canEdit: boolean;
};

const toCoordinate = (value: string) =>
    value.trim() === '' ? null : Number(value);

/**
 * Where it was recorded: the venue, the region → province → city or
 * municipality → barangay chain, and GPS. Suggestions from the AI and from
 * the file's GPS tag can be applied, then saved.
 */
export default function ArchiveLocation({
    recordId,
    archive,
    suggestion,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm(
        archive.location,
    );
    const save = useUpdateRecord(recordId);
    const gps = archive.suggestions;
    const hasGps = gps.latitude !== null && gps.longitude !== null;
    const gpsApplied =
        hasGps &&
        values.latitude === gps.latitude &&
        values.longitude === gps.longitude;
    const [locating, setLocating] = useState(false);

    const pick = (latitude: number, longitude: number) =>
        change({ latitude, longitude, location_source: 'manual' });

    // The browser asks the user first; only the coordinates are kept.
    const useMyLocation = () => {
        if (!navigator.geolocation) {
            toast.error('This browser cannot share its location.');

            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                pick(
                    Math.round(position.coords.latitude * 1_000_000) /
                        1_000_000,
                    Math.round(position.coords.longitude * 1_000_000) /
                        1_000_000,
                );
            },
            () => {
                setLocating(false);
                toast.error(
                    'Your location could not be read (permission denied or unavailable).',
                );
            },
            { enableHighAccuracy: true, timeout: 10_000 },
        );
    };

    return (
        <div className="grid gap-4">
            {canEdit && suggestion && (
                <div className="flex items-start gap-2 rounded-md bg-brand-muted/60 p-2.5 text-xs ring-1 ring-brand/30">
                    <Sparkles
                        className="mt-0.5 size-4 shrink-0 text-brand"
                        aria-hidden
                    />
                    <p className="flex-1">
                        The transcript mentions{' '}
                        <strong>{suggestion.text}</strong>.
                        {suggestion.region_id === null &&
                            ' No matching region was found, so only the venue can be filled in.'}
                    </p>
                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-7"
                        onClick={() =>
                            change({
                                location: values.location ?? suggestion.text,
                                region_id: suggestion.region_id,
                                prov_id: suggestion.prov_id,
                                city_id: suggestion.city_id,
                                brgy_id: suggestion.brgy_id,
                                location_source: 'ai',
                            })
                        }
                    >
                        Use
                    </Button>
                </div>
            )}

            <ArchiveField
                label="Venue"
                htmlFor="archive-venue"
                hint="Free text, e.g. Provincial Capitol grounds"
                error={errors.location}
            >
                <Input
                    id="archive-venue"
                    value={values.location ?? ''}
                    maxLength={250}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({
                            location: event.target.value || null,
                            location_source: 'manual',
                        })
                    }
                />
            </ArchiveField>

            <LocationPicker
                idPrefix="archive-location"
                value={values}
                disabled={!canEdit}
                errors={errors}
                onChange={(location) =>
                    change({ ...location, location_source: 'manual' })
                }
            />

            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-medium">GPS position</span>
                    {canEdit && (
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="h-7 text-xs"
                                disabled={locating}
                                onClick={useMyLocation}
                            >
                                <Crosshair aria-hidden />
                                Use my location
                            </Button>
                            {values.latitude !== null && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="h-7 text-xs"
                                    onClick={() =>
                                        change({
                                            latitude: null,
                                            longitude: null,
                                        })
                                    }
                                >
                                    <X aria-hidden />
                                    Remove pin
                                </Button>
                            )}
                        </div>
                    )}
                </div>
                <Suspense
                    fallback={<Skeleton className="h-64 w-full rounded-md" />}
                >
                    <LocationMap
                        latitude={values.latitude}
                        longitude={values.longitude}
                        disabled={!canEdit}
                        onPick={pick}
                    />
                </Suspense>
                {canEdit && (
                    <p className="text-xs text-muted-foreground">
                        Click the map to drop the pin, or type the coordinates
                        below.
                    </p>
                )}
            </div>

            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Latitude"
                    htmlFor="archive-latitude"
                    error={errors.latitude}
                >
                    <Input
                        id="archive-latitude"
                        type="number"
                        step="any"
                        value={values.latitude ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                latitude: toCoordinate(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Longitude"
                    htmlFor="archive-longitude"
                    error={errors.longitude}
                >
                    <Input
                        id="archive-longitude"
                        type="number"
                        step="any"
                        value={values.longitude ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                longitude: toCoordinate(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
            </div>
            {canEdit && hasGps && !gpsApplied && (
                <div className="flex items-center gap-2 rounded-md bg-muted/60 p-2.5 text-xs">
                    <MapPin
                        className="size-4 shrink-0 text-brand"
                        aria-hidden
                    />
                    <p className="flex-1">
                        The file has a GPS tag: {gps.latitude}, {gps.longitude}.
                    </p>
                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-7"
                        onClick={() =>
                            change({
                                latitude: gps.latitude,
                                longitude: gps.longitude,
                                location_source: 'container_gps',
                            })
                        }
                    >
                        Use
                    </Button>
                </div>
            )}

            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Location saved.'))
                    }
                />
            )}
        </div>
    );
}
