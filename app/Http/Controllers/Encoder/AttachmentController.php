<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreAttachmentRequest;
use App\Models\Record;
use App\Models\RecordAttachment;
use App\Services\Records\ArchivePresenter;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Release forms, contracts, sidecars and documents. They can hold personal
 * data, so they live on the private media disk under a generated name, are
 * downloaded only through the record policy, and never appear in public
 * responses (PROJECT_SPEC §11).
 */
class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request, Record $record, AuditTrail $audit): JsonResponse
    {
        $file = $request->file('file');
        abort_if($file === null, 422);

        $id = (string) Str::ulid();
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs("records/{$record->id}/attachments", "{$id}.{$extension}", 'media');
        abort_if($path === false, 500, 'The file could not be stored.');

        $attachment = new RecordAttachment([
            'kind' => $request->validated('kind'),
            'description' => $request->validated('description'),
        ]);
        $attachment->id = $id;
        $attachment->uploaded_by = $request->user()?->id;
        $attachment->path = $path;
        $attachment->original_filename = mb_substr(basename($file->getClientOriginalName()), 0, 250);
        $attachment->mime_type = (string) ($file->getMimeType() ?? 'application/octet-stream');
        $attachment->file_size = (int) $file->getSize();
        $attachment->sha256_checksum = (string) hash_file('sha256', Storage::disk('media')->path($path));
        $record->attachments()->save($attachment);

        $audit->log($record, AuditAction::Updated, $request->user(), "Attachment added ({$attachment->kind->label()}): {$attachment->original_filename}, SHA-256 {$attachment->sha256_checksum}");

        return response()->json(['attachment' => ArchivePresenter::attachment($attachment)], 201);
    }

    public function show(Record $record, RecordAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $record);

        return Storage::disk('media')->download($attachment->path, $attachment->original_filename, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Record $record, RecordAttachment $attachment, AuditTrail $audit): JsonResponse
    {
        Gate::authorize('update', $record);

        Storage::disk('media')->delete($attachment->path);
        $attachment->delete();
        $audit->log($record, AuditAction::Deleted, request()->user(), "Attachment deleted ({$attachment->kind->label()}): {$attachment->original_filename}");

        return response()->json(['deleted' => true]);
    }
}
