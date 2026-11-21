<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(IndexUsersRequest $request): Response
    {
        $filters = $request->validated();

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_active', 'daily_processing_minutes_limit', 'created_at'])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $inner) => $inner->whereLike('name', $like)->orWhereLike('email', $like));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->withCount('records')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
                'daily_processing_minutes_limit' => $user->daily_processing_minutes_limit,
                'records_count' => $user->records_count ?? 0,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users-page/users', [
            'users' => $users,
            'filters' => ['q' => $filters['q'] ?? '', 'role' => $filters['role'] ?? null],
            'defaultDailyMinutes' => config('archive.encoder_daily_processing_minutes'),
        ]);
    }

    /**
     * Staff accounts are created verified: the admin vouches for the address, and
     * the person signs in with the password the admin gives them.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = UserRole::from($request->validated('role'));
        $user->is_active = true;
        $user->daily_processing_minutes_limit = $request->validated('daily_processing_minutes_limit');
        $user->email_verified_at = now();
        $user->save();

        return response()->json(['id' => $user->id, 'message' => "Created {$user->role->value} account for {$user->name}."], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data)) {
            $user->role = UserRole::from($data['role']);
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('daily_processing_minutes_limit', $data)) {
            $user->daily_processing_minutes_limit = $data['daily_processing_minutes_limit'];
        }

        $user->save();

        return response()->json(['id' => $user->id, 'message' => "Updated {$user->name}."]);
    }
}
