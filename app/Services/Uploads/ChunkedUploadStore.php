<?php

namespace App\Services\Uploads;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resumable uploads in sequential chunks, so videos larger than PHP's
 * post_max_size can be uploaded. Parts live on the private media disk under
 * `uploads/`; the session (owner, size, bytes received) lives in the cache for
 * a day. Only the user who started an upload can add to it or claim it.
 *
 * @phpstan-type UploadSession array{user_id: int, original_filename: string, extension: string, size: int, received: int}
 */
class ChunkedUploadStore
{
    private const int TTL_SECONDS = 86_400;

    /**
     * @return array{id: string, received: int}
     */
    public function start(User $user, string $originalFilename, string $extension, int $size): array
    {
        $id = (string) Str::uuid();

        $this->disk()->put($this->partPath($id), '');
        $this->cache()->put($this->key($id), [
            'user_id' => $user->id,
            'original_filename' => $originalFilename,
            'extension' => $extension,
            'size' => $size,
            'received' => 0,
        ], self::TTL_SECONDS);

        return ['id' => $id, 'received' => 0];
    }

    /**
     * Append the chunk that starts at `$offset`. A chunk at any other offset is
     * rejected with 409, and the client resumes from the `received` it reports.
     */
    public function append(string $id, User $user, int $offset, UploadedFile $chunk): int
    {
        return Cache::lock("upload-lock:{$id}", 60)->block(10, function () use ($id, $user, $offset, $chunk): int {
            $session = $this->session($id, $user);

            if ($offset !== $session['received']) {
                throw new ConflictHttpException("Expected the chunk at byte {$session['received']}.");
            }

            $chunkSize = (int) $chunk->getSize();

            if ($session['size'] < $session['received'] + $chunkSize) {
                throw new ConflictHttpException('The chunk is larger than the rest of the file.');
            }

            $this->appendFile($this->disk()->path($this->partPath($id)), (string) $chunk->getRealPath());

            $session['received'] += $chunkSize;
            $this->cache()->put($this->key($id), $session, self::TTL_SECONDS);

            return $session['received'];
        });
    }

    /**
     * @return UploadSession
     */
    public function status(string $id, User $user): array
    {
        return $this->session($id, $user);
    }

    /**
     * Hand a finished upload over: returns its session and the part's path on the
     * media disk. The caller moves the file; the session is forgotten.
     *
     * @return array{session: UploadSession, path: string}
     */
    public function claim(string $id, User $user): array
    {
        $session = $this->session($id, $user);

        if ($session['received'] !== $session['size']) {
            throw new ConflictHttpException('The upload is not finished yet.');
        }

        $this->cache()->forget($this->key($id));

        return ['session' => $session, 'path' => $this->partPath($id)];
    }

    /**
     * @return UploadSession
     */
    private function session(string $id, User $user): array
    {
        /** @var UploadSession|null $session */
        $session = $this->cache()->get($this->key($id));

        // An upload someone else started is reported as missing, not forbidden.
        if ($session === null || $session['user_id'] !== $user->id) {
            throw new NotFoundHttpException('Upload not found or expired.');
        }

        return $session;
    }

    private function appendFile(string $target, string $source): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($target, 'ab');

        if ($in === false || $out === false) {
            throw new RuntimeException('Could not open the upload for writing.');
        }

        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function partPath(string $id): string
    {
        return "uploads/{$id}.part";
    }

    private function key(string $id): string
    {
        return "upload:{$id}";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }

    private function cache(): CacheRepository
    {
        return Cache::store();
    }
}
