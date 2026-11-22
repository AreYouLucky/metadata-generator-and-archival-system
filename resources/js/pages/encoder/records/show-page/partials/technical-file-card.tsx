import { CircleAlert, CircleCheck, CircleDashed } from 'lucide-react';
import { formatBytes, formatClock, formatEnumLabel } from '@/lib/utilities';
import type { ArchiveFile } from '@/types/archive';

/** "48000" -> "48 kHz", bit rate -> "4.5 Mb/s". */
const kHz = (rate: number | null) => (rate ? `${rate / 1000} kHz` : null);
const mbps = (bitrate: number | null) =>
    bitrate ? `${(bitrate / 1_000_000).toFixed(1)} Mb/s` : null;

/**
 * One stored file's technical record (read-only, from the probe stage) and
 * its checksums with their status.
 */
export default function TechnicalFileCard({ file }: { file: ArchiveFile }) {
    const tech = file.technical;
    const rows: [string, string | number | null | undefined][] = tech
        ? [
              ['Format', tech.file_format],
              ['Duration', tech.duration ? formatClock(tech.duration) : null],
              ['Bit rate', mbps(tech.bitrate)],
              ['Start timecode', tech.start_timecode],
              [
                  'Video',
                  [tech.video_codec, tech.video_profile]
                      .filter(Boolean)
                      .join(' · ') || null,
              ],
              ['Resolution', tech.resolution],
              ['Frame rate', tech.frame_rate],
              ['Aspect ratio', tech.aspect_ratio],
              [
                  'Scan type',
                  tech.scan_type ? formatEnumLabel(tech.scan_type) : null,
              ],
              ['Bit depth', tech.bit_depth ? `${tech.bit_depth}-bit` : null],
              ['Chroma', tech.chroma_subsampling],
              [
                  'Colour',
                  [tech.color_space, tech.color_primaries, tech.color_transfer]
                      .filter(Boolean)
                      .join(' / ') || null,
              ],
              [
                  'Audio',
                  [
                      tech.audio_codec,
                      tech.audio_channels ? `${tech.audio_channels} ch` : null,
                      kHz(tech.audio_sample_rate),
                  ]
                      .filter(Boolean)
                      .join(' · ') || null,
              ],
          ]
        : [];
    const StatusIcon =
        file.checksum_status === 'ok'
            ? CircleCheck
            : file.checksum_status === 'mismatch'
              ? CircleAlert
              : CircleDashed;

    return (
        <article className="grid gap-3 rounded-lg border border-border p-3">
            <header className="flex items-baseline justify-between gap-2">
                <h4 className="text-sm font-medium">
                    {formatEnumLabel(file.role)}
                    <span className="ml-2 font-normal text-muted-foreground">
                        {file.original_filename}
                    </span>
                </h4>
                {file.file_size !== null && (
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {formatBytes(file.file_size)}
                    </span>
                )}
            </header>
            {tech ? (
                <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1 text-xs">
                    {rows
                        .filter(
                            ([, value]) =>
                                value !== null &&
                                value !== undefined &&
                                value !== '',
                        )
                        .map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="break-words">{value}</dd>
                            </div>
                        ))}
                    {tech.audio_tracks.length > 1 && (
                        <div className="contents">
                            <dt className="text-muted-foreground">
                                Audio tracks
                            </dt>
                            <dd>
                                {tech.audio_tracks
                                    .map(
                                        (track) =>
                                            `${track.index}: ${track.codec ?? '?'}${track.channels ? ` ${track.channels} ch` : ''}${track.language ? ` (${track.language})` : ''}`,
                                    )
                                    .join(', ')}
                            </dd>
                        </div>
                    )}
                </dl>
            ) : (
                <p className="text-xs text-muted-foreground">Not probed yet.</p>
            )}
            <div className="grid gap-1 rounded-md bg-muted p-2 font-mono text-[11px] break-all">
                <p className="flex items-center gap-1.5 font-sans text-xs">
                    <StatusIcon
                        className={
                            file.checksum_status === 'ok'
                                ? 'size-3.5 text-emerald-400'
                                : file.checksum_status === 'mismatch'
                                  ? 'size-3.5 text-red-400'
                                  : 'size-3.5 text-muted-foreground'
                        }
                        aria-hidden
                    />
                    {file.checksum_status === 'ok' && 'Checksums verified'}
                    {file.checksum_status === 'mismatch' && 'Checksum mismatch'}
                    {file.checksum_status === 'pending' && 'Checksums pending'}
                </p>
                {file.md5_checksum && <span>MD5 {file.md5_checksum}</span>}
                {file.sha256_checksum && (
                    <span>SHA-256 {file.sha256_checksum}</span>
                )}
            </div>
        </article>
    );
}
