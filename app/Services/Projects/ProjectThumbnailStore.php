<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Project cover images on the private media disk, under a generated name.
 * They are served only through the authorised thumbnail route.
 */
class ProjectThumbnailStore
{
    public function replace(Project $project, UploadedFile $image): void
    {
        $extension = strtolower($image->extension() ?: 'jpg');
        $path = "projects/{$project->id}/thumbnail-".Str::lower(Str::random(16)).".{$extension}";

        $this->disk()->putFileAs(dirname($path), $image, basename($path));
        $this->remove($project);

        $project->thumbnail_path = $path;
        $project->save();
    }

    public function remove(Project $project): void
    {
        if ($project->thumbnail_path !== null) {
            $this->disk()->delete($project->thumbnail_path);
            $project->thumbnail_path = null;
            $project->save();
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }
}
