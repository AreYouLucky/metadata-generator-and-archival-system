<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\ApiTokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\DestroyApiTokenRequest;
use App\Http\Requests\Encoder\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Settings → API tokens: personal tokens for the public API (/api/v1), and
 * the secret that signs its webhooks. A token acts with its owner's
 * permissions, limited further to the abilities chosen; its text is shown
 * once, when it is created, and only its hash is kept. The webhook secret
 * is also shown only when it is made.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('encoder/settings/api-tokens-page/api-tokens', [
            'tokens' => $user->tokens()
                ->latest('id')
                ->limit(StoreApiTokenRequest::MAX_TOKENS)
                ->get(['id', 'tokenable_type', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
                ->map(fn (PersonalAccessToken $token): array => self::present($token))
                ->values()
                ->all(),
            'abilities' => ApiTokenAbility::options(),
            'expiryDays' => StoreApiTokenRequest::EXPIRY_DAYS,
            'hasWebhookSecret' => $user->webhook_secret !== null,
            'apiBaseUrl' => url('/api/v1'),
            'limits' => [
                'requests_per_minute' => (int) config('archive.api.requests_per_minute'),
                'max_upload_mb' => (int) config('archive.api.max_upload_mb'),
            ],
        ]);
    }

    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $days = $request->validated('expires_in_days');

        $created = $user->createToken(
            (string) $request->validated('name'),
            array_values((array) $request->validated('abilities')),
            $days === null ? null : now()->addDays((int) $days),
        );

        return response()->json([
            'token' => self::present($created->accessToken),
            // Shown once; only its hash is stored.
            'plain_text' => $created->plainTextToken,
        ], 201);
    }

    public function destroy(DestroyApiTokenRequest $request, int $token): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->whereKey($token)->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Make a new webhook signing secret (replacing the old one) and show it once.
     */
    public function webhookSecret(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = 'whsec_'.Str::random(40);

        $user->webhook_secret = $secret;
        $user->save();

        return response()->json(['secret' => $secret], 201);
    }

    /**
     * @return array{id: int, name: string, abilities: list<string>, last_used_at: string|null, expires_at: string|null, created_at: string|null, expired: bool}
     */
    private static function present(PersonalAccessToken $token): array
    {
        return [
            'id' => (int) $token->getKey(),
            'name' => $token->name,
            'abilities' => array_values((array) $token->abilities),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'expired' => $token->expires_at !== null && $token->expires_at->isPast(),
        ];
    }
}
