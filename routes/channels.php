<?php

use App\Models\Record;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('admin.system', function (User $user): bool {
    return $user->is_active && $user->isAdmin();
});

// Pipeline progress for one record: whoever may view the record (RecordPolicy).
Broadcast::channel('records.{record}', function (User $user, Record $record): bool {
    return $user->can('view', $record);
});
